<?php
namespace Comex\Command;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class ReconcileCommandTest extends \Comex\ComexTestCase {

  public function setup() {
    parent::setup();
  }

  public function getExamples() {
    $dir = dirname(__DIR__) . '/fixtures/reconcile';
    $exs = [];
    foreach (Finder::create()->in($dir)->depth(0)->directories() as $subdir) {
      $exs[] = [(string) $subdir];
    }
    return $exs;
  }

  /**
   * @param string $baseDir
   * @dataProvider getExamples
   */
  public function testExample($baseDir) {
    $expectComposerJson = file_get_contents("$baseDir/out/composer.json");
    $expectInfoXml = file_get_contents("$baseDir/out/info.xml");
    $extraArgs = file_exists("$baseDir/in/reconcile-args.json")
      ? json_decode(file_get_contents("$baseDir/in/reconcile-args.json"), 1)
      : [];

    $commandTester = $this->createCommandTester(array(
      'command' => 'reconcile',
      '--dry-run' => TRUE,
      'ext' => "$baseDir/in",
    ) + $extraArgs, ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
    $allOutput = $commandTester->getDisplay(FALSE);

    if (preg_match(';Write composer.json. \(Dry Run\)(.*)Write info.xml. \(Dry Run\)(.*);s', $allOutput, $m)) {
      $actualComposerJson = $m[1];
      $actualInfoXml = $m[2];
      $this->assertEquals(trim($expectComposerJson), trim($actualComposerJson));
      $this->assertEquals(trim($expectInfoXml), trim($actualInfoXml));
    }
    else {
      $this->fail('Failed to parse output:' . $allOutput);
    }
  }

}
