<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityLastNameTrait {

  #[ORM\Column(name: 'last_name', type: 'string', nullable: false)]
  private string $lastName;

  /**
   * {@inheritdoc}
   */
  public function getLastName(): string {
    return $this->lastName;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastName(string $lastName): static {
    $this->lastName = $lastName;

    return $this;
  }

}
