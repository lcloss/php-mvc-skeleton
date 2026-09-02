<?php
return [
  'name' => env('APP_NAME', 'PHP MVC'),
  'debug' => filter_var(env('DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN),
  'timezone' => env('TIMEZONE', 'UTC'),
  'views_path' => dirname(__DIR__) . '/views',
];
