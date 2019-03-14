<?php
namespace Comex\Util;

class Xml {

  /**
   * @param string $file
   *   File path.
   * @return \SimpleXMLElement
   * @throws \Exception
   *   An exception if the file is missing or malformed.
   */
  public static function parseFile($file) {
    if (!file_exists($file)) {
      throw new \Exception("File not found: $file");
    }
    return self::parse(file_get_contents($file));
  }

  /**
   * Read a well-formed XML file
   *
   * @param string $content
   *   Raw XML content
   * @return \SimpleXMLElement
   * @throws \Exception
   *   An exception if the file is malformed.
   */
  public static function parse($content) {
    list ($xml, $error) = static::parseSafely($content);
    if ($xml === FALSE) {
      throw new \Exception("Failed to parse info XML\n\n$error");
    }
    return $xml;
  }

  /**
   * Read a well-formed XML file
   *
   * @param $string
   *
   * @return array
   *   (0 => SimpleXMLElement|FALSE, 1 => errorMessage|FALSE)
   */
  public static function parseSafely($string) {
    $xml = FALSE; // SimpleXMLElement
    $error = FALSE; // string

    $oldLibXMLErrors = libxml_use_internal_errors();
    libxml_use_internal_errors(TRUE);

    $xml = simplexml_load_string($string,
      'SimpleXMLElement', LIBXML_NOCDATA
    );
    if ($xml === FALSE) {
      $error = self::formatErrors(libxml_get_errors());
    }

    libxml_use_internal_errors($oldLibXMLErrors);

    return array($xml, $error);
  }

  /**
   * @param $errors
   *
   * @return string
   */
  protected static function formatErrors($errors) {
    $messages = array();

    foreach ($errors as $error) {
      if ($error->level != LIBXML_ERR_ERROR && $error->level != LIBXML_ERR_FATAL) {
        continue;
      }

      $parts = array();
      if ($error->file) {
        $parts[] = "File=$error->file";
      }
      $parts[] = "Line=$error->line";
      $parts[] = "Column=$error->column";
      $parts[] = "Code=$error->code";

      $messages[] = implode(" ", $parts) . ": " . trim($error->message);
    }

    return implode("\n", $messages);
  }

  /**
   * Produce a pretty-printed version of a Simple XML document.
   *
   * @param \SimpleXMLElement $simpleXml
   * @return string
   */
  public static function prettyPrint($simpleXml) {
    $dom = new \DOMDocument("1.0");
    $dom->preserveWhiteSpace = FALSE;
    $dom->formatOutput = TRUE;
    $dom->loadXML($simpleXml->asXML());
    return $dom->saveXML();
  }

}
