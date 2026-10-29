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
 * A single entry in a Vocabulary.
 *
 * Term contains three optional properties that are not present in a bare
 * name/weight term: parent, code and source. These are deliberately generic,
 * because they are the three things almost every real controlled vocabulary
 * needs and that a bare name cannot express.
 *
 * parent: hierarchy. Sub-specialties under specialties, regions under
 * countries, categories under sections.
 *
 * code: the term's identifier in whatever system is authoritative for it: a
 * SNOMED code, an ISO 639 language code, a department code. One column
 * serves all of them because a term needs only one.
 *
 * source: where the term came from: an import, a named external system, or a
 * person typing it into the admin UI.
 *
 * All three are nullable, so nothing that used Term before this change has to
 * do anything at all. An application that only wants flat name/weight terms
 * carries three empty columns and never notices them.
 */
#[ORM\Table(name: 'vocabulary_terms')]
#[ORM\Entity(repositoryClass: Repository\TermRepository::class)]
// a terms name is unique within its vocabulary, not globally: "English" may
// legitimately exist in both a Languages and a Course Titles vocabulary.
#[ORM\UniqueConstraint(name: 'UNIQ_TERM_VOCABULARY_NAME', columns: ['vocabulary_id', 'name'])]
#[ORM\Cache(
  usage: 'NONSTRICT_READ_WRITE',
  region: 'default_entity_region'
)]
// Deliberately NOT an API Platform resource (vocabularies are admin-managed reference data).
// API Platform loads every bundle's Entity/ folder automatically, so a bare
// #[ApiResource] here gave EVERY application using this bundle full
// create/read/update/delete on it, with no access check. An application that
// needs it over HTTP should declare its own resource with explicit
// operations and a `security` expression on each.
class Term {

  use PixieTraits\Entity\EntityIdTrait;

  #[ORM\ManyToOne(targetEntity: Entity\Vocabulary::class, inversedBy: "terms")]
  #[ORM\JoinColumn(nullable: false)]
  private $vocabulary;

  use PixieTraits\Entity\EntityNameTrait;
  use PixieTraits\Entity\EntityWeightTrait;

  /**
   * The term this one sits beneath, if the vocabulary is hierarchical.
   *
   * Self-referencing and nullable: a null parent is a root term, which is also
   * every term in a flat vocabulary. That is what lets one entity serve both
   * shapes without a second class.
   *
   * onDelete=SET NULL is deliberate: deleting a parent promotes its children
   * to roots rather than deleting them. Cascading would mean removing "Medicine"
   * silently destroys every sub-specialty beneath it — an enormous amount of
   * data lost to one click on a term that merely turned out to be redundant.
   * Orphaned children are visible and fixable; deleted ones are not.
   */
  #[ORM\ManyToOne(targetEntity: Entity\Term::class, inversedBy: 'children')]
  #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
  private ?Term $parent = null;

  /**
   * Terms sitting directly beneath this one.
   *
   * No cascade and no orphanRemoval, for the reason given on $parent: children
   * outlive their parent.
   *
   * @var Collection<int, Term>
   */
  #[ORM\OneToMany(targetEntity: Entity\Term::class, mappedBy: 'parent')]
  private Collection $children;

  /**
   * The term's identifier in whatever system is authoritative for it.
   *
   * Free text rather than a typed column because the authority differs per
   * vocabulary — SNOMED for conditions, ISO 639 for languages, a local staff
   * code for departments. Not unique, because two vocabularies may legitimately
   * reuse a code, and because plenty of terms have none.
   */
  #[ORM\Column(name: 'code', type: 'string', length: 64, nullable: true)]
  private ?string $code = null;

  /**
   * Where this term came from — an import name, an external system, or null
   * for "someone typed it in".
   *
   * Worth recording because it is the difference between "this term is wrong,
   * fix it here" and "this term is wrong, fix it upstream or the next import
   * will bring it back".
   */
  #[ORM\Column(name: 'source', type: 'string', length: 64, nullable: true)]
  private ?string $source = null;

  public function __construct() {
    $this->children = new ArrayCollection();
  }

  public function getParent(): ?Term {
    return $this->parent;
  }

  /**
   * Sets this term's parent.
   *
   * Guards against a term becoming its own parent, which would produce a row
   * that is its own ancestor and hang any tree walk that trusts the data.
   * Deeper cycles (A → B → A) are not checked here because doing so costs a
   * query per call; enforce those in whatever builds the tree if a vocabulary
   * is deep enough to make them plausible.
   */
  public function setParent(?Term $parent): self {
    if ($parent === $this) {
      throw new \InvalidArgumentException('A term cannot be its own parent.');
    }

    $this->parent = $parent;

    return $this;
  }

  /**
   * @return Collection<int, Term>
   */
  public function getChildren(): Collection {
    return $this->children;
  }

  public function getCode(): ?string {
    return $this->code;
  }

  public function setCode(?string $code): self {
    $this->code = $code;

    return $this;
  }

  public function getSource(): ?string {
    return $this->source;
  }

  public function setSource(?string $source): self {
    $this->source = $source;

    return $this;
  }

  /**
   * Gets the vocabulary for this term.
   *
   * @return Vocabulary|null
   */
  public function getVocabulary(): ?Vocabulary {
    return $this->vocabulary;
  }

  /**
   * Sets the vocabulary for this term.
   *
   * @param Vocabulary|null $vocabulary
   * @return self
   */
  public function setVocabulary(?Vocabulary $vocabulary): self {
    $this->vocabulary = $vocabulary;

    return $this;
  }

  /**
   * String representation of the Term.
   *
   * @return string
   */
  public function __toString(): string {
    return $this->getName() ?? '';
  }
}
