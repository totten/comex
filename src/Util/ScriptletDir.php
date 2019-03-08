<?php
namespace Extpub\Util;

/**
 * Class ScriptletDir
 * @package Extpub\Util
 *
 * A scriptlet directory is a collection of small scripts... organized in...
 * a... directory. Each "*.php" file in the directory contains a callback
 * function, in the form:
 *
 *   return function(...) {};
 *
 * To run all these callbacks, you might say:
 *
 * ScriptletDir::create('myname')->run([$arg1, $arg2, &$alterable]);
 */
class ScriptletDir {

  protected $callbacks = [];

  /**
   * Create and load a scriptlet dir.
   *
   * @param string|array $name
   *   A short symbolic name.
   *   This is used to build a full path to the scriptlet dir.
   * @return ScriptletDir
   */
  public static function create($name) {
    $s = new static();
    $s->load(dirname(dirname(__DIR__)) . '/scriptlet/' . $name);
    return $s;
  }

  /**
   * @param string|array $paths
   * @return $this
   */
  public function load($paths) {
    $paths = (array) $paths;
    foreach ($paths as $path) {
      $files = (array) glob("$path/*.php");
      foreach ($files as $file) {
        $this->callbacks[$file] = require $file;
      }
    }
    uksort($this->callbacks, function($a, $b) {
      return strcmp(basename($a), basename($b));
    });
    return $this;
  }

  /**
   * Invoke all the callbacks.
   *
   * @param array $args
   *   List of arguments to pass to the scriptlets.
   *   Optionally, you may specific items by reference.
   * @return array
   *   List of results from each scriptlet.
   */
  public function run($args) {
    $result = [];
    foreach ($this->callbacks as $file => $cb) {
      $result[$file] = call_user_func_array($cb, $args);
    }
    return $result;
  }

}
