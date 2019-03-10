<?php
namespace Comex\Command;

use Symfony\Component\Console\Output\OutputInterface;

class PlanCommandTest extends \Comex\ComexTestCase {
  public function setup() {
    parent::setup();
  }

  /**
   * Simulate creation of an extension test-build using a Github PR URL.
   */
  public function testPlanSmallFeed() {
    $feed = dirname(__DIR__) . '/fixtures/feeds/small-feed.json';
    $commandTester = $this->createCommandTester(array(
      'command' => 'scan',
      '--git-feed' => 'file://' . $feed,
      '--web-root' => '/tmp/myweb',
    ), ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

    $linePatterns = [
      ';' . preg_quote('comex build --ext=\'org.civicrm.api4\' --git-url=\'https://github.com/civicrm/org.civicrm.api4\' --commit=\'be447c73fb6a31f2535869500f64054131305da7\' --ver=\'4.0.0\' --web-root=\'/tmp/myweb/\'', ';') . ';',
      ';' . preg_quote('comex build --ext=\'org.civicrm.api4\' --git-url=\'https://github.com/civicrm/org.civicrm.api4\' --commit=\'d5a853a6f4d1cad11e8655755b329f15eb3fc27b\' --ver=\'4.1.0\' --web-root=\'/tmp/myweb/\'', ';') . ';'
    ];

    $allOutput = $commandTester->getDisplay(FALSE);
    $lines = explode("\n", $allOutput);
    foreach ($linePatterns as $linePattern) {
      $grep = preg_grep($linePattern, $lines);
      $this->assertCount(1, $grep, "Expected one match for pattern ($linePattern)");
    }
  }

}
