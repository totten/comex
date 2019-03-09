<?php

namespace Comex\Util;

/**
 * Class GitRepoNormalizer
 *
 * Some authors provide git URLs using the Github or Gitlab SSH
 * notation (e.g. `git@github.com:myuser/myrepo.git`). However, this is a public
 * listing used by various processes, so it's better to use the anonymous-friendly
 * https notation.
 */
class GitRepoNormalizer {

  public static function normalize($gitUrl) {
    $gitHostsPattern = 'github\.com|lab\.civicrm\.org';
    $ownerPattern = '[a-zA-Z0-9\-\._]+';
    $repoPattern = '[a-zA-Z0-9\-\._]+';
    if (preg_match(";^git@($gitHostsPattern):($ownerPattern)/({$repoPattern})$;", $gitUrl, $matches)) {
      $host = $matches[1];
      $owner = $matches[2];
      $repo = preg_replace(';\.git$;', '', $matches[3]);
      $gitUrl = sprintf('https://%s/%s/%s.git', $host, $owner, $repo);
    }
    return $gitUrl;
  }

}
