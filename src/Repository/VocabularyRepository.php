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
 * @extends ServiceEntityRepository<Entity\Vocabulary>
 */
class VocabularyRepository extends ServiceEntityRepository {
  use PixieTraits\Repository\CacheableFindAllTrait;
  use PixieTraits\Repository\CacheableFindByTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ManagerRegistry $registry,
    private readonly LoggerInterface $logger,
  ) {
    parent::__construct($registry, Entity\Vocabulary::class);
  }

  /**
   * Finds all disabled vocabularies.
   *
   * @return Entity\Vocabulary[]
   */
  public function findAllDisabled(): array {
    return $this->findBy(['enabled' => false]);
  }

  /**
   * Finds all enabled vocabularies.
   *
   * @return Entity\Vocabulary[]
   */
  public function findAllEnabled(): array {
    return $this->findBy(['enabled' => true]);
  }

  /**
   * Finds all unlocked vocabularies.
   *
   * @return Entity\Vocabulary[]
   */
  public function findAllLocked(): array {
    return $this->findBy(['locked' => true]);
  }

  /**
   * Finds all unlocked vocabularies.
   *
   * @return Entity\Vocabulary[]
   */
  public function findAllSortedByLabel(): array {
    return $this->findBy([], ['label' => 'ASC']);
  }

  /**
   * Finds a vocabulary by label.
   *
   * @param string $label
   * @return Entity\Vocabulary|null
   */
  public function findByLabel(string $label): ?Entity\Vocabulary {
    return $this->findOneBy(['label' => $label]);
  }

  /**
   * Finds a vocabulary by machine name.
   *
   * @param string $machineName
   * @return Entity\Vocabulary|null
   * @see self::findByLabel()
   * @aliasing self::findByLabel
   */
  public function findByMachineName(string $machineName): ?Entity\Vocabulary {
    return $this->findByLabel($machineName);
  }

}
