<?php
define('PHPUNIT_TEST', true);
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/Autoloader.php';
require_once __DIR__ . '/../lib/Env.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Blade.php';

Env::set('APP_NAME', 'Test App');
Env::set('DB_CONNECTION', 'sqlite');
Env::set('DB_DATABASE', ':memory:');
Env::set('TIMEZONE', 'UTC');
date_default_timezone_set('UTC');

require_once __DIR__ . '/../migrate.php';
