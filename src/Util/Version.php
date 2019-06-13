<?php
namespace Comex\Util;

define('COMEX_VERSION_PAD', 10);

class Version {

  /**
   * Determine if a version is valid
   *
   * @return bool
   */
  public function isValid($ver) {
    $parts = $this->split($ver);
    foreach ($parts as $part) {
      if (!preg_match('/^(z[0-9]+|pre[0-9]+)$/', $part)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Normalize version, producing a code that can be correclty
   * sorted in SQL.
   *
   * @return string
   */
  public function normalize($ver) {
    return implode('-', $this->split($ver));
  }

  /**
   * Clean version number:
   *  - Use consistent delimiter
   *  - Change revision #s (r456) to straight numbers (456)
   *  - Explode into numeric and alphabetic parts
   *  - Make numerals higher than alphas (1.2.0 > 1.2.alpha)
   *
   * @return array of version parts
   */
  public function split($ver, $pad = NULL) {
    if ($pad === NULL) {
      $pad = COMEX_VERSION_PAD;
    }

    $ver = strtolower($ver);
    $ver = preg_replace('/-/', '.', $ver);
    $ver = preg_replace('/^r/', '', $ver);
    $ver .= '.';

    $parts = array();
    $len = strlen($ver);
    $buf = '';
    $state = 'NEW';
    for ($i = 0; $i < $len; $i++) {
      if ($ver{$i} == '.') {
        $newCharType = 'SEP';
      }
      elseif (is_numeric($ver{$i})) {
        $newCharType = 'NUM';
      }
      else {
        $newCharType = 'ALPHA';
      }

      // printf("ver=[%s] state=%-5s newCharType=%-5s char=[%s] parts=[%-12s] buf=[%s]\n", $ver, $state, $newCharType, $ver{$i}, implode('/', $parts), $buf);
      switch ($state) {
        case 'NEW':
          $buf .= $ver{$i};
          $state = $newCharType;
          break;
        case 'NUM':
        case 'ALPHA':
        default:
          if ($state == $newCharType) {
            $buf .= $ver{$i};
          }
          elseif ($newCharType == 'SEP') {
            $parts[] = $buf;
            $buf = '';
            $state = 'NEW';
          }
          elseif ($newCharType == 'NUM') {
            $parts[] = $buf;
            $buf = $ver{$i};
            $state = $newCharType;
          }
          elseif ($newCharType == 'ALPHA') {
            $parts[] = $buf;
            $buf = $ver{$i};
            $state = $newCharType;
          }
          break;
      }
    }

    $codes = array(
      'alpha' => 'pre010',
      'beta' => 'pre020',
      'rc' => 'pre030',
    );
    foreach ($parts as $i => &$part) {
      if (is_numeric($part)) {
        if (strlen($part) > $pad) {
          $part = 'invalid';
        }
        else {
          $part = sprintf("z%0${pad}s", $part);
        }
      }
      else {
        if (isset($codes[$part])) {
          $part = $codes[$part];
        }
      }
    }

    return $parts;
  }

}


