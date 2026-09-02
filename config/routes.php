<?php
return [
    [
        'method' => 'GET',
        'path' => '/',
        'handler' => 'app\controllers\HomeController@index',
        'middlewares' => [],
    ],

    // Exemplo de rota com parâmetro:
    // [
    //     'method' => 'GET',
    //     'path' => '/posts/{id}',
    //     'handler' => 'app\controllers\PostController@show',
    //     'middlewares' => [],
    // ],

    // Exemplo de rota protegida por autenticação:
    // [
    //     'method' => 'GET',
    //     'path' => '/dashboard',
    //     'handler' => 'app\controllers\DashboardController@index',
    //     'middlewares' => ['auth'],
    // ],
];
