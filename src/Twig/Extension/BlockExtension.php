<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Twig\Extension;

use Pixiekat\SymfonyHelpers\Twig\Runtime\BlockExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the block system to templates.
 *
 * Split into an Extension (declares what exists) and a Runtime (does the work,
 * and is the thing that has services injected into it). Twig instantiates the
 * runtime lazily, so a page that never places a block never builds a
 * BlockManager, a repository or a database connection for it.
 *
 * Note there is no `is_safe` flag on these functions: BlockManager returns a
 * Twig\Markup object, which Twig already knows not to escape. Marking the
 * function itself safe would blanket-trust anything it ever returns, including
 * future code paths; returning Markup keeps the trust tied to the actual value.
 */
class BlockExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      // {{ place_block('social_links') }}
      // {{ place_block('social_links', { show_title: false, limit: 5 }) }}
      new TwigFunction('place_block', [BlockExtensionRuntime::class, 'placeBlock']),

      // {% set block = get_block('social_links') %} — for fully custom markup.
      new TwigFunction('get_block', [BlockExtensionRuntime::class, 'getBlock']),
    ];
  }
}
