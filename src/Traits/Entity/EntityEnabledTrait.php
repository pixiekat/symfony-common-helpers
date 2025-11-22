<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait EntityEnabledTrait {

  #[ORM\Column(name: 'is_enabled', type: Types::BOOLEAN, nullable: false, options: ['default' => true])]
  private ?bool $enabled = true;

  public function disable(): ?static {
    $this->enabled = false;

    return $this;
  }

  public function disabled(): ?bool {
    return $this->enabled === false;
  }

  public function enable(): ?static {
    $this->enabled = true;

    return $this;
  }

  public function enabled(): ?bool {
    return $this->enabled === true;
  }

  public function isDisabled(): ?bool {
    return $this->disabled();
  }

  public function isEnabled(): ?bool {
    return $this->enabled();
  }

  public function setEnabled(bool $isEnabled): ?static {
    $this->enabled = $isEnabled;

    return $this;
  }

  public function setIsEnabled(?bool $isEnabled): ?static {
    return $this->setEnabled($isEnabled);
  }

}
