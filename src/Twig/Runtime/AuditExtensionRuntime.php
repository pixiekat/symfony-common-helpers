<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Twig\Runtime;

use Pixiekat\SymfonyHelpers\Interfaces\Entity\AuditLogInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Turns an audit entry into a readable sentence.
 *
 * ── HOW A PHRASING IS CHOSEN ───────────────────────────────────────────────
 * Look up `audit.action.<action>` in the messages catalogue. If there is no
 * entry for it, fall back to `audit.action.default`, which is deliberately
 * written to make sense for ANY action:
 *
 *     audit.action.default:      '%actor% performed %action% on %target%'
 *     audit.action.user.deleted: '%actor% deleted user %target%'
 *
 * The fallback is what keeps this from becoming a chore. A new action logged
 * from anywhere in the application renders as a sensible sentence immediately;
 * writing a nicer phrasing for it is an improvement you make when you feel like
 * it, never a prerequisite for the screen not looking broken.
 *
 * ── WHY NOT A REGISTRY OF PHP CLASSES ──────────────────────────────────────
 * A tagged 'audit action' service per action type would allow richer output —
 * links to the target, conditional wording. It would also mean a class, a
 * service tag and a test for every kind of event, which is a great deal of
 * machinery for what is nearly always one sentence with two substitutions. If a
 * particular action ever genuinely needs logic, give it its own template
 * fragment in the admin listing rather than reaching for a registry.
 *
 * Escaping: this returns a plain string, NOT Markup. Actor and target labels
 * come from user-supplied data (a display name, a block title), so Twig must
 * escape them on output. Do not add |raw at the call site.
 */
class AuditExtensionRuntime implements RuntimeExtensionInterface {

  /**
   * Translation key used when an action has no phrasing of its own.
   */
  public const DEFAULT_KEY = 'audit.action.default';

  /**
   * Constructor.
   *
   * @param TranslatorInterface $translator Supplies the phrasings.
   */
  public function __construct(
    private readonly TranslatorInterface $translator,
  ) {  }

  /**
   * Renders one entry as a sentence.
   *
   * @param AuditLogInterface $entry The entry.
   *
   * @return string The sentence, unescaped — let Twig escape it on output.
   */
  public function message(AuditLogInterface $entry): string {
    $parameters = [
      '%actor%' => $entry->getActorLabel(),
      '%action%' => $entry->getAction(),
      // An entry with no target still has to read as a sentence; an empty
      // string would leave a dangling "on ".
      '%target%' => $this->describeTarget($entry),
    ];

    $key = 'audit.action.' . $entry->getAction();
    $message = $this->translator->trans($key, $parameters);

    // Symfony's translator returns the key unchanged when there is no entry for
    // it, which is how we detect a miss. Comparing against the key is the only
    // portable test — there is no "has this id" on TranslatorInterface.
    if ($message === $key) {
      $message = $this->translator->trans(self::DEFAULT_KEY, $parameters);
    }

    return $message;
  }

  /**
   * Builds the phrase standing in for the target.
   *
   * Prefers the label snapshot, falls back to type + id, and finally to a dash
   * so a missing target never renders as a blank gap the reader has to
   * interpret.
   *
   * @param AuditLogInterface $entry The entry.
   *
   * @return string The target phrase.
   */
  private function describeTarget(AuditLogInterface $entry): string {
    $label = $entry->getTargetLabel();

    if ($label !== null && trim($label) !== '') {
      return $label;
    }

    $type = $entry->getTargetType();
    $id = $entry->getTargetId();

    if ($type !== null && $id !== null) {
      return $type . ' #' . $id;
    }

    return $type ?? '—';
  }
}
