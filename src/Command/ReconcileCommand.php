<?php
namespace Comex\Command;

use Comex\Util\ComposerJson;
use Comex\Util\Filesystem;
use Comex\Util\ScriptletDir;
use Comex\Util\Xml;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class ReconcileCommand extends BaseCommand {

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
      ->setName('reconcile')
      ->setDescription('Given an extension, reconcile info.xml and composer.json.')
      ->setHelp('Given an extension, reconcile info.xml and composer.json.

  Example: comex ext:reconcile /var/www/ext/myextension
      ')
      ->addArgument('ext', InputArgument::REQUIRED, 'Full path to the extension source code');
    $this->useOptions(['dry-run', 'ver']);
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
    list ($infoXml, $error) = \Comex\Util\Xml::parse(file_get_contents($infoXmlFile));
    if ($infoXml === FALSE) {
      throw new \Exception("Failed to parse info XML\n\n$error");
    }

    if ($input->getOption('ver')) {
      $this->setField($infoXml, 'version', $input->getOption('ver'));
    }

    ScriptletDir::create('reconcile')->run([$output, $infoXml, &$composerJson]);

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

  protected function setField($info, $fieldXpath, $value) {
    $elements = $info->xpath($fieldXpath);
    if (empty($elements)) {
      throw new \RuntimeException("Error: Path (" . $fieldXpath . ") did not match any elements.");
    }
    foreach ($elements as $element) {
      $element->{0} = $value;
    }
  }

}
