<?php
use \Symfony\Component\Console\Output\OutputInterface;
use \Extpub\Util\Naming;

/**
 * Map the "requires" from "info.xml" to "composer.json".
 */
return function(OutputInterface $output, SimpleXMLElement $infoXml, &$composerJson) {
  foreach ($infoXml->requires->ext as $ext) {
    $pkg = Naming::xmlKeyToComposerPkg((string) $ext);
    $tgtVer = empty($ext['version']) ? '*' : (string) $ext['version'];
    if (!isset($composerJson['requires'][$pkg])) {
      $output->writeln("<info>In <comment>composer.json</comment>, add requirement <comment>$pkg</comment>:<comment>$tgtVer</comment>.</info>", OutputInterface::VERBOSITY_VERBOSE);
      $composerJson['require'][$pkg] = $tgtVer;
    }
  }
};
