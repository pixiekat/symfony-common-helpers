<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

trait EntityDescriptionTrait {

  /**
   * {@inheritdoc}
   */
  public function description(): mixed {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): mixed {
    return $this->description();
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription(mixed $description): self {
    $this->description = $description;
    return $this;
  }
}
