<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Twig\Extension;

use Pixiekat\SymfonyHelpers\Twig\Runtime\AdminExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Control panel helpers for templates.
 *
 * Functions rather than Twig globals on purpose. A global is evaluated for
 * every template render whether it is used or not, and building the nav means
 * running an authorization check per entry; a function backed by a lazy runtime
 * costs nothing on the pages that never ask.
 *
 * (The one genuine global the bundle does register is `admin_layout`, which is
 * a plain string from a container parameter — no work to do, and it has to be
 * available to `{% extends %}`, which runs before anything else in a template.)
 */
class AdminExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      // {% set admin_nav_sections = helpers_admin_nav()|merge([ ...your own... ]) %}
      new TwigFunction('helpers_admin_nav', [AdminExtensionRuntime::class, 'nav']),

      // {% if helpers_feature('shoutbox') %} ... {% endif %}
      new TwigFunction('helpers_feature', [AdminExtensionRuntime::class, 'feature']),
    ];
  }
}
