<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Enum;

/**
 * What ShoutModerator decided to do with a submission.
 *
 * Three outcomes rather than two, because "allow" and "block" are not enough:
 * the interesting case is content that is probably fine but worth a look, and
 * collapsing that into either bucket is what makes moderation either leaky or
 * unusable.
 */
enum ModerationDecision: string {

  /** Publish immediately. The normal path. */
  case Publish = 'publish';

  /** Publish, but mark it so a moderator sees it in the queue. */
  case Flag = 'flag';

  /** Withhold pending review. Not visible to the public. */
  case Hold = 'hold';

  /** Refuse outright. Nothing is stored. */
  case Reject = 'reject';

  /**
   * The shout status this decision maps to.
   *
   * Reject has no status because a rejected shout is never persisted — the
   * caller throws instead. Returning null here rather than inventing a status
   * keeps "we did not store this" honest.
   *
   * @return ShoutStatus|null The status, or null when nothing is stored.
   */
  public function toStatus(): ?ShoutStatus {
    return match ($this) {
      self::Publish, self::Flag => ShoutStatus::Published,
      self::Hold => ShoutStatus::Pending,
      self::Reject => null,
    };
  }

  /**
   * Whether the submitter should be told their shout was stopped.
   *
   * Only outright rejection is announced. Telling someone their post is held
   * for review invites them to rewrite it until it slips through, and telling
   * them it was flagged tells them exactly what to avoid next time.
   *
   * @return bool True if the poster should see an error.
   */
  public function isVisibleToPoster(): bool {
    return $this === self::Reject;
  }
}
