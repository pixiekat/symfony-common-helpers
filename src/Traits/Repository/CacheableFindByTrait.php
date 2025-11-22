<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Repository;

trait CacheableFindByTrait {
  public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array {
    $query = $this->createQueryBuilder('t')
      ->setCacheable(true)
    ;

    if ($criteria) {
      foreach ($criteria as $field => $value) {
        $paramName = str_replace('.', '_', $field);
        $query->andWhere("t.$field = :$paramName")
          ->setParameter($paramName, $value);
      }
    }

    if ($orderBy) {
      foreach ($orderBy as $field => $direction) {
        $query->addOrderBy("t.$field", $direction);
      }
    }

    $query
      ->setMaxResults($limit)
      ->setFirstResult($offset)
    ;

    return $query->getQuery()->getResult();
  }
}
