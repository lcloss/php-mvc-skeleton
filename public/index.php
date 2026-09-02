<?php
// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load required lib files (not yet autoloaded)
define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/lib/Autoloader.php';
require_once BASE_PATH . '/lib/Env.php';
require_once BASE_PATH . '/lib/Router.php';
require_once BASE_PATH . '/lib/Request.php';
require_once BASE_PATH . '/lib/Blade.php';
require_once BASE_PATH . '/lib/Database.php';
require_once BASE_PATH . '/lib/CSRF.php';
require_once BASE_PATH . '/lib/Mail.php';

// Load and bootstrap the application
$app = new \app\App();
$app->bootstrap();
$app->run();
