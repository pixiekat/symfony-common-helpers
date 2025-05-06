<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityActiveTrait {

  #[ORM\Column(name: 'is_active', nullable: false, options: ['default' => true])]
  private ?bool $active = true;

  public function getActive(): ?bool {
    return $this->active;
  }

  public function isActive(): bool {
    return $this->active === true;
  }

  public function isInactive(): bool {
    return $this->active === false;
  }

  public function activate(): self {
    $this->setActive(true);
    return $this;
  }

  public function setActive(?bool $active): self {
    $this->active = $active;

    return $this;
  }

  public function deactivate(): self {
    $this->setActive(false);
    return $this;
  }

}
