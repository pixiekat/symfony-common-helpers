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

  /**
   * Governed by symfony_helpers.features.taxonomy.
   *
   * With that switch off, BaseVoter::vote() abstains before any check below
   * runs, so this feature vanishes from the control panel menu and its routes
   * answer 403.
   */
  protected const FEATURE = 'taxonomy';

  protected function supports(string $attribute, mixed $subject): bool {
    return in_array($attribute, $this->getAttributes()) && ($subject instanceof Entity\Term || $subject instanceof Entity\Vocabulary || $subject === null);
  }

  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
    $user = $token->getUser();

    if (!$user instanceof UserInterface) {
      return false;
    }

    switch ($attribute) {
      case self::TAXONOMY_REMOVE_TERM_VOCABULARY:
        // A locked block is one somebody deliberately protected — usually
        // because a template calls place_block() on it and deleting it would
        // leave a hole in the page. Only a sysadmin gets past that.
        if ($subject instanceof Entity\Vocabulary && $subject->isLocked()) {
          return $this->isSysAdmin();
        }

        return $this->isSysAdmin() || $this->hasRole('ROLE_ADMIN');

      default:
        return $this->isSysAdmin() || $this->hasRole('ROLE_ADMIN');
    }

    // If none of the above, deny access
    return false;
  }

}
