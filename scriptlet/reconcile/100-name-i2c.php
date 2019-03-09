<?php
use \Symfony\Component\Console\Output\OutputInterface;
use \Comex\Util\Naming;

/**
 * Map info.xml's "key" ("org.example.foo") to composer.json's "name" ("comex/org.example.foo").
 */
return function (OutputInterface $output, SimpleXMLElement $infoXml, &$composerJson) {

  $key = (string) $infoXml->attributes()->key;
  if (empty($key)) {
    throw new \RuntimeException("info.xml does not specify a key");
  }
  $pkg = Naming::xmlKeyToComposerPkg($key);

  if (empty($composerJson['name']) || $composerJson['name'] === $pkg || $composerJson['name'] === '{{info.mappedKey}}') {
    $output->writeln("<info>In <comment>composer.json</comment>, set <comment>name</comment> and <comment>version</comment> to match.</info>", OutputInterface::VERBOSITY_VERBOSE);
    $composerJson['name'] = '{{info.mappedKey}}';
    $composerJson['version'] = '{{info.version}}';
    return;
  }

  foreach (['provide', 'replace'] as $elem) {
    if (isset($composerJson[$elem]) && in_array($pkg, $composerJson[$elem])) {
      $output->writeln("<info>In <comment>composer.json</comment>, the name <comment>$pkg</comment> is already set by <comment>$elem</comment>.</info>", OutputInterface::VERBOSITY_VERBOSE);
      return; // OK
    }
  }

  // Main goal: setup an alias
  if (!isset($composerJson['replace'])) {
    $composerJson['replace'] = [];
  }
  $composerJson['replace'][$pkg] = '{{info.version}}';

  // Secondarily: if they omitted version (regardless of whether we're aliased),
  // the version should be filled in.
  if (!isset($composerJson['version'])) {
    $composerJson['version'] = '{{info.version}}';
  }
};
