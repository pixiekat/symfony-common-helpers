<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Interfaces\Security\Voter;

/**
 * Permission attributes for the block system.
 *
 * Constants rather than bare strings so templates can write
 * `is_granted(constant('...BlockVoterInterface::BLOCK_EDIT'), block)` and get a
 * hard failure on a typo instead of a silently denied permission.
 *
 * BaseVoter::getAttributes() reflects over these constants to decide what the
 * voter supports, so adding a permission here is genuinely all that is needed.
 */
interface BlockVoterInterface {

  /** Umbrella permission guarding the whole admin section. */
  public const BLOCK_ADMINISTER = 'administer blocks';

  public const BLOCK_ADD = 'add block';
  public const BLOCK_EDIT = 'edit block';
  public const BLOCK_DELETE = 'delete block';
  public const BLOCK_LIST = 'list blocks';
  public const BLOCK_VIEW = 'view block';

  public const BLOCK_ITEM_ADD = 'add block item';
  public const BLOCK_ITEM_EDIT = 'edit block item';
  public const BLOCK_ITEM_DELETE = 'delete block item';
  public const BLOCK_ITEM_LIST = 'list block items';
}
