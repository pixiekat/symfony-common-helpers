<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Security\Voter;

use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Interfaces;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Decides who may read the audit log.
 *
 * Deliberately stricter than the other admin voters: the log records who did
 * what, from which IP address, and that is exactly the sort of thing an
 * ordinary administrator has no business browsing about their colleagues. Only
 * a sysadmin gets in by default.
 *
 * Loosen it by adding ROLE_ADMIN to the check below if your site is small
 * enough that the distinction is silly — but make that an explicit decision
 * rather than inheriting it from the taxonomy screens.
 */
final class AuditVoter extends BaseVoter implements Interfaces\Security\Voter\AuditVoterInterface {

  /**
   * {@inheritdoc}
   */
  protected function supports(string $attribute, mixed $subject): bool {
    return in_array($attribute, $this->getAttributes(), true)
      && ($subject instanceof Entity\AuditLog || $subject === null);
  }

  /**
   * {@inheritdoc}
   */
  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
    $user = $token->getUser();

    if (!$user instanceof UserInterface) {
      return false;
    }

    return $this->isSysAdmin();
  }
}
