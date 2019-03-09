<?php

#### Find primary autoloader
define('COMEX_FILE', dirname(__DIR__) . '/bin/comex');
$autoloaders = array(
  implode(DIRECTORY_SEPARATOR, array(dirname(__DIR__), 'vendor', 'autoload.php')),
  implode(DIRECTORY_SEPARATOR, array(dirname(dirname(dirname(dirname(__DIR__)))), 'vendor', 'autoload.php')),
);
foreach ($autoloaders as $autoloader) {
  if (file_exists($autoloader)) {
    $loader = require $autoloader;
    break;
  }
}

if (!isset($loader)) {
  die("Failed to find autoloader");
}

#### Extra - Register classes in "tests" directory
$loader->addPsr4('Comex\\', __DIR__ . DIRECTORY_SEPARATOR);
