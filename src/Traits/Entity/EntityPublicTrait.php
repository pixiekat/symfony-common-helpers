<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityPublicTrait {

  #[ORM\Column(name: 'is_public', nullable: false, options: ['default' => true])]
  private ?bool $isPublic = true;

  public function getIsPublic(): ?bool {
    return $this->isPublic;
  }

  public function isPublic(): bool {
    return $this->isPublic === true;
  }

  public function isPrivate(): bool {
    return $this->isPublic === false;
  }

  public function markPublic(): self {
    $this->setIsPublic(true);
    return $this;
  }

  public function setIsPublic(?bool $isPublic): self {
    $this->isPublic = $isPublic;

    return $this;
  }

  public function markPrivate(): self {
    $this->setIsPublic(false);
    return $this;
  }

}
