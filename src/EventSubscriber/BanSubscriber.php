<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\EventSubscriber;

use Psr\Log\LoggerInterface;
use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Repository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;

class BanSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly LoggerInterface $logger,
    private readonly Environment $twig,
  ) {}

  public function onRequestEvent(RequestEvent $event): void {
    $request = $event->getRequest();
    $ipAddress = $request->getClientIp();
    if ($ipAddress === null) {
      $this->logger->debug('No IP address found in the request.');
      return;
    }

    // Check if the IP address is banned
    if ($this->isBanned($ipAddress)) {
      $this->logger->warning('Blocked request from banned IP address: ' . $ipAddress);
      $bannedMsg = $this->twig->render('@PixiekatSymfonyHelpers/ban/banned.html.twig', [
        'ipAddress' => $ipAddress,
      ]);
      $response = new Response($bannedMsg, 403);
      $event->setResponse($response);
    }

  }

  public static function getSubscribedEvents(): array {
    return [
        RequestEvent::class => 'onRequestEvent',
    ];
  }

  private function isBanned(string $ipAddress): bool {
    $repository = $this->entityManager->getRepository(Entity\Ban::class);
    $isBanned = $repository->findIfIpBanned($ipAddress);
    return $isBanned !== null ?? false;
  }
}
