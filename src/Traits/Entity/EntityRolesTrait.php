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

    // UID = 1 always has ROLE_SUPER_ADMIN
    if (method_exists($this, 'getId') && $this->getId() === 1) {
      $roles[] = 'ROLE_SUPER_ADMIN';
    }

    // if user has "TEST" in their last name, give them ROLE_TESTER
    if (method_exists($this, 'getLastName') && str_contains($this->getLastName(), 'TEST')) {
      $roles[] = 'ROLE_TESTER';
    }

    // guarantee every user at least has ROLE_USER
    if (empty($roles)) {
      $roles[] = 'ROLE_USER';
    }
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
