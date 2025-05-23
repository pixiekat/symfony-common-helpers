<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait EntityEnabledTrait {

  #[ORM\Column(name: 'is_enabled', length: 1, nullable: false)]
  protected ?bool $enabled = true;

  public function disable(): ?static {
    $this->enabled = false;

    return $this;
  }

  public function disabled(): ?int {
    return $this->enabled === false ? 1 : 0;
  }

  public function enable(): ?static {
    $this->enabled = true;

    return $this;
  }

  public function enabled(): ?int {
    return $this->enabled === true ? 1 : 0;
  }

  public function isDisabled(): ?int {
    return $this->disabled();
  }

  public function isEnabled(): ?int {
    return $this->enabled();
  }

  public function setIsEnabled(bool $isEnabled): ?static {
    $this->enabled = $isEnabled;

    return $this;
  }

}
