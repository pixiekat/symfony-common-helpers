<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Security\Voter;

use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Interfaces;
use Pixiekat\SymfonyHelpers\Security as PixieSecurity;
use Pixiekat\SymfonyHelpers\Traits;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class LoginRegisterVoter extends BaseVoter implements Interfaces\Security\Voter\LoginRegisterVoterInterface {

  protected function supports(string $attribute, mixed $subject): bool {
    return in_array($attribute, $this->getAttributes());
  }

  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
    $user = $token->getUser();

    switch ($attribute) {
      case self::LOGIN_REGISTER_REGISTER_ACCOUNT:
        return $this->isAnonymous();
    }

    // If none of the above, deny access
    return false;
  }

}
