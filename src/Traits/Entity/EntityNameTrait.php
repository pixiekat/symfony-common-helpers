<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityNameTrait {

  #[ORM\Column(type: 'string', length: 255, nullable: false)]
  private string $name;

  /**
   * {@inheritdoc}
   */
  public function getName(): mixed {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
   public function name(): mixed {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function setName(mixed $name): self {
    $this->name = $name;
    return $this;
  }
}
