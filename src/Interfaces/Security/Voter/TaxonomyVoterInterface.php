<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Interfaces\Security\Voter;

interface TaxonomyVoterInterface {

  public const TAXONOMY_ADMINISTER = 'administer taxonomy';
  public const TAXONOMY_ADD_TERM = 'add term';
  public const TAXONOMY_EDIT_TERM = 'edit term';
  public const TAXONOMY_LIST_TERMS = 'list terms';
  public const TAXONOMY_REMOVE_TERM = 'remove term';
  public const TAXONOMY_VIEW_TERM = 'view term';
  public const TAXONOMY_ADD_TERM_VOCABULARY = 'add term vocabulary';
  public const TAXONOMY_EDIT_TERM_VOCABULARY = 'edit term vocabulary';
  public const TAXONOMY_LIST_TERM_VOCABULARIES = 'list term vocabularies';
  public const TAXONOMY_REMOVE_TERM_VOCABULARY = 'remove term vocabulary';
  public const TAXONOMY_VIEW_TERM_VOCABULARY = 'view term vocabulary';
}
