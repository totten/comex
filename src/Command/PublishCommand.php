<?php
namespace Extpub\Command;

use Extpub\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
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
      ->setHelp('Scan the distribution tree and compile a list of packages');
    $this->useOptions(['web-root', 'web-url']);
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $webRoot = $input->getOption('web-root');

    $output->writeln("<info>Search <comment>$webRoot/dist</comment> for <comment>*.composer.json</comment></info>");
    $this->compileMasterIndex($output,
      Finder::create()->in("$webRoot/dist")->name('*.composer.json'),
      "$webRoot/packages.json");
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
    file_put_contents($packagesJsonFile, json_encode($packagesJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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

}
