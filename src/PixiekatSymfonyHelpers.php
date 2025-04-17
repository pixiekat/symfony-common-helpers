<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Pixiekat\SymfonyHelpers\DependencyInjection\SymfonyHelpersExtension;

class PixiekatSymfonyHelpers extends AbstractBundle {
  public function getPath(): string {
      return \dirname(__DIR__);
  }

  public function getContainerExtension(): ?ExtensionInterface {
    return new SymfonyHelpersExtension();
  }
}
