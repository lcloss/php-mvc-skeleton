# PHP MVC Skeleton

Framework PHP simples e leve — sem dependências pesadas — para páginas e pequenos sites (landing pages, formulários, painéis simples). Extraído e generalizado a partir do projeto [desempenho-mais-2](../desempenho-mais-2).

Inclui:

- **Router** com suporte a parâmetros de rota (`/posts/{id}`) e middlewares.
- **Blade**, um motor de templates próprio (subset do Laravel Blade: `@extends`, `@section`, `@yield`, `@include`, `@if`/`@else`, `@foreach`, `{{ }}` / `{!! !!}`, `@csrf`).
- **Database**, wrapper PDO com suporte a SQLite, MySQL e PostgreSQL, e um mini sistema de migrações (`migrate.php`, ficheiros em `app/migrations`).
- **Model** base com `create`/`find`/`all`/`update`/`delete`.
- **Request**, **CSRF**, **Env** (`.env` sem dependências externas) e **Mail** (via PHPMailer).
- Testes com [Pest](https://pestphp.com/).

## Usar como template para um novo projeto

```bash
composer create-project lcloss/php-mvc-skeleton meu-novo-projeto
cd meu-novo-projeto
php -S localhost:8000 -t public
```

O `composer create-project` corre automaticamente `bin/setup.php`, que cria o `.env` a partir do `.env.example`.

## Desenvolvimento local (a partir deste repositório)

```bash
composer install
cp .env.example .env
php migrate.php
php -S localhost:8000 -t public
composer test
```

## Estrutura

```
app/
  App.php                 # bootstrap da aplicação
  controllers/            # controllers (namespace app\controllers)
  controllers/middlewares/
  models/                 # models (namespace app\models)
  migrations/             # uma migração por ficheiro, com SQL por driver
config/
  app.php, database.php, routes.php
lib/                      # núcleo da framework (sem namespace, carregado pelo Autoloader)
public/
  index.php               # front controller
  .htaccess
views/                    # templates .blade.php
storage/logs/
tests/
```

## Rotas

Definidas em `config/routes.php`:

```php
[
    'method' => 'GET',
    'path' => '/posts/{id}',
    'handler' => 'app\controllers\PostController@show',
    'middlewares' => ['auth'],
],
```

## Publicar no Packagist

1. Ajusta `name` em `composer.json` (`vendor/pacote`).
2. Faz push para o GitHub e submete o repositório em [packagist.org](https://packagist.org).
3. Ativa o webhook do GitHub para atualizações automáticas.

## Licença

MIT.
