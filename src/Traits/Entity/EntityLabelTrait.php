<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityLabelTrait {

  #[ORM\Column(name: 'label', length: 255, nullable: true)]
  private ?string $label = null;

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
