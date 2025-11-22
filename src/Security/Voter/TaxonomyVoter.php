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

final class TaxonomyVoter extends BaseVoter implements Interfaces\Security\Voter\TaxonomyVoterInterface {

  protected function supports(string $attribute, mixed $subject): bool {
    return in_array($attribute, $this->getAttributes()) && ($subject instanceof Entity\Term || $subject instanceof Entity\Vocabulary || $subject === null);
  }

  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
    $user = $token->getUser();

    if (!$user instanceof UserInterface) {
      return false;
    }

    switch ($attribute) {
      default:
        return ($this->isSysAdmin() || $this->hadRole('ROLE_ADMIN'));
        break;
    }

    // If none of the above, deny access
    return false;
  }

}
