<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Twig\Extension;

use Pixiekat\SymfonyHelpers\Twig\Runtime\ShoutboxExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the shoutbox widget to templates.
 *
 * Named to rhyme with place_block() — both answer "put this thing here" — so
 * there is one mental model for dropping bundle features into a layout:
 *
 *   {{ place_block('social_links') }}
 *   {{ place_shoutbox() }}
 */
class ShoutboxExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      // {{ place_shoutbox() }}
      // {{ place_shoutbox('staff', { limit: 10, show_title: false }) }}
      new TwigFunction('place_shoutbox', [ShoutboxExtensionRuntime::class, 'placeShoutbox']),

      // {{ shoutbox_latest('default', 5) }} — data only, for custom markup.
      new TwigFunction('shoutbox_latest', [ShoutboxExtensionRuntime::class, 'latest']),
    ];
  }
}
