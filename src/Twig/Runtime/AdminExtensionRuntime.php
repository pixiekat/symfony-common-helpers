<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Twig\Runtime;

use Pixiekat\SymfonyHelpers\Services\AdminNavProvider;
use Pixiekat\SymfonyHelpers\Services\FeatureChecker;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * The working half of AdminExtension.
 *
 * @see \Pixiekat\SymfonyHelpers\Twig\Extension\AdminExtension
 */
class AdminExtensionRuntime implements RuntimeExtensionInterface {

  /**
   * Constructor.
   *
   * @param AdminNavProvider $nav Builds the bundle's menu sections.
   * @param FeatureChecker $features Which parts of the bundle are switched on.
   */
  public function __construct(
    private readonly AdminNavProvider $nav,
    private readonly FeatureChecker $features,
  ) {  }

  /**
   * The bundle's control panel menu sections, filtered for the current user.
   *
   * Merge your own alongside them in your branding layout:
   *
   *   {% set admin_nav_sections = helpers_admin_nav()|merge([
   *     {label: 'Content', links: [{route: 'app_pages_list', label: 'Pages'}]},
   *   ]) %}
   *
   * @return array The sections, in the shape _cp_nav.html.twig expects.
   */
  public function nav(): array {
    return $this->nav->sections();
  }

  /**
   * Whether a bundle feature is switched on.
   *
   * For templates that want to branch on a feature without going through a
   * permission check — a public page deciding whether to place the shoutbox,
   * for instance.
   *
   * @param string $feature The feature name, e.g. 'shoutbox'.
   *
   * @return bool True if enabled.
   */
  public function feature(string $feature): bool {
    return $this->features->isEnabled($feature);
  }
}
