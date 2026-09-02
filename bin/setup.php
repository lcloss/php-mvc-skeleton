<?php
/**
 * Executado por `composer create-project` (post-create-project-cmd).
 * Prepara um novo projeto a partir do skeleton: cria o .env e a pasta de storage.
 */

$root = dirname(__DIR__);

$env = $root . '/.env';
$envExample = $root . '/.env.example';

if (!file_exists($env) && file_exists($envExample)) {
    copy($envExample, $env);
    echo "Ficheiro .env criado a partir de .env.example\n";
}

$storageLogs = $root . '/storage/logs';
if (!is_dir($storageLogs)) {
    mkdir($storageLogs, 0777, true);
}

echo "Projeto pronto. Próximos passos:\n";
echo "  1. Ajusta as variáveis em .env\n";
echo "  2. php migrate.php\n";
echo "  3. php -S localhost:8000 -t public\n";
