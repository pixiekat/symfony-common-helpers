<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Voter;

trait VoterCRUDTrait {

  /**
   * Allows the user to delete the entry.
   */
  public const DELETE = 'delete';

  /**
   * Allows the user to insert a new entry.
   */
  public const INSERT = 'insert';

  /**
   * Allows the user to update the entry.
   */
  public const UPDATE = 'update';

  /**
   * Allows the user to view the entry.
   */
  public const VIEW = 'view';

}
