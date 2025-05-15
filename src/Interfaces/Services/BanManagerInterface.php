<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Interfaces\Services;

interface BanManagerInterface {

  /**
   * Block 8 CIDR.
   */
  public const BAN_MANAGER_CIDR_8 = 8;

  /**
   * Block 16 CIDR.
   */
  public const BAN_MANAGER_CIDR_16 = 16;

  /**
   * Block 24 CIDR.
   */
  public const BAN_MANAGER_CIDR_24 = 24;

  /**
   * Block 32 CIDR.
   */
  public const BAN_MANAGER_CIDR_32 = 32;
}
