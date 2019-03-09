<?php
namespace Comex\Command;

use Comex\Util\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class CompileCommand extends BaseCommand {

  /**
   * @param string|NULL $name
   */
  public function __construct($name = NULL) {
    $this->fs = new Filesystem();
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('compile')
      ->setDescription('Scan web/meta/**/composer.json and compile a full packages.json')
      ->setHelp('Scan web/meta/**/composer.json and compile a full packages.json');
    $this->useOptions(['force', 'web-root']);
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->checkRequiredInputs($input, ['web-root']);
    $webRoot = $input->getOption('web-root');

    $output->writeln("<info>Compile <comment>packages.json</comment> from <comment>meta/**/composer.json</comment></info>");
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
