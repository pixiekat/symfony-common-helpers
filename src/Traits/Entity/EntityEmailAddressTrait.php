<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Entity;

trait EntityEmailAddressTrait {

  /**
   * {@inheritdoc}
   */
  public function getEmailAddress(): ?string {
    if (property_exists($this, 'emailAddress')) {
      return $this->emailAddress;
    }
    else if (property_exists($this, 'email')) {
      return $this->email;
    }
    else {
      throw new \RuntimeException('No property to get email address');
    }
    return null;
  }

  /**
   * {@inheritdoc}
   */
  public function setEmailAddress(string $emailAddress): static {
    if (property_exists($this, 'email')) {
      $this->email = $emailAddress;
    }
    else if (property_exists($this, 'emailAddress')) {
      $this->emailAddress = $emailAddress;
    }
    else {
      throw new \RuntimeException('No property to set email address');
    }

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
