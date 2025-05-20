<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Controller;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class UserController extends AbstractController {

  public function __construct(
    private readonly Security $security,
  ) {}

  #[Route('/login', name: 'pixiekat_symfony_helpers_login')]
  public function index(AuthenticationUtils $authenticationUtils): Response {

    if ($this->security->isGranted('IS_AUTHENTICATED')) {
      return $this->redirect('/');
    }
    // get the login error if there is one
    $error = $authenticationUtils->getLastAuthenticationError();

    // last username entered by the user
    $lastUsername = $authenticationUtils->getLastUsername();

    return $this->render('@PixiekatSymfonyHelpers/user/login.html.twig', [
      'last_username' => $lastUsername,
      'error' => $error,
    ]);
  }
}
