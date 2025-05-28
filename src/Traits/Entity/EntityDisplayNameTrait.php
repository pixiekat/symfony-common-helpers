<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

trait EntityDisplayNameTrait {

  public function getDisplayName($includeTitle = true): ?string {
    $name = [];
    if (method_exists($this, 'getFirstName')) {
      $name[] = $this->getFirstName();
    }
    if (method_exists($this, 'getMiddleName')) {
      $name[] = $this->getMiddleName();
    }
    if (method_exists($this, 'getLastName')) {
      $name[] = $this->getLastName();
    }

    if (!empty($name)) {
      return implode(' ', $name);
    }
    return $this->getEmailAddress();
  }

}
