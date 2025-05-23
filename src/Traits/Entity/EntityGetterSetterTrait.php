<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityGetterSetterTrait {

  public function __get(string $key): mixed {
    return property_exists($this, $key) ? $this->$key : throw new \Exception("Property '{$key}' does not exist");
  }

  public function __set(string $key, mixed $value): void {
    if (property_exists($this, $key)) {
      $this->$key = $value;
    }
    else {
      throw new \Exception("Cannot set nonexistent property '{$key}'");
    }
  }
}
