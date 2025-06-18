<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Controller;

use App\Entity as AppEntity;
use App\Repository as AppRepository;
use Doctrine\ORM\EntityManagerInterface;
use Pixiekat\SymfonyHelpers\Form as PixieForms;
use Pixiekat\SymfonyHelpers\Interfaces as PixieInterfaces;
use Pixiekat\SymfonyHelpers\Security as PixieSecurity;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form as SymfonyForm;
use Symfony\Component\Form\Extension\Core\Type as FormTypes;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
    private readonly PixieSecurity\EmailVerifier $emailVerifier,
    private readonly ResetPasswordHelperInterface $resetPasswordHelper,
    private readonly Security $security,
    private readonly TranslatorInterface $translator,
    private readonly UserPasswordHasherInterface $passwordHasher,
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

  #[Route('/logout', name: 'pixiekat_symfony_helpers_logout')]
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

  #[IsGranted(PixieInterfaces\Security\Voter\LoginRegisterVoterInterface::LOGIN_REGISTER_REGISTER_ACCOUNT)]
  #[Route('/user/register', name: 'pixiekat_symfony_helpers_register')]
  public function register(Request $request): Response {
    $user = new AppEntity\User();
    $user->deactivate(); // Ensure the user is active by default
    $user->setCreatedAt(new \DateTimeImmutable());

    $form = $this->createForm(PixieForms\RegistrationForm::class, $user, [
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted()) {
      if ($form->has('cancel') && $form->get('cancel')->isClicked()) {
        return $this->redirectToRoute('pixiekat_symfony_helpers_login');
      }

      $emailField = 'emailAddress';
      $emailAddress = null;
      if ($form->has('emailAddress')) {
        $emailAddress = $form->get('emailAddress')->getData();
      }
      else if ($form->has('email')) {
        $emailField = 'email';
        $emailAddress = $form->get('email')->getData();
      }

      if ($emailAddress) {
        $existingUser = $this->entityManager->getRepository(AppEntity\User::class)->findOneBy([$emailField => $emailAddress]);
        if ($existingUser) {
          $form->get($emailField)->addError(new SymfonyForm\FormError('This email address is already in use.'));
        }

        if (!empty($emailAddress) && method_exists(AppEntity\User::class, 'verifyEmailDomain') && !AppEntity\User::verifyEmailDomain($email)) {
          $invalidEmailDomainError = $this->translator->trans('Registration is currently restricted to the following domains: %domains%', ['%domains%' => implode(', ', AppEntity\User::ALLOWED_REGISTERED_EMAIL_DOMAINS)]);
          if ($form->has($emailField)) {
            $form->get($emailField)->addError(new SymfonyForm\FormError($invalidEmailDomainError));
          }
          else {
            $form->addError(new SymfonyForm\FormError($invalidEmailDomainError));
          }
        }
      }
    }

    if ($form->isSubmitted() && $form->isValid()) {
      try {
        // encode the plain password
        $user->setPassword(
          $this->passwordHasher->hashPassword(
            $user,
            $form->get('password')->getData()
          )
        );

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // generate a signed url and email it to the user
        $this->emailVerifier->sendEmailConfirmation('pixiekat_symfony_helpers_verify_email', $user,
          (new TemplatedEmail())
            ->from('noreply@noreply.com')
            ->to($user->getEmailAddress())
            ->subject('Please Confirm Your Email Address')
            ->htmlTemplate('@PixiekatSymfonyHelpers/user/confirm-email.html.twig')
            ->context([
              'user' => $user,
            ])
        );
        $this->logger->info('User registered: ' . $user->getEmailAddress());
        $this->addFlash('success', 'An email has been sent to verify your email address. Please check your email and click the link to confirm your email address.');
        return $this->redirectToRoute('pixiekat_symfony_helpers_login');
      }
      catch (\Exception $e) {
        $this->logger->error('An error occurred while registering the user: ' . $e->getMessage());
        $this->addFlash('error', 'An error occurred during registration. Please try again: ' . $e->getMessage());
      }
    }

    return $this->render('@PixiekatSymfonyHelpers/user/register.html.twig', [
      'form' => $form->createView(),
    ]);
  }

  #[Route('/user/verify-email', name: 'pixiekat_symfony_helpers_verify_email')]
  public function verifyUserEmail(Request $request, AppRepository\UserRepository $userRepository): Response {
    $id = $request->query->get('id');

    if (null === $id) {
      $this->addFlash('error', 'An error occurred while verifying the email address.');
      $this->logger->error('User not found for email verification: ' . $id);
      return $this->redirectToRoute('pixiekat_symfony_helpers_register');
    }

    $identifier = 'email';
    if (property_exists(AppEntity\User::class, 'emailAddress')) {
      $identifier = 'emailAddress';
    }
    if (property_exists(AppEntity\User::class, 'uuid')) {
      $identifier = $user->getUuid()->__toString();
    }
    $user = $userRepository->findOneBy([$identifier => $id]);

    if (null === $user) {
      $this->addFlash('error', 'An error occurred while verifying the email address.');
      $this->logger->error('User not found for email verification: ' . $id);
      return $this->redirectToRoute('pixiekat_symfony_helpers_register');
    }

    // validate email confirmation link, sets User::isVerified=true and persists
    try {
      $this->emailVerifier->handleEmailConfirmation($request, $user);
    }
    catch (VerifyEmailExceptionInterface $exception) {
      $this->addFlash('error', $this->translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));
      $this->logger->error('An error occurred while verifying the email address: ' . $exception->getReason());

      return $this->redirectToRoute('pixiekat_symfony_helpers_register');
    }

    $this->addFlash('success', 'Your email address has been verified.');
    $this->logger->info('User email address verified: ' . $user->getEmailAddress());

    return $this->redirectToRoute('pixiekat_symfony_helpers_login');
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

    return $this->render('@PixiekatSymfonyHelpers/user/reset-password-request.html.twig', [
      'form' => $form->createView(),
    ]);
  }

  #[Route('/reset/{token}', name: 'pixiekat_symfony_helpers_password_reset_token')]
  #[IsGranted('PUBLIC_ACCESS')]
  public function reset(Request $request, ?string $token = null): Response {
    if ($token) {
      // We store the token in session and remove it from the URL, to avoid the URL being
      // loaded in a browser and potentially leaking the token to 3rd party JavaScript.
      $this->storeTokenInSession($token);

      return $this->redirectToRoute('pixiekat_symfony_helpers_password_reset_token');
    }

    $token = $this->getTokenFromSession();

    if (null === $token) {
      throw $this->createNotFoundException('No reset password token found in the URL or in the session.');
    }

    try {
      $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
    }
    catch (ResetPasswordExceptionInterface $e) {
      $this->addFlash('reset_password_error', sprintf(
        '%s - %s',
        $this->translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_VALIDATE, [], 'ResetPasswordBundle'),
        $this->translator->trans($e->getReason(), [], 'ResetPasswordBundle')
      ));

      return $this->redirectToRoute('pixiekat_symfony_helpers_password_reset');
    }

    // The token is valid; allow the user to change their password.
    $form = $this->createFormBuilder([], ['csrf_protection' => true])
      ->add('plainPassword', RepeatedType::class, [
        'type' => PasswordType::class,
        'options' => [
          'attr' => [
            'autocomplete' => 'new-password',
          ],
        ],
        'first_options' => [
          'constraints' => [
            new NotBlank([
              'message' => 'Please enter a password',
            ]),
            new Length([
              'min' => 4,
              'minMessage' => 'Your password should be at least {{ limit }} characters',
              // max length allowed by Symfony for security reasons
              'max' => 4096,
            ]),
          ],
          'label' => 'New password',
        ],
        'second_options' => [
          'label' => 'Repeat Password',
        ],
        'invalid_message' => 'The password fields must match.',
        // Instead of being set onto the object directly,
        // this is read and encoded in the controller
        'mapped' => false,
      ])
      ->add('submit', SubmitType::class, [
        'attr' => ['class' => 'btn btn-primary'],
        'label' => 'Change Password',
      ])
    ;

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      // A password reset token should be used only once, remove it.
      $this->resetPasswordHelper->removeResetRequest($token);

      // Encode(hash) the plain password, and set it.
      $encodedPassword = $this->passwordHasher->hashPassword(
        $user,
        $form->get('plainPassword')->getData()
      );

      $user->setPassword($encodedPassword);
      $this->entityManager->flush();

      // The session is cleaned up after the password has been changed.
      $this->cleanSessionAfterReset();

      return $this->redirectToRoute('user_login');
    }

      return $this->render('@PixiekatSymfonyHelpers/user/reset-password.html.twig', [
        'form' => $form->createView(),
      ]);
  }

  private function processSendingPasswordResetEmail(string $emailFormData): RedirectResponse {
    $user = null;
    $user = $this->entityManager->getRepository(AppEntity\User::class)->findOneBy([
      'emailAddress' => $emailFormData,
    ]);
    if (!$user) {
      $user = $this->entityManager->getRepository(AppEntity\User::class)->findOneBy([
        'email' => $emailFormData,
      ]);
    }

      // Do not reveal whether a user account was found or not.
      if (!$user) {
          return $this->redirectToRoute('pixiekat_symfony_helpers_password_check_email');
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
          $this->logger->error('An error occurred while sending the email: ' . $e->getReason());

          return $this->redirectToRoute('pixiekat_symfony_helpers_password_check_email');
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

      return $this->redirectToRoute('pixiekat_symfony_helpers_password_check_email');
  }

}
