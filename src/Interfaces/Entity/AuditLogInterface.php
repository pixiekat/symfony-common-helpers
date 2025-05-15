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
   * Gets the audit log entity type.
   *
   * @return string The entity type of this audit log item.
   */
  public function getEntityType(): string;

  /**
   * Gets the audit log owner.
   *
   * @return string The owner of this audit log item.
   */
  public function getPerformedBy(): string;

  /**
   * Gets the date the audit log was created.
   *
   * @return \DateTimeImmutable The DateTime object.
   */
  public function getCreatedAt(): ?\DateTimeImmutable;

  /**
   * Sets the audit log action.
   *
   * @param string $action The action of this audit log item.
   *
   * @return static The current instantiated instance.
   */
  public function setAction(string $action): static;

  /**
   * Sets the audit log entity type.
   *
   * @param string $entityType The entity type of this audit log item.
   *
   * @return static The current instantiated instance.
   */
  public function setEntityType(string $entityType): static;

  /**
   * Sets the audit log performed by.
   *
   * @param string $performedBy The owner of this audit log item.
   *
   * @return static The current instantiated instance.
   */
  public function setPerformedBy(string $performedBy): static;

  /**
   * Sets the audit log creation date.
   *
   * @param \DateTimeImmutable $createdAt The date of this audit log item.
   */
  public function setCreatedAt(\DateTimeImmutable $createdAt): static;
}
