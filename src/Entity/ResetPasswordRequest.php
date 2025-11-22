<?php

namespace Pixiekat\SymfonyHelpers\Entity;

use App\Entity as AppEntity;
use Pixiekat\SymfonyHelpers\Repository\ResetPasswordRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use Pixiekat\SymfonyHelpers\Traits as PixieTraits;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestTrait;

#[ORM\Entity(repositoryClass: ResetPasswordRequestRepository::class)]
#[ORM\Cache(
  usage: 'READ_ONLY',
  region: 'default_entity_region'
)]
class ResetPasswordRequest extends BaseEntity implements ResetPasswordRequestInterface {
  use ResetPasswordRequestTrait;
  use PixieTraits\Entity\EntityIdTrait;

  #[ORM\ManyToOne]
  #[ORM\JoinColumn(nullable: false)]
  private ?AppEntity\User $user = null;

  public function __construct(object $user, \DateTimeInterface $expiresAt, string $selector, string $hashedToken) {
    $this->user = $user;
    $this->initialize($expiresAt, $selector, $hashedToken);
  }

  public function getUser(): object {
    return $this->user;
  }
}
