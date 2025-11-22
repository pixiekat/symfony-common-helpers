<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Repository;
use Pixiekat\SymfonyHelpers\Traits as PixieTraits;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'vocabulary_terms')]
#[ORM\Entity(repositoryClass: Repository\TermRepository::class)]
#[ORM\Cache(
  usage: 'NONSTRICT_READ_WRITE',
  region: 'default_entity_region'
)]
#[ApiResource]
class Term {

  use PixieTraits\Entity\EntityIdTrait;

  #[ORM\ManyToOne(targetEntity: Entity\Vocabulary::class, inversedBy: "terms")]
  #[ORM\JoinColumn(nullable: false)]
  private $vocabulary;

  use PixieTraits\Entity\EntityNameTrait;
  use PixieTraits\Entity\EntityWeightTrait;

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
