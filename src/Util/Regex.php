<?php

namespace Comex\Util;

class Regex {

  /**
   * @param array $array
   *   Ex: ['ab', 'cd']
   * @param $delim
   *   Ex: '/'
   * @return string
   *   Ex: '(ab|cd)'
   */
  public static function quotedOr($array, $delim) {
    return '('
    . implode('|', array_map(function ($s) use ($delim) {
      return preg_quote($s, $delim);
    }, $array))
    . ')';
  }

}
