<?php
namespace Extpub\Command;

use Extpub\GitRepo;
use Extpub\Util\Filesystem;
use Extpub\Util\GitRepoNormalizer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class PlanCommand extends BaseCommand {

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
      ->useOptions(['git-feed', 'limit'])
      ->useArguments(['git-repos'])
      ->setName('plan')
      ->setDescription('Scan a list of repos and plan the build-steps')
      ->setHelp('Scan a list of repos and plan the build-steps

You may specify the target repos using a JSON feed:

   extpub plan --git-feed=https://civicrm.org/extdir/git-urls.json

Or you may specify target repos using file paths:

  extpub plan ~/src/{first,second,third}

Note: There are security implications to correctly determining the
extension-key for which repo is allowed to publish extensions.

In the JSON feed, the authorized extension-key must be given.
In the local file paths, the extension-key is inferred automatically
without any special authorization.
');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $repos = $this->pickRepos($input, $output);

    if ($output->isVeryVerbose()) {
      $output->getErrorOutput()->writeln("<info>Scanning repos:</info>");
      $output->getErrorOutput()->writeln(json_encode($repos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);
    }

    if (empty($repos)) {
      $output->writeln("<error>No repos found. Please specify a --git-feed or a list of git-repos.</error>");
      return 1;
    }

    $tasks = [];
    foreach ($repos as $repo) {
      if (!empty($repo['path'])) {
        $output->getErrorOutput()->writeln("<info>Scan <comment>{$repo['git_url']}</comment> (<comment>{$repo['path']}</comment>)</info>", OutputInterface::VERBOSITY_VERBOSE);
      }
      else {
        $output->getErrorOutput()->writeln("<info>Scan <comment>{$repo['git_url']}</comment></info>", OutputInterface::VERBOSITY_VERBOSE);
      }
      $versions = $this->findVersions($repo['git_url']);
      foreach ($versions as $version => $commit) {
        // $id = sha1(implode(';;', [$repo['key'], $repo['git_url'], $commit, $version]));
        $task = sprintf('extpub build --ext=%s --git-url=%s --commit=%s --ver=%s',
          escapeshellarg($repo['key']),
          escapeshellarg($repo['git_url']),
          escapeshellarg($commit),
          escapeshellarg($version)
        );
        if (!empty($repo['path'])) {
          $task .= sprintf(' --sub-dir=%s', escapeshellarg($repo['path']));
        }
        $tasks[] = $task;
      }
    }
    foreach ($tasks as $task) {
      echo "$task\n";
    }
  }

  public function getFeed($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $json = curl_exec($ch);
    curl_close($ch);
    return json_decode($json, 1);
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param string $feedUrl
   * @param string $readyStatus
   * @return array
   *   Array (string $gitUrl).
   */
  public function getFilteredFeed(OutputInterface $output, $feedUrl, $readyStatus) {
    $repos = [];
    foreach ($this->getFeed($feedUrl) as $repo) {
      $repo['git_url'] = GitRepoNormalizer::normalize($repo['git_url']);
      $repo['path'] = isset($repo['path']) ? $repo['path'] : '';

      if (!preg_match(';^https?://;', $repo['git_url'])) {
        $output->getErrorOutput()
          ->writeln("<info>Skipped malformed URL <comment>{$repo['git_url']}</comment></info>", OutputInterface::VERBOSITY_VERBOSE);
      }
      elseif ($repo['ready'] !== $readyStatus) {
        $output->getErrorOutput()
          ->writeln("<info>Skipped <comment>{$repo['git_url']}</comment></info>", OutputInterface::VERBOSITY_VERBOSE);
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
    $tags = [];
    foreach ($lines as $line) {
      if (preg_match(';^([0-9a-f]+)\s+refs/tags/v?(\d[\w\.\-]+)$;', $line, $m)) {
        $tags[$m[2]] = $m[1];
      }
    }
    return $tags;
  }

  /**
   * Given a git repo, determine its primary extension-key.
   *
   * @param string $infoXmlFile
   * @return string|NULL
   * @throws \Exception
   */
  protected function getKeyFromInfoXml($infoXmlFile) {
    if (!file_exists($infoXmlFile)) {
      return NULL;
    }

    list ($infoXml, $error) = \Extpub\Util\Xml::parse(file_get_contents($infoXmlFile));
    if ($infoXml === FALSE) {
      throw new \Exception("Failed to parse info XML\n\n$error");
    }

    return (string) $infoXml->attributes()->key;
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
      $repos = array_merge($repos, $this->getFilteredFeed($output, $feedUrl, 'ready'));
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

}
