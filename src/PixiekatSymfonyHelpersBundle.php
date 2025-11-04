<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers;

use Pixiekat\SymfonyHelpers\DependencyInjection\MonologChannelCollectorPass;
use Pixiekat\SymfonyHelpers\DependencyInjection\SymfonyHelpersExtension;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class PixiekatSymfonyHelpersBundle extends AbstractBundle {

  public function build(ContainerBuilder $container): void {
    parent::build($container);
    $container->addCompilerPass(new MonologChannelCollectorPass());
  }

  public function getPath(): string {
      return \dirname(__DIR__);
  }

  public function getContainerExtension(): ?ExtensionInterface {
    return new SymfonyHelpersExtension();
  }
}
