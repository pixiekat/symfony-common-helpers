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

#[ORM\Table(name: 'vocabularies')]
#[ORM\Entity(repositoryClass: Repository\VocabularyRepository::class)]
#[ORM\Cache(
  usage: 'NONSTRICT_READ_WRITE',
  region: 'default_entity_region'
)]
#[ApiResource]
class Vocabulary {
  use PixieTraits\Entity\EntityIdTrait;
  use PixieTraits\Entity\EntityLabelTrait;
  use PixieTraits\Entity\EntityNameTrait;
  use PixieTraits\Entity\EntityDescriptionTrait;

  #[ORM\OneToMany(targetEntity: Entity\Term::class, mappedBy: 'vocabulary', orphanRemoval: true, cascade: ['persist', 'remove'])]
  private $terms;

  use PixieTraits\Entity\EntityEnabledTrait;
  use PixieTraits\Entity\EntityLockedTrait;
  use PixieTraits\Entity\EntityUuidTrait;

  /**
   * Constructor
   */
  public function __construct() {
    $this->setUuid(Uuid::v4());
    $this->setEnabled(true);
    $this->setLocked(false);
    $this->terms = new ArrayCollection();
  }

  /**
   * Gets the terms associated with this vocabulary.
   *
   * @return Collection<int, Term>
   */
  public function getTerms(): Collection {
    return $this->terms;
  }

  /**
   * Adds a term to this vocabulary.
   *
   * @param Term $term
   * @return self
   */
  public function addTerm(Term $term): self {
    if (!$this->terms->contains($term)) {
      $this->terms[] = $term;
      $term->setVocabulary($this);
    }

    return $this;
  }

  /**
   * Removes a term from this vocabulary.
   *
   * @param Term $term
   * @return self
   */
  public function removeTerm(Term $term): self {
    if ($this->terms->removeElement($term)) {
      // set the owning side to null (unless already changed)
      if ($term->getVocabulary() === $this) {
        $term->setVocabulary(null);
      }
    }

    return $this;
  }

  /**
   * @see $this->label()
   * @see Pixiekat\SymfonyHelpers\Traits\Entity\EntityLabelTrait
   * @deprecated Use $this->label() instead
   */
  public function getLabelName(): ?string{
    return $this->label();
  }

  /**
   * @see $this->setLabel()
   * @see Pixiekat\SymfonyHelpers\Traits\Entity\EntityLabelTrait
   * @deprecated Use $this->setLabel() instead
   */
  public function setLabelName(string $labelName): self {
    return $this->setLabel($labelName);
  }

  /**
   * String representation of the Vocabulary
   *
   * @return string
   */
  public function __toString(): string {
    return $this->getName() ?? '';
  }
}
