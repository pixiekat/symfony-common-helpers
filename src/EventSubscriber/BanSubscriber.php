<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\EventSubscriber;

use Psr\Log\LoggerInterface;
use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Interfaces;
use Pixiekat\SymfonyHelpers\Repository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;

class BanSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly Interfaces\Services\BanManagerInterface $banManager,
    private readonly EntityManagerInterface $entityManager,
    private readonly LoggerInterface $logger,
    private readonly Environment $twig,
  ) {}

  public function onRequestEvent(RequestEvent $event): void {
    $request = $event->getRequest();

    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    // Allow internal pages, like WDT and Profiler
    if (str_starts_with($request->getPathInfo(), '/_')) {
      return;
    }

    $ipAddress = $request->getClientIp();
    if ($ipAddress === null) {
      $this->logger->debug('No IP address found in the request.');
      return;
    }

    // Check if the IP address is banned
    $isBanned = $this->banManager->findIpBan($ipAddress);
    if ($isBanned) {
      $this->logBannedIp($ipAddress);
      $content = $this->twig->render('@PixiekatSymfonyHelpers/ban/banned.html.twig', [
        'ipAddress' => $ipAddress,
      ]);
      $response = new Response($content, 403);
      $event->setResponse($response);
    }

  }

  public static function getSubscribedEvents(): array {
    return [
        RequestEvent::class => 'onRequestEvent',
    ];
  }

  /**
   * Logs the banned IP address.
   *
   * @param string $ipAddress The IP address to log.
   */
  private function logBannedIp(string $ipAddress): void {
    $this->logger->warning('Blocked request from banned IP address: ' . $ipAddress);
  }
}
