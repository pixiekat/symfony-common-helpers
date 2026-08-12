<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\DependencyInjection;

use Pixiekat\SymfonyHelpers\Interfaces\Entity\HelpersUserInterface;
use Pixiekat\SymfonyHelpers\Services\AuditLogManager;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Wires the bundle into the application container.
 *
 * Two jobs:
 *   load()    — register the bundle's own services and parameters.
 *   prepend() — contribute configuration to OTHER bundles before they load,
 *               which is how the Doctrine interface resolution gets set up
 *               without hand-editing doctrine.yaml in every project.
 */
class SymfonyHelpersExtension extends Extension implements PrependExtensionInterface {

  /**
   * Teaches Doctrine which concrete class HelpersUserInterface stands for.
   *
   * HOW PREPEND WORKS
   * -----------------
   * Symfony calls every bundle's prepend() before any extension's load(), and
   * whatever a bundle prepends is merged in UNDERNEATH the application's own
   * configuration. So this writes a sensible default that the app can still
   * override in its own doctrine.yaml — exactly the behaviour you want from an
   * install-time convenience: it works out of the box and it never fights you.
   *
   * The effect is identical to writing this by hand:
   *
   *   doctrine:
   *     orm:
   *       resolve_target_entities:
   *         Pixiekat\SymfonyHelpers\Interfaces\Entity\HelpersUserInterface: App\Entity\User
   *
   * @param ContainerBuilder $container The container being built.
   *
   * @throws \LogicException If the configured user class is missing or does not
   *   implement the interface — both would otherwise surface much later as an
   *   opaque Doctrine mapping error a long way from the actual cause.
   */
  public function prepend(ContainerBuilder $container): void {
    $this->prependMonologChannels($container);

    // Nothing to do if Doctrine is not installed. The taxonomy, logging and
    // utility helpers work fine without it, so this must not become a hard
    // requirement of using the bundle at all.
    if (!$container->hasExtension('doctrine')) {
      return;
    }

    $config = $this->processConfiguration(
      new Configuration(),
      $container->getExtensionConfig($this->getAlias()),
    );

    if (!$config['resolve_target_entities']) {
      return;
    }

    $userClass = $config['user_class'];

    if (!class_exists($userClass)) {
      throw new \LogicException(sprintf(
        'The user class "%s" configured for the Pixiekat Symfony Helpers bundle does not exist. '
        . 'Set symfony_helpers.user_class in config/packages/symfony_helpers.yaml to your own user entity, '
        . 'or set symfony_helpers.resolve_target_entities to false if this application has no user entity.',
        $userClass,
      ));
    }

    if (!is_subclass_of($userClass, HelpersUserInterface::class)) {
      throw new \LogicException(sprintf(
        'The user class "%s" must implement %s so bundle entities can map their user relations against it. '
        . 'Add that interface to the class — it extends Symfony\'s UserInterface, so your entity almost '
        . 'certainly satisfies it already apart from the declaration itself.',
        $userClass,
        HelpersUserInterface::class,
      ));
    }

    $container->prependExtensionConfig('doctrine', [
      'orm' => [
        'resolve_target_entities' => [
          HelpersUserInterface::class => $userClass,
        ],
      ],
    ]);
  }

  /**
   * Registers the Monolog channels this bundle logs to.
   *
   * AuditLogManager mirrors every entry to an 'audit' channel so the log file
   * carries a record that no failing transaction can take back. That channel
   * has to exist, and asking every consuming application to remember to declare
   * it is exactly the kind of setup step that gets skipped — after which the
   * audit trail quietly falls back to the default logger and mixes in with
   * everything else.
   *
   * Prepended config merges UNDERNEATH the application's own, so an app that
   * declares its own channels list keeps it; this only ensures 'audit' is
   * present. Route it wherever you like from there:
   *
   *   monolog:
   *     handlers:
   *       audit:
   *         type: rotating_file
   *         path: '%kernel.logs_dir%/audit.log'
   *         level: info
   *         channels: ['audit']
   *         max_files: 90
   *
   * MonologChannelCollectorPass will then pick it up, so it also shows in
   * LoggingManager::getAvailableChannels().
   *
   * @param ContainerBuilder $container The container being built.
   *
   * @return void
   */
  private function prependMonologChannels(ContainerBuilder $container): void {
    if (!$container->hasExtension('monolog')) {
      // No Monolog: LoggingManager already falls back to the default logger
      // when a channel is missing, so the audit mirror still gets written —
      // just not to a channel of its own.
      return;
    }

    $container->prependExtensionConfig('monolog', [
      'channels' => [AuditLogManager::CHANNEL],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function load(array $configs, ContainerBuilder $container): void {
    $config = $this->processConfiguration(new Configuration(), $configs);

    // Exposed as a parameter so bundle services needing the concrete class
    // (registration forms, user CRUD) can be handed it rather than importing
    // App\Entity\User the way they currently do.
    $container->setParameter('symfony_helpers.user_class', $config['user_class']);

    $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
    $loader->load('services.yml');
  }
}
