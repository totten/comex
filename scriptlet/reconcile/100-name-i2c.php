<?php
use \Symfony\Component\Console\Output\OutputInterface;
use \Extpub\Util\Naming;

/**
 * Map info.xml's "key" ("org.example.foo") to composer.json's "name" ("cxt/org.example.foo").
 */
return function(OutputInterface $output, SimpleXMLElement $infoXml, &$composerJson) {

  $key = (string) $infoXml->attributes()->key;
  if (empty($key)) {
    throw new \RuntimeException("info.xml does not specify a key");
  }
  $pkg = Naming::xmlKeyToComposerPkg($key);

  if (empty($composerJson['name'])) {
    $composerJson['name'] = $pkg;
  }
  elseif ($composerJson['name'] === $pkg) {
    return; // OK
  }
  else {
    throw new \Exception("Names do not match: $key (info.xml) vs {$composerJson['name']} (composer.json)");
  }

};
