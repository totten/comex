<?php
namespace Extpub\Util;


class Naming {

  const VENDOR = 'cxt';

  /**
   * @param string $key
   *   Ex: 'org.civicrm.foobar'
   * @return string
   *   Ex: 'cxt/org.civicrm.foobar'
   */
  public static function xmlKeyToComposerPkg($key) {
    if (!preg_match('/^[a-z0-9\._\-]+$/', $key)) {
      throw new \RuntimeException("Malformed key: $key");
    }
    return self::VENDOR . '/' . strtolower($key);
  }

  /**
   * @param string $pkg
   *   Ex: 'cxt/org.civicrm.foobar'
   *   Ex: 'symfony/console'
   * @return string|NULL
   *   Ex: 'org.civicrm.foobar'
   *   Ex: NULL
   */
  public static function composerPkgToXmlKey($pkg) {
    list ($vendor, $name) = explode('/', $pkg);
    if ($vendor !== self::VENDOR) {
      throw new \RuntimeException("Cannot convert package ($pkg) to extension key");
    }
    if (!preg_match('/^[a-z0-9\._\-]+$/', $name)) {
      throw new \RuntimeException("Malformed key: $name");
    }
    return $name;
  }

  /**
   * @param string $pkg
   *   Ex: 'cxt/org.civicrm.foobar'
   * @return bool
   */
  public static function isExtPkg($pkg) {
    return strpos($pkg, self::VENDOR . '/') === 0;
  }

}
