<?php
namespace Comex\Command;

use Comex\Config;
use Comex\Util\Filesystem;
use Comex\Util\Xml;
use Comex\Version;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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

    $config = Config::loadConfig();

    $output->writeln("<info>Compile <comment>packages.json</comment> from <comment>meta/**/composer.json</comment></info>");
    $this->compileMasterIndex($output,
      Finder::create()->in("{$webRoot}meta")->name('composer.json'),
      "{$webRoot}packages.json");

    $output->writeln("<info>Compile <comment>civipkg</comment> SQL table from <comment>meta/**/info.xml</comment></info>");
    $this->compileSql($output,
      Finder::create()->in("{$webRoot}meta")->name('info.xml'),
      \Doctrine\DBAL\DriverManager::getConnection($config['datasource']));
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
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param Finder $finder
   *   List of info.xml files.
   * @param Connection $conn
   */
  protected function compileSql(OutputInterface $output, $finder, Connection $conn) {
    // TODO:: Splitting/batching (by folder, name, or date) so that checksums are more stable and cache-friendly...
    // $pkgs = $this->readComposerJsonList($finder);
    // $packagesJson = ['packages' => $this->indexPackages($pkgs)];

    $schema = $this->createSchema();
    $sm = $conn->getSchemaManager();
    if (in_array('infoxml', $sm->listTableNames())) {
      $output->writeln("<info>Drop SQL schema</info>");
      foreach ($schema->toDropSql($conn->getDatabasePlatform()) as $sql) {
        $output->writeln($sql, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_VERBOSE);
        $conn->exec($sql);
      }
    }

    $output->writeln("<info>Create SQL schema</info>");
    foreach ($schema->toSql($conn->getDatabasePlatform()) as $sql) {
      $output->writeln($sql, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_VERBOSE);
      $conn->exec($sql);
    }

    $output->writeln("<info>Fill SQL</info>");
    $this->fillSql($finder, $conn);
    // $this->putFile($packagesJsonFile, json_encode($packagesJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  protected function createSchema() {
    $schema = new \Doctrine\DBAL\Schema\Schema();
    $myTable = $schema->createTable("infoxml");
    $myTable->addColumn("key", "string", ["length" => 128]);
    $myTable->addColumn("status", "string", ["length" => 16]);
    $myTable->addColumn("ready", "smallint", []);
    $myTable->addColumn("ext_version", "string", ["length" => 128]);
    $myTable->addColumn("civi_version", "string", ["length" => 128]);
    $myTable->addColumn("info_xml", "string", ["length" => 255]);
    $myTable->setPrimaryKey(['key', 'ext_version']);
    $myTable->addIndex(['status', 'ready', 'civi_version', 'key', 'ext_version']);
    return $schema;
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

  /**
   * Given a set of "info.xml" files, populate the SQL-based
   * lookup system.
   *
   * @param $finder
   * @param \Doctrine\DBAL\Connection $conn
   */
  protected function fillSql($finder, Connection $conn) {
    $version = new \Comex\Util\Version();
    foreach ($finder->sortByModifiedTime()->files() as $file) {
      /** @var SplFileInfo $file */
      /** @var \SimpleXMLElement $infoXml */
      $infoXml = Xml::parse($file->getContents());
      $earliestCiviVersion = NULL;
      foreach ($infoXml->compatibility->ver as $ver) {
        $ver = (string) $ver;
        if ($earliestCiviVersion === NULL || version_compare($earliestCiviVersion, $ver, '>')) {
          $earliestCiviVersion = $ver;
        }
      }

      $rowId = [
        'key' => (string) $infoXml->attributes()->key,
        'ext_version' => $version->normalize((string) $infoXml->version),
      ];
      $rowData = [
        'status' => (string) $infoXml->develStage,
        'ready' => 1, // FIXME: Actually check review status
        'civi_version' => $version->normalize($earliestCiviVersion),
        'info_xml' => $file->getRelativePathname(),
      ];

      // There could be multiple copies of same version if author re-issued tag.
      // Insert-or-update prioritizes the most recent build (by virtue of file sorting above).
      try {
        $conn->insert('infoxml', $rowId + $rowData);
      } catch (UniqueConstraintViolationException $e) {
        $conn->update('infoxml', $rowData, $rowId);
      }
    }
  }

}
