<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Repository;

trait CacheableFindAllTrait {
  public function findAll(): array {
    return $this->createQueryBuilder('t')
      ->getQuery()
      ->setCacheable(true)
      ->getResult()
    ;
  }
}
