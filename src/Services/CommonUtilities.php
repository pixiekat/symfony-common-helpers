<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Services;

use App\Entity;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Interfaces\Services as AppServices;
use Pixiekat\SymfonyHelpers\Services\AuditLogManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;


class CommonUtilities {

  public static function toCamelCase($string) {
    return preg_replace_callback('/(?:^|_| )(.?)/',
      function($matches) {
          return strtoupper($matches[1]);
      }, $string);
  }
  public static function toSnakeCase($string) {
    return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($string)));
  }
}
