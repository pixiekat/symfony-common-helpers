<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

class UrlHelperExtensionRuntime implements RuntimeExtensionInterface {
  public function __construct() {  }

  public function generateNerdFontIcon(string $iconName) {
    return "<i class='nf nf-fa-{$iconName}'></i>";
  }

    /**
     * Given a URL, return the subdomain, domain, and TLD as a string.
     *
     * @param string $url The URL to parse.
     * @param bool $includeSubdomain Whether to include the subdomain in the output.
     * @param int $subdomainDepth The depth of the subdomain to include (0 means include all subdomains, 1 means include only the last subdomain, etc.).
     *
     * @return string The subdomain, domain, and TLD as a string.
     */
    public function getDomainAndTldFromUrl(string $url, bool $includeSubdomain = true, int $subdomainDepth = 0): string {
        // parse the URL and extract the host
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return '';
        }

        // split the host into parts
        $parts = explode('.', $host);
        $numParts = count($parts);

        // if there are less than 2 parts, return the host as is
        if ($numParts < 2) {
            return $host;
        }

        // get the domain and TLD
        $domain = $parts[$numParts - 2];
        $tld = $parts[$numParts - 1];

        // get the subdomain if requested
        $subdomain = '';
        if ($includeSubdomain && $numParts > 2) {
            $subdomainParts = array_slice($parts, 0, $numParts - 2);
            if ($subdomainDepth > 0) {
                $subdomainParts = array_slice($subdomainParts, -$subdomainDepth);
            }
            $subdomain = implode('.', $subdomainParts);
        }

        // return the domain and TLD
        return $subdomain ? "$subdomain.$domain.$tld" : "$domain.$tld";
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
