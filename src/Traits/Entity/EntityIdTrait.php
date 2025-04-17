<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityIdTrait {

  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column(name: 'id', nullable: false)]
  private ?int $id = null;

  /**
   * {@inheritdoc}
   */
  public function id(): ?int {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): ?int {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function setId(?int $id): static {
    $this->id = $id;

    return $this;
  }
}
