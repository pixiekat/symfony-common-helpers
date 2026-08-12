<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pixiekat\SymfonyHelpers\Interfaces\Entity\HelpersUserInterface;
use Pixiekat\SymfonyHelpers\Repository\ResetPasswordRequestRepository;
use Pixiekat\SymfonyHelpers\Traits as PixieTraits;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestTrait;

/**
 * A pending "I forgot my password" request.
 *
 * This used to be re-created by hand in every application because the relation
 * below named App\Entity\User, a class that does not exist from the bundle's
 * point of view. It now points at HelpersUserInterface, which the application
 * resolves to its own user entity — see that interface for the full rationale.
 *
 * @see \Pixiekat\SymfonyHelpers\Interfaces\Entity\HelpersUserInterface
 */
#[ORM\Entity(repositoryClass: ResetPasswordRequestRepository::class)]
#[ORM\Cache(
  usage: 'READ_ONLY',
  region: 'default_entity_region'
)]
class ResetPasswordRequest extends BaseEntity implements ResetPasswordRequestInterface {
  use ResetPasswordRequestTrait;
  use PixieTraits\Entity\EntityIdTrait;

  /**
   * The user who asked for the reset.
   *
   * targetEntity is an INTERFACE on purpose. Doctrine cannot map an interface
   * on its own, but ResolveTargetEntityListener substitutes the application's
   * concrete user class at compile time, driven by the resolve_target_entities
   * config this bundle prepends for you. The resulting schema is unchanged from
   * the old hardcoded mapping (still a user_id join column), so no migration is
   * needed for existing installs.
   */
  #[ORM\ManyToOne(targetEntity: HelpersUserInterface::class)]
  #[ORM\JoinColumn(nullable: false)]
  private ?HelpersUserInterface $user = null;

  /**
   * Constructor.
   *
   * Called by ResetPasswordRequestRepository::createResetPasswordRequest(),
   * which is in turn called by symfonycasts/reset-password-bundle.
   *
   * @param HelpersUserInterface $user The user requesting the reset.
   * @param \DateTimeInterface $expiresAt When the generated token stops working.
   * @param string $selector The public half of the token, used to find this row.
   * @param string $hashedToken The hashed private half, never stored in the clear.
   */
  public function __construct(HelpersUserInterface $user, \DateTimeInterface $expiresAt, string $selector, string $hashedToken) {
    $this->user = $user;
    $this->initialize($expiresAt, $selector, $hashedToken);
  }

  /**
   * Gets the user this reset request belongs to.
   *
   * The SymfonyCasts interface only promises `object` here, but we can be
   * honest about what it actually is, which keeps calling code type-safe.
   *
   * @return HelpersUserInterface The requesting user.
   */
  public function getUser(): HelpersUserInterface {
    return $this->user;
  }
}
