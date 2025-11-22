<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Repository;

use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Interfaces;
use Pixiekat\SymfonyHelpers\Traits as PixieTraits;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @extends ServiceEntityRepository<Entity\Term>
 */
class TermRepository extends ServiceEntityRepository {
  use PixieTraits\Repository\CacheableFindAllTrait;
  use PixieTraits\Repository\CacheableFindByTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ManagerRegistry $registry,
    private readonly LoggerInterface $logger,
  ) {
    parent::__construct($registry, Entity\Term::class);
  }

}
