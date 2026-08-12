<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Enum\ShoutStatus;
use Pixiekat\SymfonyHelpers\Form;
use Pixiekat\SymfonyHelpers\Interfaces;
use Pixiekat\SymfonyHelpers\Repository;
use Pixiekat\SymfonyHelpers\Services\ShoutboxManager;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Moderation screens for the shoutbox.
 *
 * The list doubles as the moderation queue: it shows every status, including
 * the spam and soft-deleted rows the public view hides, so a moderator can see
 * what was filtered as well as what got through.
 */
#[Route('/admincp/shoutbox')]
#[IsGranted(Interfaces\Security\Voter\ShoutboxVoterInterface::SHOUTBOX_ADMINISTER, message: 'You do not have permission to administer the shoutbox.')]
class ShoutAdminController extends AbstractController {

  /**
   * Constructor.
   *
   * @param EntityManagerInterface $entityManager Persistence.
   * @param Repository\ShoutRepository $shouts Shout lookups.
   * @param ShoutboxManager $shoutbox Used for status changes, so the logging
   *   stays in one place rather than being duplicated per controller action.
   * @param LoggerInterface $logger Audit trail.
   * @param Security $security Used to attribute changes in the log.
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly Repository\ShoutRepository $shouts,
    private readonly ShoutboxManager $shoutbox,
    private readonly LoggerInterface $logger,
    private readonly Security $security,
  ) {  }

  /**
   * The moderation queue.
   *
   * @param Request $request The current request, read for the ?channel filter.
   *
   * @return Response The rendered list.
   */
  #[Route('/', name: 'pixiekat_symfony_helpers_shout_list', methods: ['GET'])]
  public function list(Request $request): Response {
    $channel = $request->query->get('channel') ?: null;

    return $this->render('@PixiekatSymfonyHelpers/shoutbox/admin/list.html.twig', [
      'shouts' => $this->shouts->findForModeration($channel, 200),
      'channels' => $this->shouts->findChannels(),
      'channel' => $channel,
    ]);
  }

  /**
   * Edits a shout.
   *
   * @param Entity\Shout $shout The shout being edited.
   * @param Request $request The current request.
   *
   * @return Response The form, or a redirect once saved.
   */
  #[Route('/{shout}/edit', name: 'pixiekat_symfony_helpers_shout_edit', methods: ['GET', 'POST'])]
  #[IsGranted(Interfaces\Security\Voter\ShoutboxVoterInterface::SHOUT_EDIT, subject: 'shout', message: 'You do not have permission to edit this shout.')]
  public function edit(
    #[MapEntity(mapping: ['shout' => 'id'])] Entity\Shout $shout,
    Request $request,
  ): Response {
    $form = $this->createForm(Form\ShoutAdminType::class, $shout);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      try {
        $this->entityManager->flush();

        $this->logger->info('Shout {id} edited by user {user}.', [
          'id' => $shout->getId(),
          'user' => $this->security->getUser()?->getUserIdentifier() ?? 'anonymous',
        ]);
        $this->addFlash('success', 'Shout saved successfully.');
      }
      catch (\Exception $e) {
        $this->logger->error('Error saving shout: {message}', ['message' => $e->getMessage()]);
        $this->addFlash('error', 'An error occurred while saving the shout.');
      }

      return $this->redirectToRoute('pixiekat_symfony_helpers_shout_list');
    }

    return $this->render('@PixiekatSymfonyHelpers/shoutbox/admin/edit.html.twig', [
      'form' => $form->createView(),
      'shout' => $shout,
    ]);
  }

  /**
   * Changes a shout's status in one click from the queue.
   *
   * POST-only and CSRF-protected: this changes state, and a state-changing GET
   * can be triggered by anything that fetches a URL — a prefetcher, an image
   * tag on another site, a link in an email.
   *
   * @param Entity\Shout $shout The shout being moderated.
   * @param string $status The new status, as its backing value.
   * @param Request $request The current request.
   *
   * @return Response A redirect back to the queue.
   */
  #[Route('/{shout}/status/{status}', name: 'pixiekat_symfony_helpers_shout_status', methods: ['POST'])]
  #[IsGranted(Interfaces\Security\Voter\ShoutboxVoterInterface::SHOUT_MODERATE, subject: 'shout', message: 'You do not have permission to moderate shouts.')]
  public function status(
    #[MapEntity(mapping: ['shout' => 'id'])] Entity\Shout $shout,
    string $status,
    Request $request,
  ): Response {
    if (!$this->isCsrfTokenValid('shout_status_' . $shout->getId(), (string) $request->request->get('_token'))) {
      $this->addFlash('error', 'Invalid security token. Please try again.');

      return $this->redirectToRoute('pixiekat_symfony_helpers_shout_list');
    }

    $newStatus = ShoutStatus::tryFrom($status);

    if ($newStatus === null) {
      $this->addFlash('error', 'Unknown status.');

      return $this->redirectToRoute('pixiekat_symfony_helpers_shout_list');
    }

    $this->shoutbox->moderate($shout, $newStatus);
    $this->addFlash('success', sprintf('Shout marked as %s.', $newStatus->label()));

    return $this->redirectToRoute('pixiekat_symfony_helpers_shout_list');
  }

  /**
   * Permanently deletes a shout, after confirmation.
   *
   * Distinct from marking it Deleted: that hides it but keeps the row for
   * moderation history, which is usually what you want. This is for when the
   * content genuinely must not remain on the server.
   *
   * @param Entity\Shout $shout The shout being deleted.
   * @param Request $request The current request.
   *
   * @return Response The confirmation form, or a redirect once deleted.
   */
  #[Route('/{shout}/delete', name: 'pixiekat_symfony_helpers_shout_delete', methods: ['GET', 'POST', 'DELETE'])]
  #[IsGranted(Interfaces\Security\Voter\ShoutboxVoterInterface::SHOUT_DELETE, subject: 'shout', message: 'You do not have permission to delete this shout.')]
  public function delete(
    #[MapEntity(mapping: ['shout' => 'id'])] Entity\Shout $shout,
    Request $request,
  ): Response {
    $form = $this->createForm(Form\ConfirmDeleteType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->has('cancel') && $form->get('cancel')->isClicked()) {
      return $this->redirectToRoute('pixiekat_symfony_helpers_shout_list');
    }

    if ($form->isSubmitted() && $form->isValid()) {
      try {
        $id = $shout->getId();
        $this->entityManager->remove($shout);
        $this->entityManager->flush();

        $this->logger->info('Shout {id} permanently deleted by user {user}.', [
          'id' => $id,
          'user' => $this->security->getUser()?->getUserIdentifier() ?? 'anonymous',
        ]);
        $this->addFlash('success', 'Shout deleted successfully.');
      }
      catch (\Exception $e) {
        $this->logger->error('Error deleting shout: {message}', ['message' => $e->getMessage()]);
        $this->addFlash('error', 'An error occurred while deleting the shout.');
      }

      return $this->redirectToRoute('pixiekat_symfony_helpers_shout_list');
    }

    return $this->render('@PixiekatSymfonyHelpers/shoutbox/admin/delete.html.twig', [
      'form' => $form->createView(),
      'shout' => $shout,
    ]);
  }
}
