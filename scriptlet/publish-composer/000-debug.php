<?php
use \Symfony\Component\Console\Output\OutputInterface;

return function (OutputInterface $output, &$composerJson, $context) {
  if ($output->isVeryVerbose()) {
    $output->writeln('<info>Scriptlet(<comment>publish-composer</comment>): context = </info>' . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }
};
