<?php

it('lista as rotas de config/routes.php', function () {
  $routes = Introspect::routes();
  expect($routes)->toBeArray()->not->toBeEmpty();

  $home = array_filter($routes, fn($r) => $r['path'] === '/' && $r['method'] === 'GET');
  expect($home)->not->toBeEmpty();
  expect(array_values($home)[0]['handler'])->toBe('app\controllers\HomeController@index');
});

it('retorna o schema do banco, incluindo a tabela migrations', function () {
  $schema = Introspect::schema();
  expect($schema)->toBeArray();

  $names = array_column($schema, 'name');
  expect($names)->toContain('migrations');

  $migrations = array_values(array_filter($schema, fn($t) => $t['name'] === 'migrations'))[0];
  expect($migrations['columns'])->toBeArray()->not->toBeEmpty();
  expect(array_column($migrations['columns'], 'name'))->toContain('migration');
});

it('retorna app info com as chaves esperadas', function () {
  $info = Introspect::appInfo();

  expect($info)->toHaveKeys([
    'framework', 'php_version', 'app_name', 'debug', 'timezone', 'db_driver', 'middlewares',
  ]);
  expect($info['framework'])->toBe('lcloss/php-mvc-skeleton');
  expect($info['db_driver'])->toBe('sqlite');
  expect($info['middlewares'])->toContain('AuthMiddleware', 'WebMiddleware');
});
