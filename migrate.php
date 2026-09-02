<?php
define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/lib/Autoloader.php';
require_once BASE_PATH . '/lib/Env.php';
require_once BASE_PATH . '/lib/Database.php';

$app = new \app\App();
$app->bootstrap();

$driver = env('DB_CONNECTION') ?: null;

$migrations = [
    'sqlite' => [
        "CREATE TABLE `migrations` (
            `migration` VARCHAR(255) NOT NULL,
            `created_at` DATETIME DEFAULT current_timestamp
        );",
    ],
    'mysql' => [
        "CREATE TABLE `migrations` (
            `migration` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    ],
    'pgsql' => [
        "CREATE TABLE migrations (
            migration VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT current_timestamp
        );",
    ],
];

// Verifica se a tabela "migrations" existe
if (!Database::checkIfTableExists($driver, 'migrations')) {
    echo "Creating migrations table..." . PHP_EOL;
    Database::createTables($migrations[$driver]);
}

// Lê arquivos PHP de `app/migrations`, ordena e processa
echo "Reading files from migration directory..." . PHP_EOL;
$migrationsDir = BASE_PATH . '/app/migrations';
$files = [];
if (is_dir($migrationsDir)) {
    $files = glob($migrationsDir . '/*.php') ?: [];
    sort($files, SORT_STRING);
}

foreach ($files as $file) {
    $result = include $file;
    $migrationName = basename($file);
    echo "Processing {$migrationName}..." . PHP_EOL;

    $sql = "SELECT COUNT(*) as count FROM migrations WHERE migration = ?";
    $params = [$migrationName];
    $res = Database::executePrepare($sql, $params);
    if ($res && isset($res['count']) && $res['count'] > 0) {
        echo "Migration {$migrationName} already applied. Skipping..." . PHP_EOL;
        continue;
    }

    $queries = [];
    $statements = $result[$driver] ?? null;

    if (is_string($statements)) {
        $queries = [$statements];
    } elseif (is_array($statements)) {
        $queries = $statements;
    }

    if (!empty($queries)) {
        foreach ($queries as $query) {
            echo "Running query: " . trim($query) . PHP_EOL;
            if (preg_match('/CREATE\s+TABLE\s+`?([\w_]+)`?/i', $query, $matches)) {
                $tableName = $matches[1];
                if (Database::checkIfTableExists($driver, $tableName)) {
                    echo "Table {$tableName} already exists. Skipping creation..." . PHP_EOL;
                    $insertSql = "INSERT INTO migrations (migration, created_at) VALUES (?, ?)";
                    Database::executePrepare($insertSql, [$migrationName, date('Y-m-d H:i:s')]);
                    continue;
                }
            }
            Database::createTables($queries);
            $insertSql = "INSERT INTO migrations (migration, created_at) VALUES (?, ?)";
            Database::executePrepare($insertSql, [$migrationName, date('Y-m-d H:i:s')]);
        }
    }
}
