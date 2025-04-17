<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityIdTrait {

  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column(name: 'id', nullable: false)]
  private ?int $id = null;

  public function id(): ?int {
    return $this->id;
  }

  public function getId(): ?int {
    return $this->id;
  }
}
