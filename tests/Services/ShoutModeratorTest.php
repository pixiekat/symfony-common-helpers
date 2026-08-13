<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Tests\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pixiekat\SymfonyHelpers\Entity\Term;
use Pixiekat\SymfonyHelpers\Entity\Vocabulary;
use Pixiekat\SymfonyHelpers\Enum\ModerationDecision;
use Pixiekat\SymfonyHelpers\Interfaces\Entity\HelpersUserInterface;
use Pixiekat\SymfonyHelpers\Repository\ShoutRepository;
use Pixiekat\SymfonyHelpers\Repository\VocabularyRepository;
use Pixiekat\SymfonyHelpers\Services\ShoutModerator;
use Psr\Log\NullLogger;

/**
 * Covers ShoutModerator's decision table.
 *
 * These tests double as the specification: the evasion cases below are the
 * whole reason the normalisation step exists, and the Scunthorpe case is the
 * reason matching is anchored to word boundaries. If someone later "simplifies"
 * normalise() to a str_contains, these are what tell them why not.
 *
 * No database and no container — the moderator takes two repositories and a
 * logger, so it can be assembled by hand and the tests stay fast enough to run
 * on every save.
 */
class ShoutModeratorTest extends TestCase {

  /**
   * Builds a moderator with a given word list and posting history.
   *
   * @param array<string, int> $words Word list entries as name => severity.
   * @param int $publishedFromIp How many published shouts this address has.
   *
   * @return ShoutModerator The assembled moderator.
   */
  private function moderator(array $words = [], int $publishedFromIp = 0): ShoutModerator {
    $vocabulary = null;

    if ($words !== []) {
      $vocabulary = new Vocabulary();
      foreach ($words as $name => $weight) {
        $term = new Term();
        $term->setName($name);
        $term->setWeight($weight);
        $vocabulary->addTerm($term);
      }
    }

    $vocabularies = $this->createStub(VocabularyRepository::class);
    $vocabularies->method('findByMachineName')->willReturn($vocabulary);

    $shouts = $this->createStub(ShoutRepository::class);
    $shouts->method('countPublishedFromIp')->willReturn($publishedFromIp);

    return new ShoutModerator($vocabularies, $shouts, new NullLogger());
  }

  // ── Trust short-circuits everything ──────────────────────────────────────

  public function testAuthenticatedAuthorBypassesEveryContentCheck(): void {
    $verdict = $this->moderator(['spam' => 99])
      ->assess('spam spam http://a.example http://b.example', $this->createStub(HelpersUserInterface::class), '10.0.0.1');

    $this->assertSame(ModerationDecision::Publish, $verdict->decision);
    $this->assertContains('trusted:authenticated', $verdict->reasons);
  }

  public function testAddressWithEnoughPublishedShoutsIsTrusted(): void {
    $verdict = $this->moderator(['spam' => 99], ShoutModerator::TRUST_AFTER)
      ->assess('spam', null, '10.0.0.1');

    $this->assertSame(ModerationDecision::Publish, $verdict->decision);
    $this->assertContains('trusted:history', $verdict->reasons);
  }

  public function testAddressBelowTheTrustThresholdIsStillChecked(): void {
    $verdict = $this->moderator(['spam' => 99], ShoutModerator::TRUST_AFTER - 1)
      ->assess('spam', null, '10.0.0.1');

    $this->assertSame(ModerationDecision::Reject, $verdict->decision);
  }

  // ── The ordinary case ────────────────────────────────────────────────────

  public function testCleanMessageFromAStrangerIsPublished(): void {
    $verdict = $this->moderator(['spam' => 50])->assess('hello, nice site!', null, '10.0.0.1');

    $this->assertSame(ModerationDecision::Publish, $verdict->decision);
    $this->assertSame(0, $verdict->score);
    $this->assertSame([], $verdict->reasons);
  }

  // ── Severity tiers ───────────────────────────────────────────────────────

  public function testSeverityDecidesTheOutcome(): void {
    foreach ([
      [60, ModerationDecision::Reject],
      [15, ModerationDecision::Hold],
      [5, ModerationDecision::Flag],
      [0, ModerationDecision::Publish],
    ] as [$weight, $expected]) {
      $verdict = $this->moderator(['badger' => $weight])->assess('you badger', null, '10.0.0.1');

      $this->assertSame($expected, $verdict->decision, "weight {$weight} should give {$expected->value}");
    }
  }

  public function testSeveritiesAccumulate(): void {
    // Two mild words that are individually only worth flagging add up to a hold.
    $verdict = $this->moderator(['badger' => 6, 'weasel' => 6])->assess('badger weasel', null, '10.0.0.1');

    $this->assertSame(12, $verdict->score);
    $this->assertSame(ModerationDecision::Hold, $verdict->decision);
  }

  // ── Links ────────────────────────────────────────────────────────────────

  public function testASingleLinkOnlyFlags(): void {
    $verdict = $this->moderator()->assess('look at http://example.com', null, '10.0.0.1');

    $this->assertSame(ModerationDecision::Flag, $verdict->decision);
    $this->assertContains('links:1', $verdict->reasons);
  }

  public function testSeveralLinksAreHeld(): void {
    $verdict = $this->moderator()
      ->assess('http://a.example http://b.example http://c.example', null, '10.0.0.1');

    $this->assertSame(ModerationDecision::Hold, $verdict->decision);
    $this->assertContains('links:3', $verdict->reasons);
  }

  // ── Evasions the normaliser is there to undo ─────────────────────────────

  #[DataProvider('evasions')]
  public function testEvasionsStillMatch(string $body, string $why): void {
    $verdict = $this->moderator(['spam' => 60])->assess($body, null, '10.0.0.1');

    $this->assertSame(ModerationDecision::Reject, $verdict->decision, $why);
  }

  /**
   * @return array<string, array{0: string, 1: string}>
   */
  public static function evasions(): array {
    return [
      'plain' => ['buy spam now', 'the baseline'],
      'leetspeak' => ['buy sp4m now', '4 substituted for a'],
      'mixed case' => ['buy SpAm now', 'case folding'],
      'diacritics' => ['buy spám now', 'accents stripped'],
      'padded letters' => ['buy spaaaam now', 'runs of 3+ collapsed'],
      'spaced out' => ['buy s p a m now', 'matched against the despaced copy'],
      'zero width' => ["buy sp\u{200B}am now", 'zero-width space removed'],
    ];
  }

  // ── The false positive that makes filters unusable ───────────────────────

  public function testWordBoundariesPreventTheScunthorpeProblem(): void {
    $moderator = $this->moderator(['ass' => 90]);

    foreach (['I passed the class', 'a bass guitar', 'Scunthorpe is lovely'] as $innocent) {
      $verdict = $moderator->assess($innocent, null, '10.0.0.1');

      $this->assertSame(ModerationDecision::Publish, $verdict->decision, "\"{$innocent}\" must not match");
    }
  }

  public function testTheWordStillMatchesWhenItStandsAlone(): void {
    $verdict = $this->moderator(['ass' => 90])->assess('you ass', null, '10.0.0.1');

    $this->assertSame(ModerationDecision::Reject, $verdict->decision);
  }

  // ── Escape hatches ───────────────────────────────────────────────────────

  public function testAnEntryWrappedInSlashesIsTreatedAsARegex(): void {
    $verdict = $this->moderator(['/gr+eat deal/' => 60])->assess('what a grrrreat deal', null, '10.0.0.1');

    $this->assertSame(ModerationDecision::Reject, $verdict->decision);
  }

  public function testAMalformedRegexIsSkippedRatherThanFatal(): void {
    // A broken entry typed into the admin screen must not take the shoutbox
    // down — it should simply never match.
    $verdict = $this->moderator(['/unclosed(/' => 90, 'spam' => 60])->assess('hello there', null, '10.0.0.1');

    $this->assertSame(ModerationDecision::Publish, $verdict->decision);
  }

  public function testMissingVocabularyDegradesToLinkChecksOnly(): void {
    // A fresh install has no blocked_words vocabulary at all.
    $verdict = $this->moderator()->assess('anything at all', null, '10.0.0.1');

    $this->assertSame(ModerationDecision::Publish, $verdict->decision);
  }

  // ── Reasons are recorded, because the queue needs them ───────────────────

  public function testReasonsCarryTheWordAndItsSeverity(): void {
    $verdict = $this->moderator(['badger' => 15])->assess('you badger', null, '10.0.0.1');

    $this->assertContains('word:badger(15)', $verdict->reasons);
    $this->assertSame('word:badger(15)', $verdict->reasonSummary());
  }
}
