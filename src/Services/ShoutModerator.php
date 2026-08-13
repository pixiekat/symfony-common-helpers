<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Services;

use Pixiekat\SymfonyHelpers\Entity\Term;
use Pixiekat\SymfonyHelpers\Enum\ModerationDecision;
use Pixiekat\SymfonyHelpers\Interfaces\Entity\HelpersUserInterface;
use Pixiekat\SymfonyHelpers\Model\ModerationVerdict;
use Pixiekat\SymfonyHelpers\Repository\ShoutRepository;
use Pixiekat\SymfonyHelpers\Repository\VocabularyRepository;
use Psr\Log\LoggerInterface;

/**
 * Decides whether a submitted shout is published, held, flagged or refused.
 *
 * ── THE SHAPE OF THE PROBLEM ───────────────────────────────────────────────
 * A shoutbox is a live conversation; that liveness IS the feature. Holding
 * everything for review turns it into a slow contact form, so the goal is never
 * "review everything" — it is "review the small slice that is not obviously
 * fine". That means the default has to be publish, and everything here is about
 * earning an exception to it.
 *
 * ── TRUST BEATS CONTENT ────────────────────────────────────────────────────
 * Content matching is the WEAKEST signal available. Somebody determined to be
 * unpleasant will work around a word list in about four seconds; somebody
 * posting casino spam will not. So trust is checked first and short-circuits
 * everything: a logged-in user, or an address that has posted before without
 * ever being moderated, never touches the word list at all. This is the
 * "first post moderated" pattern, and it is what keeps the box feeling live for
 * the people who actually use it.
 *
 * ── THE WORD LIST IS TAXONOMY ──────────────────────────────────────────────
 * Rather than a config array or a new entity, the list is a Vocabulary named
 * 'blocked_words' whose Terms are the entries. That gets three things free:
 * the taxonomy admin screens already exist, the list is editable without a
 * deploy, and Term::weight is a per-word SEVERITY rather than a boolean — which
 * is what lets one list drive four different outcomes instead of just "block".
 *
 *     weight >= 50   reject outright, nothing is stored
 *     weight >= 10   hold for review
 *     weight >=  1   publish, but flag for a look
 *
 * A term wrapped in slashes is treated as a regular expression, for the cases
 * a plain word cannot express. Use it sparingly — it is the part that will bite
 * you at 2am.
 *
 * ── WHY AUTO-BANNING IS NOT HERE ───────────────────────────────────────────
 * Deliberately absent. Bans are matched on IP, and mobile carriers put whole
 * regions behind one CGNAT address, so an automatic ban on a single word match
 * can take out thousands of innocent people — silently, with no way for them to
 * tell you. Rejection is cheap and reversible; a ban is not. If you do want
 * escalation, base it on REPETITION (N rejections from one address inside a
 * window), which is the actual signature of a persistent abuser rather than of
 * one unfortunate sentence, and give it an expiry.
 *
 * @see \Pixiekat\SymfonyHelpers\Model\ModerationVerdict
 * @see \Pixiekat\SymfonyHelpers\Enum\ModerationDecision
 */
class ShoutModerator {

  /**
   * Machine name of the vocabulary holding the word list.
   *
   * Absent vocabulary means no word checks — the moderator degrades to trust
   * and link signals rather than failing, so a fresh install works immediately.
   */
  public const VOCABULARY = 'blocked_words';

  /**
   * Score at or above which a submission is refused outright.
   */
  public const THRESHOLD_REJECT = 50;

  /**
   * Score at or above which a submission is held for review.
   */
  public const THRESHOLD_HOLD = 10;

  /**
   * Score at or above which a submission is published but flagged.
   */
  public const THRESHOLD_FLAG = 1;

  /**
   * How many previously published shouts make an address trusted.
   */
  public const TRUST_AFTER = 3;

  /**
   * Score added for the first link, and for every link after it.
   *
   * Links are the single strongest spam signal in a shoutbox — far stronger
   * than any word — so a first-time poster including two of them is worth a
   * look even if every word is innocent.
   */
  public const SCORE_FIRST_LINK = 5;
  public const SCORE_EXTRA_LINK = 10;

  /**
   * Constructor.
   *
   * @param VocabularyRepository $vocabularies Supplies the word list.
   * @param ShoutRepository $shouts Supplies the posting history used for trust.
   * @param LoggerInterface $logger Records refusals and holds.
   */
  public function __construct(
    private readonly VocabularyRepository $vocabularies,
    private readonly ShoutRepository $shouts,
    private readonly LoggerInterface $logger,
  ) {  }

  /**
   * Assesses a submission.
   *
   * @param string $body The message as typed.
   * @param HelpersUserInterface|null $author The authenticated poster, if any.
   * @param string|null $ipAddress The poster's address, if known.
   *
   * @return ModerationVerdict The decision and the reasons behind it.
   */
  public function assess(string $body, ?HelpersUserInterface $author = null, ?string $ipAddress = null): ModerationVerdict {

    // ── Trust, first and decisive ──────────────────────────────────────────
    // An account is a real identity that took effort to obtain and can be
    // revoked. Nothing below is worth running against one.
    if ($author !== null) {
      return ModerationVerdict::publish(['trusted:authenticated']);
    }

    if ($ipAddress !== null && $this->shouts->countPublishedFromIp($ipAddress) >= self::TRUST_AFTER) {
      return ModerationVerdict::publish(['trusted:history']);
    }

    // ── Content signals, for strangers only ────────────────────────────────
    $score = 0;
    $reasons = [];

    [$linkScore, $linkReason] = $this->scoreLinks($body);
    if ($linkScore > 0) {
      $score += $linkScore;
      $reasons[] = $linkReason;
    }

    foreach ($this->matchWordList($body) as $reason => $weight) {
      $score += $weight;
      $reasons[] = $reason;
    }

    $decision = match (true) {
      $score >= self::THRESHOLD_REJECT => ModerationDecision::Reject,
      $score >= self::THRESHOLD_HOLD => ModerationDecision::Hold,
      $score >= self::THRESHOLD_FLAG => ModerationDecision::Flag,
      default => ModerationDecision::Publish,
    };

    if ($decision !== ModerationDecision::Publish) {
      $this->logger->info('Shout moderated as {decision} (score {score}): {reasons}', [
        'decision' => $decision->value,
        'score' => $score,
        'reasons' => implode(', ', $reasons),
        'ip' => $ipAddress,
      ]);
    }

    return new ModerationVerdict($decision, $reasons, $score);
  }

  /**
   * Scores the links in a message.
   *
   * @param string $body The message.
   *
   * @return array{0: int, 1: string} The score and a reason code.
   */
  private function scoreLinks(string $body): array {
    $count = preg_match_all('#https?://#i', $body);

    if ($count < 1) {
      return [0, ''];
    }

    $score = self::SCORE_FIRST_LINK + (($count - 1) * self::SCORE_EXTRA_LINK);

    return [$score, sprintf('links:%d', $count)];
  }

  /**
   * Finds word list hits in a message.
   *
   * @param string $body The message.
   *
   * @return array<string, int> Reason code => severity.
   */
  private function matchWordList(string $body): array {
    $terms = $this->wordList();

    if ($terms === []) {
      return [];
    }

    // Two haystacks. The second catches the oldest evasion in the book —
    // "f u c k" — without needing a spaced variant of every list entry.
    $normalised = $this->normalise($body);
    $despaced = $this->collapseSpacedOut($normalised);

    $hits = [];

    foreach ($terms as $word => $weight) {
      $pattern = $this->patternFor($word);

      if ($pattern === null) {
        continue;
      }

      // @ suppression: a malformed regex typed into the admin screen should
      // skip that one entry, not take down every shout on the site.
      if (@preg_match($pattern, $normalised) === 1 || @preg_match($pattern, $despaced) === 1) {
        $hits[sprintf('word:%s(%d)', $word, $weight)] = $weight;
      }
    }

    return $hits;
  }

  /**
   * Joins up runs of single characters written out one at a time.
   *
   *     "buy s p a m now"  ->  "buy spam now"
   *     "s.p.a.m"          ->  "spam"
   *
   * Note what this deliberately does NOT do: strip all whitespace. That was the
   * obvious first attempt and it is wrong, because "buy s p a m now" collapses
   * to "buyspamnow" — and \bspam\b cannot match inside a longer run of letters,
   * so the evasion still worked. Dropping the word boundaries to compensate
   * would bring the Scunthorpe problem straight back ("class" contains "ass").
   *
   * Targeting only runs of three or more single characters keeps the boundaries
   * intact around the joined word and leaves ordinary prose untouched.
   *
   * @param string $value Already-normalised text.
   *
   * @return string The text with letter-by-letter runs joined up.
   */
  private function collapseSpacedOut(string $value): string {
    return preg_replace_callback(
      '/(?:(?<=^)|(?<=\s))(\w(?:[\s.\-_]\w){2,})(?=[\s.]|$)/u',
      static fn(array $matches): string => preg_replace('/[\s.\-_]+/u', '', $matches[1]) ?? $matches[1],
      $value,
    ) ?? $value;
  }

  /**
   * Builds the match pattern for a list entry.
   *
   * A term wrapped in slashes is used verbatim as a regular expression;
   * anything else is matched on WORD BOUNDARIES. The boundaries are what stop
   * the Scunthorpe problem — a plain substring search for a rude word inside
   * innocent town names is the classic way to make a filter unusable.
   *
   * @param string $word The list entry.
   *
   * @return string|null A regex, or null if the entry is unusable.
   */
  private function patternFor(string $word): ?string {
    $word = trim($word);

    if ($word === '') {
      return null;
    }

    if (str_starts_with($word, '/') && strrpos($word, '/') > 0) {
      return $word . 'u';
    }

    return '/\b' . preg_quote($this->normalise($word), '/') . '\b/u';
  }

  /**
   * Folds a string down to something worth matching against.
   *
   * Each step undoes a specific evasion:
   *   - zero-width characters, invisible but enough to break a match
   *   - diacritics, so "fuck" and "fück" are the same word
   *   - leetspeak substitutions
   *   - runs of a repeated letter, so "heeeeey" matches "hey"
   *
   * None of this defeats a determined person, and it is not meant to. It
   * defeats the lazy version, which is most of the volume.
   *
   * @param string $value The raw text.
   *
   * @return string The folded text.
   */
  private function normalise(string $value): string {
    $value = mb_strtolower($value, 'UTF-8');

    // Zero-width space, non-joiner, joiner, BOM.
    $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value) ?? $value;

    // Diacritics. iconv rather than the intl Transliterator so the bundle does
    // not gain an ext-intl requirement for a nice-to-have.
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($ascii !== false) {
      $value = mb_strtolower($ascii, 'UTF-8');
    }

    $value = strtr($value, [
      '4' => 'a', '@' => 'a', '3' => 'e', '1' => 'i', '!' => 'i',
      '0' => 'o', '5' => 's', '$' => 's', '7' => 't',
    ]);

    // Collapse three-or-more runs to a single character. Two is left alone so
    // ordinary doubled letters ("pass", "committee") are untouched.
    return preg_replace('/(.)\1{2,}/u', '$1', $value) ?? $value;
  }

  /**
   * Loads the word list as entry => severity.
   *
   * @return array<string, int> The list, empty if the vocabulary is absent.
   */
  private function wordList(): array {
    $vocabulary = $this->vocabularies->findByMachineName(self::VOCABULARY);

    if ($vocabulary === null) {
      $this->logger->info('Shout moderation vocabulary "{vocabulary}" is absent; word checks are disabled.', [
        'vocabulary' => self::VOCABULARY,
      ]);
      return [];
    }

    $list = [];

    /** @var Term $term */
    foreach ($vocabulary->getTerms() as $term) {
      $name = (string) $term->getName();
      $this->logger->debug('Shout moderation vocabulary "{vocabulary}" includes term "{term}" with weight {weight}.', [
        'vocabulary' => self::VOCABULARY,
        'term' => $name,
        'weight' => $term->getWeight(),
      ]);

      if ($name !== '') {
        // Weight is the severity. A term left at the default 0 scores nothing,
        // which makes it a harmless note-to-self rather than an active rule.
        $list[$name] = (int) $term->getWeight();
        $this->logger->debug('Shout moderation vocabulary "{vocabulary}" term "{term}" has weight {weight}.', [
          'vocabulary' => self::VOCABULARY,
          'term' => $name,
          'weight' => $term->getWeight(),
        ]);
      }
    }

    return $list;
  }
}
