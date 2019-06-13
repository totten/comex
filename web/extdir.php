<?php
/**
 * This end-point is comparable to the traditional `/extdir/{FILTER}/single` route.
 *
 * @link https://civicrm.org/extdir/ver=5.12.alpha1|uf=Drupal|status=|ready=|mock=1/single
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

list (, $filterExpr, $item) = explode('/', $_SERVER['PATH_INFO']);
$data = extdir_find_packages(\Comex\Util\FilterCodex::decode($filterExpr));
switch ($item) {
  case 'single':
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    break;

  default:
    throw new \RuntimeException("Unrecognized file name");
}

/**
 * @param array $filter
 * @return array
 *   Array(string $key => string $infoXml).
 */
function extdir_find_packages($filter) {
  $config = \Comex\Config::loadConfig();
  $conn = \Doctrine\DBAL\DriverManager::getConnection($config['datasource']);

  /** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
  $queryBuilder = $conn->createQueryBuilder()
    ->select('key', 'info_xml', 'status', 'ready', 'ext_version', 'civi_version')
    ->from('infoxml')
    ->addOrderBy('key', 'ASC')
    ->addOrderBy('ext_version', 'ASC')
  ;

  if (!array_key_exists('status', $filter)) {
    $queryBuilder->andWhere('status = "stable"');
  }
  elseif ($filter['status'] !== '') {
    $queryBuilder->andWhere('status = ' .  $queryBuilder->createNamedParameter($filter['status']));
  }

  if (!array_key_exists('ready', $filter)) {
    $queryBuilder->andWhere('ready = 1');
  }
  elseif ($filter['ready'] !== '') {
    // Does anyone ever do this? The old behavior wasn't very useful...
    $queryBuilder->andWhere('ready = ' .  $queryBuilder->createNamedParameter($filter['ready']));
  }

  if (!isset($filter['ver'])) {
    throw new \Exception("Missing required 'ver'");
  }
  else {
    $op = version_compare($filter['ver'], '4.7.alpha1', '<') ? '=' : '<=';
    $v = new \Comex\Util\Version();
    list ($maj, $min) = explode('.', $filter['ver']);
    $queryBuilder->andWhere('civi_version  ' . $op . ' ' . $queryBuilder->createNamedParameter($v->normalize("$maj.$min")));
  }

  $stmt = $queryBuilder->execute();
  $data = [];
  while ($row = $stmt->fetch()) {
    // Note: Query may return multiple versions, but this will prioritize latest.
    $data[$row['key']] = file_get_contents($config['metaroot'] . $row['info_xml']);
  }
  return $data;
}
