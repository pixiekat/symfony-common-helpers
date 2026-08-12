<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Exception;

/**
 * Thrown when ShoutboxManager refuses to accept a shout.
 *
 * Deliberately one exception type with named constructors rather than a small
 * hierarchy: the caller almost always wants to do the same thing (show the
 * message to the visitor), and the `reason` code is there for the rare case
 * that wants to branch.
 */
class ShoutRejectedException extends \RuntimeException {

  /** The poster's IP address is banned. */
  public const REASON_BANNED = 'banned';

  /** The poster is shouting faster than the flood limit allows. */
  public const REASON_FLOOD = 'flood';

  /** The message was empty once trimmed. */
  public const REASON_EMPTY = 'empty';

  /**
   * Constructor.
   *
   * @param string $reason One of the REASON_* constants.
   * @param string $message A message safe to show to the visitor.
   */
  private function __construct(
    public readonly string $reason,
    string $message,
  ) {
    parent::__construct($message);
  }

  /**
   * The poster is banned.
   *
   * The message stays vague on purpose — telling someone exactly which of their
   * addresses is banned, and for how long, mostly helps them evade it.
   *
   * @return self
   */
  public static function banned(): self {
    return new self(self::REASON_BANNED, 'You are not able to post here.');
  }

  /**
   * The poster is going too fast.
   *
   * @param int $seconds How long they need to wait.
   * @return self
   */
  public static function flood(int $seconds): self {
    return new self(
      self::REASON_FLOOD,
      sprintf('You are posting too quickly. Please wait about %d seconds and try again.', $seconds),
    );
  }

  /**
   * The message was blank.
   *
   * @return self
   */
  public static function empty(): self {
    return new self(self::REASON_EMPTY, 'Your message was empty.');
  }
}
