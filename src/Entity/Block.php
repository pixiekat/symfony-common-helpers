<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Repository;
use Pixiekat\SymfonyHelpers\Traits as PixieTraits;
use Symfony\Component\Uid\Uuid;

/**
 * A named, reusable chunk of a page.
 *
 * WHAT A BLOCK IS
 * ---------------
 * A block is content that you want to define once and drop into a template
 * wherever you like:
 *
 *   {{ place_block('social_links') }}
 *
 * The developer decides WHERE it goes (by putting that call in the template);
 * the block decides WHAT it says. That division is deliberate — a full
 * "regions" system, where placement itself lives in the database, only earns
 * its complexity when non-developers need to move things around. When you are
 * the only person touching the templates, `place_block()` in the markup is both
 * simpler to reason about and easier to grep for.
 *
 * TWO WAYS TO HOLD CONTENT
 * ------------------------
 * 1. ITEMS — an ordered list of BlockItem rows (label + url + icon + weight).
 *    This covers the overwhelmingly common case: link lists, nav menus, badge
 *    rows, "elsewhere on the web" panels. Your social links and meta links are
 *    both exactly this shape.
 * 2. BODY — a single blob of markup, for a block that is just prose.
 *
 * A block can use either or both; the default template renders the body first
 * and then the item list.
 *
 * EXTENSIBILITY, PRE-BAKED
 * ------------------------
 * Two fields exist for things you do not need yet but will:
 *   - `template` lets one block opt out of the default markup entirely.
 *   - `flags` (a JSON bag from EntityFlagsTrait) is per-block configuration,
 *     reachable in Twig as `block.getFlag('some_key')`.
 * Neither costs anything until used, and having them present means the day you
 * want a dynamic block type or per-route visibility rules, there is somewhere
 * obvious to put that state without a migration.
 *
 * @see \Pixiekat\SymfonyHelpers\Entity\BlockItem
 * @see \Pixiekat\SymfonyHelpers\Services\BlockManager
 */
#[ORM\Table(name: 'blocks')]
#[ORM\UniqueConstraint(name: 'uniq_block_name', columns: ['name'])]
// EntityUuidTrait marks the column unique, which makes Doctrine generate a
// hash-named index. Naming it here matches what the migration created.
#[ORM\UniqueConstraint(name: 'uniq_block_uuid', columns: ['uuid'])]
#[ORM\Entity(repositoryClass: Repository\BlockRepository::class)]
#[ORM\Cache(
  usage: 'NONSTRICT_READ_WRITE',
  region: 'default_entity_region'
)]
class Block {

  use PixieTraits\Entity\EntityIdTrait;

  /**
   * The machine name, e.g. 'social_links'. This is the handle you pass to
   * place_block(), so it is unique and should be treated as an API: renaming it
   * breaks every template that references it.
   */
  use PixieTraits\Entity\EntityNameTrait;

  /**
   * The human-readable heading, e.g. 'Socials'. Rendered as the block's <h2>
   * unless suppressed with the show_title option.
   */
  use PixieTraits\Entity\EntityLabelTrait;

  /** Admin-facing note about what this block is for. Never rendered publicly. */
  use PixieTraits\Entity\EntityDescriptionTrait;

  /** Ordering hint, for when a template places several blocks in a loop. */
  use PixieTraits\Entity\EntityWeightTrait;

  /** Disabled blocks render as an empty string rather than disappearing loudly. */
  use PixieTraits\Entity\EntityEnabledTrait;

  /** Locked blocks are ones an admin UI should refuse to delete. */
  use PixieTraits\Entity\EntityLockedTrait;

  /** Stable identifier that survives export/import between environments. */
  use PixieTraits\Entity\EntityUuidTrait;

  /** Free-form per-block configuration. @see getFlag()/setFlag() */
  use PixieTraits\Entity\EntityFlagsTrait;

  /**
   * Optional markup for blocks that are just a chunk of prose.
   *
   * Treated as trusted HTML by the default template (it is authored by you, not
   * by visitors). If you ever accept block bodies from untrusted users, escape
   * this at render time instead.
   */
  #[ORM\Column(type: 'text', nullable: true)]
  private ?string $body = null;

  /**
   * Optional Twig template overriding the bundle default for this block only.
   *
   * e.g. '_partials/_social-links.html.twig'. Receives the same variables as
   * the default template, so it is a drop-in replacement.
   */
  #[ORM\Column(length: 255, nullable: true)]
  private ?string $template = null;

  /**
   * The block's items, pre-sorted by weight.
   *
   * ORM\OrderBy means the database does the sorting on every fetch, which is
   * why no userland sort function is needed anywhere in this design.
   *
   * @var Collection<int, BlockItem>
   */
  #[ORM\OneToMany(targetEntity: Entity\BlockItem::class, mappedBy: 'block', orphanRemoval: true, cascade: ['persist', 'remove'])]
  #[ORM\OrderBy(['weight' => 'ASC', 'id' => 'ASC'])]
  private Collection $items;

  /**
   * Constructor.
   *
   * Note this deliberately overrides the __construct() that EntityUuidTrait
   * provides — that one calls parent::__construct(), which only works for
   * entities extending BaseEntity. Declaring our own here wins over the trait's
   * (PHP always prefers a method defined on the class itself) and lets us seed
   * the uuid and the items collection together.
   *
   * @param string|null $name Optional machine name, handy when seeding fixtures.
   */
  public function __construct(?string $name = null) {
    $this->setUuid(Uuid::v4());
    $this->setEnabled(true);
    $this->setLocked(false);
    $this->items = new ArrayCollection();

    if ($name !== null) {
      $this->setName($name);
    }
  }

  /**
   * Gets the block's prose body.
   *
   * @return string|null The raw markup, or null if this block is items-only.
   */
  public function getBody(): ?string {
    return $this->body;
  }

  /**
   * Sets the block's prose body.
   *
   * @param string|null $body The raw markup.
   * @return self
   */
  public function setBody(?string $body): self {
    $this->body = $body;

    return $this;
  }

  /**
   * Gets the per-block template override.
   *
   * @return string|null A Twig path, or null to use the bundle default.
   */
  public function getTemplate(): ?string {
    return $this->template;
  }

  /**
   * Sets the per-block template override.
   *
   * @param string|null $template A Twig path, or null to use the bundle default.
   * @return self
   */
  public function setTemplate(?string $template): self {
    $this->template = $template;

    return $this;
  }

  /**
   * Gets every item on this block, including disabled ones.
   *
   * Rendering goes through BlockManager, which filters to enabled items — this
   * accessor stays unfiltered so an admin UI can still see and re-enable them.
   *
   * @return Collection<int, BlockItem>
   */
  public function getItems(): Collection {
    return $this->items;
  }

  /**
   * Gets only the items that should actually be rendered.
   *
   * @return array<int, BlockItem> A plain, weight-ordered list of enabled items.
   */
  public function getEnabledItems(): array {
    return array_values(
      $this->items->filter(fn(BlockItem $item) => $item->isEnabled())->toArray()
    );
  }

  /**
   * Adds an item, keeping both sides of the relation in sync.
   *
   * @param BlockItem $item The item to attach.
   * @return self
   */
  public function addItem(BlockItem $item): self {
    if (!$this->items->contains($item)) {
      $this->items[] = $item;
      $item->setBlock($this);
    }

    return $this;
  }

  /**
   * Removes an item, keeping both sides of the relation in sync.
   *
   * orphanRemoval on the association means a detached item is deleted from the
   * database on the next flush rather than left behind with a null block_id.
   *
   * @param BlockItem $item The item to detach.
   * @return self
   */
  public function removeItem(BlockItem $item): self {
    if ($this->items->removeElement($item)) {
      if ($item->getBlock() === $this) {
        $item->setBlock(null);
      }
    }

    return $this;
  }

  /**
   * String representation of the block.
   *
   * @return string The label if there is one, otherwise the machine name.
   */
  public function __toString(): string {
    return (string) ($this->getLabel() ?? $this->getName() ?? '');
  }
}
