<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Interfaces\Entity;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Marker interface for "whatever this application calls its user entity".
 *
 * WHY THIS EXISTS
 * ---------------
 * A reusable bundle must never reference App\Entity\User — that class only
 * exists inside the consuming application, so any bundle that names it can only
 * ever be installed into an app that happens to have it, spelled exactly that
 * way. That coupling is why a ResetPasswordRequest entity had to be re-created
 * by hand in every new project.
 *
 * Instead, bundle entities declare their relations against THIS interface, and
 * the application tells Doctrine which real class the interface stands for:
 *
 *   # config/packages/doctrine.yaml (written for you by the bundle — see
 *   # SymfonyHelpersExtension::prepend())
 *   doctrine:
 *     orm:
 *       resolve_target_entities:
 *         Pixiekat\SymfonyHelpers\Interfaces\Entity\HelpersUserInterface: App\Entity\User
 *
 * At container-compile time Doctrine's ResolveTargetEntityListener rewrites the
 * mapping metadata, swapping the interface for the concrete class. The database
 * schema is identical to what you'd get by naming App\Entity\User directly —
 * the join column is still derived from the property name (user_id) — so this
 * is a source-level change only and needs no migration.
 *
 * WHY NOT MAP Symfony's UserInterface DIRECTLY
 * --------------------------------------------
 * resolve_target_entities is a single global interface => class map. If this
 * bundle claimed Symfony\Component\Security\Core\User\UserInterface, it would
 * be claiming it for every other bundle in the application too, and an app with
 * two user entities (say Customer and Administrator) could never express that.
 * Owning our own narrower interface keeps the bundle a good citizen.
 *
 * USAGE
 * -----
 * In the consuming application:
 *
 *   class User implements HelpersUserInterface, PasswordAuthenticatedUserInterface {
 *     // ...your existing entity, unchanged...
 *   }
 *
 * Extending UserInterface means anything typed against this interface also gets
 * getUserIdentifier(), getRoles() and eraseCredentials() for free, so bundle
 * services can do real work with it rather than passing an opaque `object`
 * around (which is what the old signatures were reduced to).
 *
 * @see \Pixiekat\SymfonyHelpers\Entity\ResetPasswordRequest
 * @see \Pixiekat\SymfonyHelpers\DependencyInjection\SymfonyHelpersExtension::prepend()
 */
interface HelpersUserInterface extends UserInterface {

  /**
   * Gets the primary key of this user.
   *
   * Declared here because bundle code (audit logging, ban lookups, block
   * visibility) frequently needs a stable scalar handle on the user, and every
   * entity in this library already provides it via EntityIdTrait.
   *
   * @return int|null The id, or null if the entity has not been persisted yet.
   */
  public function getId(): ?int;
}
