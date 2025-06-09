<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Controller;

use App\Entity;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type as FormTypes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class UserController extends AbstractController {
  use ResetPasswordControllerTrait;

  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly LoggerInterface $logger,
    private readonly MailerInterface $mailer,
    private readonly Security $security,
    private readonly TranslatorInterface $translator,
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

  #[Route('/logout', name: 'user_logout')]
  #[IsGranted('PUBLIC_ACCESS')]
  public function logout(AuthenticationUtils $authenticationUtils): Response {
    try {
      $user = $authenticationUtils->getLastAuthenticatedUser();
      $this->logger->info('User logged out.', ['user' => $user->getDisplayName()]);
    }
    catch (\Exception $e) {
      $this->logger->info('User logged out.');
    }

    return $this->redirectToRoute('<front>');
  }

  #[Route('/user/check-email', name: 'pixiekat_symfony_helpers_password_check_email')]
  public function checkEmail(): Response {
    // Generate a fake token if the user does not exist or someone hit this page directly.
    // This prevents exposing whether or not a user was found with the given email address or not
    if (null === ($resetToken = $this->getTokenObjectFromSession())) {
      $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
    }

    return $this->render('@PixiekatSymfonyHelpers/user/check-email.html.twig', [
      'resetToken' => $resetToken,
    ]);
  }

  #[IsGranted('PUBLIC_ACCESS')]
  #[Route('/user/reset-password', name: 'pixiekat_symfony_helpers_password_reset')]
  public function request(Request $request): Response {

    $form = $this->createFormBuilder([], ['csrf_protection' => true])
      ->add('emailAddress', FormTypes\EmailType::class, [
        'attr' => ['autocomplete' => 'email'],
        'constraints' => [
          new Assert\NotBlank([
            'message' => 'Please enter your email',
          ]),
        ],
      ])
      ->add('submit', FormTypes\SubmitType::class, [
        'label' => 'Send Password Reset Link',
      ])
      ->getForm()
    ;

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      return $this->processSendingPasswordResetEmail(
        $form->get('emailAddress')->getData(),
      );
    }

    return $this->render('@PixiekatSymfonyHelpers/user/reset-password.html.twig', [
      'form' => $form->createView(),
    ]);
  }

  private function processSendingPasswordResetEmail(string $emailFormData): RedirectResponse {
    $user = null;
    $user = $this->entityManager->getRepository(Entity\User::class)->findOneBy([
      'emailAddress' => $emailFormData,
    ]);
    if (!$user) {
      $user = $this->entityManager->getRepository(Entity\User::class)->findOneBy([
        'email' => $emailFormData,
      ]);
    }

      // Do not reveal whether a user account was found or not.
      if (!$user) {
          return $this->redirectToRoute('app_check_email');
      }

      try {
          $resetToken = $this->resetPasswordHelper->generateResetToken($user);
      } catch (ResetPasswordExceptionInterface $e) {
          // If you want to tell the user why a reset email was not sent, uncomment
          // the lines below and change the redirect to 'app_forgot_password_request'.
          // Caution: This may reveal if a user is registered or not.
          //
          // $this->addFlash('reset_password_error', sprintf(
          //     '%s - %s',
          //     $translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_HANDLE, [], 'ResetPasswordBundle'),
          //     $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
          // ));
          $this->addFlash('error', 'An error occurred. Please try again later.');
          $this->appUtilities->getAppLogger()->error('An error occurred while sending the email: ' . $e->getReason());

          return $this->redirectToRoute('app_check_email');
      }

      $email = (new TemplatedEmail())
          ->from('BIDMCMedicineHMSappts@bidmc.harvard.edu')
          ->to($user->getEmailAddress())
          ->subject('Your password reset request')
          ->htmlTemplate('reset_password/email.html.twig')
          ->context([
              'resetToken' => $resetToken,
              'user' => $user,
          ])
      ;

      $mailer->send($email);

      // Store the token object in session for retrieval in check-email route.
      $this->setTokenObjectInSession($resetToken);

      return $this->redirectToRoute('app_check_email');
  }

}
