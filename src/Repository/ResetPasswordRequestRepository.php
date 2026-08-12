<?php

namespace Pixiekat\SymfonyHelpers\Repository;

use Pixiekat\SymfonyHelpers\Entity\ResetPasswordRequest;
use Pixiekat\SymfonyHelpers\Interfaces\Entity\HelpersUserInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Persistence\Repository\ResetPasswordRequestRepositoryTrait;
use SymfonyCasts\Bundle\ResetPassword\Persistence\ResetPasswordRequestRepositoryInterface;

/**
 * @extends ServiceEntityRepository<ResetPasswordRequest>
 *
 * @method ResetPasswordRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method ResetPasswordRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method ResetPasswordRequest[]    findAll()
 * @method ResetPasswordRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ResetPasswordRequestRepository extends ServiceEntityRepository implements ResetPasswordRequestRepositoryInterface {
  use ResetPasswordRequestRepositoryTrait;

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, ResetPasswordRequest::class);
  }

  /**
   * Creates (but does not persist) a reset request for the given user.
   *
   * The `object $user` parameter type is inherited from SymfonyCasts'
   * ResetPasswordRequestRepositoryInterface and cannot be narrowed — PHP allows
   * an implementation to WIDEN a parameter type, never to tighten it. So we
   * accept `object` to satisfy the contract and then assert what we actually
   * need, which turns a confusing "typed property must not be accessed" error
   * deep inside Doctrine into a clear message at the point of the mistake.
   *
   * @param object $user The user requesting the reset. Must be a HelpersUserInterface.
   * @param \DateTimeInterface $expiresAt When the generated token stops working.
   * @param string $selector The public half of the token.
   * @param string $hashedToken The hashed private half.
   *
   * @throws \InvalidArgumentException If the app's user entity does not implement HelpersUserInterface.
   *
   * @return ResetPasswordRequestInterface The unsaved request.
   */
  public function createResetPasswordRequest(object $user, \DateTimeInterface $expiresAt, string $selector, string $hashedToken): ResetPasswordRequestInterface {
    if (!$user instanceof HelpersUserInterface) {
      throw new \InvalidArgumentException(sprintf(
        'Expected a user implementing %s, got %s. Add that interface to your user entity so the bundle can map the reset-password relation against it.',
        HelpersUserInterface::class,
        get_debug_type($user),
      ));
    }

    return new ResetPasswordRequest($user, $expiresAt, $selector, $hashedToken);
  }
}
