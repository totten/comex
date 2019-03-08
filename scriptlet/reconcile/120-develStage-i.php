<?php
use \Symfony\Component\Console\Output\OutputInterface;

/**
 * Extract alpha/beta info from version.
 */
return function(OutputInterface $output, SimpleXMLElement $infoXml, &$composerJson) {
  $version = (string) $infoXml->version;
  if (preg_match('/(alpha|beta)/', $version, $m)) {
    $infoXml->develStage = $m[1];
  }
  else {
    $infoXml->develStage = 'stable';
  }
};
