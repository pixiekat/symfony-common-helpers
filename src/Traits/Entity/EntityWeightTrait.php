<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityWeightTrait {

  #[ORM\Column(nullable: false, options: ['default' => 0])]
  private ?int $weight = 0;

  public function getWeight(): ?int {
    return $this->weight;
  }

  public function setWeight(?int $weight): self {
    $this->weight = $weight;

    return $this;
  }

}
