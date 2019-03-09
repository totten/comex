<?php
namespace Comex;

class Application extends \Symfony\Component\Console\Application {

  /**
   * Primary entry point for execution of the standalone command.
   */
  public static function main($binDir) {
    if (self::isPhar() && !self::isInternalHost()) {
      fwrite(STDERR, "comex.phar is intended for use on internal civicrm.org infra\n");
      exit(1);
    }
    $application = new Application('comex', '@package_version@');
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
    $commands[] = new \Comex\Command\ScanCommand();
    $commands[] = new \Comex\Command\BuildCommand();
    $commands[] = new \Comex\Command\ReconcileCommand();
    $commands[] = new \Comex\Command\ExtractCommand();
    $commands[] = new \Comex\Command\CompileCommand();
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
