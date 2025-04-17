<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

trait EntityIdTrait {
  public function id(): ?int {
    return $this->id;
  }

  public function getId(): ?int {
    return $this->id;
  }
}
