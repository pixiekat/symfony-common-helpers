<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Services;

/**
 * Answers "is this part of the bundle switched on?".
 *
 * One small service rather than the features array being injected in five
 * places, so there is exactly one definition of what "enabled" means and one
 * place to change if the answer ever needs to be more than a boolean (per-role,
 * per-environment, per-tenant).
 *
 * Consulted by two things:
 *   - BaseVoter, so a disabled feature denies every permission and its routes
 *     answer 403 rather than remaining reachable to anyone who knows the URL.
 *   - AdminNavProvider, so it disappears from the control panel menu.
 *
 * An unknown feature name is treated as ENABLED. That sounds backwards until
 * you consider the failure it prevents: a voter naming a feature that has no
 * config node yet — because the constant was added before the config was —
 * would otherwise deny everything, and the symptom would be a 403 nobody can
 * explain. Unknown means "not something this class governs", not "off".
 */
class FeatureChecker {

  /**
   * Constructor.
   *
   * @param array<string, bool> $features The resolved feature map, injected
   *   from the symfony_helpers.features parameter.
   */
  public function __construct(
    private readonly array $features = [],
  ) {  }

  /**
   * Whether a feature is switched on.
   *
   * @param string|null $feature The feature name, or null for code that has no
   *   feature of its own — which is always enabled.
   *
   * @return bool True if enabled.
   */
  public function isEnabled(?string $feature): bool {
    if ($feature === null || !array_key_exists($feature, $this->features)) {
      return true;
    }

    return (bool) $this->features[$feature];
  }

  /**
   * Whether a feature is switched off.
   *
   * @param string|null $feature The feature name.
   *
   * @return bool True if disabled.
   */
  public function isDisabled(?string $feature): bool {
    return !$this->isEnabled($feature);
  }

  /**
   * The whole map, for debugging and for templates that want to branch.
   *
   * @return array<string, bool> The features.
   */
  public function all(): array {
    return $this->features;
  }
}
