<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityRolesTrait {

  #[ORM\Column]
  private array $roles = [];

  /**
   * @see UserInterface
   */
  public function getRoles(): array {
    $roles = $this->roles;
    $roles[] = 'ROLE_USER';
    return array_unique($roles);
  }

  /**
   * @param list<string> $roles
   */
  public function setRoles(array $roles): static {
    $this->roles = $roles;

    return $this;
  }

}
