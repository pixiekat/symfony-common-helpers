<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pixiekat\SymfonyHelpers\Interfaces;
use Pixiekat\SymfonyHelpers\Interfaces\Entity\HelpersUserInterface;
use Pixiekat\SymfonyHelpers\Repository\AuditLogRepository;
use Pixiekat\SymfonyHelpers\Traits\Deprecated as DeprecatedTraits;
use Pixiekat\SymfonyHelpers\Traits\Entity as PixieTraits;

/**
 * One thing that somebody did.
 *
 * ── THE DESIGN RULE: AN AUDIT LOG MUST OUTLIVE ITS SUBJECTS ────────────────
 * Everything an entry needs in order to still make sense is copied INTO the
 * entry at write time. "Katy deleted vocabulary Genres" has to keep reading
 * that way a year later, when the vocabulary is long gone and Katy's account
 * has been renamed twice. So each side is stored twice:
 *
 *   actor    + actorLabel     the live link, and the name as it was
 *   targetId + targetLabel    the identifier, and the name as it was
 *
 * The relations are conveniences for "show me everything this user did"; the
 * labels are the record. Deleting a user SETs NULL on the relation and leaves
 * the label untouched, so history is never quietly rewritten by a cascade.
 *
 * That duplication is normally a smell. Here it is the entire point: an audit
 * log built out of joins tells you what is true now, not what happened then.
 *
 * ── COLUMN NAMES ───────────────────────────────────────────────────────────
 * Three properties were renamed in the rework (entityType, performedBy,
 * additionalData). The columns were renamed to match rather than left drifting
 * from the code — see the migration — and the old accessors survive in
 * AuditLogDeprecatedTrait until it is time to pull them.
 *
 * @see \Pixiekat\SymfonyHelpers\Services\AuditLogManager
 * @see \Pixiekat\SymfonyHelpers\Traits\Deprecated\AuditLogDeprecatedTrait
 */
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(name: 'idx_audit_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_audit_action', columns: ['action'])]
#[ORM\Index(name: 'idx_audit_target', columns: ['target_type', 'target_id'])]
// Named explicitly rather than left to Doctrine, which would generate a hash
// like IDX_D62F285810DAF24A for the join column and then want to rename the
// one the migration created on every schema diff.
#[ORM\Index(name: 'idx_audit_actor_id', columns: ['actor_id'])]
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\HasLifecycleCallbacks]
class AuditLog implements Interfaces\Entity\AuditLogInterface {

  use PixieTraits\EntityIdTrait;
  use PixieTraits\EntityCreatedAtTrait;

  /**
   * The pre-rework accessors. Delete this line and src/Traits/Deprecated/ when
   * every consuming application has moved across.
   */
  use DeprecatedTraits\AuditLogDeprecatedTrait;

  /**
   * The label used when nobody is logged in — console commands, cron, workers.
   *
   * A literal rather than null, because "the system did it" and "we forgot to
   * record who did it" are different facts and the log should not blur them.
   */
  public const ACTOR_SYSTEM = 'system';

  /**
   * The machine key for what happened, e.g. 'user.deleted'.
   *
   * Indexed and only 64 characters: it is filtered on constantly in the admin
   * screen and is a key, not prose.
   */
  #[ORM\Column(length: 64, nullable: false)]
  private string $action;

  /**
   * The user who did it, where they are still around.
   *
   * onDelete SET NULL, never CASCADE. Deleting a user must not delete the
   * record of what they did — that is precisely the record you most want when
   * an account gets removed.
   */
  #[ORM\ManyToOne(targetEntity: HelpersUserInterface::class)]
  #[ORM\JoinColumn(name: 'actor_id', nullable: true, onDelete: 'SET NULL')]
  private ?HelpersUserInterface $actor = null;

  /**
   * The actor's identifier as it was at the time. Never null — see ACTOR_SYSTEM.
   *
   * The database default is declared as well as the PHP one so the two agree
   * (otherwise schema:validate reports drift forever), and so that a row
   * inserted by hand or by a data migration still names an actor rather than
   * failing on a NOT NULL with no default.
   */
  #[ORM\Column(name: 'actor_label', length: 255, nullable: false, options: ['default' => self::ACTOR_SYSTEM])]
  private string $actorLabel = self::ACTOR_SYSTEM;

  /**
   * Short type of the thing acted upon, e.g. 'block', 'user'.
   *
   * A short alias rather than a fully-qualified class name: the FQCN changes
   * when you move a class between namespaces, and historical rows should not
   * become wrong because of a refactor.
   */
  #[ORM\Column(name: 'target_type', length: 64, nullable: true)]
  private ?string $targetType = null;

  /**
   * Identifier of the thing acted upon, as a string so it can hold an int id, a
   * uuid or a machine name without needing a second column per shape.
   */
  #[ORM\Column(name: 'target_id', length: 64, nullable: true)]
  private ?string $targetId = null;

  /**
   * The target's name as it was at the time.
   */
  #[ORM\Column(name: 'target_label', length: 255, nullable: true)]
  private ?string $targetLabel = null;

  /**
   * Where the action came from. Null for CLI.
   *
   * 45 characters: a full IPv6 address with an IPv4-mapped suffix. This is
   * personal data — see the prune command for keeping it bounded.
   */
  #[ORM\Column(name: 'ip_address', length: 45, nullable: true)]
  private ?string $ipAddress = null;

  /**
   * Structured detail, handed to Monolog verbatim as the record context.
   *
   * Nullable in the database because rows written before the rework may hold
   * NULL; the accessor coalesces so callers always see an array.
   */
  #[ORM\Column(name: 'context', type: 'json', nullable: true)]
  private ?array $context = [];

  /**
   * Constructor.
   *
   * @param string $action The machine key for what happened.
   */
  public function __construct(string $action = '') {
    $this->action = $action;
    $this->setCreatedAt(new \DateTimeImmutable());
  }

  /**
   * {@inheritdoc}
   */
  public function getAction(): string {
    return $this->action;
  }

  /**
   * Sets the action key.
   *
   * @param string $action The machine key, e.g. 'user.deleted'.
   * @return static
   */
  public function setAction(string $action): static {
    $this->action = $action;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getActor(): ?HelpersUserInterface {
    return $this->actor;
  }

  /**
   * Sets the acting user, and fills in the label snapshot if it is still empty.
   *
   * Setting both from one call is deliberate: an entry with a live actor but no
   * label would go blank the moment that account was deleted, which is the one
   * failure this design exists to prevent.
   *
   * @param HelpersUserInterface|null $actor The acting user.
   * @return static
   */
  public function setActor(?HelpersUserInterface $actor): static {
    $this->actor = $actor;

    if ($actor !== null && $this->actorLabel === self::ACTOR_SYSTEM) {
      $this->actorLabel = $actor->getUserIdentifier();
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getActorLabel(): string {
    return $this->actorLabel;
  }

  /**
   * Sets the actor's display snapshot.
   *
   * @param string $actorLabel The label.
   * @return static
   */
  public function setActorLabel(string $actorLabel): static {
    $this->actorLabel = $actorLabel !== '' ? $actorLabel : self::ACTOR_SYSTEM;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetType(): ?string {
    return $this->targetType;
  }

  /**
   * Sets the target type.
   *
   * @param string|null $targetType A short alias, e.g. 'block'.
   * @return static
   */
  public function setTargetType(?string $targetType): static {
    $this->targetType = $targetType;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetId(): ?string {
    return $this->targetId;
  }

  /**
   * Sets the target identifier.
   *
   * @param string|int|null $targetId The identifier; cast to string.
   * @return static
   */
  public function setTargetId(string|int|null $targetId): static {
    $this->targetId = $targetId === null ? null : (string) $targetId;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetLabel(): ?string {
    return $this->targetLabel;
  }

  /**
   * Sets the target's display snapshot.
   *
   * @param string|null $targetLabel The label.
   * @return static
   */
  public function setTargetLabel(?string $targetLabel): static {
    // Trimmed to the column width here rather than letting the database do it:
    // MySQL in non-strict mode truncates silently, and a half-written audit
    // label is worse than a short one you chose.
    $this->targetLabel = $targetLabel === null ? null : mb_substr($targetLabel, 0, 255);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIpAddress(): ?string {
    return $this->ipAddress;
  }

  /**
   * Sets the originating IP address.
   *
   * @param string|null $ipAddress The address.
   * @return static
   */
  public function setIpAddress(?string $ipAddress): static {
    $this->ipAddress = $ipAddress;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): array {
    return $this->context ?? [];
  }

  /**
   * Sets the structured detail.
   *
   * @param array $context The context.
   * @return static
   */
  public function setContext(array $context): static {
    $this->context = $context;

    return $this;
  }

  /**
   * Whether this entry names something it was done to.
   *
   * Saves templates from repeating a two-part null check.
   *
   * @return bool True if there is a target.
   */
  public function hasTarget(): bool {
    return $this->targetType !== null;
  }

  /**
   * String representation, for logs and debugging.
   *
   * @return string A single-line summary.
   */
  public function __toString(): string {
    $summary = $this->actorLabel . ' ' . $this->action;

    if ($this->hasTarget()) {
      $summary .= ' ' . $this->targetType . ' ' . ($this->targetLabel ?? '#' . $this->targetId);
    }

    return $summary;
  }
}
