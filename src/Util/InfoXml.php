<?php
namespace Comex\Util;

class InfoXml {

  /**
   * @param \SimpleXMLElement $infoXml
   *   Ex: '<extension><requires><ext version="4.3">org.civicrm.foo</ext><ext>org.civicrm.bar</ext></requires></extension>'
   * @return array
   *   Ex: ['org.civicrm.foo' => '4.3', 'org.civicrm.bar' => '']
   */
  public static function getRequires(\SimpleXMLElement $infoXml) {
    if (!$infoXml->requires->ext) {
      return [];
    }

    $requires = [];
    foreach ($infoXml->requires->ext as $extXml) {
      $extKey = (string) $extXml;
      $version = (string) $extXml['version'];
      $requires[$extKey] = $version;
    }
    ksort($requires);
    return $requires;
  }

  /**
   * @param \SimpleXMLElement $infoXml
   *   Ex: '<extension><requires>...</requires></extension>'
   * @param array $requires
   *   Ex: ['org.civicrm.foo' => '4.3', 'org.civicrm.bar' => '']
   */
  public static function setRequires(\SimpleXMLElement $infoXml, $requires) {
    if ($infoXml->requires) {
      unset($infoXml->requires);
    }

    ksort($requires);

    $requiresXml = $infoXml->addChild('requires');
    foreach ($requires as $key => $version) {
      $extXml = $requiresXml->addChild('ext', $key);
      if ($version) {
        $extXml['version'] = $version;
      }
    }
  }

}
