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
    $config = $this->processConfiguration(
      new Configuration(),
      $container->getExtensionConfig($this->getAlias()),
    );

    // Parameters are set HERE and not in load(), because another bundle's
    // load() may need them and load order is not ours to control. TwigBundle is
    // registered before this one in bundles.php, so twig's load() resolves
    // %symfony_helpers.admin_layout% in the globals we prepend below — and it
    // does that before this extension's load() ever runs. Setting them during
    // prepend(), which the kernel calls for every bundle before ANY load(),
    // removes the ordering question entirely.
    $this->setParameters($container, $config);

    $this->prependMonologChannels($container);
    $this->prependTwigGlobals($container, $config);

    // Nothing to do if Doctrine is not installed. The taxonomy, logging and
    // utility helpers work fine without it, so this must not become a hard
    // requirement of using the bundle at all.
    if (!$container->hasExtension('doctrine')) {
      return;
    }

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
   * Publishes the admin layout name as a Twig global.
   *
   * Every admin template in this bundle does `{% extends admin_layout %}`, so
   * the value has to be available before anything else in the template runs —
   * which rules out setting it per-controller and makes a global the right
   * shape. It is a plain string from a container parameter, so unlike the menu
   * there is no work being done eagerly.
   *
   * Why a variable extends instead of a template override: overriding a bundle
   * template requires templates/bundles/<Bundle>/…, which resolves against the
   * PROJECT directory and is therefore available to applications only. A bundle
   * (Lumina) cannot use it, and would otherwise have to fight for the
   * @PixiekatSymfonyHelpers namespace via twig.paths, where the winner depends
   * on bundle registration order. Configuration works identically for both:
   *
   *   # an application, in config/packages/symfony_helpers.yaml
   *   symfony_helpers:
   *     admin_layout: 'admin/layout.html.twig'
   *
   *   # a bundle, from its own extension's prepend()
   *   $container->prependExtensionConfig('symfony_helpers', [
   *     'admin_layout' => '@Lumina/admin/layout.html.twig',
   *   ]);
   *
   * The cost is that lint:twig cannot follow a variable extends, so a typo in
   * the layout name surfaces at render time rather than at lint time.
   *
   * The value is passed in from the processed config and inlined rather than
   * referenced as %symfony_helpers.admin_layout% — see escapeTwigGlobal() for
   * why that distinction matters.
   *
   * @param ContainerBuilder $container The container being built.
   * @param array $config The processed configuration.
   *
   * @return void
   */
  private function prependTwigGlobals(ContainerBuilder $container, array $config): void {
    if (!$container->hasExtension('twig')) {
      return;
    }

    $container->prependExtensionConfig('twig', [
      'globals' => [
        'admin_layout' => $this->escapeTwigGlobal((string) $config['admin_layout']),
      ],
    ]);
  }

  /**
   * Escapes a value so twig.globals treats it as text, not a service.
   *
   * ── THE COLLISION ──────────────────────────────────────────────────────────
   * TwigBundle normalises any global whose value starts with '@' into a SERVICE
   * REFERENCE — that is how `globals: { mailer: '@app.mailer' }` works. Twig's
   * own namespaced template names also start with '@'. So the perfectly
   * reasonable:
   *
   *     symfony_helpers:
   *       admin_layout: '@LuminaUi/admin/layout.html.twig'
   *
   * is read as "the service LuminaUi/admin/layout.html.twig", and the container
   * fails with:
   *
   *     The service "twig" has a dependency on a non-existent service
   *     "LuminaUi/admin/layout.html.twig".
   *
   * TwigBundle's documented escape for a literal leading '@' is to double it,
   * so '@@Lumina/...' arrives in the template as '@Lumina/...'. Doing that here
   * rather than asking every consumer to write '@@' means a bundle template
   * name can be configured exactly as it is written everywhere else — which is
   * the only spelling anyone would think to try.
   *
   * Note this is also why the value is inlined rather than passed as
   * '%symfony_helpers.admin_layout%': parameter placeholders in extension
   * config are resolved BEFORE the extension's Configuration normalises it, so
   * the placeholder would expand to a leading '@' and hit the same trap with
   * nowhere left to intervene.
   *
   * @param string $value The configured template name.
   *
   * @return string The value, safe to hand to twig.globals.
   */
  private function escapeTwigGlobal(string $value): string {
    return str_starts_with($value, '@') ? '@' . $value : $value;
  }

  /**
   * {@inheritdoc}
   */
  /**
   * Publishes the resolved configuration as container parameters.
   *
   * Called from prepend() rather than load() — see the note there about bundle
   * load order. Kept as its own method so the reason lives in one place instead
   * of being implied by where the calls happen to sit.
   *
   * @param ContainerBuilder $container The container being built.
   * @param array $config The processed configuration.
   *
   * @return void
   */
  private function setParameters(ContainerBuilder $container, array $config): void {
    // Lets bundle services that need the concrete user class (registration
    // forms, user CRUD) be handed it rather than importing App\Entity\User.
    $container->setParameter('symfony_helpers.user_class', $config['user_class']);

    // Consumed by FeatureChecker, which gates both the voters and the admin menu.
    $container->setParameter('symfony_helpers.features', $config['features']);

    // Referenced by the admin_layout Twig global.
    $container->setParameter('symfony_helpers.admin_layout', $config['admin_layout']);
  }

  /**
   * {@inheritdoc}
   */
  public function load(array $configs, ContainerBuilder $container): void {

    $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
    $loader->load('services.yml');
  }
}
