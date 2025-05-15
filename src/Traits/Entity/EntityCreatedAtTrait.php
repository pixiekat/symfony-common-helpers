<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityCreatedAtTrait {

  #[ORM\Column(name: 'created_at', nullable: false)]
  private \DateTimeImmutable $createdAt;

  public function getCreatedAt(): \DateTimeImmutable {
    return $this->createdAt ?? new \DateTimeImmutable;
  }

  public function setCreatedAt(\DateTimeImmutable $createdAt): static {
    $this->createdAt = $createdAt;

    return $this;
  }

  #[ORM\PrePersist]
  public function setCreatedAtValue(): void{
    $this->createdAt = new \DateTimeImmutable();
  }
}
