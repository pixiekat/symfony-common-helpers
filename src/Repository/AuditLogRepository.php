<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Repository;

use App\Entity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entity\AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository {

  /**
   * {@inheritdoc}
   */
  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, Entity\AuditLog::class);
  }

}
