<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Controller;

use Pixiekat\SymfonyHelpers\Interfaces;
use Pixiekat\SymfonyHelpers\Repository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The audit log viewer — vBulletin 3's admin log, rebuilt.
 *
 * Read-only by design; see AuditVoterInterface for why there is no edit or
 * delete action. Pruning happens from the console.
 */
#[Route('/admincp/audit')]
#[IsGranted(Interfaces\Security\Voter\AuditVoterInterface::AUDIT_ADMINISTER, message: 'You do not have permission to view the audit log.')]
class AuditAdminController extends AbstractController {

  /**
   * Rows per page.
   */
  private const PER_PAGE = 50;

  /**
   * Constructor.
   *
   * @param Repository\AuditLogRepository $auditLogs The read side.
   */
  public function __construct(
    private readonly Repository\AuditLogRepository $auditLogs,
  ) {  }

  /**
   * The log listing, filtered and paged.
   *
   * A GET form, so a filtered view is a URL you can bookmark, share with
   * whoever you are investigating something with, and reload without the
   * browser asking about re-submission.
   *
   * @param Request $request The current request.
   *
   * @return Response The rendered listing.
   */
  #[Route('/', name: 'pixiekat_symfony_helpers_audit_list', methods: ['GET'])]
  public function list(Request $request): Response {
    $filters = $this->readFilters($request);

    // max(1, ...) rather than trusting the query string: ?page=0 or ?page=-3
    // would otherwise produce a negative offset and a database error.
    $page = max(1, $request->query->getInt('page', 1));
    $total = $this->auditLogs->countForAdmin($filters);
    $pages = max(1, (int) ceil($total / self::PER_PAGE));
    $page = min($page, $pages);

    return $this->render('@PixiekatSymfonyHelpers/audit/admin/list.html.twig', [
      'entries' => $this->auditLogs->findForAdmin($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
      'actions' => $this->auditLogs->findActions(),
      'target_types' => $this->auditLogs->findTargetTypes(),
      'filters' => $request->query->all(),
      'page' => $page,
      'pages' => $pages,
      'total' => $total,
    ]);
  }

  /**
   * Translates query parameters into repository filters.
   *
   * Dates are parsed here rather than in the repository so a nonsense value
   * ("?from=banana") becomes "no date filter" instead of an exception on an
   * admin screen. A filter that quietly does nothing is friendlier than a 500,
   * and the empty box shows the reader it was not applied.
   *
   * @param Request $request The current request.
   *
   * @return array The filters, in the shape AuditLogRepository expects.
   */
  private function readFilters(Request $request): array {
    $filters = [];

    foreach (['action', 'action_prefix', 'target_type', 'actor_label', 'ip'] as $key) {
      $value = trim((string) $request->query->get($key, ''));
      if ($value !== '') {
        $filters[$key] = $value;
      }
    }

    foreach (['from' => 'from', 'to' => 'to'] as $param => $key) {
      $raw = trim((string) $request->query->get($param, ''));
      if ($raw === '') {
        continue;
      }

      try {
        $date = new \DateTimeImmutable($raw);
      }
      catch (\Exception) {
        continue;
      }

      // A bare date means the whole of that day. Without this, "to=2026-08-12"
      // resolves to midnight and silently excludes everything that happened
      // during the day the reader actually asked about.
      if ($key === 'to' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $date = $date->setTime(23, 59, 59);
      }

      $filters[$key] = $date;
    }

    return $filters;
  }
}
