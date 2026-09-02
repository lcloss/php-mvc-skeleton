<?php
/**
 * Introspecção do projeto para desenvolvedores e agentes de IA.
 *
 * Uso:
 *   php bin/ai-context.php [routes|schema|info|all] [--json]
 */
define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/lib/Autoloader.php';
require_once BASE_PATH . '/lib/Env.php';
require_once BASE_PATH . '/lib/Database.php';
require_once BASE_PATH . '/lib/Introspect.php';

$app = new \app\App();
$app->bootstrap();

$args = array_slice($argv, 1);
$json = in_array('--json', $args, true);
$args = array_values(array_filter($args, fn($a) => $a !== '--json'));
$command = $args[0] ?? 'all';

$data = match ($command) {
  'routes' => ['routes' => Introspect::routes()],
  'info' => ['info' => Introspect::appInfo()],
  'schema' => ['schema' => Introspect::schema()],
  'all' => [
    'info' => Introspect::appInfo(),
    'routes' => Introspect::routes(),
    'schema' => Introspect::schema(),
  ],
  default => null,
};

if ($data === null) {
  fwrite(STDERR, "Comando desconhecido: {$command}\n");
  fwrite(STDERR, "Uso: php bin/ai-context.php [routes|schema|info|all] [--json]\n");
  exit(1);
}

if ($json) {
  echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
  exit(0);
}

if (isset($data['info'])) {
  echo "== Info da aplicação ==\n";
  printTable(array_map(fn($k, $v) => [$k, is_array($v) ? implode(', ', $v) : (string)($v ?? '')], array_keys($data['info']), $data['info']), ['Campo', 'Valor']);
  echo "\n";
}

if (isset($data['routes'])) {
  echo "== Rotas ==\n";
  $rows = array_map(fn($r) => [
    $r['method'] ?? '',
    $r['path'] ?? '',
    $r['handler'] ?? '',
    implode(', ', $r['middlewares'] ?? []),
  ], $data['routes']);
  printTable($rows, ['Método', 'Path', 'Handler', 'Middlewares']);
  echo "\n";
}

if (isset($data['schema'])) {
  echo "== Schema do banco ==\n";
  foreach ($data['schema'] as $table) {
    echo "-- {$table['name']}\n";
    $rows = array_map(fn($c) => [
      $c['name'],
      $c['type'],
      $c['nullable'] ? 'sim' : 'não',
      $c['pk'] ? 'sim' : 'não',
      (string)($c['default'] ?? ''),
    ], $table['columns']);
    printTable($rows, ['Coluna', 'Tipo', 'Nullable', 'PK', 'Default']);
    echo "\n";
  }
}

function printTable(array $rows, array $header): void {
  $columns = count($header);
  $widths = array_map(fn($h) => mb_strlen($h), $header);
  foreach ($rows as $row) {
    for ($i = 0; $i < $columns; $i++) {
      $widths[$i] = max($widths[$i], mb_strlen((string)($row[$i] ?? '')));
    }
  }

  $printRow = function (array $cells) use ($widths, $columns) {
    $parts = [];
    for ($i = 0; $i < $columns; $i++) {
      $parts[] = str_pad((string)($cells[$i] ?? ''), $widths[$i]);
    }
    echo implode('  ', $parts) . "\n";
  };

  $printRow($header);
  $printRow(array_map(fn($w) => str_repeat('-', $w), $widths));
  foreach ($rows as $row) {
    $printRow($row);
  }
}
