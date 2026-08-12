<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Pixiekat\SymfonyHelpers\Entity;

/**
 * @extends ServiceEntityRepository<Entity\AuditLog>
 *
 * @method Entity\AuditLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method Entity\AuditLog|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Entity\AuditLog[]    findAll()
 *
 * NOTE: this class used to open with `use App\Entity;` and construct against
 * App\Entity\AuditLog — a class that does not exist, because App\ belongs to the
 * consuming application, not to this bundle. Any attempt to resolve the
 * repository therefore threw, which is why the audit log had almost certainly
 * never actually run. Same root cause as the old ResetPasswordRequest coupling.
 */
class AuditLogRepository extends ServiceEntityRepository {

  /**
   * {@inheritdoc}
   *
   * Deliberately not cacheable: audit rows are append-only and read newest-first
   * by a handful of administrators. A second-level cache would be invalidated by
   * essentially every write and read back by almost nobody.
   */
  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, Entity\AuditLog::class);
  }

  /**
   * The moderation/inspection listing, newest first.
   *
   * @param array $filters Any of: action (exact), action_prefix (matches a whole
   *   subsystem, e.g. 'user' finds 'user.*'), target_type, actor_label, ip,
   *   from (\DateTimeInterface), to (\DateTimeInterface).
   * @param int $limit Page size.
   * @param int $offset Rows to skip.
   *
   * @return Entity\AuditLog[] The entries.
   */
  public function findForAdmin(array $filters = [], int $limit = 50, int $offset = 0): array {
    $query = $this->buildFilteredQuery($filters)
      // Fetch-joined so the listing can link to a surviving actor without one
      // extra query per row.
      ->leftJoin('a.actor', 'u')->addSelect('u')
      ->addOrderBy('a.createdAt', 'DESC')
      ->addOrderBy('a.id', 'DESC')
      ->setMaxResults($limit)
      ->setFirstResult($offset)
    ;

    return $query->getQuery()->getResult();
  }

  /**
   * Total rows matching the same filters, for pagination.
   *
   * Kept as a separate COUNT rather than counting a fetched result set, so
   * paging through a large table never loads more than one page of entities.
   *
   * @param array $filters Same shape as findForAdmin().
   *
   * @return int The number of matching rows.
   */
  public function countForAdmin(array $filters = []): int {
    return (int) $this->buildFilteredQuery($filters)
      ->select('COUNT(a.id)')
      ->getQuery()
      ->getSingleScalarResult()
    ;
  }

  /**
   * The distinct action keys present in the table, for the filter dropdown.
   *
   * @return string[] The action keys, alphabetically.
   */
  public function findActions(): array {
    $rows = $this->createQueryBuilder('a')
      ->select('DISTINCT a.action')
      ->addOrderBy('a.action', 'ASC')
      ->getQuery()
      ->getScalarResult()
    ;

    return array_column($rows, 'action');
  }

  /**
   * The distinct target types present in the table, for the filter dropdown.
   *
   * @return string[] The target types, alphabetically.
   */
  public function findTargetTypes(): array {
    $rows = $this->createQueryBuilder('a')
      ->select('DISTINCT a.targetType')
      ->andWhere('a.targetType IS NOT NULL')
      ->addOrderBy('a.targetType', 'ASC')
      ->getQuery()
      ->getScalarResult()
    ;

    return array_filter(array_column($rows, 'targetType'));
  }

  /**
   * Deletes entries older than a cut-off.
   *
   * Issued as a single DQL DELETE rather than loading entities and removing
   * them: pruning can touch hundreds of thousands of rows, and hydrating those
   * into objects just to throw them away would exhaust memory for no benefit.
   *
   * @param \DateTimeImmutable $before Delete entries created strictly before this.
   *
   * @return int The number of rows deleted.
   */
  public function deleteOlderThan(\DateTimeImmutable $before): int {
    return (int) $this->createQueryBuilder('a')
      ->delete()
      ->andWhere('a.createdAt < :before')
      ->setParameter('before', $before)
      ->getQuery()
      ->execute()
    ;
  }

  /**
   * Counts entries older than a cut-off, without deleting them.
   *
   * Exists so the prune command can offer a --dry-run that reports the real
   * number. A destructive command you cannot rehearse is one people avoid.
   *
   * @param \DateTimeImmutable $before The cut-off.
   *
   * @return int The number of matching rows.
   */
  public function countOlderThan(\DateTimeImmutable $before): int {
    return (int) $this->createQueryBuilder('a')
      ->select('COUNT(a.id)')
      ->andWhere('a.createdAt < :before')
      ->setParameter('before', $before)
      ->getQuery()
      ->getSingleScalarResult()
    ;
  }

  /**
   * Applies the shared filter set.
   *
   * Split out so findForAdmin() and countForAdmin() cannot drift apart — a
   * paginator whose count uses different conditions from its page query is a
   * classic source of "page 4 of 3".
   *
   * @param array $filters The filters.
   *
   * @return QueryBuilder The builder, aliased 'a'.
   */
  private function buildFilteredQuery(array $filters): QueryBuilder {
    $query = $this->createQueryBuilder('a');

    if (!empty($filters['action'])) {
      $query->andWhere('a.action = :action')->setParameter('action', $filters['action']);
    }

    if (!empty($filters['action_prefix'])) {
      // LIKE 'user.%' — matches a whole subsystem. The prefix is escaped so a
      // literal % or _ typed into the filter box cannot turn into a wildcard.
      $prefix = addcslashes((string) $filters['action_prefix'], '%_\\');
      $query->andWhere('a.action LIKE :prefix')->setParameter('prefix', $prefix . '.%');
    }

    if (!empty($filters['target_type'])) {
      $query->andWhere('a.targetType = :targetType')->setParameter('targetType', $filters['target_type']);
    }

    if (!empty($filters['actor_label'])) {
      $actor = addcslashes((string) $filters['actor_label'], '%_\\');
      $query->andWhere('a.actorLabel LIKE :actorLabel')->setParameter('actorLabel', '%' . $actor . '%');
    }

    if (!empty($filters['ip'])) {
      $query->andWhere('a.ipAddress = :ip')->setParameter('ip', $filters['ip']);
    }

    if (!empty($filters['from']) && $filters['from'] instanceof \DateTimeInterface) {
      $query->andWhere('a.createdAt >= :from')->setParameter('from', $filters['from']);
    }

    if (!empty($filters['to']) && $filters['to'] instanceof \DateTimeInterface) {
      $query->andWhere('a.createdAt <= :to')->setParameter('to', $filters['to']);
    }

    return $query;
  }
}
