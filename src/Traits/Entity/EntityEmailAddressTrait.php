<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

trait EntityEmailAddressTrait {

  /**
   * {@inheritdoc}
   */
  public function getEmailAddress(): ?string {
    return $this->emailAddress;
  }

  /**
   * {@inheritdoc}
   */
  public function setEmailAddress(string $emailAddress): static {
    $this->emailAddress = $emailAddress;

    return $this;
  }

}
