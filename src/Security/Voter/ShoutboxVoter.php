<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Security\Voter;

use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Interfaces;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Decides who may shout and who may moderate.
 *
 * The one interesting decision is SHOUT_POST, which is granted to EVERYONE
 * including anonymous visitors — an open shoutbox being the normal case. Abuse
 * is handled downstream by ShoutboxManager (ban checks and flood control)
 * rather than by refusing anonymous posting outright.
 *
 * To make the shoutbox members-only, change the SHOUT_POST branch to
 * `return $this->isAuthenticated();` — one line, one place, and every entry
 * point respects it because they all ask the voter.
 */
final class ShoutboxVoter extends BaseVoter implements Interfaces\Security\Voter\ShoutboxVoterInterface {

  /**
   * {@inheritdoc}
   */
  protected function supports(string $attribute, mixed $subject): bool {
    return in_array($attribute, $this->getAttributes(), true)
      && ($subject instanceof Entity\Shout || $subject === null);
  }

  /**
   * {@inheritdoc}
   */
  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {

    // Checked BEFORE the UserInterface guard below, because the whole point of
    // this attribute is that it is available to visitors with no account.
    if ($attribute === self::SHOUT_POST) {
      return true;
    }

    $user = $token->getUser();

    if (!$user instanceof UserInterface) {
      return false;
    }

    return $this->isSysAdmin() || $this->hasRole('ROLE_ADMIN');
  }
}
