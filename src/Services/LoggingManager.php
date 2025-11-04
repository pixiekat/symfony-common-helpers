<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Services;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class LoggingManager {
  public function __construct(
    private readonly ContainerInterface $container,
    private readonly LoggerInterface $logger,
    private readonly array $availableChannels
  ) {}

  public function logDefault(string $level, string $message, array $context = []): void {
    $this->logger->log($level, $message, $context);
  }

  public function logToChannel(string $channel, string $level, string $message, array $context = []): void {
    try {
      /** @var LoggerInterface $logger */
      $logger = $this->container->get("monolog.logger.$channel");
      $logger->log($level, $message, $context);
    } catch (ServiceNotFoundException) {
      $this->logger->log($level, "[fallback:$channel] $message", $context);
    }
  }

  public function getAvailableChannels(): array {
    return $this->availableChannels;
  }
}
