<?php
namespace Extpub\Command;

use Extpub\Application;
use Symfony\Component\Console\Command\Command;
use Extpub\GitRepo;
use Extpub\Util\ArrayUtil;
use Extpub\Util\Filesystem;
use Extpub\Util\Process as ProcessUtil;
use Extpub\Util\Process;
use Extpub\Util\ProcessBatch;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class BaseCommand extends Command {

  /**
   * @param array $names
   *   List of standard options to enable.
   * @return $this
   */
  protected function useOptions($names) {
    foreach ($names as $name) {
      switch ($name) {

        case 'download-url':
          $this->addOption('download-url', NULL, InputOption::VALUE_REQUIRED, 'Intended public URL');
          break;

        case 'commit':
          $this->addOption('commit', NULL, InputOption::VALUE_OPTIONAL, 'Intended git commit/revision');
          break;

        case 'dry-run':
          $this->addOption('dry-run', 'N', InputOption::VALUE_NONE, 'Do not execute');
          break;

        case 'ext':
          $this->addOption('ext', NULL, InputOption::VALUE_OPTIONAL, 'Fully qualified extension name');
          break;

        case 'force':
          $this->addOption('force', 'f', InputOption::VALUE_NONE, 'If an extension folder already exists, download it anyway.');
          break;

        case 'git-feed':
          $this->addOption('git-feed', NULL, InputOption::VALUE_REQUIRED, 'URL of the list of Git repos (Ex: https://civicrm.org/extdir/git-urls.json)');
          break;

        case 'git-url':
          $this->addOption('git-url', NULL, InputOption::VALUE_REQUIRED, 'URL of the list of a git repo');
          break;

        case 'limit':
          $this->addOption('limit', NULL, InputOption::VALUE_OPTIONAL, 'Max number of items to process');
          break;

        case 'sub-dir':
          $this->addOption('sub-dir', NULL, InputOption::VALUE_REQUIRED, 'Subdirectory of the git repo which contains the extension');
          break;

        case 'timeout':
          $this->addOption('timeout', NULL, InputOption::VALUE_REQUIRED, 'Max number of seconds to spend on any individual task', 600);
          break;

        case 'ver':
          $this->addOption('ver', NULL, InputOption::VALUE_OPTIONAL, 'Intended version number');
          break;

        case 'web-root':
          $this->addOption('web-root', 'r', InputOption::VALUE_REQUIRED, 'Location of the web root. Ex: /srv/buildkit/build', dirname(dirname(EXTPUB_FILE)) . '/web');
          break;

        case 'web-url':
          $this->addOption('web-url', NULL, InputOption::VALUE_REQUIRED, 'Public web URL');
          break;

        default:
          throw new \RuntimeException("Cannot define option: $name");
      }
    }
    return $this;
  }

  /**
   * @param array $names
   *   List of standard arguments to enable.
   * @return $this
   */
  protected function useArguments($names) {
    foreach ($names as $name) {
      switch ($name) {
        case 'git-repos':
          $this->addArgument('git-repos', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'List of git repos');
          break;
      }
    }
    return $this;
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    if ($this->getDefinition()->hasOption('web-root')) {
      $this->normalizeDirectoryOption($input, 'web-root');
    }
    if ($this->getDefinition()->hasOption('web-url')) {
      $this->normalizeBaseUrlOption($input, 'web-url');
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param $batch
   */
  protected function runBatch(InputInterface $input, OutputInterface $output, ProcessBatch $batch) {
    foreach ($batch->getProcesses() as $proc) {
      /** @var \Symfony\Component\Process\Process $proc */
      $proc->setTimeout($input->getOption('timeout'));
    }

    $batch->runAllOk($output, $input->getOption('dry-run'));
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param $option
   */
  protected function normalizeDirectoryOption(InputInterface $input, $option) {
    $value = $input->getOption($option);
    if ($value && $value{strlen($value) - 1} !== DIRECTORY_SEPARATOR) {
      $value .= DIRECTORY_SEPARATOR;
      $input->setOption($option, $value);
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param $option
   */
  protected function normalizeBaseUrlOption(InputInterface $input, $option) {
    $value = $input->getOption($option);
    if ($value && $value{strlen($value) - 1} !== '/') {
      $value .= '/';
      $input->setOption($option, $value);
    }
  }

}
