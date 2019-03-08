<?php
namespace Extpub\Command;

use Extpub\Util\Filesystem;
use Extpub\Util\Naming;
use Extpub\Util\Process;
use Extpub\Util\ProcessBatch;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BuildCommand extends BaseCommand {

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
      ->useOptions(['ext', 'git-url', 'commit', 'ver', 'sub-dir', 'force', 'dry-run', 'timeout', 'web-root'])
      ->setName('build')
      ->setDescription('Build a zip file for an extension (from git)')
      ->setHelp('Build a zip file for an extension (from git)');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    foreach (['ext', 'git-url', 'commit', 'ver'] as $opt) {
      if (!$input->getOption($opt)) {
        throw new \Exception("Missing required parameter: --$opt");
      }
    }

    if (!$this->isValidVersion($input->getOption('ver'))) {
      throw new \Exception("Malformed version number");
    }

    if (!Naming::isValidKey($input->getOption('ext'))) {
      throw new \Exception("Malformed extension");
    }

    if (!$this->isValidCommit($input->getOption('commit'))) {
      throw new \Exception("Malformed commit");
    }

    $id = implode('-', [
      preg_replace('/\.git$/', '', basename($input->getOption('git-url'))),
      $input->getOption('ver'),
      sha1(implode(';;', [
        $input->getOption('ext'),
        $input->getOption('git-url'),
        $input->getOption('commit'),
        $input->getOption('ver'),
        $input->getOption('sub-dir')
      ]))
    ]);

    $args = [
      'EXTPUB' => EXTPUB_FILE,
      'EXT' => $input->getOption('ext'),
      'GIT_URL' => $input->getOption('git-url'),
      'COMMIT' => $input->getOption('commit'),
      'VER' => $input->getOption('ver'),
      'SUB_DIR' => $input->getOption('sub-dir'),
      'ID' => $id,
      'TMP' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . $id . '-' . rand(0, 100000),
      'WEB_ROOT' => $input->getOption('web-root'),
      'ZIP' => $input->getOption('web-root') . "dist/" . $input->getOption('ext') . '/' . "$id.zip"
    ];
    $args['ZIP_DIR'] = dirname($args['ZIP']);
    $args['SRC_DIR'] = $args['TMP'];
    if ($args['SUB_DIR']) {
      $args['SRC_DIR'] .= DIRECTORY_SEPARATOR . $args['SUB_DIR'];
    }

    if (file_exists($args['ZIP']) && !$input->getOption('force')) {
      $output->writeln("<info>Skip: File <comment>" . basename($args['ZIP']) . "</comment> already exists. Use --force to override.</info>");
      return 0;
    }

    $batch = new ProcessBatch();
    $batch->add('<info>Init temp dir</info>', new \Symfony\Component\Process\Process(
      Process::interpolate('mkdir @TMP; cd @TMP; git init', $args)
    ));
    $batch->add("<info>Get version <comment>{$args['VER']}</comment> (commit <comment>{$args['COMMIT']}</comment>)</info>", new \Symfony\Component\Process\Process(
      Process::interpolate('git remote add origin @GIT_URL && git fetch --depth 1 origin @COMMIT && git checkout @COMMIT', $args),
      $args['TMP']
    ));
    $batch->add('<info>Update <comment>info.xml</comment> and <comment>composer.json</comment></info>', new \Symfony\Component\Process\Process(
      Process::interpolate('php @EXTPUB reconcile @SRC_DIR --ver=@VER && git add info.xml composer.json && git commit -m "Auto-update metadata"', $args),
      $args['SRC_DIR']
    ));
    if (empty($args['SUB_DIR']) || $args['SUB_DIR'] === '.') {
      $batch->add("<info>Generate <comment>" . basename($args['ZIP']) . "</comment></info>", new \Symfony\Component\Process\Process(
        Process::interpolate('mkdir -p @ZIP_DIR && git archive HEAD --format zip > @ZIP', $args),
        $args['TMP']
      ));
    }
    else {
      $batch->add("<info>Generate <comment>" . basename($args['ZIP']) . "</comment></info>", new \Symfony\Component\Process\Process(
        Process::interpolate('mkdir -p @ZIP_DIR && git archive HEAD --format zip @SUB_DIR -o @ZIP', $args),
        $args['TMP']
      ));

    }
    $batch->add('<info>Remove temp dir</info>', new \Symfony\Component\Process\Process(
      Process::interpolate('rm -rf @TMP', $args)
    ));
    $this->runBatch($input, $output, $batch);
  }

  protected function isValidVersion($ver) {
    return (bool) preg_match('/^\d[\d\.\-a-z]+$/', $ver);
  }

  protected function isValidCommit($commit) {
    return (bool) preg_match('/^[a-f0-9]+$/', $commit);
  }

}
