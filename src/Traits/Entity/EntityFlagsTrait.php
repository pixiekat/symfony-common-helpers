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
    // $flags starts as null, and relying on PHP to auto-vivify an array out of
    // null is a deprecated-adjacent habit worth not having. Be explicit.
    $this->flags ??= [];
    $this->flags[$key] = $value;
  }

  public function unsetFlag(string $key): void {
    unset($this->flags[$key]);
  }

  public function hasFlag(string $key): bool {
    // Coalesce before the check: $flags is nullable, and array_key_exists()
    // throws a TypeError on null rather than returning false, so calling
    // hasFlag() on an entity that never had a flag set used to fatal.
    return array_key_exists($key, $this->flags ?? []);
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
