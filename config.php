<?php
return [
  // See: https://www.doctrine-project.org/projects/doctrine-dbal/en/2.9/reference/configuration.html#configuration
  'webroot' => __DIR__ . '/web/',
  'metaroot' => __DIR__ . '/web/meta/',
  'datasource' => [
    'url' => 'sqlite::' . __DIR__ . '/var/comex.sqlite3'
  ],
];
