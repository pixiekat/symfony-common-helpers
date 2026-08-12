<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Services;

use Doctrine\ORM\EntityManagerInterface;
use Pixiekat\SymfonyHelpers\Entity\Shout;
use Pixiekat\SymfonyHelpers\Enum\ShoutStatus;
use Pixiekat\SymfonyHelpers\Exception\ShoutRejectedException;
use Pixiekat\SymfonyHelpers\Interfaces\Entity\HelpersUserInterface;
use Pixiekat\SymfonyHelpers\Repository\ShoutRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Accepts and reads shouts.
 *
 * All the policy — who may post, how fast, whether a post is published straight
 * away — lives here rather than in the controller, so the block renderer, the
 * controller and any future API route cannot drift apart on the rules.
 *
 * REUSING WHAT ALREADY EXISTS
 * ---------------------------
 * Ban enforcement goes through the bundle's own BanManager, which already
 * understands literal addresses and /8, /16 and /24 prefixes. A shoutbox is
 * exactly the kind of thing that attracts the traffic bans exist for, so wiring
 * the two together is most of the abuse story for free.
 *
 * FLOOD CONTROL WITHOUT A NEW DEPENDENCY
 * --------------------------------------
 * Rather than requiring symfony/rate-limiter and the app-level configuration
 * that comes with it, the throttle is a COUNT over the shouts table within a
 * time window. That is one cheap indexed query, it survives a cache flush or a
 * restart (a memory-backed limiter does not), and it needs nothing from the
 * consuming application. If you later outgrow it — many thousands of posts an
 * hour — swapping in the rate limiter means changing only this class.
 */
class ShoutboxManager {

  /**
   * How many shouts one IP may post inside the flood window.
   */
  public const FLOOD_LIMIT = 5;

  /**
   * The flood window, in seconds.
   */
  public const FLOOD_WINDOW = 60;

  /**
   * Constructor.
   *
   * @param ShoutRepository $shouts Read side.
   * @param EntityManagerInterface $entityManager Write side.
   * @param BanManager $banManager Existing ban checks, reused wholesale.
   * @param Security $security Used to attribute a shout to the logged-in user.
   * @param RequestStack $requestStack Used to discover the client IP.
   * @param LoggerInterface $logger Moderation trail.
   */
  public function __construct(
    private readonly ShoutRepository $shouts,
    private readonly EntityManagerInterface $entityManager,
    private readonly BanManager $banManager,
    private readonly Security $security,
    private readonly RequestStack $requestStack,
    private readonly LoggerInterface $logger,
  ) {  }

  /**
   * The latest public shouts in a channel.
   *
   * @param string $channel The channel to read.
   * @param int $limit How many to return.
   *
   * @return Shout[] The shouts, newest first.
   */
  public function latest(string $channel = Shout::DEFAULT_CHANNEL, int $limit = 20): array {
    return $this->shouts->findLatest($channel, $limit);
  }

  /**
   * Accepts a new shout, or explains why it will not.
   *
   * Checks run cheapest-and-most-decisive first: an empty body needs no
   * database at all, a ban outranks a flood limit, and only then do we spend a
   * COUNT query on throttling.
   *
   * @param string $body The message.
   * @param string|null $authorName A name for anonymous posters. Ignored when
   *   someone is logged in — their account is the more trustworthy identity.
   * @param string $channel Which shoutbox to post into.
   *
   * @throws ShoutRejectedException If the shout is refused. The exception
   *   message is safe to show to the visitor.
   *
   * @return Shout The persisted shout.
   */
  public function post(string $body, ?string $authorName = null, string $channel = Shout::DEFAULT_CHANNEL): Shout {
    $body = trim($body);

    if ($body === '') {
      throw ShoutRejectedException::empty();
    }

    if (mb_strlen($body) > Shout::MAX_BODY_LENGTH) {
      $body = mb_substr($body, 0, Shout::MAX_BODY_LENGTH);
    }

    $ipAddress = $this->getClientIp();

    if ($ipAddress !== null && $this->banManager->findIpBan($ipAddress) !== null) {
      $this->logger->info('Refused shout from banned address {ip}.', ['ip' => $ipAddress]);

      throw ShoutRejectedException::banned();
    }

    if ($ipAddress !== null && $this->isFlooding($ipAddress)) {
      $this->logger->info('Throttled shout from {ip}.', ['ip' => $ipAddress]);

      throw ShoutRejectedException::flood(self::FLOOD_WINDOW);
    }

    $user = $this->security->getUser();

    $shout = new Shout($channel);
    $shout
      ->setBody($body)
      ->setIpAddress($ipAddress)
      ->setStatus(ShoutStatus::Published)
    ;

    // A real account beats a typed-in name: anyone can type "katy" into a form,
    // so when we know who this is, we say so and ignore the free-text field.
    if ($user instanceof HelpersUserInterface) {
      $shout->setAuthor($user);
    }
    else {
      $authorName = $authorName !== null ? trim($authorName) : null;
      $shout->setAuthorName($authorName !== '' ? $authorName : null);
    }

    $this->entityManager->persist($shout);
    $this->entityManager->flush();

    $this->logger->info('Shout {id} posted to channel {channel} by {who}.', [
      'id' => $shout->getId(),
      'channel' => $channel,
      'who' => $shout->getDisplayName(),
    ]);

    return $shout;
  }

  /**
   * Changes a shout's moderation status.
   *
   * @param Shout $shout The shout to moderate.
   * @param ShoutStatus $status The new status.
   *
   * @return Shout The updated shout.
   */
  public function moderate(Shout $shout, ShoutStatus $status): Shout {
    $previous = $shout->getStatus();
    $shout->setStatus($status);
    $this->entityManager->flush();

    $this->logger->info('Shout {id} moved from {from} to {to} by {user}.', [
      'id' => $shout->getId(),
      'from' => $previous->value,
      'to' => $status->value,
      'user' => $this->security->getUser()?->getUserIdentifier() ?? 'anonymous',
    ]);

    return $shout;
  }

  /**
   * Whether this address has used up its quota for the current window.
   *
   * @param string $ipAddress The address to check.
   *
   * @return bool True if the poster should be turned away.
   */
  private function isFlooding(string $ipAddress): bool {
    $since = new \DateTimeImmutable(sprintf('-%d seconds', self::FLOOD_WINDOW));

    return $this->shouts->countRecentFromIp($ipAddress, $since) >= self::FLOOD_LIMIT;
  }

  /**
   * The current request's client IP, if there is a request at all.
   *
   * Returns null in CLI contexts (fixtures, console commands), which the caller
   * treats as "no ban or flood check possible" rather than as an error.
   *
   * Note getClientIp() only trusts X-Forwarded-For when the app has configured
   * trusted proxies. If you run behind a reverse proxy and skip that config,
   * every shout will record the proxy's address and the flood limit will apply
   * to your whole site at once.
   *
   * @return string|null The address, or null.
   */
  private function getClientIp(): ?string {
    return $this->requestStack->getCurrentRequest()?->getClientIp();
  }
}
