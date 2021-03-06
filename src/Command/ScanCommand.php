<?php
namespace Comex\Command;

use Comex\Util\Filesystem;
use Comex\Util\GitRepoNormalizer;
use Comex\Util\Process;
use Comex\Util\Regex;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class ScanCommand extends BaseCommand {

  /**
   * @var Filesystem
   */
  var $fs;

  /**
   * @param string|NULL $name
   */
  public function __construct($name = NULL) {
    $this->fs = new Filesystem();
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->useOptions(['git-feed', 'limit', 'web-root'])
      ->useArguments(['git-repos'])
      ->setName('scan')
      ->addOption('print', 'p', InputOption::VALUE_OPTIONAL, 'Print a list of the planned tasks. (Optionally, specify format: b|bash|j|json|x|xargs')
      ->setDescription('Scan a list of repos and plan the build-steps')
      ->setHelp('Scan a list of repos and plan the build-steps

You may specify the target repos using a JSON feed:

  comex scan --git-feed=https://civicrm.org/extdir/git-urls.json

Or you may specify target repos using file paths:

  comex scan ~/src/{first,second,third}

The command prints a list of tasks. You can pipe them to a task runner, such as xargs:

  comex scan ~/src/{first,second,third} -px | xargs -L1 -P4 ./bin/comex

Note: There are security implications to correctly determining the
extension-key for which repo is allowed to publish extensions.

In the JSON feed, the authorized extension-key must be given.
In the local file paths, the extension-key is inferred automatically
without any special authorization.
');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $repos = $this->pickRepos($input, $output);
    $errorOutput = $this->getErrorOutput($output);

    if ($output->isVeryVerbose()) {
      $errorOutput->writeln("<info>Scanning repos:</info>");
      $errorOutput->writeln(json_encode($repos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);
    }

    if (empty($repos)) {
      $output->writeln("<error>No repos found. Please specify a --git-feed or a list of git-repos.</error>");
      return 1;
    }

    $tasks = [];
    foreach ($repos as $repo) {
      if (!empty($repo['path'])) {
        $errorOutput->writeln("<info>Scan <comment>{$repo['git_url']}</comment> (<comment>{$repo['path']}</comment>)</info>", OutputInterface::VERBOSITY_VERBOSE);
      }
      else {
        $errorOutput->writeln("<info>Scan <comment>{$repo['git_url']}</comment></info>", OutputInterface::VERBOSITY_VERBOSE);
      }
      $versions = $this->findVersions($repo['git_url']);
      foreach ($versions as $version => $commit) {
        $taskId = sha1(implode(';;', [$repo['key'], $commit, $version, empty($repo['path']) ? '' : $repo['path']]));
        $cmd = 'comex build --ext=@EXT --git-url=@GIT_URL --commit=@COMMIT --ver=@VERSION --web-root=@WEB_ROOT'
          . (empty($repo['path']) ? '' : ' --sub-dir=@SUBDIR');
        $args = [
          'EXT' => $repo['key'],
          'GIT_URL' => $repo['git_url'],
          'COMMIT' => $commit,
          'VERSION' => $version,
          'WEB_ROOT' => $input->getOption('web-root'),
          'SUB_DIR' => $repo['path'],
        ];
        $tasks[$taskId] = [
          'taskId' => $taskId,
          'title' => sprintf('Build %s v%s', $repo['key'], $version),
          'cmd' => Process::interpolate($cmd, $args),
        ];
      }
    }

    $print = $this->parseOptionalOption($input, ['--print', '-p'], 'bash', 'bash');
    $this->sendTasks($output, $print, $tasks);
  }

  /**
   * @param string $url
   *   URL of the JSON feed.
   * @return array
   *   Parsed content of the JSON feed.
   * @throws \Exception
   */
  public function getFeed($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $unparsed = curl_exec($ch);
    curl_close($ch);
    $parsed = json_decode($unparsed, 1);
    if ($parsed === NULL) {
      throw new \Exception("Invalid JSON feed: $url");
    }
    return $parsed;
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param string $feedUrl
   * @param string $readyStatus
   * @param array $schemes
   *   List of supported URL schemes.
   * @return array
   *   Array (string $gitUrl).
   */
  public function getFilteredFeed(OutputInterface $output, $feedUrl, $readyStatus, $schemes) {
    $urlRegex = ';^' . Regex::quotedOr($schemes, ';') . '://;';

    $repos = [];
    foreach ($this->getFeed($feedUrl) as $repo) {
      $repo['git_url'] = GitRepoNormalizer::normalize($repo['git_url']);
      $repo['path'] = isset($repo['path']) ? $repo['path'] : '';

      if (!preg_match($urlRegex, $repo['git_url'])) {
        $this->getErrorOutput($output)
          ->writeln("<info>Skipped <comment>{$repo['git_url']}</comment>: Disabled or unsupported scheme</info>", OutputInterface::VERBOSITY_VERBOSE);
      }
      elseif ($repo['ready'] !== $readyStatus) {
        $this->getErrorOutput($output)
          ->writeln("<info>Skipped <comment>{$repo['git_url']}</comment>: Incorrect status</info>", OutputInterface::VERBOSITY_VERBOSE);
      }
      else {
        $repos[] = $repo;
      }
    }
    return $repos;
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param array $repoDirs
   *   Array(string $path).
   * @return array
   *   Each item is Array(git_url => $string, key => $string, path => $string)
   */
  public function getRepos(OutputInterface $output, $repoDirs) {
    $repos = [];

    foreach ($repoDirs as $repoDir) {
      $count = 0;
      foreach (Finder::create()->in($repoDir)->name('info.xml') as $infoXml) {
        $count++;
        $key = $this->getKeyFromInfoXml("$infoXml");
        if ($key) {
          $repos[] = [
            'key' => $key,
            'git_url' => "file://$repoDir",
            'path' => realpath(dirname($infoXml)) === realpath($repoDir)
                ? ''
                : rtrim($this->fs->makePathRelative(dirname("$infoXml"), "$repoDir"), DIRECTORY_SEPARATOR),
          ];
        }
      }

      if ($count === 0) {
        $output->getErrorOutput()
          ->writeln("<info>Skipped <comment>{$repoDir}</comment>: Cannot determine extension-key from <comment>info.xml</comment>.</info>", OutputInterface::VERBOSITY_VERBOSE);
      }
    }
    return $repos;
  }

  /**
   * @param string $gitUrl
   * @return array
   *   Array(string $version => string $sha1).
   */
  protected function findVersions($gitUrl) {
    $escapeUrl = escapeshellarg($gitUrl);
    $rawTags = `git ls-remote $escapeUrl 'refs/tags/*'`;

    $lines = explode("\n", $rawTags);
    $versions = [];
    foreach ($lines as $line) {
      if (preg_match(';^([0-9a-f]+)\s+refs/tags/v?(\d[\w\.\-]+)$;', $line, $m)) {
        $versions[$m[2]] = $m[1];
      }
    }
    ksort($versions);
    return $versions;
  }

  /**
   * Given an info.xml, determine its primary extension-key.
   *
   * @param string $infoXmlFile
   * @return string|NULL
   * @throws \Exception
   */
  protected function getKeyFromInfoXml($infoXmlFile) {
    if (!file_exists($infoXmlFile)) {
      return NULL;
    }
    return (string) \Comex\Util\Xml::parseFile($infoXmlFile)['key'];
  }

  /**
   * Based on the $input, figure out a list of target extensions/repos.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return array
   *   Each item is Array(key => $string, git_url => $string, path => $string)
   */
  protected function pickRepos(InputInterface $input, OutputInterface $output) {
    $repos = [];
    if ($feedUrl = $input->getOption('git-feed')) {
      $schemes = preg_match(';^file://;', $feedUrl)
        ? ['https', 'http', 'git', 'file']
        : ['https', 'http', 'git'];
      $repos = array_merge($repos, $this->getFilteredFeed($output, $feedUrl, 'ready', $schemes));
    }
    if ($repoDirs = $input->getArgument('git-repos')) {
      $repos = array_merge($repos, $this->getRepos($output, $repoDirs));
    }
    usort($repos, function ($a, $b) {
      return strcmp($a['key'], $b['key']);
    });
    if ($input->getOption('limit')) {
      $repos = array_slice($repos, 0, $input->getOption('limit'));
      return $repos;
    }
    return $repos;
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param string $format
   * @param array $tasks
   * @throws \Exception
   */
  protected function sendTasks(OutputInterface $output, $format, $tasks) {
    switch ($format) {
      case '';
      case NULL:
        break;

      case 'b':
      case 'bash':
        foreach ($tasks as $task) {
          $output->writeln($task['cmd'], OutputInterface::OUTPUT_RAW);
        }
        break;

      case 'j':
      case 'json':
        $output->writeln(json_encode(array_values($tasks), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);
        break;

      case 'x':
      case 'xargs':
        foreach ($tasks as $task) {
          echo preg_replace('/^comex /', '', $task['cmd']) . "\n";
        }
        break;

      default:
        throw new \Exception("Unrecognized print format");
    }
  }

}
