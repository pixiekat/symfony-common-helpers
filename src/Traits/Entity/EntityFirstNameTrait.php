<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityFirstNameTrait {

  #[ORM\Column(name: 'first_name', type: 'string', nullable: false)]
  private string $firstName;

  /**
   * {@inheritdoc}
   */
  public function getFirstName(): string {
    return $this->firstName;
  }

  /**
   * {@inheritdoc}
   */
  public function setFirstName(string $firstName): static {
    $this->firstName = $firstName;

    return $this;
  }

}
