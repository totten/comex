<?php
namespace Extpub\Util;

class Zip {

  /**
   * Given a zip file, find all directory names in the root
   *
   * @param \ZipArchive $zip
   *
   * @return array(string)
   *   no trailing /
   */
  public static function findBaseDirs(\ZipArchive $zip) {
    $cnt = $zip->numFiles;
    $basedirs = array();

    for ($i = 0; $i < $cnt; $i++) {
      $filename = $zip->getNameIndex($i);
      if (preg_match('/^[^\/]+\/$/', $filename) && $filename != './' && $filename != '../') {
        $basedirs[] = rtrim($filename, '/');
      }
    }

    return $basedirs;
  }

}
