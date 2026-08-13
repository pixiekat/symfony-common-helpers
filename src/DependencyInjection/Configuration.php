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
        // Each feature the bundle ships can be switched off by an application
        // that does not want it. Turning one off does three things at once:
        // its entry disappears from the control panel menu, its voter denies
        // every attribute (so the routes answer 403 rather than staying quietly
        // reachable to anyone who guesses the URL), and nothing has to be
        // remembered in two places.
        //
        // Entities and migrations are deliberately NOT affected. A disabled
        // feature keeps its tables: switching something off for a while should
        // not throw away the data, and a migration that ran conditionally would
        // make every consumer's schema a different shape.
        ->arrayNode('features')
          ->addDefaultsIfNotSet()
          ->info('Switches for the features this bundle ships. A disabled feature is hidden from the admin menu and its voter denies access.')
          ->children()
            ->booleanNode('audit')->defaultTrue()->info('The audit log and its viewer.')->end()
            ->booleanNode('bans')->defaultTrue()->info('IP bans.')->end()
            ->booleanNode('blocks')->defaultTrue()->info('Blocks and block items.')->end()
            ->booleanNode('shoutbox')->defaultTrue()->info('The shoutbox.')->end()
            ->booleanNode('taxonomy')->defaultTrue()->info('Vocabularies and terms.')->end()
          ->end()
        ->end()
        ->scalarNode('admin_layout')
          ->defaultValue('@PixiekatSymfonyHelpers/admin/cp_layout.html.twig')
          ->cannotBeEmpty()
          ->info('The template every admin screen in this bundle extends. Point it at your own branding layer — the one that sets admin_nav_sections and admin_brand.')
          ->example('@Lumina/admin/layout.html.twig')
        ->end()
      ->end()
    ;

    return $treeBuilder;
  }
}
