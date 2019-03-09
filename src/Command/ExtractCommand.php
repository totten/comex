<?php
namespace Comex\Command;

use Comex\Util\ComposerJson;
use Comex\Util\Filesystem;
use Comex\Util\ScriptletDir;
use Comex\Util\Xml;
use Comex\Util\Zip;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ExtractCommand extends BaseCommand {

  /**
   * @param string|NULL $name
   */
  public function __construct($name = NULL) {
    $this->fs = new Filesystem();
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('extract')
      ->setDescription('Scan *.zip files. Extract and filter the composer.json+info.xml')
      ->setHelp('Scan web/dist/**.zip files. Extract metadata, augment, and write to web/meta.

Note: 
 - Filters are defined in scriptlet/extract-info and scriptlet/extract-composer.
 - By default, we do not re-extract files from the past. (Use --force to overwrite.)
');
    $this->useOptions(['force', 'web-root', 'web-url']);
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->checkRequiredInputs($input, ['web-root', 'web-url']);
    $webRoot = $input->getOption('web-root');

    $output->writeln("<info>Search <comment>*.zip</comment> for <comment>composer.json</comment> and <comment>info.xml</comment></info>");
    $this->extractMetadata($input, $output, "{$webRoot}dist/", "{$webRoot}meta/", $input->getOption('force'));
  }


  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param string $zipRoot
   *   The directory in which we scan for *.zip
   *   Ex: /var/www/dist
   * @param string $metaRoot
   *   The directory in which we record metadata files.
   *   Ex: /var/www/meta
   * @param bool $force
   */
  protected function extractMetadata(InputInterface $input, OutputInterface $output, $zipRoot, $metaRoot, $force = FALSE) {
    $zipFiles = Finder::create()->in("$zipRoot")->name('*.zip');
    foreach ($zipFiles as $zipFile) {
      /** @var SplFileInfo $zipFile */
      $context = [
        'metaRoot' => $metaRoot,
        'outPrefix' => ($outPrefix = $metaRoot . preg_replace(';\.zip$;', '', $zipFile->getRelativePathname())),
        'outComposerJson' => "$outPrefix/composer.json",
        'outInfoXml' => "$outPrefix/info.xml",
        'webUrl' => $input->getOption('web-url'),
        'webRoot' => $input->getOption('web-root'),
        'zipRoot' => "$zipRoot",
        'zipFile' => "$zipFile",
        'zipUrl' => $input->getOption('web-url') . rtrim($this->fs->makePathRelative("$zipFile", $input->getOption('web-root')), DIRECTORY_SEPARATOR),
      ];

      if (!$force && (file_exists($context['outInfoXml']))) {
        continue;
      }

      $zip = new \ZipArchive();
      $zip->open((string) $zipFile);
      $zipBaseDirs = Zip::findBaseDirs($zip);
      $context['zipIntPrefix'] = $zipIntPrefix = count($zipBaseDirs) === 1 ? trim($zipBaseDirs[0], '/') . '/' : '';

      $content = file_get_contents("zip://$zipFile#{$zipIntPrefix}composer.json");
      if (!empty($content)) {
        $output->writeln("<info>Found <comment>" . basename("$zipFile") . "</comment>. Extract and filter <comment>composer.json</comment></info>");
        $this->putFile($context['outComposerJson'], $this->filterComposerJson($output, $content, $context));
      }

      $content = file_get_contents("zip://$zipFile#{$zipIntPrefix}info.xml");
      if (!empty($content)) {
        $output->writeln("<info>Found <comment>" . basename("$zipFile") . "</comment>. Extract and filter <comment>info.xml</comment></info>");
        $this->putFile($context['outInfoXml'], $this->filterInfoXml($output, $content, $context));
      }
    }
  }

  protected function filterComposerJson(OutputInterface $output, $content, $context) {
    $composerJson = json_decode($content, 1);
    ScriptletDir::create('extract-composer')->run([$output, &$composerJson, $context]);
    return ComposerJson::prettyPrint($composerJson);
  }

  protected function filterInfoXml(OutputInterface $output, $content, $context) {
    /** @var \SimpleXMLElement $infoXml */
    list ($infoXml, $error) = \Comex\Util\Xml::parse($content);
    if ($infoXml === FALSE) {
      throw new \Exception("Failed to parse info XML\n\n$error");
    }

    ScriptletDir::create('extract-info')->run([$output, $infoXml, $context]);
    return Xml::prettyPrint($infoXml);
  }

  protected function putFile($path, $content) {
    $parent = dirname($path);
    if (!is_dir($parent)) {
      if (!mkdir($parent, 0777, TRUE)) {
        throw new \RuntimeException("Failed to mkdir: $parent");
      }
    }
    file_put_contents($path, $content);
  }

}
