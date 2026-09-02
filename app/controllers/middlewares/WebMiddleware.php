<?php

namespace app\controllers\middlewares;
class WebMiddleware
{
    private static $request = null;

    public function handle($request)
    {
        // Armazena a instância Request para acesso global
        self::$request = $request;
        return true;
    }

    public static function getRequest()
    {
        return self::$request;
    }
}
