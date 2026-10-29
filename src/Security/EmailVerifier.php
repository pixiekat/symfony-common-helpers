<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Security;

use App\Entity as AppEntity;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class EmailVerifier {
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly LoggerInterface $logger,
    private readonly MailerInterface $mailer,
    private readonly VerifyEmailHelperInterface $verifyEmailHelper,
  ) {  }

  public function sendEmailConfirmation(string $verifyEmailRouteName, AppEntity\User $user, TemplatedEmail $email): void {
    try {

      $identifier = $user->getEmail();
      $signaturePart = "{$user->id()} - {$user->getDisplayName()}";
      if (method_exists($user, 'getUuid')) {
        $signaturePart = $user->getUuid()->__toString();
        $identifier = $user->getUuid()->__toString();
      }
      $this->logger->info('Generated signature part: ' . $signaturePart);
      /**
       * Get a signed Url that can be emailed to a user.
       *
       * @param string $routeName   name of route that will be used to verify users
       * @param string $userId      unique user identifier
       * @param string $userEmail   the email that is being verified
       * @param array  $extraParams any additional parameters (route wildcards or query parameters)
       *                            that will be used when generating the route for
       *                            signed URL
       */
      $signatureComponents = $this->verifyEmailHelper->generateSignature(
        $verifyEmailRouteName,
        $identifier,
        $user->getEmail(),
        ['id' => $identifier]
      );

      $context = $email->getContext();
      $context['signedUrl'] = $signatureComponents->getSignedUrl();
      $context['expiresAtMessageKey'] = $signatureComponents->getExpirationMessageKey();
      $context['expiresAtMessageData'] = $signatureComponents->getExpirationMessageData();

      $email->context($context);

      $this->mailer->send($email);
    }
    catch (\Exception $e) {
      $this->logger->error('Error sending email confirmation: ' . $e->getMessage());
    }
  }

  /**
   * @throws VerifyEmailExceptionInterface
   */
  public function handleEmailConfirmation(Request $request, AppEntity\User $user): void {
    // The identifier MUST be the one the link was signed with, or every link
    // is rejected as "for a different account". Both senders sign with the
    // UUID when the user has one: sendEmailConfirmation() above, and apps'
    // own mailers (e.g. Infosage's RegistrationMailer). This used to try
    // getEmail()/getEmailAddress() FIRST, so for any user with a UUID no
    // verification link could ever succeed. Keep this order in step with
    // sendEmailConfirmation().
    if (method_exists($user, 'getUuid') && $user->getUuid() !== null) {
      $identifier = $user->getUuid()->__toString();
    }
    elseif (method_exists($user, 'getEmail')) {
        $identifier = $user->getEmail();
    }
    elseif (method_exists($user, 'getEmailAddress')) {
         $identifier = $user->getEmailAddress();
    }
    else {
        throw new \LogicException('User entity must have getUuid(), getEmail() or getEmailAddress() method');
    }
    $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, $identifier, $user->getEmailAddress());

    $user->activate(true);

    $this->entityManager->persist($user);
    $this->entityManager->flush();
  }
}
