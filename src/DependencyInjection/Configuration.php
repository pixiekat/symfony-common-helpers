<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration tree for the bundle.
 *
 * Everything here is optional and defaulted, so an application that does
 * nothing at all still boots — the defaults describe the standard Symfony
 * layout that `make:user` produces.
 *
 * Configure in config/packages/symfony_helpers.yaml:
 *
 *   symfony_helpers:
 *     user_class: App\Entity\User
 *
 * The alias `symfony_helpers` is derived by Symfony from the extension class
 * name (SymfonyHelpersExtension → symfony_helpers).
 */
class Configuration implements ConfigurationInterface {

  /**
   * {@inheritdoc}
   */
  public function getConfigTreeBuilder(): TreeBuilder {
    $treeBuilder = new TreeBuilder('symfony_helpers');
    $rootNode = $treeBuilder->getRootNode();

    $rootNode
      ->children()
        ->scalarNode('user_class')
          ->defaultValue('App\\Entity\\User')
          ->cannotBeEmpty()
          ->info('The application\'s user entity. Bundle entities map their user relations against HelpersUserInterface, and this is the concrete class Doctrine resolves that interface to.')
          ->example('App\\Entity\\Member')
        ->end()
        ->booleanNode('resolve_target_entities')
          ->defaultTrue()
          ->info('Whether the bundle should write the doctrine.orm.resolve_target_entities mapping for you. Turn this off only if you want to declare it by hand.')
        ->end()
      ->end()
    ;

    return $treeBuilder;
  }
}
