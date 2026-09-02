<?php

namespace app\controllers;

use app\controllers\middlewares\WebMiddleware;

class Controller
{
    protected $request;

    public function __construct()
    {
        $this->request = WebMiddleware::getRequest();
    }

    protected function request()
    {
        return $this->request;
    }

    protected function view($view, $data = [])
    {
        $blade = new \Blade();
        return $blade->render($view, $data);
    }

    protected function redirect($url)
    {
        // Em ambiente de teste, não executa exit para permitir que os testes continuem
        if (defined('PHPUNIT_TEST') || getenv('PHPUNIT_TEST')) {
            return;
        }
        header('Location: ' . $url);
        exit;
    }
}
