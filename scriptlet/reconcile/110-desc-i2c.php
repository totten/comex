<?php
use \Symfony\Component\Console\Output\OutputInterface;

/**
 * Map "description" from "info.xml" to "composer.json".
 */
return function(OutputInterface $output, SimpleXMLElement $infoXml, &$composerJson) {
  $desc = (string) $infoXml->description;
  if (empty($desc)) {
    $desc = '';
  }

  if (!isset($composerJson['description'])) {
    $output->writeln("<info>In <comment>composer.json</comment>, add description.</info>", OutputInterface::VERBOSITY_VERBOSE);
    $composerJson['description'] = $desc;
  }
};
