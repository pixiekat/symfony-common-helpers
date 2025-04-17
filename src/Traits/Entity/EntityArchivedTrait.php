<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use \DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

trait EntityArchivedTrait {

  #[ORM\Column(name: 'archived_at', nullable: true)]
  private ?DateTimeImmutable $archivedAt = null;

  public function archive(): self {
    $this->setArchivedAt(new \DateTimeImmutable());
    return $this;
  }

  public function getArchivedAt(): ?\DateTimeInterface {
    return $this->archivedAt;
  }

  public function isArchived(): bool {
    return $this->archivedAt !== null;
  }

  public function setArchivedAt(?\DateTimeInterface $archivedAt): self {
    $this->archivedAt = $archivedAt;

    return $this;
  }

  public function unarchive(): self {
    $this->setArchivedAt(null);
    return $this;
  }

}
