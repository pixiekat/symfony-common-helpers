<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Security\Voter;

use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Interfaces;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Decides who may administer blocks.
 *
 * Follows the same shape as TaxonomyVoter — administrators can do everything,
 * nobody else can do anything — with the switch left in place as the seam for
 * finer rules later (for example: locked blocks are editable only by a
 * sysadmin, or per-block ownership).
 */
final class BlockVoter extends BaseVoter implements Interfaces\Security\Voter\BlockVoterInterface {

  /**
   * {@inheritdoc}
   */
  protected function supports(string $attribute, mixed $subject): bool {
    return in_array($attribute, $this->getAttributes(), true)
      && ($subject instanceof Entity\Block || $subject instanceof Entity\BlockItem || $subject === null);
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
      case self::BLOCK_DELETE:
        // A locked block is one somebody deliberately protected — usually
        // because a template calls place_block() on it and deleting it would
        // leave a hole in the page. Only a sysadmin gets past that.
        if ($subject instanceof Entity\Block && $subject->isLocked()) {
          return $this->isSysAdmin();
        }

        return $this->isSysAdmin() || $this->hasRole('ROLE_ADMIN');

      default:
        return $this->isSysAdmin() || $this->hasRole('ROLE_ADMIN');
    }
  }
}
