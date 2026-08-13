<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Model;

use Pixiekat\SymfonyHelpers\Enum\ModerationDecision;

/**
 * What the moderator decided, and why.
 *
 * The "why" is not decoration. A moderation queue that shows you held messages
 * without saying what tripped is a queue you have to re-read every entry of by
 * hand; one that says `wordlist:slur(60)` and `links:3` lets you skim. The
 * reasons are stored on the shout's `flags` so they survive into the admin
 * screen, and they are the raw material for tuning the word list later.
 */
final class ModerationVerdict {

  /**
   * Constructor.
   *
   * @param ModerationDecision $decision What to do with the submission.
   * @param string[] $reasons Machine-readable reason codes, e.g. 'links:3'.
   * @param int $score The accumulated severity that produced the decision.
   */
  public function __construct(
    public readonly ModerationDecision $decision,
    public readonly array $reasons = [],
    public readonly int $score = 0,
  ) {  }

  /**
   * A verdict that lets the shout straight through.
   *
   * @param string[] $reasons Why it was trusted, e.g. ['trusted:authenticated'].
   * @return self
   */
  public static function publish(array $reasons = []): self {
    return new self(ModerationDecision::Publish, $reasons);
  }

  /**
   * Whether the shout should be stored at all.
   *
   * @return bool False only for outright rejection.
   */
  public function shouldStore(): bool {
    return $this->decision !== ModerationDecision::Reject;
  }

  /**
   * The reasons as a single string, for logs.
   *
   * @return string Comma-separated reason codes, or 'none'.
   */
  public function reasonSummary(): string {
    return $this->reasons === [] ? 'none' : implode(', ', $this->reasons);
  }
}
