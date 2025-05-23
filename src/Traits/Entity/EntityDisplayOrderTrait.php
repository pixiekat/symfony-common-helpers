<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait EntityDisplayOrderTrait {

  #[ORM\Column(type: Types::INTEGER, nullable: true)]
  protected ?int $displayOrder = 0;

  public function displayOrder(): ?int {
    return $this->displayOrder;
  }

  public function getDisplayOrder(): ?int {
    return $this->displayOrder;
  }

  public function setDisplayOrder(int $displayOrder): static {
    $this->displayOrder = $displayOrder;

    return $this;
  }

  public function weight(): ?int {
    return $this->getDisplayOrder();
  }

}
