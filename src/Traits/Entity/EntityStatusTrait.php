<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use \DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

trait EntityStatusTrait {

  #[ORM\Column(name: 'status', nullable: false, options: ['default' => true])]
  private ?bool $status = true;

  public function getStatus(): ?bool {
    return $this->status;
  }

  public function isPublished(): bool {
    return $this->status === true;
  }

  public function isUnpublished(): bool {
    return $this->status === false;
  }

  public function publish(): self {
    $this->setStatus(true);
    return $this;
  }

  public function setStatus(?bool $status): self {
    $this->status = $status;

    return $this;
  }

  public function unpublish(): self {
    $this->setStatus(false);
    return $this;
  }

}
