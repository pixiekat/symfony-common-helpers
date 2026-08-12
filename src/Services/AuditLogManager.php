<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Services;

use Doctrine\ORM\EntityManagerInterface;
use Pixiekat\SymfonyHelpers\Entity\AuditLog;
use Pixiekat\SymfonyHelpers\Interfaces\Entity\HelpersUserInterface;
use Pixiekat\SymfonyHelpers\Traits\Deprecated as DeprecatedTraits;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Writes audit entries to the database, to Monolog, or to both.
 *
 * ── TWO SINKS, AND WHY BOTH ────────────────────────────────────────────────
 *   log()          database + the 'audit' Monolog channel. The default, for
 *                  anything a human might later need to look up: who deleted
 *                  what, who was banned, who changed a permission.
 *   logToLogger()  Monolog only. For the high-volume, low-value events that
 *                  would bloat the table without ever being queried — a cache
 *                  clear, a cron tick, a successful login on a busy site.
 *
 * They are not redundant. The log file is written the instant it is called and
 * cannot be rolled back; the database row participates in the caller's
 * transaction and can be. So if an operation fails and its transaction unwinds,
 * the DB has no misleading row saying it succeeded, and the log file still has
 * a line saying it was attempted. Each sink is wrong in a different direction,
 * on purpose.
 *
 * The Monolog write goes through this bundle's own LoggingManager rather than
 * an injected channel logger, because that class already falls back to the
 * default logger when a channel is missing — so an application without Monolog
 * channels configured degrades to a plain log line instead of a container
 * error. The 'audit' channel itself is registered for you by
 * SymfonyHelpersExtension::prepend().
 *
 * @see \Pixiekat\SymfonyHelpers\Entity\AuditLog
 * @see \Pixiekat\SymfonyHelpers\Services\LoggingManager
 */
class AuditLogManager {

  /**
   * The Monolog channel audit entries are mirrored to.
   */
  public const CHANNEL = 'audit';

  /**
   * The pre-rework log() signature, renamed. Delete this line and
   * src/Traits/Deprecated/ once no application calls it.
   */
  use DeprecatedTraits\AuditLogManagerDeprecatedTrait;

  /**
   * Constructor.
   *
   * @param EntityManagerInterface $entityManager Persistence.
   * @param LoggingManager $logging Channel-aware logging with a safe fallback.
   * @param Security $security Identifies the acting user.
   * @param RequestStack $requestStack Supplies the client IP.
   * @param LoggerInterface $logger Used only to report that auditing itself failed.
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly LoggingManager $logging,
    private readonly Security $security,
    private readonly RequestStack $requestStack,
    private readonly LoggerInterface $logger,
  ) {  }

  /**
   * Records an action to the database and to the audit log channel.
   *
   * ── ABOUT $flush ───────────────────────────────────────────────────────────
   * Defaults to TRUE, which is the safe default for the way audit calls are
   * usually written:
   *
   *     $em->remove($vocabulary);
   *     $em->flush();
   *     $audit->log('vocabulary.deleted', $vocabulary);   // flushes its own row
   *
   * With a false default that entry would be persisted and then silently
   * dropped, because nothing flushes afterwards — a lost audit trail that
   * nobody notices for months.
   *
   * The cost is that flush() commits the WHOLE unit of work, not just this row.
   * If you are mid-way through building other entities and are not ready to
   * commit them, pass flush: false and let your own flush carry the audit row:
   *
   *     $em->persist($user);
   *     $audit->log('user.created', $user, flush: false);
   *     $em->flush();                                     // both, atomically
   *
   * @param string $action Machine key for what happened, e.g. 'user.deleted'.
   * @param object|null $target The thing acted upon. Pass it BEFORE deleting it,
   *   so the label snapshot can still be read off it.
   * @param array $context Structured detail; handed to Monolog as PSR-3 context.
   * @param bool $flush Whether to flush immediately — see above.
   *
   * @return AuditLog|null The entry, or null if writing it failed.
   */
  public function log(string $action, ?object $target = null, array $context = [], bool $flush = true): ?AuditLog {
    // The channel write happens first and unconditionally: if the database
    // write below throws, the fact that this was attempted is already recorded
    // somewhere that a failing transaction cannot take back.
    $this->writeToChannel($action, $target, $context);

    try {
      $entry = $this->build($action, $target, $context);

      $this->entityManager->persist($entry);

      if ($flush) {
        $this->entityManager->flush();
      }

      return $entry;
    }
    catch (\Throwable $e) {
      // Never rethrow. Failing to audit an action must not undo the action —
      // but it must be loud, which is why this is an error() and not a silent
      // `return false` the way the old implementation did it.
      $this->logger->error('Failed to write audit entry for "{action}": {message}', [
        'action' => $action,
        'message' => $e->getMessage(),
        'exception' => $e,
      ]);

      return null;
    }
  }

  /**
   * Records an action to the audit log channel only, never the database.
   *
   * For the frequent and the forgettable. Nothing to flush, nothing to prune,
   * no row to page through in the admin screen.
   *
   * @param string $action Machine key for what happened.
   * @param object|null $target The thing acted upon.
   * @param array $context Structured detail.
   *
   * @return void
   */
  public function logToLogger(string $action, ?object $target = null, array $context = []): void {
    $this->writeToChannel($action, $target, $context);
  }

  /**
   * Builds an entry, filling in actor, target and request details.
   *
   * @param string $action The action key.
   * @param object|null $target The thing acted upon.
   * @param array $context Structured detail.
   *
   * @return AuditLog The unsaved entry.
   */
  private function build(string $action, ?object $target, array $context): AuditLog {
    $entry = new AuditLog($action);
    $entry->setContext($context);
    $entry->setIpAddress($this->requestStack->getCurrentRequest()?->getClientIp());

    $user = $this->security->getUser();
    if ($user instanceof HelpersUserInterface) {
      $entry->setActor($user);
      $entry->setActorLabel($user->getUserIdentifier());
    }
    elseif ($user !== null) {
      // A user that does not implement our interface can still be named, even
      // though it cannot be related to. Better a label than nothing.
      $entry->setActorLabel($user->getUserIdentifier());
    }

    if ($target !== null) {
      [$type, $id, $label] = $this->describe($target);
      $entry->setTargetType($type)->setTargetId($id)->setTargetLabel($label);
    }

    return $entry;
  }

  /**
   * Works out how to describe an arbitrary object in the log.
   *
   * Uses duck typing rather than demanding targets implement an interface: the
   * things worth auditing include entities from this bundle, from the host app,
   * and from third-party bundles, and requiring an interface would mean the
   * interesting ones simply never get audited.
   *
   * @param object $target The thing acted upon.
   *
   * @return array{0: string, 1: string|null, 2: string|null} Type, id, label.
   */
  private function describe(object $target): array {
    // Short class name, snake_cased: App\Entity\BlockItem -> block_item. The
    // FQCN is deliberately not stored — moving a class between namespaces
    // should not retroactively change what old rows say.
    $short = (new \ReflectionClass($target))->getShortName();
    $type = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short));

    $id = null;
    if (method_exists($target, 'getId')) {
      $rawId = $target->getId();
      $id = $rawId === null ? null : (string) $rawId;
    }

    // First accessor that yields something non-empty wins. __toString last,
    // because entities in this bundle define it as a sensible summary but a
    // third-party one might return something enormous.
    $label = null;
    foreach (['getDisplayName', 'getLabel', 'getName', 'getTitle', '__toString'] as $method) {
      if (!method_exists($target, $method)) {
        continue;
      }

      try {
        $candidate = $target->{$method}();
      }
      catch (\Throwable) {
        // An accessor that throws on a half-built or already-removed entity is
        // not a reason to lose the whole audit entry.
        continue;
      }

      if (is_string($candidate) && trim($candidate) !== '') {
        $label = trim($candidate);
        break;
      }
    }

    return [$type, $id, $label];
  }

  /**
   * Mirrors an entry to the audit Monolog channel.
   *
   * @param string $action The action key.
   * @param object|null $target The thing acted upon.
   * @param array $context Structured detail.
   *
   * @return void
   */
  private function writeToChannel(string $action, ?object $target, array $context): void {
    $actor = $this->security->getUser()?->getUserIdentifier() ?? AuditLog::ACTOR_SYSTEM;

    $record = [
      'action' => $action,
      'actor' => $actor,
      'ip' => $this->requestStack->getCurrentRequest()?->getClientIp(),
    ];

    if ($target !== null) {
      [$type, $id, $label] = $this->describe($target);
      $record += ['target_type' => $type, 'target_id' => $id, 'target_label' => $label];
    }

    // Caller context last so it cannot be clobbered by the derived keys, but
    // also cannot clobber them — array union keeps the left-hand side.
    $this->logging->logToChannel(self::CHANNEL, 'info', $this->summarise($action, $actor, $record), $record + $context);
  }

  /**
   * Builds the human-readable half of the log line.
   *
   * The structured context is what you grep; this is what you read.
   *
   * @param string $action The action key.
   * @param string $actor The actor label.
   * @param array $record The derived target details.
   *
   * @return string A single-line summary.
   */
  private function summarise(string $action, string $actor, array $record): string {
    $summary = sprintf('%s %s', $actor, $action);

    if (!empty($record['target_type'])) {
      $summary .= ' ' . $record['target_type'];
      $summary .= ' ' . ($record['target_label'] ?? '#' . ($record['target_id'] ?? '?'));
    }

    return $summary;
  }
}
