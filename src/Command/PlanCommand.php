<?php
namespace Extpub\Command;

use Extpub\GitRepo;
use Extpub\Util\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
      ->setName('plan')
      ->setDescription('Plan a list of steps for processing various repos and branches');
    $this->useOptions(['git-feed', 'limit', 'web-url']);
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $repos =  $this->getFeed($input->getOption('git-feed'));
    if ($input->getOption('limit')) {
      $repos = array_slice($repos, 0, $input->getOption('limit'));
    }

    $tasks = [];
    foreach ($repos as $repo) {
      if ($repo['ready'] !== 'ready') {
        $output->getErrorOutput()->writeln("<info>Skipped <comment>{$repo['git_url']}</comment></info>", OutputInterface::VERBOSITY_VERBOSE);
        continue;
      }

      $output->getErrorOutput()->writeln("<info>Scan <comment>{$repo['git_url']}</comment></info>", OutputInterface::VERBOSITY_VERBOSE);
      $versions = $this->findVersions($repo['git_url']);
      foreach ($versions as $version => $commit) {
        // $id = sha1(implode(';;', [$repo['key'], $repo['git_url'], $commit, $version]));
        $tasks[] = sprintf('extpub build --ext=%s --git-url=%s --rev=%s --version=%s --download-url=%s',
          escapeshellarg($repo['key']),
          escapeshellarg($repo['git_url']),
          escapeshellarg($commit),
          escapeshellarg($version),
          escapeshellarg($input->getOption('web-url') . 'dist/{{EXT}}/{{EXT}}-{{VERSION}}-{{ID}}.zip'));
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

}
