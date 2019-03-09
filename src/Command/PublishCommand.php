<?php
namespace Extpub\Command;

use Extpub\Util\ComposerJson;
use Extpub\Util\Filesystem;
use Extpub\Util\ScriptletDir;
use Extpub\Util\Xml;
use Extpub\Util\Zip;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class PublishCommand extends BaseCommand {

  /**
   * @param string|NULL $name
   */
  public function __construct($name = NULL) {
    $this->fs = new Filesystem();
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('publish')
      ->setDescription('Scan the distribution tree and compile a list of packages')
      ->setHelp('Scan the distribution tree and compile a list of packages

This command has two phases:

1. Extraction: Scan web/dist/**.zip for composer.json and info.xml files.
   Filter the content and write to web/meta/
2. Aggregation: Scan web/meta/**/composer.json. Combine to form packages.json.
');
    $this->useOptions(['force', 'web-root', 'web-url']);
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    if (empty($input->getOption('web-root')) || empty($input->getOption('web-url'))) {
      throw new \Exception("Both --web-root and --web-url are required parameters.");
    }

    $webRoot = $input->getOption('web-root');

    $output->writeln("<info>Search <comment>*.zip</comment> for <comment>composer.json</comment> and <comment>info.xml</comment></info>");
    $this->extractMetadata($input, $output, "{$webRoot}dist/", "{$webRoot}meta/", $input->getOption('force'));

    $output->writeln("<info>Aggregate <comment>*.composer.json</comment></info>");
    $this->compileMasterIndex($output,
      Finder::create()->in("$webRoot/meta")->name('composer.json'),
      "{$webRoot}packages.json");
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param Finder $finder
   *   List of composer.json files.
   * @param string $packagesJsonFile
   */
  protected function compileMasterIndex(OutputInterface $output, $finder, $packagesJsonFile) {
    // TODO:: Splitting/batching (by folder, name, or date) so that checksums are more stable and cache-friendly...
    $pkgs = $this->readComposerJsonList($finder);
    $packagesJson = ['packages' => $this->indexPackages($pkgs)];
    $output->writeln("<info>Write <comment>$packagesJsonFile</comment></info>");
    $this->putFile($packagesJsonFile, json_encode($packagesJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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
        $output->writeln("<info>Extract <comment>" . basename("$zipFile") . "</comment> > <comment>composer.json</comment></info>", OutputInterface::VERBOSITY_VERBOSE);
        $this->putFile($context['outComposerJson'], $this->filterComposerJson($output, $content, $context));
      }

      $content = file_get_contents("zip://$zipFile#{$zipIntPrefix}info.xml");
      if (!empty($content)) {
        $output->writeln("<info>Extract <comment>" . basename("$zipFile") . "</comment> > <comment>info.xml</comment></info>", OutputInterface::VERBOSITY_VERBOSE);
        $this->putFile($context['outInfoXml'], $this->filterInfoXml($output, $content, $context));
      }
    }
  }

  /**
   * Parse all the matching JSON files.
   *
   * @param \Symfony\Component\Finder\Finder $f
   * @return array
   *   Aggregated data from all the JSON files.
   * @throws \Exception
   */
  protected function readComposerJsonList(Finder $f) {
    $pkgs = [];
    foreach ($f->files() as $file) {
      /** @var SplFileInfo $file */
      $pkg = json_decode($file->getContents(), 1);
      if ($pkg === NULL) {
        throw new \Exception("Malformatted JSON file: $file");
      }
      $pkgs[] = $pkg;
    }
    usort($pkgs, function ($a, $b) {
      $aKey = $a['name'] . ';;' . $a['version'];
      $bKey = $b['name'] . ';;' . $b['version'];
      return strcmp($aKey, $bKey);
    });
    return $pkgs;
  }

  /**
   * @param array $pkgs
   *   List of packages. (Each item is a composer.json record.)
   * @return array
   *   List of packages, indexed by name and version.
   *   Ex: $idx['foo/bar']['1.2.3'] = $composerJson.
   */
  protected function indexPackages($pkgs) {
    $idx = [];

    foreach ($pkgs as $pkg) {
      $idx[$pkg['name']][$pkg['version']] = $pkg;
    }

    ksort($idx);
    foreach (array_keys($idx) as $idxKey) {
      ksort($idx[$idxKey]);
    }

    return $idx;
  }

  protected function filterComposerJson(OutputInterface $output, $content, $context) {
    $composerJson = json_decode($content, 1);
    ScriptletDir::create('publish-composer')->run([$output, &$composerJson, $context]);
    return ComposerJson::prettyPrint($composerJson);
  }

  protected function filterInfoXml(OutputInterface $output, $content, $context) {
    /** @var \SimpleXMLElement $infoXml */
    list ($infoXml, $error) = \Extpub\Util\Xml::parse($content);
    if ($infoXml === FALSE) {
      throw new \Exception("Failed to parse info XML\n\n$error");
    }

    ScriptletDir::create('publish-info')->run([$output, $infoXml, $context]);
    return Xml::prettyPrint($infoXml);
  }

  protected function putFile($path, $content) {
    $parent = dirname($path);
    if (!is_dir($parent)) {
      mkdir($parent, 0777, TRUE);
    }
    file_put_contents($path, $content);
  }

}
