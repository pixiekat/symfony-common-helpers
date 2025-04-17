<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

trait EntityLabelTrait {

  /**
   * {@inheritdoc}
   */
  public function getLabel(): mixed {
    return $this->label();
  }

  /**
   * {@inheritdoc}
   */
   public function label(): mixed {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel(mixed $label): self {
    $this->label = $label;
    return $this;
  }
}
