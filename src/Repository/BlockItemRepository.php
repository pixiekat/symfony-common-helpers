<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Traits as PixieTraits;

/**
 * @extends ServiceEntityRepository<Entity\BlockItem>
 *
 * @method Entity\BlockItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method Entity\BlockItem|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Entity\BlockItem[]    findAll()
 *
 * Deliberately thin: items are almost always reached through their Block, which
 * fetch-joins them in a single query. This repository exists for the admin-side
 * work of editing one item directly.
 */
class BlockItemRepository extends ServiceEntityRepository {
  use PixieTraits\Repository\CacheableFindAllTrait;
  use PixieTraits\Repository\CacheableFindByTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, Entity\BlockItem::class);
  }
}
