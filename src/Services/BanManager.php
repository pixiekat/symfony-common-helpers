<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Services;

use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Interfaces;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

class BanManager implements Interfaces\Services\BanManagerInterface {

  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly LoggerInterface $logger,
  ) {  }

  /**
   * {@inheritdoc}
   *
   * @todo: Add support for CIDR blocks.
   */
  public function findIpBan(string $ipAddress): ?Entity\Ban {

    if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
      $this->logger->error('Invalid IP address format: ' . $ipAddress);
      return null;
    }

    // Get the repository for the Ban entity
    $repository = $this->entityManager->getRepository(Entity\Ban::class);

    // Check if if the cidr 32 type is banned first
    $ipAddresses = [];
    $ipAddresses[] = $ipAddress;

    foreach ([
      Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_8,
      Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_16,
      Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_24,
    ] as $cidrType) {
      switch ($cidrType) {
        case Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_8:
          $ipAddresses[] = substr($ipAddress, 0, strpos($ipAddress, '.') + 1);
          break;

        case Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_16:
          $ipAddresses[] = substr($ipAddress, 0, strpos($ipAddress, '.') + 2);
          break;

        case Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_24:
          $ipAddresses[] = substr($ipAddress, 0, strrpos($ipAddress, '.') + 1);
          break;

        default:
          throw new \InvalidArgumentException('Invalid CIDR type');
      }
    }

    $ipAddresses = array_unique($ipAddresses);
    $ban = $repository->findIfIpBanned($ipAddresses);
    // if $ban is not null, it means the IP address is banned
    if ($ban) {
      $this->logger->debug('Found IP address ' . $ipAddress . ' in the database.');
      return $ban;
    }

    // Clearly not banned or can't find a ban.
    return null;
  }

  protected function checkCidr($ip, $cidrType) {
    $ip = rtrim($ip, '.'); // Remove trailing dot if present
    $parts = explode('.', $ip); // Split IP address into parts
    $length = count($parts);
    switch ($length) {
      case 1:
        return Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_8;
        break;
      case 2:
        return Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_16;
        break;
      case 3:
        return Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_24;
        break;
      case 4:
        return Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_32;
        break;
      default:
        $this->logger->warning('Invalid IP address format: ' . $ip);
    }
    return false;
  }

}
