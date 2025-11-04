<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait EntityFlagsTrait {

  #[ORM\Column(type: 'json', name: 'flags', nullable: true, options: ['default' => null])]
  private ?array $flags = null;

  public function getFlags(): array {
    return $this->flags ?? [];
  }

  public function setFlags(array $flags): void {
    $this->flags = $flags;
  }

  public function getFlag(string $key): mixed {
    return $this->flags[$key] ?? null;
  }

  public function setFlag(string $key, mixed $value): void {
    $this->flags[$key] = $value;
  }

  public function unsetFlag(string $key): void {
    unset($this->flags[$key]);
  }

  public function hasFlag(string $key): bool {
    return array_key_exists($key, $this->flags);
  }

  public static function getAvailableFlags(): array {
    $reflection = new \ReflectionClass(static::class);
    return array_keys(array_filter(
      $reflection->getConstants(),
      fn($k) => str_starts_with($k, 'FLAG_'),
      ARRAY_FILTER_USE_KEY
    ));
  }

  public static function getAvailableFlagValues(): array {
    $reflection = new \ReflectionClass(static::class);
    return array_values(array_filter(
      $reflection->getConstants(),
      fn($k) => str_starts_with($k, 'FLAG_'),
      ARRAY_FILTER_USE_KEY
    ));
  }

}
