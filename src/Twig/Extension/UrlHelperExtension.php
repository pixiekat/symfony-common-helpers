<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Twig\Extension;

use Pixiekat\SymfonyHelpers\Twig\Runtime\UrlHelperExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class UrlHelperExtension extends AbstractExtension {
  public function getFilters(): array {
    return [];
  }

  public function getFunctions(): array {
    return [
      new TwigFunction('generate_link', [UrlHelperExtensionRuntime::class, 'generateUrl'], [
        'is_safe' => ['html'],
      ]),
    ];
  }
}
