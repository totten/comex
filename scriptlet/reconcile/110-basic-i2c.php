<?php
use \Symfony\Component\Console\Output\OutputInterface;

/**
 * Map some simple 1-1 fields from "info.xml" to "composer.json" -- "description", "license"
 */
return function(OutputInterface $output, SimpleXMLElement $infoXml, &$composerJson) {
  $map = [
    'description' => 'description',
    'license' => 'license',
    'version' => 'version',
  ];
  foreach ($map as $iField => $cField) {
    $value = (string) $infoXml->{$iField};
    if (empty($value)) {
      $value = '';
    }

    if (!isset($composerJson[$cField])) {
      $output->writeln("<info>In <comment>composer.json</comment>, add <comment>$cField</comment>.</info>", OutputInterface::VERBOSITY_VERBOSE);
      $composerJson[$cField] = $value;
    }
  }

};
