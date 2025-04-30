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

  #[ORM\Column(name: 'audit_action', type: 'string', nullable: false)]
  private $action = null;

  /**
   * {@inheritdoc}
   */
  public function getAction(): string {
    return $this->action;
  }

  /**
   * {@inheritdoc}
   */
  public function setAction(string $action): static {
    $this->action = $action;

    return $this;
  }
}
