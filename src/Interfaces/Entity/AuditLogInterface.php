<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Interfaces\Entity;

use Pixiekat\SymfonyHelpers\Interfaces\Entity\HelpersUserInterface;

/**
 * Contract for an audit log entry.
 *
 * ── WHY THE ACTION CONSTANTS CHANGED VALUE ─────────────────────────────────
 * They used to be human sentences: 'Added', 'Updated', 'Deleted'. Storing
 * display text is a trap — it cannot be translated, it cannot be renamed
 * without rewriting every historical row, and it cannot be matched on reliably
 * ('Deleted' vs 'deleted' vs 'Removed'). The values are now stable machine keys
 * and the display text lives in translations/messages.en.yml under
 * `audit.action.*`, rendered by the audit_message() Twig function.
 *
 * Keys are namespaced with a dot — 'user.deleted', 'block.created' — so an
 * admin filter can match a whole subsystem with a LIKE 'user.%'.
 */
interface AuditLogInterface {

  /**
   * Generic CRUD actions, for callers that have nothing more specific to say.
   */
  public const ACTION_CREATED = 'created';
  public const ACTION_UPDATED = 'updated';
  public const ACTION_DELETED = 'deleted';

  /**
   * The old AUDIT_LOG_ACTION_* constants deliberately do NOT live here any more.
   * They are declared in Traits\Deprecated\AuditLogDeprecatedTrait alongside the
   * old methods, so retiring the whole deprecation surface is one `use` line and
   * one folder — not an archaeology exercise across three files. An interface
   * constant cannot be removed by deleting a trait; a trait constant can.
   *
   * @see \Pixiekat\SymfonyHelpers\Traits\Deprecated\AuditLogDeprecatedTrait
   */

  /**
   * Gets the machine key describing what happened, e.g. 'user.deleted'.
   *
   * @return string The action key.
   */
  public function getAction(): string;

  /**
   * Gets the user who performed the action, if they still exist.
   *
   * May be null for actions taken by the system (console commands, cron) or
   * where the account has since been deleted. Use getActorLabel() for display —
   * it survives both cases.
   *
   * @return HelpersUserInterface|null The actor.
   */
  public function getActor(): ?HelpersUserInterface;

  /**
   * Gets the actor's name as it was AT THE TIME the entry was written.
   *
   * Denormalised on purpose. An audit log whose text changes when someone
   * renames their account, or goes blank when the account is deleted, is not an
   * audit log — it is a join that happens to be right today.
   *
   * @return string The actor label, e.g. an email address, or 'system'.
   */
  public function getActorLabel(): string;

  /**
   * Gets the short type of the thing acted upon, e.g. 'block'.
   *
   * @return string|null The target type, or null for actions with no subject.
   */
  public function getTargetType(): ?string;

  /**
   * Gets the identifier of the thing acted upon.
   *
   * A string rather than an int so it can hold a uuid, a composite key or a
   * machine name without a second column.
   *
   * @return string|null The target id.
   */
  public function getTargetId(): ?string;

  /**
   * Gets the target's name as it was at the time, for the same reason as
   * getActorLabel(). This is the half that makes "deleted vocabulary #7"
   * readable a year later.
   *
   * @return string|null The target label.
   */
  public function getTargetLabel(): ?string;

  /**
   * Gets the IP address the action came from.
   *
   * @return string|null The address, or null for CLI actions.
   */
  public function getIpAddress(): ?string;

  /**
   * Gets arbitrary structured detail about the action.
   *
   * Named to match PSR-3: the same array is handed to Monolog as the log
   * record's context, so one shape serves both sinks.
   *
   * @return array The context.
   */
  public function getContext(): array;

  /**
   * Gets the moment the entry was written.
   *
   * @return \DateTimeImmutable|null The timestamp.
   */
  public function getCreatedAt(): ?\DateTimeImmutable;
}
