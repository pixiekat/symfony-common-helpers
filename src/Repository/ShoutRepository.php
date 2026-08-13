<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Enum\ShoutStatus;

/**
 * @extends ServiceEntityRepository<Entity\Shout>
 *
 * @method Entity\Shout|null find($id, $lockMode = null, $lockVersion = null)
 * @method Entity\Shout|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Entity\Shout[]    findAll()
 */
class ShoutRepository extends ServiceEntityRepository {

  /**
   * {@inheritdoc}
   *
   * Note this repository deliberately does NOT use CacheableFindByTrait: shouts
   * change constantly and are read newest-first, so a second-level cache would
   * be invalidated almost every time it was consulted.
   */
  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, Entity\Shout::class);
  }

  /**
   * The most recent public shouts in a channel, newest first.
   *
   * @param string $channel The channel to read.
   * @param int $limit How many to return.
   *
   * @return Entity\Shout[] The shouts, newest first.
   */
  public function findLatest(string $channel = Entity\Shout::DEFAULT_CHANNEL, int $limit = 20): array {
    return $this->createQueryBuilder('s')
      ->leftJoin('s.author', 'a')->addSelect('a')
      ->andWhere('s.channel = :channel')->setParameter('channel', $channel)
      ->andWhere('s.status = :status')->setParameter('status', ShoutStatus::Published)
      ->addOrderBy('s.createdAt', 'DESC')
      ->addOrderBy('s.id', 'DESC')
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult()
    ;
  }

  /**
   * Every shout in a channel regardless of status, newest first.
   *
   * For the moderation queue, which needs to see the spam and the soft-deleted
   * rows that findLatest() hides.
   *
   * @param string|null $channel A channel to filter by, or null for all channels.
   * @param int $limit How many to return.
   *
   * @return Entity\Shout[] The shouts, newest first.
   */
  public function findForModeration(?string $channel = null, int $limit = 100): array {
    $query = $this->createQueryBuilder('s')
      ->leftJoin('s.author', 'a')->addSelect('a')
      ->addOrderBy('s.createdAt', 'DESC')
      ->addOrderBy('s.id', 'DESC')
      ->setMaxResults($limit)
    ;

    if ($channel !== null) {
      $query->andWhere('s.channel = :channel')->setParameter('channel', $channel);
    }

    return $query->getQuery()->getResult();
  }

  /**
   * The distinct channels that currently have shouts in them.
   *
   * Used to build the channel filter in the admin UI. Channels are implicit —
   * they exist because something was posted to them — so this is the only way
   * to enumerate them.
   *
   * @return string[] The channel names, alphabetically.
   */
  public function findChannels(): array {
    $rows = $this->createQueryBuilder('s')
      ->select('DISTINCT s.channel')
      ->addOrderBy('s.channel', 'ASC')
      ->getQuery()
      ->getScalarResult()
    ;

    return array_column($rows, 'channel');
  }

  /**
   * Counts an address's shouts that are still published.
   *
   * The trust signal behind "first post moderated": an address whose earlier
   * shouts are all still up has, by definition, never been moderated. Counting
   * only Published rows is what makes that true — the moment a moderator marks
   * one Spam or Deleted, the count drops and the address loses its trust
   * automatically, with no separate reputation table to keep in step.
   *
   * @param string $ipAddress The address to check.
   *
   * @return int How many of its shouts are currently published.
   */
  public function countPublishedFromIp(string $ipAddress): int {
    return (int) $this->createQueryBuilder('s')
      ->select('COUNT(s.id)')
      ->andWhere('s.ipAddress = :ip')->setParameter('ip', $ipAddress)
      ->andWhere('s.status = :status')->setParameter('status', ShoutStatus::Published)
      ->getQuery()
      ->getSingleScalarResult()
    ;
  }

  /**
   * Counts recent shouts from one IP address, for flood control.
   *
   * Counts EVERY status, not just published ones — otherwise moderating a
   * flooder's posts would hand them a fresh quota, which is precisely backwards.
   *
   * @param string $ipAddress The address to check.
   * @param \DateTimeImmutable $since Only count shouts posted after this moment.
   *
   * @return int How many shouts that address has posted in the window.
   */
  public function countRecentFromIp(string $ipAddress, \DateTimeImmutable $since): int {
    return (int) $this->createQueryBuilder('s')
      ->select('COUNT(s.id)')
      ->andWhere('s.ipAddress = :ip')->setParameter('ip', $ipAddress)
      ->andWhere('s.createdAt >= :since')->setParameter('since', $since)
      ->getQuery()
      ->getSingleScalarResult()
    ;
  }
}
