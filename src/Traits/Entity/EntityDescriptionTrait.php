<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

trait EntityDescriptionTrait {

  public function description(): mixed {
    return $this->description;
  }

  public function getDescription(): mixed {
    return $this->description();
  }

  public function setDescription(mixed $description): self {
    $this->description = $description;
    return $this;
  }
}
