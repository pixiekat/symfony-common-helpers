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

    $rel = null;
    if ($external) {
      $rel = " rel='external'";
    }

    return "<a href='{$url}'{$rel}>{$title}</a>";
  }
}
