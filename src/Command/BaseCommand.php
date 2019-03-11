<?php
namespace Comex\Command;

use Symfony\Component\Console\Command\Command;
use Comex\GitRepo;
use Comex\Util\ProcessBatch;
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

        case 'assert-key':
          $this->addOption('assert-key', NULL, InputOption::VALUE_REQUIRED, 'Assert that the extension has the given key');
          break;

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
          $this->addOption('web-root', NULL, InputOption::VALUE_REQUIRED, 'Location of the web root. Ex: /srv/buildkit/build', dirname(dirname(COMEX_FILE)) . '/web');
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
    if ($this->getDefinition()->hasOption('dry-run') && $input->getOption('dry-run')) {
      $output->writeln('<info>NOTE:</info> Executing a dry-run');
    }
    if ($this->getDefinition()->hasOption('web-root')) {
      $this->normalizeDirectoryOption($input, 'web-root');
    }
    if ($this->getDefinition()->hasOption('web-url')) {
      $this->normalizeBaseUrlOption($input, 'web-url');
    }
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return \Symfony\Component\Console\Output\OutputInterface
   */
  protected function getErrorOutput(OutputInterface $output) {
    return is_callable([
      $output,
      'getErrorOutput'
    ]) ? $output->getErrorOutput() : $output;
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

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param $requiredInputs
   * @throws \Exception
   */
  protected function checkRequiredInputs(InputInterface $input, $requiredInputs) {
    foreach ($requiredInputs as $option) {
      if (empty($input->getOption($option))) {
        throw new \Exception("Missing required parameter: --$option");
      }
    }
  }


  /**
   * Parse an option's data. This is for options where the default behavior
   * (of total omission) differs from the activated behavior
   * (of an active but unspecified option).
   *
   * Example, suppose we want these interpretations:
   *   cv en         ==> Means "--refresh=auto"; see $omittedDefault
   *   cv en -r      ==> Means "--refresh=yes"; see $activeDefault
   *   cv en -r=yes  ==> Means "--refresh=yes"
   *   cv en -r=no   ==> Means "--refresh=no"
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param array $rawNames
   *   Ex: array('-r', '--refresh').
   * @param string $omittedDefault
   *   Value to use if option is completely omitted.
   * @param string $activeDefault
   *   Value to use if option is activated without data.
   * @return string
   */
  public function parseOptionalOption(InputInterface $input, $rawNames, $omittedDefault, $activeDefault) {
    $value = NULL;
    foreach ($rawNames as $rawName) {
      if ($input->hasParameterOption($rawName)) {
        if (NULL === $input->getParameterOption($rawName)) {
          return $activeDefault;
        }
        else {
          return $input->getParameterOption($rawName);
        }
      }
    }
    return $omittedDefault;
  }

}
