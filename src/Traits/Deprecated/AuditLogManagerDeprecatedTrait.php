<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Deprecated;

use Pixiekat\SymfonyHelpers\Entity\AuditLog;

/**
 * The pre-rework AuditLogManager::log() signature, under a new name.
 *
 * ── THIS ONE NEEDS A HAND, AND HERE IS WHY ─────────────────────────────────
 * The entity deprecations next door are seamless: old method name in, new
 * method called, nobody has to change anything. This one cannot be, because the
 * old and new APIs share a method NAME:
 *
 *     old:  log(string $action, string $entityType, string $performedBy, ?array $additionalData = [])
 *     new:  log(string $action, ?object $target = null, array $context = [], bool $flush = true)
 *
 * PHP resolves a method defined on the class in preference to one pulled in
 * from a trait, so a trait cannot shadow the real log(). Detecting which call
 * shape was used at runtime would mean widening the new signature to
 * `string|object|null` and branching on argument types — the sort of cleverness
 * that reads fine today and is a mystery in eighteen months.
 *
 * So the old signature lives on as logEntity(). Migrating is a rename:
 *
 *     $audit->log('Deleted', 'User', $me->getUserIdentifier(), ['id' => 7]);
 *     $audit->logEntity('Deleted', 'User', $me->getUserIdentifier(), ['id' => 7]);   // keeps working
 *     $audit->log('user.deleted', $user, ['reason' => 'spam']);                      // where you want to end up
 *
 * ── ON BREAKING NOTHING ────────────────────────────────────────────────────
 * Worth knowing before you worry about call sites: the old implementation
 * could never actually have written a row. AuditLogRepository was constructed
 * against App\Entity\AuditLog — a class that does not exist from inside this
 * bundle — so resolving the repository threw, and log() caught every exception
 * and returned false without a word. Any application "using" the old API has
 * been quietly recording nothing. logEntity() genuinely works, so upgrading may
 * make audit rows appear where there were none.
 *
 * ── HOW TO RETIRE IT ───────────────────────────────────────────────────────
 * Delete the `use` line in Services\AuditLogManager and delete
 * src/Traits/Deprecated/.
 *
 * @see \Pixiekat\SymfonyHelpers\Services\AuditLogManager
 *
 * @deprecated since 1.1, to be removed in 2.0.
 */
trait AuditLogManagerDeprecatedTrait {

  /**
   * Records an action using the pre-rework argument shape.
   *
   * The old parameters map cleanly onto the new columns — $entityType was the
   * target type, $performedBy was the actor label, $additionalData was the
   * context — so nothing is lost in translation. What you do NOT get is the
   * actor relation or the target id/label snapshots, because the old signature
   * never carried the objects they are derived from. That is the reason to
   * finish the migration rather than sit on this shim.
   *
   * @deprecated since 1.1, use log() with an object target instead.
   *
   * @param string $action The action. Historically display text ('Added'); the
   *   new convention is a machine key ('user.created').
   * @param string $entityType The type of thing acted upon.
   * @param string $performedBy Who did it.
   * @param array|null $additionalData Structured detail.
   *
   * @return bool True if the entry was written, matching the old return type.
   */
  public function logEntity(string $action, string $entityType, string $performedBy, ?array $additionalData = []): bool {
    trigger_deprecation(
      'pixiekat/symfony-common-helpers',
      '1.1',
      'Calling "%s()" is deprecated, use "log($action, $targetObject, $context)" instead.',
      __METHOD__,
    );

    // Goes through the normal path so the Monolog mirror, the IP capture and
    // the failure handling all behave identically to a modern call.
    //
    // flush: false matters here. The type and actor below cannot be derived —
    // the old signature passes strings, not the objects log() reads them from —
    // so they have to be applied afterwards. Letting log() flush first would
    // write the row, then leave those two columns to be carried by whatever
    // flush happened to come next, or by none at all.
    $entry = $this->log($action, null, $additionalData ?? [], flush: false);

    if (!$entry instanceof AuditLog) {
      return false;
    }

    $entry->setTargetType($entityType);
    $entry->setActorLabel($performedBy);

    try {
      $this->entityManager->flush();
    }
    catch (\Throwable $e) {
      $this->logger->error('Failed to write audit entry for "{action}": {message}', [
        'action' => $action,
        'message' => $e->getMessage(),
        'exception' => $e,
      ]);

      return false;
    }

    return true;
  }
}
