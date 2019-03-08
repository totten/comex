<?php
namespace Extpub\Command;

use Extpub\GitRepo;
use Extpub\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;


class IdxCompileCommand extends BaseCommand {

  /**
   * @param string|NULL $name
   */
  public function __construct($name = NULL) {
    $this->fs = new Filesystem();
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('idx:compile')
      ->setDescription('Aggregate all composer.json files')
      ->setHelp('Aggregate all composer.json files')
      ->addArgument('file-pattern', InputArgument::OPTIONAL, 'Pattern to identify JSON files', '*.json');
    $this->useOptions(['web']);
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $srcDir = $input->getOption('web') . 'dist';
    $filePattern = $input->getArgument('file-pattern');
    $packagesJsonFile = $input->getOption('web') . 'packages.json';

    $output->writeln("<info>Search <comment>$srcDir</comment> for <comment>$filePattern</comment></info>");

    // TODO:: Splitting/batching (by folder, name, or date) so that checksums are more stable and cache-friendly...
    //    $idxDir = $input->getOption('web') . 'p';
    //    foreach (Finder::create()->in($srcDir)->directories()->depth(0) as $dir) {
    //      /** @var SplFileInfo $dir */
    //      $pkgs = $this->readComposerJson(Finder::create()->in("$dir")->name($filePattern));
    //      print_r(["$dir" => $pkgs]);
    //    }

    $pkgs = $this->readComposerJson(Finder::create()->in("$srcDir")->name($filePattern));

    $packagesJson = [
      'packages' => $this->indexPackages($pkgs),
    ];

    $output->writeln("<info>Write <comment>$packagesJsonFile</comment></info>");
    file_put_contents($packagesJsonFile, json_encode($packagesJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  }

  protected function toFileArray($fs, $func) {
    $r = [];
    foreach ($fs as $f) {
      /** @var SplFileInfo $f */
      $r[] = $f->$func();
    }
    return $r;
  }

  protected function readComposerJson(Finder $f) {
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
