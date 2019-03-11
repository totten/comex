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
    $expectComposerJson = $this->getFile("$baseDir/out/composer.json");
    $expectInfoXml = $this->getFile("$baseDir/out/info.xml");
    $expectError = $this->getFile("$baseDir/out/error.json");
    $extraArgs = ($f = $this->getFile("$baseDir/in/reconcile-args.json")) ? json_decode($f, 1) : [];

    try {
      $commandTester = $this->createCommandTester(array(
          'command' => 'reconcile',
          '--dry-run' => TRUE,
          'ext-repo' => "$baseDir/in",
        ) + $extraArgs, ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
    }
    catch (\Exception $e) {
      if ($expectError !== NULL) {
        $expect = json_decode($expectError, 1);
        $this->assertRegexp($expect['outputPattern'], $e->getMessage());
        return;
      }
      else {
        throw $e;
      }
    }

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

  protected function getFile($file) {
    return file_exists($file) ? file_get_contents($file) : NULL;
  }

}
