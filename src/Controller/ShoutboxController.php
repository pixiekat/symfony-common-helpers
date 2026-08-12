<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Controller;

use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Exception\ShoutRejectedException;
use Pixiekat\SymfonyHelpers\Form;
use Pixiekat\SymfonyHelpers\Interfaces;
use Pixiekat\SymfonyHelpers\Services\ShoutboxManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The public face of the shoutbox.
 *
 * GRACEFUL DEGRADATION
 * --------------------
 * This is an ordinary form POST followed by a redirect (post/redirect/get). No
 * JavaScript is involved at any point: the shoutbox works in Lynx, in a browser
 * with scripts disabled, and on a flaky connection where a fetch() would simply
 * vanish. Turbo, if the host application uses it, layers on top without needing
 * anything here to change — the response is a normal redirect either way.
 *
 * The redirect also means a refresh after posting re-renders the page instead
 * of re-submitting the shout, which is the whole reason PRG exists.
 */
#[Route('/shoutbox')]
class ShoutboxController extends AbstractController {

  /**
   * Constructor.
   *
   * @param ShoutboxManager $shoutbox Where all the policy lives.
   * @param LoggerInterface $logger Used for unexpected failures only.
   * @param Security $security Used to decide whether to show a name field.
   */
  public function __construct(
    private readonly ShoutboxManager $shoutbox,
    private readonly LoggerInterface $logger,
    private readonly Security $security,
  ) {  }

  /**
   * A full page for one channel, for visitors who want the whole history.
   *
   * Also the fallback destination when a shout is posted from a page that did
   * not say where to return to.
   *
   * Note this action builds no form and loads no shouts: the template calls
   * place_shoutbox(), the same function any other page would use. Doing it that
   * way means the widget has exactly one implementation, so the standalone page
   * cannot quietly drift away from the embedded one.
   *
   * @param string $channel The channel to show.
   *
   * @return Response The rendered page.
   */
  #[Route('/{channel}', name: 'pixiekat_symfony_helpers_shoutbox', methods: ['GET'], requirements: ['channel' => '[a-z0-9_\-]+'], defaults: ['channel' => Entity\Shout::DEFAULT_CHANNEL])]
  public function index(string $channel): Response {
    return $this->render('@PixiekatSymfonyHelpers/shoutbox/index.html.twig', [
      'channel' => $channel,
    ]);
  }

  /**
   * Accepts a posted shout and sends the visitor back where they came from.
   *
   * @param string $channel The channel being posted to.
   * @param Request $request The current request.
   *
   * @return Response A redirect, always.
   */
  #[Route('/{channel}/post', name: 'pixiekat_symfony_helpers_shoutbox_post', methods: ['POST'], requirements: ['channel' => '[a-z0-9_\-]+'], defaults: ['channel' => Entity\Shout::DEFAULT_CHANNEL])]
  #[IsGranted(Interfaces\Security\Voter\ShoutboxVoterInterface::SHOUT_POST, message: 'You are not able to post here.')]
  public function post(string $channel, Request $request): Response {
    $form = $this->createShoutForm($channel);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $data = $form->getData();

      try {
        $this->shoutbox->post(
          body: $data['body'] ?? '',
          authorName: $data['authorName'] ?? null,
          channel: $channel,
        );
        $this->addFlash('success', 'Shouted!');
      }
      catch (ShoutRejectedException $e) {
        // A refusal is an expected outcome, not a failure: the message on the
        // exception is already written for a visitor to read.
        $this->addFlash('error', $e->getMessage());
      }
      catch (\Exception $e) {
        $this->logger->error('Error posting shout: {message}', [
          'message' => $e->getMessage(),
        ]);
        $this->addFlash('error', 'Something went wrong and your shout was not saved.');
      }
    }
    elseif ($form->isSubmitted()) {
      // Validation errors would normally be shown by re-rendering the form, but
      // the shoutbox is usually embedded in someone else's page and we have
      // nowhere to re-render. Flashing the first error keeps the feedback loop
      // intact without the controller needing to know its own surroundings.
      $error = $form->getErrors(true)->current();
      $this->addFlash('error', $error ? $error->getMessage() : 'Your shout could not be posted.');
    }

    return $this->redirect($this->resolveReturnPath($request, $channel));
  }

  /**
   * Builds the post form.
   *
   * Must stay in step with ShoutboxExtensionRuntime::buildForm() — the form the
   * widget renders and the form this action validates have to agree on their
   * options, or a submission will not bind.
   *
   * @param string $channel The channel being posted to.
   *
   * @return \Symfony\Component\Form\FormInterface The form.
   */
  private function createShoutForm(string $channel): \Symfony\Component\Form\FormInterface {
    return $this->createForm(Form\ShoutType::class, null, [
      'action' => $this->generateUrl('pixiekat_symfony_helpers_shoutbox_post', ['channel' => $channel]),
      'channel' => $channel,
      // A logged-in visitor's account is their identity; offering a free-text
      // name field alongside it would only invite impersonation.
      'include_author_name' => $this->security->getUser() === null,
    ]);
  }

  /**
   * Works out where to send the visitor after posting.
   *
   * SECURITY — OPEN REDIRECT
   * ------------------------
   * The return path arrives from the request, so it is attacker-controlled and
   * must never be trusted as-is. Handing it straight to redirect() would turn
   * this route into an open redirect: a link to your own trusted domain that
   * quietly lands the visitor on someone else's, which is a gift to phishing.
   *
   * The rules below accept only same-site absolute paths:
   *   - must start with a single "/" — rejects "https://evil.example"
   *   - must not start with "//" — rejects the protocol-relative "//evil.example"
   *   - must not contain "\" — some parsers treat it as "/", so "/\evil.example"
   *     can be read as protocol-relative too
   *
   * Anything else falls back to the shoutbox's own page.
   *
   * @param Request $request The current request.
   * @param string $channel The channel, for the fallback route.
   *
   * @return string A path safe to redirect to.
   */
  private function resolveReturnPath(Request $request, string $channel): string {
    $target = (string) $request->request->get('_return', '');

    if ($target !== ''
      && str_starts_with($target, '/')
      && !str_starts_with($target, '//')
      && !str_contains($target, '\\')
    ) {
      return $target;
    }

    return $this->generateUrl('pixiekat_symfony_helpers_shoutbox', ['channel' => $channel]);
  }
}
