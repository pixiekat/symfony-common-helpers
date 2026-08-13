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

final class BanVoter extends BaseVoter implements Interfaces\Security\Voter\BanVoterInterface {

  /**
   * Governed by symfony_helpers.features.bans.
   *
   * With that switch off, BaseVoter::vote() abstains before any check below
   * runs, so this feature vanishes from the control panel menu and its routes
   * answer 403.
   */
  protected const FEATURE = 'bans';

  use Traits\Voter\VoterCRUDTrait;

  protected function supports(string $attribute, mixed $subject): bool {
    return in_array($attribute, $this->getAttributes()) && ($subject instanceof Entity\Ban || $subject === null);
  }

  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
    $user = $token->getUser();
    if (!$user instanceof UserInterface) {
      return false;
    }

    return match ($attribute) {
      self::BAN_ADD_BAN,
      self::BAN_EDIT_BAN,
      self::BAN_LIST_BANS,
      self::BAN_REMOVE_BAN,
      self::BAN_VIEW_BAN => $this->isSysAdmin(),
      default => false,
    };
  }

}
