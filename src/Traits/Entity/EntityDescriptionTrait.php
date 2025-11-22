<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityDescriptionTrait {

  #[ORM\Column(type: 'text', nullable: true)]
  private mixed $description = null;

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
