<?php
use \Symfony\Component\Console\Output\OutputInterface;

return function (OutputInterface $output, array &$composerJson, $context) {
  $composerJson['dist'] = [
    'type' => 'zip',
    'url' => $context['zipUrl'],
    // 'shasum' => sha1_file($context['zipFile']),
  ];
};
