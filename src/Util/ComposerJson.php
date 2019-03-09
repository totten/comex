<?php
namespace Comex\Util;

class ComposerJson {

  /**
   * @param string $file
   *   File name.
   * @param mixed $default
   *   Default to use if the file does not exist.
   * @return array
   * @throws \Exception
   */
  public static function loadFile($file, $default) {
    if (!file_exists($file)) {
      return $default;
    }

    $j = json_decode(file_get_contents($file), 1);
    if ($j === NULL) {
      throw new \Exception("Failed to parse JSON file: $file");
    }

    return $j;
  }

  protected static function fieldWeight($field) {
    $order = ['name', 'description', 'version', 'type', 'keywords', 'homepage', 'readme', 'time', 'license', 'authors', 'support', 'require', 'require-dev', 'conflict', 'replace', 'provide', 'suggest'];
    $w = array_search($field, $order);
    return sprintf("%04d-%s", $w === FALSE ? 9999 : $w, $field);
  }

  /**
   * @param array $composerJson
   *   The data from a composer.json.
   * @return string
   *   Pretty-printed string representation of $composerJson.
   */
  public static function prettyPrint($composerJson) {
    uksort($composerJson, function($a, $b){
      return strcmp(self::fieldWeight($a), self::fieldWeight($b));
    });

    foreach (self::getPackageListFields() as $elem) {
      if (isset($composerJson[$elem])) {
        ksort($composerJson[$elem]);
      }
    }

    return json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
  }

  /**
   * @return array
   */
  public static function getPackageListFields() {
    return [
      'require',
      'require-dev',
      'provide',
      'replace',
      'suggest',
      'conflict'
    ];
  }

}
