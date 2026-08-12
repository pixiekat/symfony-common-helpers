<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Twig\Extension;

use Pixiekat\SymfonyHelpers\Twig\Runtime\AuditExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Renders audit entries as sentences.
 *
 * The vBulletin 3 admin log read as prose — "Katy deleted user Bob" — rather
 * than as a row of database columns, and that is most of why it was pleasant to
 * skim. Getting there needs a per-action phrasing, and the cheapest place to
 * keep phrasings is the translation catalogue: adding one is a line of YAML, it
 * is translatable for free, and no PHP has to change.
 */
class AuditExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      // {{ audit_message(entry) }}
      new TwigFunction('audit_message', [AuditExtensionRuntime::class, 'message']),
    ];
  }
}
