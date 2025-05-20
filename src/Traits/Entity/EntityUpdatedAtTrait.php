<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityUpdatedAtTrait {

  #[ORM\Column(name: 'updated_at', nullable: true)]
  private \DateTimeImmutable $updatedAt;

  public function getUpdatedAt(): ?\DateTimeImmutable {
    return $this->updatedAt ?? null;
  }

  public function setUpdatedAt(\DateTimeImmutable $updatedAt): static {
    $this->updatedAt = $updatedAt;

    return $this;
  }

  #[ORM\PreUpdate]
  public function setUpdatedAtValue(): void{
    $this->updatedAt = new \DateTimeImmutable();
  }
}
