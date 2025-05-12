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

    // split by cidr type
    foreach ([
      //Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_8,
      //Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_16,
      //Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_24,
      Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_32,
    ] as $cidrType) {
      switch ($cidrType) {
        case Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_8:
          $ipAddress = substr($ipAddress, 0, strpos($ipAddress, '.') + 1);
          break;

        case Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_16:
          $ipAddress = substr($ipAddress, 0, strpos($ipAddress, '.') + 1);
          break;

        case Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_24:
          $ipAddress = substr($ipAddress, 0, strrpos($ipAddress, '.') + 1);
          break;

        case Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_32:
          // No change needed
          break;

        default:
          throw new \InvalidArgumentException('Invalid CIDR type');
      }

      if ($cidrType == Interfaces\Services\BanManagerInterface::BAN_MANAGER_CIDR_32) {
        $ban = $repository->findIfIpBanned($ipAddress);
      }
      else {
        $ban = $repository->findIfIpBannedByCidrType($ipAddress, $cidrType);
      }

      if ($ban) {
        return $ban;
      }
    }


    // Clearly not banned or can't find a ban.
    return null;
  }

}
