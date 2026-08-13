<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Security\Voter;

use Pixiekat\SymfonyHelpers\Services\FeatureChecker;
use Pixiekat\SymfonyHelpers\Traits as PixieTraits;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class BaseVoter extends Voter {

  /**
   * Which symfony_helpers.features switch governs this voter.
   *
   * Null means "not governed by a feature flag" — always considered enabled.
   * Subclasses override it with a feature name, e.g. 'blocks'.
   */
  protected const FEATURE = null;

  public function __construct(
    protected readonly Security $security,
    protected readonly LoggerInterface $logger,
    protected readonly FeatureChecker $features,
  ) {  }

  /**
   * Abstains outright when this voter's feature is switched off.
   *
   * Intercepting at vote() rather than in each subclass's supports() means a
   * new voter cannot forget to honour its flag — it gets the behaviour by
   * extending this class, which is the whole point of having a base voter.
   *
   * ABSTAIN, not DENIED. Abstaining leaves room for an application's own voter
   * to grant the same attribute for its own reasons; returning DENIED under the
   * default `affirmative` strategy would still be overridden by any granting
   * voter, so the practical outcome is the same, but abstaining states the
   * honest thing: this voter has no opinion about a feature it does not run.
   *
   * With no other voter granting it, an abstention leaves the attribute
   * ungranted, so a disabled feature's routes answer 403 rather than staying
   * quietly reachable to anyone who guesses the URL.
   *
   * {@inheritdoc}
   */
  public function vote(TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int {
    if ($this->features->isDisabled(static::FEATURE)) {
      return self::ACCESS_ABSTAIN;
    }

    return parent::vote($token, $subject, $attributes, $vote);
  }

  /**
   * Checks if the user has a specific role.
   *
   * @param string $role
   * @return boolean
   */
  public function hasRole(string $role): bool {
    return $this->security->isGranted($role);
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
    return !$this->security->isGranted('ROLE_USER');
  }

  /**
   * Checks if the user is authenticated.
   *
   * @return boolean
   */
  public function isAuthenticated(): bool {
    return $this->security->isGranted('ROLE_USER');
  }

  /**
   * Checks if the user is the first user (UID = 1).
   */
  public function isFirstUser(): bool {
    $user = $this->security->getUser();
    if ($user === null) {
      return false;
    }
    return method_exists($user, 'getId') && $user->getId() === 1;
  }

  /**
   * Gets a list of attributes that this voter supports.
   *
   * @return array
   */
  protected function getAttributes(): array {
    $constants = (new \ReflectionClass($this))->getConstants();
    return array_filter($constants, function ($key) {
      // ACCESS_* come from Voter itself and are vote results, not attributes.
      // FEATURE is this class's own flag name — without excluding it, a voter
      // declaring FEATURE = 'blocks' would start claiming to support an
      // attribute literally called 'blocks', so is_granted('blocks') would
      // resolve to something nobody meant.
      return strpos($key, 'ACCESS_') !== 0 && $key !== 'FEATURE';
    }, ARRAY_FILTER_USE_KEY);
  }

}
