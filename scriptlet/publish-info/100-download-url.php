<?php
use \Symfony\Component\Console\Output\OutputInterface;

return function (OutputInterface $output, SimpleXMLElement $infoXml, $context) {
  $infoXml->downloadUrl = $context['zipUrl'];
};
