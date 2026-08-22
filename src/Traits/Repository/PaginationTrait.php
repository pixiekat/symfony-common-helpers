<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Repository;

use Doctrine\ORM\Tools\Pagination\Paginator;

trait PaginationTrait {
  /**
   * Returns a Doctrine Paginator (Countable + iterable): count($paginator) is the
   * grand total across all pages; iterating yields only this page's rows.
   *
   * Returns a Doctrine Paginator (Countable + iterable): count($paginator) is the
   * grand total across all pages; iterating yields only this page's rows.
   */
  public function paginateStandalone(int $page, int $perPage = 25, ?array $orderBy = []): Paginator {
    $page = max(1, $page);

    $orderBy = $orderBy ?: ['id' => 'DESC'];

    $query = $this->createQueryBuilder('p')
      ->orderBy('p.updatedAt', 'DESC')
      ->addOrderBy('p.id', 'DESC')
      ->setFirstResult(($page - 1) * $perPage)
      ->setMaxResults($perPage)
    ;

    if ($orderBy) {
      foreach ($orderBy as $field => $direction) {
        $query->addOrderBy("p.$field", $direction);
      }
    }

    $query
      ->getQuery();

    // No to-many joins in the query, so the cheaper count strategy is safe.
    return new Paginator($query, fetchJoinCollection: false);
  }
}
