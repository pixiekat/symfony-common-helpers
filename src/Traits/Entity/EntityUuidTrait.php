<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

trait EntityUuidTrait {

  #[ORM\Column(name: 'uuid', type: UuidType::NAME, unique: true)]
  private ?Uuid $uuid = null;

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

  public function __construct() {
    parent::__construct();
    $this->uuid = Uuid::v4();
  }
}
