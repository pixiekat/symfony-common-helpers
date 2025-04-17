<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

trait EntityEmailAddressTrait {

  public function getEmailAddress(): ?string {
    return $this->emailAddress;
  }

  public function setEmailAddress(string $emailAddress): static {
    $this->emailAddress = $emailAddress;

    return $this;
  }

}
