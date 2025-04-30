<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Entity;

use Pixiekat\SymfonyHelpers\Interfaces;
use Pixiekat\SymfonyHelpers\Repository\AuditLogRepository;
use Pixiekat\SymfonyHelpers\Traits\Entity as PixieTraits;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'audit_logs')]
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
class AuditLog implements Interfaces\Entity\AuditLogInterface {
  use PixieTraits\EntityIdTrait;

  #[ORM\Column(type: 'string', nullable: false)]
  private string $action;

  #[ORM\Column(type: 'string', nullable: false)]
  private string $entityType;

  #[ORM\Column(type: 'string', nullable: false)]
  private string $performedBy;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable', nullable: false)]
  private \DateTimeImmutable $createdAt;

  /**
   * The constructor of this audit log.
   */
  public function __construct() {
    $this->setCreatedAt(new \DateTimeImmutable);
  }

  /**
   * {@inheritdoc}
   */
  public function getAction(): string {
    return $this->action;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType(): string {
    return $this->entityType;
  }

  /**
   * {@inheritdoc}
   */
  public function getPerformedBy(): string {
    return $this->performedBy;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedAt(): ?\DateTimeImmutable {
    return $this->createdAt ?? null;
  }

  /**
   * {@inheritdoc}
   */
  public function setAction(string $action): static {
    $this->action = $action;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityType(string $entityType): static {
    $this->entityType = $entityType;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setPerformedBy(string $performedBy): static {
    $this->performedBy = $performedBy;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedAt(\DateTimeImmutable $createdAt): static {
    $this->createdAt = $createdAt;

    return $this;
  }
}
