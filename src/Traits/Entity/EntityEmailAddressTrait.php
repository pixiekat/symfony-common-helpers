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

  /**
   * {@inheritdoc}
   */
  public function getEmail(): ?string {
    return $this->getEmailAddress();
  }

  /**
   * {@inheritdoc}
   */
  public function setEmail(string $email): static {
    $this->setEmailAddress($email);

    return $this;
  }

}
