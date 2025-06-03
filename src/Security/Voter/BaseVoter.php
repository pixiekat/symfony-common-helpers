<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Security\Voter;

use Symfony\Bundle\SecurityBundle\Security;
use Pixiekat\SymfonyHelpers\Traits as PixieTraits;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

abstract class BaseVoter extends Voter {

  public function __construct(
    protected readonly Security $security,
    protected readonly LoggerInterface $logger,
  ) {  }

  /**
   * Checks if the user has a specific role.
   *
   * @param string $role
   * @return boolean
   */
  public function hasRole(string $role): bool {
    return $this->security->isGranted($role) ?? false;
  }

  /**
   * Checks if the user is an admin.
   */
  public function isSysAdmin(): bool {
    foreach (['ROLE_SYSADMIN', 'ROLE_SUPER_ADMIN'] as $role) {
      if ($this->hasRole($role)) {
        return true;
      }
    }
    return false;
  }

  /**
   * @deprecated See self::isSysAdmin()
   */
  public function isAdmin(): bool {
    return $this->isSysAdmin();
  }

  /**
   * Checks if the user is anonymous.
   *
   * @return boolean
   */
  public function isAnonymous(): bool {
    return !$this->security->isGranted('ROLE_USER') ?? false;
  }

  /**
   * Checks if the user is authenticated.
   *
   * @return boolean
   */
  public function isAuthenticated(): bool {
    return $this->security->isGranted('ROLE_USER') ?? false;
  }

  /**
   * Gets a list of attributes that this voter supports.
   *
   * @return array
   */
  protected function getAttributes(): array {
    $constants = (new \ReflectionClass($this))->getConstants();
    return array_filter($constants, function ($key) {
      return strpos($key, "ACCESS_") !== 0;
    }, ARRAY_FILTER_USE_KEY);
  }

}
