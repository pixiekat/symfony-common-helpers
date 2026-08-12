<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Command;

use Pixiekat\SymfonyHelpers\Repository\AuditLogRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes audit entries past a retention cut-off.
 *
 * ── WHY THIS EXISTS ────────────────────────────────────────────────────────
 * An audit log is append-only, so it is the one table in a small site that
 * genuinely grows without bound. Two reasons to bound it deliberately rather
 * than discovering the problem later:
 *
 *   1. It holds IP addresses. That is personal data, and "we kept everything
 *      forever because nobody wrote a cron job" is not a retention policy.
 *   2. Unbounded growth turns the admin screen's COUNT query slow, which is
 *      exactly when you least want to be looking at it.
 *
 * Nothing runs this for you. Put it on a schedule:
 *
 *     0 4 * * *  bin/console pixiekat:audit:prune --days=365
 *
 * ── ON BEING ABLE TO REHEARSE IT ───────────────────────────────────────────
 * --dry-run reports the real number without deleting anything. A destructive
 * command with no rehearsal is one people put off running, and a retention
 * policy nobody dares execute is not a policy.
 */
#[AsCommand(
  name: 'pixiekat:audit:prune',
  description: 'Deletes audit log entries older than a given number of days.',
)]
class PruneAuditLogsCommand extends Command {

  /**
   * Retention used when --days is not given.
   *
   * A year: long enough to cover "what happened last time we did the annual
   * thing", short enough that it is a real policy and not a shrug.
   */
  private const DEFAULT_DAYS = 365;

  /**
   * Constructor.
   *
   * @param AuditLogRepository $auditLogs The repository doing the deleting.
   */
  public function __construct(
    private readonly AuditLogRepository $auditLogs,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Delete entries older than this many days.', self::DEFAULT_DAYS)
      ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be deleted, and delete nothing.')
      ->setHelp(<<<'HELP'
        Deletes audit log entries older than <info>--days</info> (default: 365).

          <info>bin/console pixiekat:audit:prune --days=90 --dry-run</info>
          <info>bin/console pixiekat:audit:prune --days=90</info>

        Rows are removed with a single DQL DELETE rather than being loaded and
        removed one at a time, so pruning a very large table stays cheap.
        HELP)
    ;
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $days = (int) $input->getOption('days');
    $dryRun = (bool) $input->getOption('dry-run');

    // Refusing zero and negatives rather than clamping them: `--days=0` most
    // likely means a shell variable that did not expand, and silently
    // interpreting it as "delete everything" would be a cruel reading.
    if ($days < 1) {
      $io->error('--days must be at least 1. Refusing to run: --days=0 would delete the entire log.');

      return Command::INVALID;
    }

    $before = new \DateTimeImmutable(sprintf('-%d days', $days));
    $io->text(sprintf('Cut-off: entries created before <info>%s</info>.', $before->format('j F Y, H:i')));

    if ($dryRun) {
      $count = $this->auditLogs->countOlderThan($before);
      $io->success(sprintf('%d entr%s would be deleted. Nothing was changed.', $count, $count === 1 ? 'y' : 'ies'));

      return Command::SUCCESS;
    }

    $deleted = $this->auditLogs->deleteOlderThan($before);
    $io->success(sprintf('Deleted %d audit log entr%s.', $deleted, $deleted === 1 ? 'y' : 'ies'));

    return Command::SUCCESS;
  }
}
