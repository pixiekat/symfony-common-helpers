<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Security\Voter;

use Pixiekat\SymfonyHelpers\Interfaces;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * General administrative access control.
 */
final class AdminVoter extends BaseVoter implements Interfaces\Security\Voter\AdminVoterInterface {

  /**
   * {@inheritdoc}
   */
  protected function supports(string $attribute, mixed $subject): bool {
    return in_array($attribute, $this->getAttributes(), true)
      && ($subject === null);
  }

  /**
   * {@inheritdoc}
   */
  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
    $user = $token->getUser();

    if (!$user instanceof UserInterface) {
      return false;
    }

    switch ($attribute) {
      case self::ADMIN_ADMINISTER:
        return $this->isSysAdmin() || $this->hasRole('ROLE_ADMIN');

      default:
        // Return false for default since if we get to this point, we don't want them to get to the admin panel.
        return false;
    }
  }
}
