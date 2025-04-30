<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Interfaces\Entity;

interface AuditLogInterface {

  public const AUDIT_LOG_ACTION_ADDED = 'Added';

  public const AUDIT_LOG_ACTION_UPDATED = 'Updated';

  public const AUDIT_LOG_ACTION_DELETED = 'Deleted';

  /**
   * Gets the audit log action.
   *
   * @return string The action of this audit log item.
   */
  public function getAction(): string;

  /**
   * Sets the audit log action.
   *
   * @param string $action The action of this audit log item.
   *
   * @return static The current instantiated instance.
   */
  public function setAction(string $action): static;
}
