<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Traits as PixieTraits;

/**
 * @extends ServiceEntityRepository<Entity\Block>
 *
 * @method Entity\Block|null find($id, $lockMode = null, $lockVersion = null)
 * @method Entity\Block|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Entity\Block[]    findAll()
 */
class BlockRepository extends ServiceEntityRepository {
  use PixieTraits\Repository\CacheableFindAllTrait;
  use PixieTraits\Repository\CacheableFindByTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, Entity\Block::class);
  }

  /**
   * Loads a block and its items by machine name, in one query.
   *
   * Why the explicit join rather than just findOneBy(['name' => $name]):
   * rendering a block always touches its items, so fetching them lazily would
   * mean a second query per block on every page load (the classic N+1). Joining
   * up front makes placing a block cost exactly one round trip.
   *
   * setCacheable(true) opts the query into Doctrine's second-level cache where
   * the application has enabled it, and is harmless where it has not — blocks
   * are read on essentially every request and written approximately never,
   * which is the ideal profile for that cache.
   *
   * @param string $name The machine name, e.g. 'social_links'.
   * @param bool $enabledOnly Whether to ignore disabled blocks. Rendering passes
   *   true; an admin UI wants false so it can still find them.
   *
   * @return Entity\Block|null The block, or null if there is no such block.
   */
  public function findOneByName(string $name, bool $enabledOnly = true): ?Entity\Block {
    $query = $this->createQueryBuilder('b')
      ->addSelect('i')
      ->leftJoin('b.items', 'i')
      ->andWhere('b.name = :name')
      ->setParameter('name', $name)
      ->addOrderBy('i.weight', 'ASC')
      ->addOrderBy('i.id', 'ASC')
      ->setCacheable(true)
    ;

    if ($enabledOnly) {
      $query->andWhere('b.enabled = :enabled')->setParameter('enabled', true);
    }

    return $query->getQuery()->getOneOrNullResult();
  }

  /**
   * Loads every block with its items, for the admin listing.
   *
   * The fetch join matters here: the list template shows an item count per
   * block, and without it Doctrine would lazily load each block's collection
   * separately — one query for the blocks plus one per block, the classic N+1.
   * Includes disabled blocks, because an admin screen that hides the thing you
   * came to re-enable is not much help.
   *
   * @return Entity\Block[] The blocks, weight then name ordered.
   */
  public function findAllWithItems(): array {
    return $this->createQueryBuilder('b')
      ->addSelect('i')
      ->leftJoin('b.items', 'i')
      ->addOrderBy('b.weight', 'ASC')
      ->addOrderBy('b.name', 'ASC')
      ->addOrderBy('i.weight', 'ASC')
      ->getQuery()
      ->getResult()
    ;
  }

  /**
   * Loads every enabled block, weight-ordered.
   *
   * Useful for an admin listing, or for a template that wants to loop over a
   * set of blocks rather than naming each one.
   *
   * @return Entity\Block[] The enabled blocks.
   */
  public function findAllEnabled(): array {
    return $this->createQueryBuilder('b')
      ->andWhere('b.enabled = :enabled')
      ->setParameter('enabled', true)
      ->addOrderBy('b.weight', 'ASC')
      ->addOrderBy('b.name', 'ASC')
      ->setCacheable(true)
      ->getQuery()
      ->getResult()
    ;
  }
}
