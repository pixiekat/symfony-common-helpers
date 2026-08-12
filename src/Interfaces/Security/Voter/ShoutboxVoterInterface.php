<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Interfaces\Security\Voter;

/**
 * Permission attributes for the shoutbox.
 *
 * SHOUT_POST is separated from the moderation permissions on purpose: it is the
 * one attribute an anonymous visitor is normally granted, and keeping it
 * distinct means "who may shout" is a single, obvious decision in ShoutboxVoter
 * rather than something tangled up with the admin checks.
 */
interface ShoutboxVoterInterface {

  /** Umbrella permission guarding the moderation screens. */
  public const SHOUTBOX_ADMINISTER = 'administer shoutbox';

  /** May post a shout. Granted to everyone by default. */
  public const SHOUT_POST = 'post shout';

  public const SHOUT_EDIT = 'edit shout';
  public const SHOUT_DELETE = 'delete shout';
  public const SHOUT_LIST = 'list shouts';
  public const SHOUT_MODERATE = 'moderate shout';
}
