<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Enum;

/**
 * Moderation state of a shout.
 *
 * A backed enum rather than a bare string column so the set of valid states is
 * enforced by PHP and by Doctrine (enumType), and so adding a state is a change
 * in one place instead of a grep for magic strings.
 *
 * Note there is no hard delete in the normal flow: DELETED hides a shout while
 * keeping it for moderation history. An admin can still purge a row outright
 * when they genuinely want it gone.
 */
enum ShoutStatus: string {

  /** Visible to everyone. */
  case Published = 'published';

  /** Held back for a human to look at. Not rendered publicly. */
  case Pending = 'pending';

  /** Marked as spam. Not rendered publicly, kept as evidence. */
  case Spam = 'spam';

  /** Soft-deleted. Not rendered publicly, kept for moderation history. */
  case Deleted = 'deleted';

  /**
   * Whether shouts in this state are shown to the public.
   *
   * Centralising this means a new state cannot accidentally become visible by
   * default — you have to come here and say so.
   *
   * @return bool True if the shout should render publicly.
   */
  public function isPublic(): bool {
    return $this === self::Published;
  }

  /**
   * A human-readable name for admin listings and form choices.
   *
   * @return string The label.
   */
  public function label(): string {
    return match ($this) {
      self::Published => 'Published',
      self::Pending => 'Pending review',
      self::Spam => 'Spam',
      self::Deleted => 'Deleted',
    };
  }

  /**
   * Choices formatted for a Symfony ChoiceType, i.e. ['Label' => case].
   *
   * @return array<string, self> The label => case map.
   */
  public static function choices(): array {
    $choices = [];
    foreach (self::cases() as $case) {
      $choices[$case->label()] = $case;
    }

    return $choices;
  }
}
