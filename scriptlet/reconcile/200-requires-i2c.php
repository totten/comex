<?php
use \Symfony\Component\Console\Output\OutputInterface;
use \Comex\Util\Naming;

/**
 * Map the "requires" from "info.xml" to "composer.json".
 */
return function(OutputInterface $output, SimpleXMLElement $infoXml, &$composerJson) {
  $infoRequires = \Comex\Util\InfoXml::getRequires($infoXml);
  foreach ($infoRequires as $extKey => $extVer) {
    $pkg = Naming::xmlKeyToComposerPkg($extKey);
    $tgtVer = empty($extVer) ? '*' : $extVer;
    if (!isset($composerJson['require'][$pkg])) {
      $output->writeln("<info>In <comment>composer.json</comment>, add requirement <comment>$pkg</comment>:<comment>$tgtVer</comment>.</info>", OutputInterface::VERBOSITY_VERBOSE);
      $composerJson['require'][$pkg] = $tgtVer;
    }
  }
};
