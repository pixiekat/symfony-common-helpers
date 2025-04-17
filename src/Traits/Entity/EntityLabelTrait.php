<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

trait EntityLabelTrait {
  public function getLabel(): mixed {
    return $this->label();
  }

   public function label(): mixed {
    return $this->label;
  }

  public function setLabel(mixed $label): self {
    $this->label = $label;
    return $this;
  }
}
