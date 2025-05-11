<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Repository;

use Pixiekat\SymfonyHelpers\Entity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entity\Ban>
 */
class BanRepository extends ServiceEntityRepository {

  /**
   * {@inheritdoc}
   */
  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, Entity\Ban::class);
  }

  /**
   * Checks to see if a given IP address is banned.
   */
  public function findIfIpBanned(string $ipAddress): ?Entity\Ban {
    $qb = $this->createQueryBuilder('b');

    $qb->andWhere(
      $qb->expr()->orX(
        $qb->expr()->isNull('b.expiresAt'),
        $qb->expr()->gt('b.expiresAt', ':now')
      ),
    );

    $qb->andWhere('b.ipAddress = :ipAddress');
    $qb
      ->setParameter('ipAddress', $ipAddress)
      ->setParameter('now', new \DateTimeImmutable());
    ;

    $query = $qb->getQuery();
    $result = $query->getOneOrNullResult();
    return $result ?? null;
  }
}
