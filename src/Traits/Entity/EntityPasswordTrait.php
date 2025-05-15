<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityPasswordTrait {

  #[ORM\Column]
  private ?string $password = null;

  /**
   * @see PasswordAuthenticatedUserInterface
   */
  public function getPassword(): ?string {
    return $this->password;
  }

  public function setPassword(string $password): static {
    $this->password = $password;

    return $this;
  }

}
