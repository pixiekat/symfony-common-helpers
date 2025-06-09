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
    $identifier = $user->getEmail();
    if (method_exists($user, 'getUuid')) {
      $identifier = $user->getUuid()->__toString();
    }
    $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, $identifier, $user->getEmailAddress());

    $user->activate(true);

    $this->entityManager->persist($user);
    $this->entityManager->flush();
  }
}
