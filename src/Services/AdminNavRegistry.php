<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Services;

use Pixiekat\SymfonyHelpers\Interfaces\Services\AdminNavProviderInterface;

/**
 * Collects every bundle's control panel menu sections into one list.
 *
 * The providers arrive as a tagged iterator, so a bundle that implements
 * AdminNavProviderInterface appears in the menu with no registration and no
 * change here. Adding a screen becomes a change in the bundle that owns it,
 * which is the only place that knows about it.
 *
 * @see \Pixiekat\SymfonyHelpers\Interfaces\Services\AdminNavProviderInterface
 */
class AdminNavRegistry {

  /**
   * Constructor.
   *
   * @param iterable<AdminNavProviderInterface> $providers The tagged providers.
   *   An iterable rather than an array so Symfony can build them lazily — a
   *   page that never renders the menu never constructs a provider, and
   *   providers do authorization work.
   */
  public function __construct(
    private readonly iterable $providers = [],
  ) {  }

  /**
   * Every section the current user should see, in priority order.
   *
   * @return array The sections.
   */
  public function sections(): array {
    $collected = [];

    foreach ($this->providers as $provider) {
      foreach ($provider->sections() as $section) {
        // Priority is carried alongside rather than inside the section, so the
        // shape handed to the template stays exactly what _cp_nav expects and
        // gains no key it would have to know to ignore.
        $collected[] = ['priority' => $provider->priority(), 'section' => $section];
      }
    }

    // usort is not stable across PHP versions for equal elements in older
    // releases; PHP 8 guarantees stability, which is what preserves
    // registration order within a priority.
    usort($collected, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);

    return array_column($collected, 'section');
  }
}
