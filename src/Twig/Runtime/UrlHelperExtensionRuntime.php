<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

class UrlHelperExtensionRuntime implements RuntimeExtensionInterface {
  public function __construct() {  }

  public function generateNerdFontIcon(string $iconName) {
    return "<i class='nf nf-fa-{$iconName}'></i>";
  }

  public function generateUrl(string $url, string $title, bool $external = true) {
    if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
      $url = "http://" . $url;
    }

    $parts = [];
    $parts[] = "href='{$url}'";
    if ($external) {
      $parts[] = "rel='external'";
      $parts[] = "target='_blank'";
    }

    return "<a " . implode(" ", $parts) . ">{$title}</a>";
  }
}
