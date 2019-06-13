<?php
namespace Comex;

use Doctrine\DBAL\Configuration;

class Config {

  /**
   * @return array
   */
  public static function loadConfig() {
    $config = [];
    foreach (['config.php', 'config.php.local'] as $file) {
      $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . $file;
      if (file_exists($file)) {
        $config = array_merge($config, require $file);
      }
    }
    return $config;
  }

}