<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Twig\Runtime;

use Pixiekat\SymfonyHelpers\Entity\Block;
use Pixiekat\SymfonyHelpers\Services\BlockManager;
use Twig\Extension\RuntimeExtensionInterface;
use Twig\Markup;

/**
 * The working half of BlockExtension.
 *
 * Kept as a thin adapter over BlockManager on purpose — every real decision
 * about what a block is and how it renders lives in the service, so controllers
 * and templates cannot drift apart.
 *
 * @see \Pixiekat\SymfonyHelpers\Twig\Extension\BlockExtension
 * @see \Pixiekat\SymfonyHelpers\Services\BlockManager
 */
class BlockExtensionRuntime implements RuntimeExtensionInterface {

  /**
   * Constructor.
   *
   * @param BlockManager $blockManager The service that loads and renders blocks.
   */
  public function __construct(
    private readonly BlockManager $blockManager,
  ) {  }

  /**
   * Renders a block where the template asks for it.
   *
   *   {{ place_block('social_links') }}
   *   {{ place_block('meta_links', { show_title: false }) }}
   *   {{ place_block('shouts', { limit: 10 }) }}
   *
   * @param string $name The block's machine name.
   * @param array $options Render options — show_title, limit, template, vars.
   *
   * @return Markup The rendered block, or empty markup if it does not exist.
   */
  public function placeBlock(string $name, array $options = []): Markup {
    return $this->blockManager->render($name, $options);
  }

  /**
   * Fetches a block entity for templates that want to build their own markup.
   *
   *   {% set social = get_block('social_links') %}
   *   {% if social %}
   *     {% for item in social.enabledItems %}...{% endfor %}
   *   {% endif %}
   *
   * @param string $name The block's machine name.
   *
   * @return Block|null The block, or null if it does not exist or is disabled.
   */
  public function getBlock(string $name): ?Block {
    return $this->blockManager->get($name);
  }
}
