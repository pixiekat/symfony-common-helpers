<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Services;

use Psr\Log\LoggerInterface;
use Pixiekat\SymfonyHelpers\Entity\Block;
use Pixiekat\SymfonyHelpers\Repository\BlockRepository;
use Twig\Environment;
use Twig\Markup;

/**
 * Loads and renders blocks.
 *
 * This is the one place that knows how a block turns into HTML, which is what
 * lets both Twig (`place_block('social_links')`) and a controller
 * (`$blockManager->render('social_links')`) produce identical output without
 * either of them duplicating the logic.
 *
 * DESIGN NOTE — WHY A SERVICE AND NOT A TWIG-ONLY HELPER
 * ------------------------------------------------------
 * Putting the logic in a service rather than straight into the Twig runtime
 * means it can be unit-tested without booting Twig, called from a controller
 * that returns a fragment response, and eventually wrapped in a cache or a
 * permission check in exactly one spot. The Twig extension is a thin adapter
 * over this class on purpose.
 *
 * @see \Pixiekat\SymfonyHelpers\Twig\Runtime\BlockExtensionRuntime
 */
class BlockManager {

  /**
   * The template used when a block does not name its own.
   */
  public const DEFAULT_TEMPLATE = '@PixiekatSymfonyHelpers/block/_block.html.twig';

  /**
   * Default render options, merged with whatever the caller passes.
   *
   * - show_title: render the block's label as a heading. Turn this off when the
   *   surrounding template already provides one — but see the note in the
   *   template about keeping it available to screen readers.
   * - heading_level: which heading rank the title uses (2..6). Configurable
   *   because the right answer depends on where the developer placed the block,
   *   and skipped heading levels are an accessibility failure.
   * - limit: render at most N items (null = all). Handy for "latest 5" style
   *   blocks without needing a separate block.
   * - template: force a specific template for this one call, overriding both
   *   the block's own template and the bundle default.
   * - vars: extra variables handed through to the template untouched, so a
   *   caller can pass page context a block needs.
   */
  private const DEFAULT_OPTIONS = [
    'show_title' => true,
    'heading_level' => 2,
    'limit' => null,
    'template' => null,
    'vars' => [],
  ];

  /**
   * Constructor.
   *
   * @param BlockRepository $blocks Where blocks are loaded from.
   * @param Environment $twig The Twig environment used for rendering.
   * @param LoggerInterface $logger Used to report missing blocks without breaking the page.
   */
  public function __construct(
    private readonly BlockRepository $blocks,
    private readonly Environment $twig,
    private readonly LoggerInterface $logger,
  ) {  }

  /**
   * Fetches a block entity by machine name.
   *
   * Use this when you want the data rather than the markup — for instance to
   * build entirely custom output in a template:
   *
   *   {% set block = get_block('social_links') %}
   *   {% for item in block.enabledItems %} ... {% endfor %}
   *
   * @param string $name The machine name.
   * @param bool $enabledOnly Whether disabled blocks should be treated as absent.
   *
   * @return Block|null The block, or null if it does not exist.
   */
  public function get(string $name, bool $enabledOnly = true): ?Block {
    return $this->blocks->findOneByName($name, $enabledOnly);
  }

  /**
   * Renders a block to HTML.
   *
   * A MISSING BLOCK IS NOT AN ERROR. It logs a warning and renders nothing.
   * That is a deliberate graceful-degradation choice: a template referencing a
   * block that has not been seeded yet should leave a hole in the page, not a
   * 500. The warning in the log is how you find out.
   *
   * @param string $name The machine name, e.g. 'social_links'.
   * @param array $options Render options; see self::DEFAULT_OPTIONS.
   *
   * @return Markup The rendered block, or empty Markup if there is nothing to show.
   */
  public function render(string $name, array $options = []): Markup {
    $options = array_merge(self::DEFAULT_OPTIONS, $options);
    $block = $this->get($name);

    if (!$block instanceof Block) {
      $this->logger->warning('Tried to place unknown or disabled block "{block}".', ['block' => $name]);

      return $this->markup('');
    }

    $items = $block->getEnabledItems();
    if (is_int($options['limit']) && $options['limit'] >= 0) {
      $items = array_slice($items, 0, $options['limit']);
    }

    // Nothing to say — no body and no items — so emit nothing at all rather
    // than an empty <section> wrapper with a stray heading in it.
    if ($items === [] && ($block->getBody() === null || trim($block->getBody()) === '')) {
      return $this->markup('');
    }

    $template = $options['template'] ?? $block->getTemplate() ?? self::DEFAULT_TEMPLATE;

    // Block-supplied variables come first so that an explicit 'vars' entry can
    // deliberately override them; that ordering makes `vars` a genuine escape
    // hatch rather than a second-class citizen.
    $rendered = $this->twig->render($template, array_merge([
      'block' => $block,
      'items' => $items,
      'show_title' => (bool) $options['show_title'],
      // Clamped rather than trusted: a stray 0 or 9 would emit <h0>/<h9>, which
      // is invalid HTML and gives assistive tech nothing useful to work with.
      'heading_level' => max(1, min(6, (int) $options['heading_level'])),
      'options' => $options,
    ], $options['vars']));

    return $this->markup($rendered);
  }

  /**
   * Wraps a rendered string so Twig prints it as HTML instead of escaping it.
   *
   * Twig\Markup is the well-behaved way to do this — unlike marking the whole
   * function `is_safe`, it keeps the decision attached to the value itself.
   *
   * @param string $html The rendered markup.
   * @return Markup The same markup, flagged as already-safe.
   */
  private function markup(string $html): Markup {
    return new Markup($html, 'UTF-8');
  }
}
