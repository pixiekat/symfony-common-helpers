<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pixiekat\SymfonyHelpers\Enum\ShoutStatus;
use Pixiekat\SymfonyHelpers\Interfaces\Entity\HelpersUserInterface;
use Pixiekat\SymfonyHelpers\Repository;
use Pixiekat\SymfonyHelpers\Traits as PixieTraits;

/**
 * One message in a shoutbox.
 *
 * WHY ONE TABLE AND A CHANNEL STRING
 * ----------------------------------
 * A site often wants more than one shoutbox eventually — a general one, a
 * per-page one, a staff-only one. Modelling that as a second "Shoutbox" entity
 * means a join and an admin screen for a thing that has no properties beyond a
 * name. A `channel` string gets the same outcome for the cost of one indexed
 * column, and can be promoted to a real entity later if channels ever grow
 * settings of their own.
 *
 * AUTHORSHIP
 * ----------
 * Either `author` (a logged-in user) or `authorName` (a name typed into the
 * form) identifies who shouted. Both are nullable: a fully anonymous shoutbox
 * is legitimate, and a user account can be deleted long after the shout was
 * posted. Rendering falls back authorName -> author -> 'Anonymous'.
 *
 * The user relation maps against HelpersUserInterface rather than a concrete
 * class, for the same reason ResetPasswordRequest does.
 *
 * @see \Pixiekat\SymfonyHelpers\Services\ShoutboxManager
 * @see \Pixiekat\SymfonyHelpers\Enum\ShoutStatus
 */
#[ORM\Table(name: 'shouts')]
#[ORM\Index(name: 'idx_shouts_channel_created', columns: ['channel', 'created_at'])]
#[ORM\Index(name: 'idx_shouts_ip_address', columns: ['ip_address'])]
#[ORM\Entity(repositoryClass: Repository\ShoutRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Shout {

  /**
   * The default channel, used when a caller does not name one.
   */
  public const DEFAULT_CHANNEL = 'default';

  /**
   * Hard cap on message length, mirrored by the form constraint.
   *
   * A shoutbox is for shouting, not essays; a bound also keeps a single post
   * from being used to flood a page.
   */
  public const MAX_BODY_LENGTH = 512;

  use PixieTraits\Entity\EntityIdTrait;
  use PixieTraits\Entity\EntityCreatedAtTrait;

  /** Room for moderation notes and future per-shout settings. */
  use PixieTraits\Entity\EntityFlagsTrait;

  /**
   * Which shoutbox this belongs to.
   *
   * Indexed together with created_at, because "latest N in this channel" is
   * essentially the only read query this entity ever serves.
   */
  #[ORM\Column(length: 64, options: ['default' => self::DEFAULT_CHANNEL])]
  private string $channel = self::DEFAULT_CHANNEL;

  /**
   * The logged-in user who posted, if any.
   *
   * onDelete: SET NULL rather than CASCADE — deleting an account should not
   * silently rewrite the history of a conversation other people took part in.
   */
  #[ORM\ManyToOne(targetEntity: HelpersUserInterface::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  private ?HelpersUserInterface $author = null;

  /**
   * The name typed into the form by an anonymous poster.
   */
  #[ORM\Column(length: 64, nullable: true)]
  private ?string $authorName = null;

  /**
   * The message itself. Always rendered escaped — this is untrusted input.
   */
  #[ORM\Column(type: 'text')]
  private string $body = '';

  /**
   * The poster's IP address.
   *
   * Stored unhashed because BanManager matches bans against literal addresses
   * and CIDR prefixes, which a hash cannot support. It is personal data: decide
   * a retention period that suits you and prune old rows, rather than keeping
   * addresses forever by default.
   */
  #[ORM\Column(length: 45, nullable: true)]
  private ?string $ipAddress = null;

  /**
   * Moderation state. Only Published renders publicly.
   */
  #[ORM\Column(length: 16, enumType: ShoutStatus::class, options: ['default' => 'published'])]
  private ShoutStatus $status = ShoutStatus::Published;

  /**
   * Constructor.
   *
   * @param string $channel Which shoutbox this belongs to.
   */
  public function __construct(string $channel = self::DEFAULT_CHANNEL) {
    $this->channel = $channel;
    $this->setCreatedAt(new \DateTimeImmutable());
  }

  /**
   * Gets the channel.
   *
   * @return string The channel name.
   */
  public function getChannel(): string {
    return $this->channel;
  }

  /**
   * Sets the channel.
   *
   * @param string $channel The channel name.
   * @return self
   */
  public function setChannel(string $channel): self {
    $this->channel = $channel;

    return $this;
  }

  /**
   * Gets the authenticated author, if there was one.
   *
   * @return HelpersUserInterface|null The user, or null for anonymous shouts.
   */
  public function getAuthor(): ?HelpersUserInterface {
    return $this->author;
  }

  /**
   * Sets the authenticated author.
   *
   * @param HelpersUserInterface|null $author The user, or null.
   * @return self
   */
  public function setAuthor(?HelpersUserInterface $author): self {
    $this->author = $author;

    return $this;
  }

  /**
   * Gets the typed-in author name.
   *
   * @return string|null The name, or null.
   */
  public function getAuthorName(): ?string {
    return $this->authorName;
  }

  /**
   * Sets the typed-in author name.
   *
   * @param string|null $authorName The name, or null.
   * @return self
   */
  public function setAuthorName(?string $authorName): self {
    $this->authorName = $authorName;

    return $this;
  }

  /**
   * The name to show for this shout.
   *
   * Keeps the fallback chain in one place so every template agrees on it.
   *
   * @return string A display name, never empty.
   */
  public function getDisplayName(): string {
    if ($this->authorName !== null && $this->authorName !== '') {
      return $this->authorName;
    }

    if ($this->author !== null) {
      return $this->author->getUserIdentifier();
    }

    return 'Anonymous';
  }

  /**
   * Gets the message body.
   *
   * @return string The raw, unescaped message. Escape it when rendering.
   */
  public function getBody(): string {
    return $this->body;
  }

  /**
   * Sets the message body.
   *
   * @param string $body The message.
   * @return self
   */
  public function setBody(string $body): self {
    $this->body = $body;

    return $this;
  }

  /**
   * Gets the poster's IP address.
   *
   * @return string|null The address, or null if it was not recorded.
   */
  public function getIpAddress(): ?string {
    return $this->ipAddress;
  }

  /**
   * Sets the poster's IP address.
   *
   * @param string|null $ipAddress The address, or null.
   * @return self
   */
  public function setIpAddress(?string $ipAddress): self {
    $this->ipAddress = $ipAddress;

    return $this;
  }

  /**
   * Gets the moderation status.
   *
   * @return ShoutStatus The status.
   */
  public function getStatus(): ShoutStatus {
    return $this->status;
  }

  /**
   * Sets the moderation status.
   *
   * @param ShoutStatus $status The status.
   * @return self
   */
  public function setStatus(ShoutStatus $status): self {
    $this->status = $status;

    return $this;
  }

  /**
   * Whether this shout is publicly visible.
   *
   * @return bool True if it should render.
   */
  public function isPublic(): bool {
    return $this->status->isPublic();
  }

  /**
   * String representation of the shout.
   *
   * @return string A short, single-line summary for logs and admin listings.
   */
  public function __toString(): string {
    $body = trim($this->body);

    return $this->getDisplayName() . ': ' . (mb_strlen($body) > 40 ? mb_substr($body, 0, 40) . '…' : $body);
  }
}
