<?php

namespace app;

class App
{
    public function bootstrap()
    {
        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Define base path
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__));
        }

        // Load environment variables
        $envPath = BASE_PATH . '/.env';
        if (!file_exists($envPath)) {
            $envPath = BASE_PATH . '/.env.example';
        }
        \Env::load($envPath);

        // Load app configuration
        $app = require BASE_PATH . '/config/app.php';
        date_default_timezone_set($app['timezone']);
        ini_set('display_errors', $app['debug'] ? '1' : '0');
    }

    public function run()
    {
        $routes = require BASE_PATH . '/config/routes.php';
        $router = new \Router($routes);
        $router->dispatch();
    }
}
