<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Interfaces\Security\Voter;

interface BanVoterInterface {

  public const BAN_ADD_BAN = 'add ban';
  public const BAN_EDIT_BAN = 'edit ban';
  public const BAN_LIST_BANS = 'list bans';
  public const BAN_REMOVE_BAN = 'remove ban';
  public const BAN_VIEW_BAN = 'view ban';
}
