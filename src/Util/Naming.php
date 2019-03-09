<?php
namespace Extpub\Util;

/**
 * Class Naming
 * @package Extpub\Util
 *
 * Helpers for manipulating package/extension names.
 */
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
   * @param string $key
   *   Ex: org.civicrm.foobar
   * @return string
   *   Ex: foobar
   *   Note: This is *NOT* the same as the short-name, so don't use it in ways
   *   that needs to be matched-up. Rather, if you're using some other key
   *   that provides uniqueness, then this can be mixed-in to make it a
   *   bit more legible.
   */
  public static function xmlKeyToHeuristicShortName($key) {
    $parts = explode('.', $key);
    return array_pop($parts);
  }

  /**
   * @param string $pkg
   *   Ex: 'cxt/org.civicrm.foobar'
   * @return bool
   */
  public static function isExtPkg($pkg) {
    return strpos($pkg, self::VENDOR . '/') === 0;
  }

  /**
   * @param string $key
   * @return bool
   *   TRUE if $key is a well-formed extension key.
   */
  public static function isValidKey($key) {
    return preg_match(';^[a-z][a-z0-9\.\-_]*$;', $key)
      && (strpos($key, '..') === FALSE);
  }

}
