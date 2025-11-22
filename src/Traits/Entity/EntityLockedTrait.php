<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait EntityLockedTrait {

  #[ORM\Column(name: 'locked', type: Types::BOOLEAN, nullable: false, options: ['default' => true])]
  private ?bool $locked = true;

  /**
   * Gets the locked status.
   *
   * @return boolean|null
   */
  public function getLocked(): ?bool {
    return $this->locked;
  }

  /**
   * Checks if the entity is locked.
   *
   * @return boolean
   */
  public function isLocked(): bool {
    return $this->locked === true;
  }

  /**
   * Checks if the entity is unlocked.
   *
   * @return boolean
   */
  public function isUnlocked(): bool {
    return $this->locked === false;
  }

  /**
   * Locks the entity.
   *
   * @return self
   */
  public function lock(): self {
    $this->setLocked(true);
    return $this;
  }

  /**
   * Sets the locked status.
   *
   * @param boolean|null $locked
   * @return self
   */
  public function setLocked(?bool $locked): self {
    $this->locked = $locked;

    return $this;
  }

  /**
   * Unlocks the entity.
   *
   * @return self
   */
  public function unlock(): self {
    $this->setLocked(false);
    return $this;
  }

}
