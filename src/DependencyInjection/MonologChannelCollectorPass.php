<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MonologChannelCollectorPass implements CompilerPassInterface {
  public function process(ContainerBuilder $container): void {
    $channels = [];

    foreach ($container->findTaggedServiceIds('monolog.logger') as $id => $tags) {
      $definition = $container->getDefinition($id);

      // Skip if no arguments or argument 0 is missing
      if (!$definition->getArguments() || !array_key_exists(0, $definition->getArguments())) {
        continue;
      }

      $channelArg = $definition->getArgument(0);

      // Skip unresolved or non-string arguments
      if ($channelArg instanceof AbstractArgument || !is_string($channelArg)) {
        continue;
      }

      $channels[] = $channelArg;
    }

    $container->setParameter('pixiekat.logging.available_channels', array_unique($channels));
  }
}
