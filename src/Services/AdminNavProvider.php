<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Services;

use Pixiekat\SymfonyHelpers\Interfaces\Security\Voter as Voters;
use Pixiekat\SymfonyHelpers\Interfaces\Services\AdminNavProviderInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Builds the control panel menu entries for the screens this bundle ships.
 *
 * ── WHY THIS IS A SERVICE AND NOT A {% set %} ──────────────────────────────
 * The menu in cp_layout is declared as data by the branding layer, which lives
 * in the consuming application. That works beautifully for the application's
 * OWN screens — and not at all for the bundle's, because it would mean every
 * app hand-listing audit, bans, blocks, shoutbox and taxonomy, and then
 * remembering to update that list whenever the bundle grows a screen. Exactly
 * the duplication the bundle exists to remove.
 *
 * So the bundle supplies its own sections, and the app merges them with its own:
 *
 *     {% set admin_nav_sections = helpers_admin_nav_sections|merge([
 *       {label: 'Content', links: [{route: 'app_pages_list', label: 'Pages'}]},
 *     ]) %}
 *
 * ── TWO FILTERS, BOTH NECESSARY ────────────────────────────────────────────
 * An entry appears only if its feature is switched on AND the current user is
 * granted its permission. They are different questions: a feature can be on for
 * the site but off for you. Checking permission here rather than in the twig
 * partial means the partial stays a dumb renderer and this stays testable.
 *
 * Note the feature check is technically redundant — a disabled feature's voter
 * abstains, so is_granted() is already false — but it is kept because relying
 * on that would make the menu quietly depend on voter internals, and because it
 * short-circuits before doing authorization work for a feature that is off.
 */
class AdminNavProvider implements AdminNavProviderInterface {

  /**
   * The helpers' own sections sit at 0 — the baseline other bundles position
   * themselves relative to.
   *
   * @return int The priority.
   */
  public function priority(): int {
    return 0;
  }

  /**
   * Constructor.
   *
   * @param FeatureChecker $features Which parts of the bundle are switched on.
   * @param AuthorizationCheckerInterface $authorization Per-user permission checks.
   */
  public function __construct(
    private readonly FeatureChecker $features,
    private readonly AuthorizationCheckerInterface $authorization,
  ) {  }

  /**
   * The bundle's menu sections, filtered for the current user.
   *
   * Shaped exactly as _cp_nav.html.twig expects:
   *   [{label: string, links: [{route: string, label: string, also?: string[]}]}]
   *
   * `also` lists the routes that belong to an entry without having a menu item
   * of their own — an edit screen should still highlight the list it came from.
   *
   * @return array The sections.
   */
  public function sections(): array {
    $sections = [];

    $sections[] = [
      'label' => 'Admin Index',
      'links' => [
        ['route' => 'pixiekat_symfony_helpers_admin_index', 'label' => 'Overview'],
      ],
    ];

    if ($this->allowed('blocks', Voters\BlockVoterInterface::BLOCK_ADMINISTER)) {
      $sections[] = [
        'label' => 'Content',
        'links' => [
          [
            'route' => 'pixiekat_symfony_helpers_block_list',
            'label' => 'Blocks',
            'also' => [
              'pixiekat_symfony_helpers_block_add',
              'pixiekat_symfony_helpers_block_edit',
              'pixiekat_symfony_helpers_block_delete',
              'pixiekat_symfony_helpers_block_item_list',
              'pixiekat_symfony_helpers_block_item_add',
              'pixiekat_symfony_helpers_block_item_edit',
              'pixiekat_symfony_helpers_block_item_delete',
            ],
          ],
        ],
      ];
    }

    if ($this->allowed('taxonomy', Voters\TaxonomyVoterInterface::TAXONOMY_ADMINISTER)) {
      $sections[] = [
        'label' => 'Taxonomy',
        'links' => [
          [
            'route' => 'pixiekat_symfony_helpers_taxonomy_vocabulary_list',
            'label' => 'Vocabularies',
            'also' => [
              'pixiekat_symfony_helpers_taxonomy_vocabulary_add',
              'pixiekat_symfony_helpers_taxonomy_vocabulary_edit',
              'pixiekat_symfony_helpers_taxonomy_vocabulary_delete',
              'pixiekat_symfony_helpers_taxonomy_term_list',
              'pixiekat_symfony_helpers_taxonomy_term_add',
              'pixiekat_symfony_helpers_taxonomy_term_edit',
              'pixiekat_symfony_helpers_taxonomy_term_delete',
            ],
          ],
        ],
      ];
    }

    $community = [];

    if ($this->allowed('shoutbox', Voters\ShoutboxVoterInterface::SHOUTBOX_ADMINISTER)) {
      $community[] = [
        'route' => 'pixiekat_symfony_helpers_shout_list',
        'label' => 'Shoutbox',
        'also' => [
          'pixiekat_symfony_helpers_shout_edit',
          'pixiekat_symfony_helpers_shout_delete',
        ],
      ];
    }

    if ($this->allowed('bans', Voters\BanVoterInterface::BAN_LIST_BANS)) {
      $community[] = [
        'route' => 'pixiekat_symfony_helpers_ban_list',
        'label' => 'Bans',
        'also' => [
          'pixiekat_symfony_helpers_ban_add',
          'pixiekat_symfony_helpers_ban_edit',
          'pixiekat_symfony_helpers_ban_remove',
        ],
      ];
    }

    if ($community !== []) {
      $sections[] = ['label' => 'Community', 'links' => $community];
    }

    if ($this->allowed('audit', Voters\AuditVoterInterface::AUDIT_ADMINISTER)) {
      $sections[] = [
        'label' => 'System',
        'links' => [
          ['route' => 'pixiekat_symfony_helpers_audit_list', 'label' => 'Audit Log'],
        ],
      ];
    }

    return $sections;
  }

  /**
   * Whether a feature is on AND the current user may use it.
   *
   * @param string $feature The feature switch name.
   * @param string $attribute The voter attribute to check.
   *
   * @return bool True if the entry should be shown.
   */
  private function allowed(string $feature, string $attribute): bool {
    return $this->features->isEnabled($feature) && $this->authorization->isGranted($attribute);
  }
}
