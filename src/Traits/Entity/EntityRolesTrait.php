<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityRolesTrait {

  #[ORM\Column]
  private array $roles = [];

  /**
   * The user's roles: exactly what is stored, plus ROLE_USER if none are.
   *
   * Roles come ONLY from the stored list, which admins set in the control
   * panel. Two implicit grants used to live here and were removed on
   * 2026-10-29:
   *
   *   - User id 1 always got ROLE_SUPER_ADMIN (copied from vBulletin's "the
   *     installer's account is the admin"). It made the first row in the
   *     users table a permanent super admin that no admin screen could see
   *     or take away, which matters once imported or migrated data decides
   *     who row 1 is.
   *   - Anyone with "TEST" in their last name got ROLE_TESTER (copied from
   *     the old InfoSAGE). People choose their own names at registration,
   *     so that let anyone give themselves a role. It also threw a TypeError
   *     for any user without a last name, because str_contains() was handed
   *     null.
   *
   * BEFORE UPGRADING an application past this change, store the roles
   * explicitly: give user 1 ROLE_SUPER_ADMIN, and testers ROLE_TESTER, in
   * the admin control panel. Otherwise they silently lose them.
   *
   * @see UserInterface
   */
  public function getRoles(): array {
    $roles = $this->roles;

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
