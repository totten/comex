<?php
use \Symfony\Component\Console\Output\OutputInterface;
use \Extpub\Util\Naming;

/**
 * Map the "requires" from composer.json to info.xml
 */
return function(OutputInterface $output, SimpleXMLElement $infoXml, &$composerJson) {
  if (empty($composerJson['require'])) {
    return;
  }

  $infoRequires = \Extpub\Util\InfoXml::getRequires($infoXml);

  foreach ($composerJson['require'] as $pkg => $ver) {
    if (!Naming::isExtPkg($pkg)) {
      continue;
    }

    $ext = Naming::composerPkgToXmlKey($pkg);
    if (empty($infoRequires[$ext])) {
      $output->writeln("<info>In <comment>info.xml</comment>, add requirement <comment>$ext</comment>.</info>", OutputInterface::VERBOSITY_VERBOSE);
      $infoRequires[$ext] = ($ver === '*') ? '' : $ver;
    }
  }

  \Extpub\Util\InfoXml::setRequires($infoXml, $infoRequires);
};
