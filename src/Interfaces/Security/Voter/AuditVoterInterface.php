<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Interfaces\Security\Voter;

/**
 * Permission attributes for the audit log.
 *
 * There is no ADD, EDIT or DELETE here, and that is the design. An audit log
 * you can edit is not evidence of anything. The only write operation in the
 * whole subsystem is the scheduled prune, which runs from the console where
 * voters do not apply — so it cannot be reached through the web at all.
 */
interface AuditVoterInterface {

  /** Umbrella permission guarding the audit screens. */
  public const AUDIT_ADMINISTER = 'administer audit log';

  /** May read the log. */
  public const AUDIT_VIEW = 'view audit log';
}
