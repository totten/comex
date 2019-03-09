<?php
use \Symfony\Component\Console\Output\OutputInterface;
use \Comex\Util\Naming;

/**
 * In "composer.json", evaluate variables of the form "{{info.version}}" or "{{info.description}}".
 */
return function(OutputInterface $output, SimpleXMLElement $infoXml, &$composerJson) {
  $vars = [
    '{{info.version}}' => (string) $infoXml->version,
    '{{info.description}}' => (string) $infoXml->description,
    '{{info.key}}' => (string) $infoXml->attributes()->key,
    '{{info.mappedKey}}' => Naming::xmlKeyToComposerPkg((string) $infoXml->attributes()->key),
    '{{info.license}}' => (string) $infoXml->attributes()->license,
  ];

  $output->writeln("<info>In <comment>composer.json</comment>, evaluate any <comment>\{\{info.*\}\}</comment> variables.</info>", OutputInterface::VERBOSITY_VERBOSE);

  $newComposerJson = [];
  foreach ($composerJson as $field => $value) {
    if (is_string($value)) {
      $newComposerJson[strtr($field, $vars)] = strtr($value, $vars);
    }
    elseif (is_array($value)) {
      $newList = [];
      foreach ($composerJson[$field] as $subField => $subFieldValue) {
        $newList[strtr($subField, $vars)] = strtr($subFieldValue, $vars);
      }
      $newComposerJson[$field] = $newList;
    }
  }
  $composerJson = $newComposerJson;

};
