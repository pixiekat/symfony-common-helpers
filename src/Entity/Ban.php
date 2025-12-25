<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Entity;

use ApiPlatform\Metadata\ApiResource;
use Pixiekat\SymfonyHelpers\Interfaces;
use Pixiekat\SymfonyHelpers\Repository;
use Pixiekat\SymfonyHelpers\Repository\AuditLogRepository;
use Pixiekat\SymfonyHelpers\Traits\Entity as PixieTraits;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'bans')]
#[ORM\Index(name: 'idx_ip_address', columns: ['ip_address'])]
#[ORM\Entity(repositoryClass: Repository\BanRepository::class)]
#[ORM\Cache(
  usage: 'READ_ONLY',
  region: 'default_entity_region'
)]
#[ApiResource]
class Ban implements Interfaces\Entity\BanInterface {
  use PixieTraits\EntityIdTrait;

  #[ORM\Column(type: 'string', length: 255,  nullable: false)]
  private string $ipAddress;

  #[ORM\Column(type: 'datetime_immutable', nullable: true)]
  private ?\DateTimeImmutable $expiresAt = null;

  use PixieTraits\EntityCreatedAtTrait;

  /**
   * The constructor of this audit log.
   */
  public function __construct() {
    $this->setCreatedAt(new \DateTimeImmutable);
  }

  /**
   * Gets the date this ban expires.
   *
   * @return \DateTimeImmutable|null The DateTime object or null if it never expires.
   */
  public function getExpiresAt(): ?\DateTimeImmutable {
    return $this->expiresAt;
  }

  /**
   * Gets the IP address of this ban.
   *
   * @return string The IP address of this ban.
   */
  public function getIpAddress(): string {
    return $this->ipAddress;
  }

  /**
   * Sets the date this ban expires.
   *
   * @param \DateTimeImmutable|null $expiresAt The DateTime object or null if it never expires.
   *
   * @return static The current instantiated instance.
   */
  public function setExpiresAt(?\DateTimeImmutable $expiresAt): static {
    $this->expiresAt = $expiresAt;

    return $this;
  }

  /**
   * Sets the IP address of this ban.
   *
   * @param string $ipAddress The IP address of this ban.
   *
   * @return static The current instantiated instance.
   */
  public function setIpAddress(string $ipAddress): static {
    $this->ipAddress = $ipAddress;

    return $this;
  }
}
