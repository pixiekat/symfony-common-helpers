<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Interfaces\Services;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Lets any bundle contribute sections to the control panel menu.
 *
 * ── THE PROBLEM THIS SOLVES ────────────────────────────────────────────────
 * The menu used to be "whatever symfony-common-helpers ships, plus whatever the
 * application merges on top". That works for exactly two participants. The
 * moment a THIRD bundle has admin screens — recipeboxes, stash-it, lumina-ui —
 * it has nowhere to put them, and every consuming application ends up
 * hand-listing another bundle's routes and keeping that list in step as the
 * bundle grows. Which is the precise duplication this library exists to remove.
 *
 * So: implement this, and the entries appear. Autoconfiguration applies the tag,
 * so there is nothing to register.
 *
 *     class RecipeboxesAdminNav implements AdminNavProviderInterface {
 *       public function sections(): array {
 *         return [['label' => 'Recipes', 'links' => [...]]];
 *       }
 *       public function priority(): int { return 0; }
 *     }
 *
 * ── EACH PROVIDER FILTERS ITS OWN ──────────────────────────────────────────
 * A provider is responsible for returning only what the current user may see —
 * its own feature flags, its own voter checks. The aggregator deliberately does
 * NOT try to police that: it cannot know what permission another bundle's
 * screens need, and a central guard that has to be taught about every bundle is
 * the coupling being avoided.
 */
#[AutoconfigureTag('pixiekat.admin_nav_provider')]
interface AdminNavProviderInterface {

  /**
   * The sections to add to the control panel menu.
   *
   * Shaped as _cp_nav.html.twig expects:
   *   [{label: string, links: [{route: string, label: string, also?: string[]}]}]
   *
   * Return [] when the current user should see none of them — that is the
   * normal way to hide a whole section, rather than returning it and hoping
   * something downstream filters it.
   *
   * @return array The sections.
   */
  public function sections(): array;

  /**
   * Ordering weight. Lower sorts first.
   *
   * The helpers' own sections sit at 0. Use a negative number to appear above
   * them, positive to appear below. Ties keep registration order, which is
   * bundle order — do not rely on it.
   *
   * @return int The priority.
   */
  public function priority(): int;
}
