<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;
use Symfony\Component\Uid\Uuid;

trait EntityUuidTrait {
  public function getUuid(): ?Uuid {
    return $this->uuid;
  }

  public function setUuid(Uuid $uuid): static {
    $this->uuid = $uuid;

    return $this;
  }

  public function uuid(): mixed {
    return $this->uuid;
  }
}
