<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Services;

use Doctrine\ORM\EntityManagerInterface;
use Pixiekat\SymfonyHelpers\Entity;

class AuditLogManager {

  public function __construct(
    private readonly EntityManagerInterface $entityManager,
  ) {  }

  /**
   * {@inheritdoc}
   */
  public function log(string $action, string $entityType, string $performedBy, ?array $additionalData = []): bool {
    try {
      $auditlog = new Entity\AuditLog;
      $auditlog->setAction($action);
      $auditlog->setEntityType($entityType);
      $auditlog->setPerformedBy($performedBy);
      $auditlog->setAdditionalData($additionalData);
      $this->entityManager->persist($auditlog);
      $this->entityManager->flush();

      return true;
    }
    catch (\Exception $e) {
      return false;
    }
  }
}
