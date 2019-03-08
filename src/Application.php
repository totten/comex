<?php
namespace Extpub;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Application extends \Symfony\Component\Console\Application {

  /**
   * Primary entry point for execution of the standalone command.
   */
  public static function main($binDir) {
    if (self::isPhar() && !self::isInternalHost()) {
      fwrite(STDERR, "extpub.phar is intended for use on internal civicrm.org infra\n");
      exit(1);
    }
    $application = new Application('extpub', '@package_version@');
    $application->run();
  }

  public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN') {
    parent::__construct($name, $version);
    $this->setCatchExceptions(TRUE);
    $this->addCommands($this->createCommands());
  }

  /**
   * Construct command objects
   *
   * @return array of Symfony Command objects
   */
  public function createCommands() {
    $commands = array();
    $commands[] = new \Extpub\Command\PublishCommand();
    $commands[] = new \Extpub\Command\PlanCommand();
    $commands[] = new \Extpub\Command\BuildCommand();
    $commands[] = new \Extpub\Command\ExtReconcileCommand();
    return $commands;
  }

  protected static function isPhar() {
    return preg_match(';^phar:;', __FILE__);
  }

  protected static function isInternalHost() {
    $fqdn = @gethostbyaddr(gethostbyname(gethostname()));
    return preg_match(';\.(civicrm\.org|nifty-buffer-107523\.internal)$;', $fqdn);
  }

}
