<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Deprecated;

/**
 * The pre-rework AuditLog API, forwarding to its replacement.
 *
 * ── WHAT THIS IS FOR ───────────────────────────────────────────────────────
 * The audit log gained an actor relation, a proper target (type + id + label)
 * and an IP address, and in doing so three property names changed:
 *
 *     entityType      ->  targetType
 *     performedBy     ->  actorLabel
 *     additionalData  ->  context
 *
 * Applications on an older version of this bundle call the old names. Rather
 * than break them on upgrade, every old method still exists here and forwards
 * to the new one, emitting a deprecation as it goes.
 *
 * ── HOW TO RETIRE IT ───────────────────────────────────────────────────────
 * Delete the `use` line in Entity\AuditLog and delete src/Traits/Deprecated/.
 * That is the entire removal. Nothing else in the bundle references any of
 * this, which is the whole reason it was gathered into one trait in one folder
 * instead of being sprinkled through the entity as commented-out cruft.
 *
 * The old AUDIT_LOG_ACTION_* constants live HERE rather than on
 * AuditLogInterface for the same reason: a constant declared on an interface
 * cannot be removed by deleting a trait, so it would outlive the cleanup and
 * have to be hunted down separately. (Constants in traits need PHP 8.2, which
 * this bundle already requires.)
 *
 * ── A WARNING THE DEPRECATIONS CANNOT GIVE YOU ─────────────────────────────
 * The AUDIT_LOG_ACTION_* VALUES changed, not just their names. They used to be
 * display text ('Added', 'Updated', 'Deleted') and are now machine keys
 * ('created', 'updated', 'deleted'). Code that compared a stored action against
 * the old literal string — `$log->getAction() === 'Added'` — will silently stop
 * matching, and no deprecation can catch that because the constant still
 * resolves. If you have existing rows, they hold the old capitalised values;
 * migrate them with something like:
 *
 *     UPDATE audit_logs SET action = LOWER(action) WHERE action IN ('Added','Updated','Deleted');
 *     UPDATE audit_logs SET action = 'created' WHERE action = 'added';
 *
 * @see \Pixiekat\SymfonyHelpers\Entity\AuditLog
 * @see \Pixiekat\SymfonyHelpers\Interfaces\Entity\AuditLogInterface
 *
 * @deprecated since 1.1, to be removed in 3.0.
 */
trait AuditLogDeprecatedTrait {

  /**
   * @deprecated since 1.1, use AuditLogInterface::ACTION_CREATED instead.
   *   NOTE the value changed from 'Added' to 'created'.
   */
  public const AUDIT_LOG_ACTION_ADDED = 'created';

  /**
   * @deprecated since 1.1, use AuditLogInterface::ACTION_UPDATED instead.
   *   NOTE the value changed from 'Updated' to 'updated'.
   */
  public const AUDIT_LOG_ACTION_UPDATED = 'updated';

  /**
   * @deprecated since 1.1, use AuditLogInterface::ACTION_DELETED instead.
   *   NOTE the value changed from 'Deleted' to 'deleted'.
   */
  public const AUDIT_LOG_ACTION_DELETED = 'deleted';

  /**
   * Gets the type of the thing acted upon.
   *
   * @deprecated since 1.1, use getTargetType() instead.
   *
   * @return string The target type. Returns '' rather than null where the new
   *   method would give null, because the old contract promised a string and
   *   callers will not be null-checking it.
   */
  public function getEntityType(): string {
    trigger_deprecation('pixiekat/symfony-common-helpers', '1.1', 'Calling "%s()" is deprecated, use "getTargetType()" instead.', __METHOD__);

    return $this->getTargetType() ?? '';
  }

  /**
   * Sets the type of the thing acted upon.
   *
   * @deprecated since 1.1, use setTargetType() instead.
   *
   * @param string $entityType The target type.
   * @return static
   */
  public function setEntityType(string $entityType): static {
    trigger_deprecation('pixiekat/symfony-common-helpers', '1.1', 'Calling "%s()" is deprecated, use "setTargetType()" instead.', __METHOD__);

    return $this->setTargetType($entityType);
  }

  /**
   * Gets who performed the action.
   *
   * @deprecated since 1.1, use getActorLabel() for the display name, or
   *   getActor() for the user entity itself — which the old string could never
   *   give you, and which is the reason for the rename.
   *
   * @return string The actor label.
   */
  public function getPerformedBy(): string {
    trigger_deprecation('pixiekat/symfony-common-helpers', '1.1', 'Calling "%s()" is deprecated, use "getActorLabel()" or "getActor()" instead.', __METHOD__);

    return $this->getActorLabel();
  }

  /**
   * Sets who performed the action.
   *
   * @deprecated since 1.1, use setActorLabel(), or better, setActor() so the
   *   entry keeps a real link to the account as well as a display snapshot.
   *
   * @param string $performedBy The actor label.
   * @return static
   */
  public function setPerformedBy(string $performedBy): static {
    trigger_deprecation('pixiekat/symfony-common-helpers', '1.1', 'Calling "%s()" is deprecated, use "setActorLabel()" or "setActor()" instead.', __METHOD__);

    return $this->setActorLabel($performedBy);
  }

  /**
   * Gets the structured detail attached to the entry.
   *
   * @deprecated since 1.1, use getContext() instead — renamed to match PSR-3,
   *   because the same array is now passed to Monolog as the record context.
   *
   * @return array The context.
   */
  public function getAdditionalData(): array {
    trigger_deprecation('pixiekat/symfony-common-helpers', '1.1', 'Calling "%s()" is deprecated, use "getContext()" instead.', __METHOD__);

    return $this->getContext();
  }

  /**
   * Sets the structured detail attached to the entry.
   *
   * @deprecated since 1.1, use setContext() instead.
   *
   * @param array $additionalData The context.
   * @return static
   */
  public function setAdditionalData(array $additionalData): static {
    trigger_deprecation('pixiekat/symfony-common-helpers', '1.1', 'Calling "%s()" is deprecated, use "setContext()" instead.', __METHOD__);

    return $this->setContext($additionalData);
  }
}
