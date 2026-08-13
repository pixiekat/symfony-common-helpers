<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Repository;
use Pixiekat\SymfonyHelpers\Traits as PixieTraits;

/**
 * One entry inside a Block — typically a link.
 *
 * This is the entity form of the hand-written arrays that tend to accumulate in
 * a "SidebarManager"-style service:
 *
 *   'bluesky_personal' => [
 *     'label'  => 'spacecadetgrrl.me',
 *     'url'    => 'https://bsky.app/profile/spacecadetgrrl.me',
 *     'icon'   => null,
 *     'weight' => 50,
 *   ],
 *
 * ...becomes a row: name, label, url, icon, weight. Editing the site's links no
 * longer means editing PHP and redeploying, and the weight sort moves from a
 * uasort() in userland to an ORDER BY in the database.
 *
 * ANYTHING ELSE GOES IN FLAGS
 * ---------------------------
 * Presentation quirks that belong to one site rather than to the general idea
 * of "a link" live in the `flags` JSON bag, so the bundle's schema stays
 * generic. From the spacecadetgrrl sidebar, for example:
 *
 *   $item->setFlag('wrapper_label', 'Made with');   // prefix shown before the handle
 *   $item->setFlag('forceSubdomain', true);         // keep the subdomain when deriving a label from the URL
 *   $item->setFlag('subdomainDepth', 1);            // ...but only the last N parts of it
 *
 * and in Twig: {{ item.getFlag('wrapper_label') }}
 *
 * Adding a new flag never needs a migration, which is exactly the trade you
 * want for fields that only one template ever reads.
 *
 * @see \Pixiekat\SymfonyHelpers\Entity\Block
 */
#[ORM\Table(name: 'block_items')]
#[ORM\UniqueConstraint(name: 'uniq_block_item_name', columns: ['block_id', 'name'])]
// Declared explicitly so it keeps the name the migration gave it. Doctrine
// creates an index for a join column automatically, but names it with a hash
// (IDX_260F2FAB…) — and then wants to rename ours to that on every schema diff.
#[ORM\Index(name: 'idx_block_items_block_id', columns: ['block_id'])]
#[ORM\Entity(repositoryClass: Repository\BlockItemRepository::class)]
#[ORM\Cache(
  usage: 'NONSTRICT_READ_WRITE',
  region: 'default_entity_region'
)]
class BlockItem {

  use PixieTraits\Entity\EntityIdTrait;

  /**
   * Machine key for this item, e.g. 'bluesky_personal'.
   *
   * Unique per block, not globally. Used to build CSS hooks
   * (.social-link--bluesky-personal) and to find a specific item in code, which
   * is why it survives from the old array-key design.
   */
  use PixieTraits\Entity\EntityNameTrait;

  /** The visible text, e.g. 'spacecadetgrrl.me'. */
  use PixieTraits\Entity\EntityLabelTrait;

  /** Sort order within the block. Lower floats to the top. */
  use PixieTraits\Entity\EntityWeightTrait;

  /** Lets you retire a link without losing it. */
  use PixieTraits\Entity\EntityEnabledTrait;

  /** Per-item extras — see the class docblock for the ones this site uses. */
  use PixieTraits\Entity\EntityFlagsTrait;

  /**
   * The block this item belongs to.
   *
   * Nullable in PHP only so removeItem() can null it before the orphan is
   * deleted; the column itself is NOT NULL.
   */
  #[ORM\ManyToOne(targetEntity: Entity\Block::class, inversedBy: 'items')]
  #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
  private ?Block $block = null;

  /**
   * Wrapper label
   *
   * Can be used to display a label above the item.
   */
  #[ORM\Column(length: 255, nullable: true)]
  private ?string $wrapperLabel = null;

  /**
   * Where the item points.
   *
   * Nullable because a link list sometimes carries a plain text entry — the
   * original social-links template already handled "label but no URL" by
   * rendering a <span> instead of an <a>, and that behaviour is preserved.
   *
   * 512 rather than 255: query strings on profile URLs get long.
   */
  #[ORM\Column(length: 512, nullable: true)]
  private ?string $url = null;

  /**
   * Optional icon identifier.
   *
   * Intentionally just a string, not a file reference — how it is interpreted
   * is the template's business (a Nerd Font codepoint, an SVG sprite id, a CSS
   * class). Keeping it opaque means the bundle does not impose an icon system.
   */
  #[ORM\Column(length: 255, nullable: true)]
  private ?string $icon = null;

  /**
   * Constructor.
   *
   * @param string|null $name Optional machine key, handy when seeding fixtures.
   */
  public function __construct(?string $name = null) {
    $this->setEnabled(true);

    if ($name !== null) {
      $this->setName($name);
    }
  }

  /**
   * Gets the owning block.
   *
   * @return Block|null The block, or null while detached.
   */
  public function getBlock(): ?Block {
    return $this->block;
  }

  /**
   * Sets the owning block.
   *
   * Prefer Block::addItem(), which sets both sides of the relation for you.
   *
   * @param Block|null $block The owning block.
   * @return self
   */
  public function setBlock(?Block $block): self {
    $this->block = $block;

    return $this;
  }

  /**
   * Gets the item's wrapper label.
   *
   * @return string|null The wrapper label, or null if none.
   */
  public function getWrapperLabel(): ?string {
    return $this->wrapperLabel;
  }

  /**
   * Sets the item's wrapper label.
   *
   * @param string|null $wrapperLabel The wrapper label, or null to remove it.
   * @return self
   */
  public function setWrapperLabel(?string $wrapperLabel): self {
    $this->wrapperLabel = $wrapperLabel;
    return $this;
  }

  /**
   * Gets the item's URL.
   *
   * @return string|null The URL, or null for a text-only entry.
   */
  public function getUrl(): ?string {
    return $this->url;
  }

  /**
   * Sets the item's URL.
   *
   * @param string|null $url The URL, or null for a text-only entry.
   * @return self
   */
  public function setUrl(?string $url): self {
    $this->url = $url;

    return $this;
  }

  /**
   * Gets the item's icon identifier.
   *
   * @return string|null The icon identifier, or null.
   */
  public function getIcon(): ?string {
    return $this->icon;
  }

  /**
   * Sets the item's icon identifier.
   *
   * @param string|null $icon The icon identifier, or null.
   * @return self
   */
  public function setIcon(?string $icon): self {
    $this->icon = $icon;

    return $this;
  }

  /**
   * Whether this item should render as a real anchor.
   *
   * Saves templates from repeating `item.url is defined and item.url is not empty`.
   *
   * @return bool True if there is a URL to link to.
   */
  public function isLink(): bool {
    return $this->url !== null && $this->url !== '';
  }

  /**
   * String representation of the item.
   *
   * @return string The label if there is one, otherwise the machine key.
   */
  public function __toString(): string {
    return (string) ($this->getLabel() ?? $this->getName() ?? '');
  }
}
