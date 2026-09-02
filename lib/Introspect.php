<?php
/**
 * Introspecção do projeto (rotas, schema do banco, info da app) para consumo
 * por humanos e por agentes de IA via bin/ai-context.php. Só retorna arrays
 * PHP puros (sem echo/print) para poder ser reaproveitada por outros
 * consumidores (ex.: um futuro servidor MCP) sem duplicar lógica.
 */
class Introspect {
  public static function routes(): array {
    $file = dirname(__DIR__) . '/config/routes.php';
    if (!file_exists($file)) return [];
    $routes = require $file;
    return is_array($routes) ? $routes : [];
  }

  public static function appInfo(): array {
    $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true) ?? [];
    $app = require dirname(__DIR__) . '/config/app.php';
    $database = require dirname(__DIR__) . '/config/database.php';

    return [
      'framework' => $composer['name'] ?? null,
      'php_version' => PHP_VERSION,
      'app_name' => $app['name'] ?? null,
      'debug' => $app['debug'] ?? null,
      'timezone' => $app['timezone'] ?? null,
      'db_driver' => $database['driver'] ?? null,
      'middlewares' => self::middlewares(),
    ];
  }

  public static function schema(): array {
    $driver = env('DB_CONNECTION', 'sqlite');
    $pdo = Database::getConnection();

    if ($driver === 'sqlite') {
      return self::schemaSqlite($pdo);
    } elseif ($driver === 'mysql') {
      return self::schemaMysql($pdo);
    } elseif ($driver === 'pgsql') {
      return self::schemaPgsql($pdo);
    }

    throw new RuntimeException('Unsupported DB driver');
  }

  private static function middlewares(): array {
    $dir = dirname(__DIR__) . '/app/controllers/middlewares';
    if (!is_dir($dir)) return [];
    $files = glob($dir . '/*.php') ?: [];
    return array_map(fn($f) => basename($f, '.php'), $files);
  }

  private static function schemaSqlite(PDO $pdo): array {
    $tables = $pdo->query(
      "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
    )->fetchAll(PDO::FETCH_COLUMN);

    $schema = [];
    foreach ($tables as $table) {
      // $table vem da própria listagem de tabelas do sqlite_master, não de
      // input externo, por isso é seguro interpolar (PRAGMA não aceita bind).
      $columns = $pdo->query('PRAGMA table_info(' . self::quoteSqliteIdentifier($table) . ')')
        ->fetchAll(PDO::FETCH_ASSOC);
      $schema[] = [
        'name' => $table,
        'columns' => array_map(fn($c) => [
          'name' => $c['name'],
          'type' => $c['type'],
          'nullable' => !$c['notnull'],
          'default' => $c['dflt_value'],
          'pk' => (bool)$c['pk'],
        ], $columns),
      ];
    }
    return $schema;
  }

  private static function schemaMysql(PDO $pdo): array {
    $stmt = $pdo->query(
      "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY
       FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE()
       ORDER BY TABLE_NAME, ORDINAL_POSITION"
    );
    $schema = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $table = $row['TABLE_NAME'];
      if (!isset($schema[$table])) {
        $schema[$table] = ['name' => $table, 'columns' => []];
      }
      $schema[$table]['columns'][] = [
        'name' => $row['COLUMN_NAME'],
        'type' => $row['COLUMN_TYPE'],
        'nullable' => $row['IS_NULLABLE'] === 'YES',
        'default' => $row['COLUMN_DEFAULT'],
        'pk' => $row['COLUMN_KEY'] === 'PRI',
      ];
    }
    return array_values($schema);
  }

  private static function schemaPgsql(PDO $pdo): array {
    $stmt = $pdo->query(
      "SELECT c.table_name, c.column_name, c.data_type, c.is_nullable, c.column_default,
              (pk.column_name IS NOT NULL) AS is_pk
       FROM information_schema.columns c
       LEFT JOIN (
         SELECT ku.table_name, ku.column_name
         FROM information_schema.table_constraints tc
         JOIN information_schema.key_column_usage ku
           ON tc.constraint_name = ku.constraint_name AND tc.table_schema = ku.table_schema
         WHERE tc.constraint_type = 'PRIMARY KEY' AND tc.table_schema = 'public'
       ) pk ON pk.table_name = c.table_name AND pk.column_name = c.column_name
       WHERE c.table_schema = 'public'
       ORDER BY c.table_name, c.ordinal_position"
    );
    $schema = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $table = $row['table_name'];
      if (!isset($schema[$table])) {
        $schema[$table] = ['name' => $table, 'columns' => []];
      }
      $schema[$table]['columns'][] = [
        'name' => $row['column_name'],
        'type' => $row['data_type'],
        'nullable' => $row['is_nullable'] === 'YES',
        'default' => $row['column_default'],
        'pk' => (bool)$row['is_pk'],
      ];
    }
    return array_values($schema);
  }

  private static function quoteSqliteIdentifier(string $name): string {
    return '"' . str_replace('"', '""', $name) . '"';
  }
}
