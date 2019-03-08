<?php
namespace Extpub\Command;

use Extpub\GitRepo;
use Extpub\Util\ComposerJson;
use Extpub\Util\Filesystem;
use Extpub\Util\Xml;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class ExtReconcileCommand extends BaseCommand {

  const VENDOR = 'cxt';

  /**
   * @var Filesystem
   */
  var $fs;

  /**
   * @param string|NULL $name
   */
  public function __construct($name = NULL) {
    $this->fs = new Filesystem();
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('ext:reconcile')
      ->setDescription('Given an extension, reconcile info.xml and composer.json.')
      ->setHelp('Given an extension, reconcile info.xml and composer.json.

  Example: extpub ext:reconcile /var/www/ext/myextension
      ')
      ->addArgument('ext', InputArgument::REQUIRED, 'Full path to the extension source code');
    $this->useOptions(['dry-run']);
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $ext = rtrim($input->getArgument('ext'), DIRECTORY_SEPARATOR);
    if (!is_dir($ext)) {
      throw new \Exception("Directory not found: " . $input->getArgument('ext'));
    }
    if (!file_exists($ext . '/info.xml')) {
      throw new \Exception("info.xml not found: " . $input->getArgument('ext'));
    }

    $composerJsonFile = $ext . '/composer.json';
    $infoXmlFile = $ext . '/info.xml';

    $composerJson = ComposerJson::loadFile($composerJsonFile, []);
    /** @var \SimpleXMLElement $infoXml */
    list ($infoXml, $error) = \Extpub\Util\Xml::parse(file_get_contents($infoXmlFile));
    if ($infoXml === FALSE) {
      throw new \Exception("Failed to parse info XML\n\n$error");
    }

    $this->convertName_info2composer($output, $infoXml, $composerJson);
    $this->convertDescription_info2composer($output, $infoXml, $composerJson);
    $this->convertRequires_info2composer($output, $infoXml, $composerJson);
    $this->convertRequires_composer2info($output, $composerJson, $infoXml);

    if ($input->getOption('dry-run')) {
      $output->writeln("<info>Write <comment>composer.json</comment>. (Dry Run)</info>");
      $output->writeln(ComposerJson::prettyPrint($composerJson));

      $output->writeln("<info>Write <comment>info.xml</comment>. (Dry Run)</info>");
      $output->writeln(Xml::prettyPrint($infoXml));
    }
    else {
      $output->writeln("<info>Write <comment>composer.json</comment>.</info>");
      file_put_contents($composerJsonFile, ComposerJson::prettyPrint($composerJson));

      $output->writeln("<info>Write <comment>info.xml</comment>.</info>");
      file_put_contents($infoXmlFile, Xml::prettyPrint($infoXml));
    }
  }

  /**
   * @param string $key
   *   Ex: 'org.civicrm.foobar'
   * @return string
   *   Ex: 'cxt/org.civicrm.foobar'
   */
  protected function xmlKeyToComposerPkg($key) {
    if (!preg_match('/^[a-z0-9\._\-]+$/', $key)) {
      throw new \RuntimeException("Malformed key: $key");
    }
    return self::VENDOR . '/' . strtolower($key);
  }

  /**
   * @param string $pkg
   *   Ex: 'cxt/org.civicrm.foobar'
   *   Ex: 'symfony/console'
   * @return string|NULL
   *   Ex: 'org.civicrm.foobar'
   *   Ex: NULL
   */
  protected function composerPkgToXmlKey($pkg) {
    list ($vendor, $name) = explode('/', $pkg);
    if ($vendor !== self::VENDOR) {
      throw new \RuntimeException("Cannot convert package ($pkg) to extension key");
    }
    if (!preg_match('/^[a-z0-9\._\-]+$/', $name)) {
      throw new \RuntimeException("Malformed key: $name");
    }
    return $name;
  }

  /**
   * @param string $pkg
   *   Ex: 'cxt/org.civicrm.foobar'
   * @return bool
   */
  protected function isExtPkg($pkg) {
    return strpos($pkg, self::VENDOR . '/') === 0;
  }

  /**
   * @param \SimpleXMLElement $infoXml
   * @param array $composerJson
   */
  protected function convertName_info2composer(OutputInterface $output, $infoXml, &$composerJson) {
    $key = (string) $infoXml->attributes()->key;
    if (empty($key)) {
      throw new \RuntimeException("info.xml does not specify a key");
    }
    $pkg = $this->xmlKeyToComposerPkg($key);

    if (empty($composerJson['name'])) {
      $composerJson['name'] = $pkg;
    }
    elseif ($composerJson['name'] === $pkg) {
      return; // OK
    }
    else {
      throw new \Exception("Names do not match: $key (info.xml) vs {$composerJson['name']} (composer.json)");
    }
  }

  /**
   * @param \SimpleXMLElement $infoXml
   * @param array $composerJson
   */
  protected function convertDescription_info2composer(OutputInterface $output, $infoXml, &$composerJson) {
    $desc = (string) $infoXml->description;
    if (empty($desc)) {
      $desc = '';
    }

    if (!isset($composerJson['description'])) {
      $output->writeln("<info>In <comment>composer.json</comment>, add description.</info>", OutputInterface::VERBOSITY_VERBOSE);
      $composerJson['description'] = $desc;
    }
  }

  /**
   * @param \SimpleXMLElement $infoXml
   * @param array $composerJson
   */
  protected function convertRequires_info2composer(OutputInterface $output, $infoXml, &$composerJson) {
    foreach ($infoXml->requires->ext as $ext) {
      $pkg = $this->xmlKeyToComposerPkg((string) $ext);
      $tgtVer = empty($ext['version']) ? '*' : (string) $ext['version'];
      if (!isset($composerJson['requires'][$pkg])) {
        $output->writeln("<info>In <comment>composer.json</comment>, add requirement <comment>$pkg</comment>:<comment>$tgtVer</comment>.</info>", OutputInterface::VERBOSITY_VERBOSE);
        $composerJson['require'][$pkg] = $tgtVer;
      }
    }
  }

  /**
   * @param array $composerJson
   * @param \SimpleXMLElement $infoXml
   */
  protected function convertRequires_composer2info(OutputInterface $output, $composerJson, $infoXml) {
    if (empty($composerJson['require'])) {
      return;
    }
    foreach ($composerJson['require'] as $pkg => $ver) {
      if (!$this->isExtPkg($pkg)) {
        continue;
      }
      $ext = $this->composerPkgToXmlKey($pkg);
      if (!$infoXml->xpath("requires[ext=\"$ext\"]")) {
        $output->writeln("<info>In <comment>info.xml</comment>, add requirement <comment>$ext</comment>.</info>", OutputInterface::VERBOSITY_VERBOSE);
        $extXml = $infoXml->requires->addChild('ext', $ext);
        if ($ver !== '*') {
          $extXml->addAttribute('version', $ver);
        }
      }
    }
  }
}
