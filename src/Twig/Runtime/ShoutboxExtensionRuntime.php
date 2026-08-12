<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Twig\Runtime;

use Pixiekat\SymfonyHelpers\Entity\Shout;
use Pixiekat\SymfonyHelpers\Form\ShoutType;
use Pixiekat\SymfonyHelpers\Services\ShoutboxManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Pixiekat\SymfonyHelpers\Interfaces\Security\Voter\ShoutboxVoterInterface;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;
use Twig\Markup;

/**
 * Renders the shoutbox widget wherever a template asks for it.
 *
 * Building the form here rather than in every controller that happens to show a
 * shoutbox is the point: a sidebar shoutbox on the homepage should not require
 * HomepageController to know anything about shouts.
 *
 * @see \Pixiekat\SymfonyHelpers\Twig\Extension\ShoutboxExtension
 */
class ShoutboxExtensionRuntime implements RuntimeExtensionInterface {

  /**
   * The template rendered when the caller does not name one.
   */
  public const DEFAULT_TEMPLATE = '@PixiekatSymfonyHelpers/shoutbox/_shoutbox.html.twig';

  /**
   * Default widget options.
   */
  private const DEFAULT_OPTIONS = [
    'limit' => 20,
    'show_title' => true,
    'heading_level' => 2,
    'title' => 'Shoutbox',
    'template' => null,
  ];

  /**
   * Constructor.
   *
   * @param ShoutboxManager $shoutbox Read side of the shoutbox.
   * @param FormFactoryInterface $formFactory Builds the post form.
   * @param UrlGeneratorInterface $urlGenerator Builds the form action.
   * @param RequestStack $requestStack Used to work out where to return to.
   * @param Security $security Decides whether to offer a name field.
   * @param AuthorizationCheckerInterface $authorization Decides whether to offer the form at all.
   * @param Environment $twig Renders the widget.
   */
  public function __construct(
    private readonly ShoutboxManager $shoutbox,
    private readonly FormFactoryInterface $formFactory,
    private readonly UrlGeneratorInterface $urlGenerator,
    private readonly RequestStack $requestStack,
    private readonly Security $security,
    private readonly AuthorizationCheckerInterface $authorization,
    private readonly Environment $twig,
  ) {  }

  /**
   * Renders the shoutbox: recent shouts plus, if permitted, the post form.
   *
   * @param string $channel Which shoutbox to render.
   * @param array $options limit, show_title, heading_level, title, template.
   *
   * @return Markup The rendered widget.
   */
  public function placeShoutbox(string $channel = Shout::DEFAULT_CHANNEL, array $options = []): Markup {
    $options = array_merge(self::DEFAULT_OPTIONS, $options);

    $mayPost = $this->authorization->isGranted(ShoutboxVoterInterface::SHOUT_POST);

    $rendered = $this->twig->render($options['template'] ?? self::DEFAULT_TEMPLATE, [
      'channel' => $channel,
      'shouts' => $this->shoutbox->latest($channel, (int) $options['limit']),
      'form' => $mayPost ? $this->buildForm($channel)->createView() : null,
      'return_path' => $this->currentPath(),
      'show_title' => (bool) $options['show_title'],
      'heading_level' => max(1, min(6, (int) $options['heading_level'])),
      'title' => $options['title'],
      'options' => $options,
    ]);

    return new Markup($rendered, 'UTF-8');
  }

  /**
   * The latest shouts as entities, for templates writing their own markup.
   *
   * @param string $channel Which shoutbox to read.
   * @param int $limit How many to return.
   *
   * @return Shout[] The shouts, newest first.
   */
  public function latest(string $channel = Shout::DEFAULT_CHANNEL, int $limit = 20): array {
    return $this->shoutbox->latest($channel, $limit);
  }

  /**
   * Builds an empty post form aimed at the shoutbox post route.
   *
   * @param string $channel The channel being posted to.
   *
   * @return \Symfony\Component\Form\FormInterface The form.
   */
  private function buildForm(string $channel): \Symfony\Component\Form\FormInterface {
    return $this->formFactory->create(ShoutType::class, null, [
      'action' => $this->urlGenerator->generate('pixiekat_symfony_helpers_shoutbox_post', ['channel' => $channel]),
      'channel' => $channel,
      'include_author_name' => $this->security->getUser() === null,
    ]);
  }

  /**
   * The path of the page the widget is being rendered into.
   *
   * Sent along as a hidden field so posting returns the visitor to the page
   * they were actually reading, rather than dumping them on the shoutbox's own
   * page. The controller re-validates it before redirecting — a value that has
   * been through the browser is untrusted no matter who put it there.
   *
   * @return string|null The path, or null outside a request.
   */
  private function currentPath(): ?string {
    return $this->requestStack->getCurrentRequest()?->getPathInfo();
  }
}
