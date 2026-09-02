<?php
return [
  'driver' => env('DB_CONNECTION', 'sqlite'),
  'host' => env('DB_HOST', '127.0.0.1'),
  'port' => env('DB_PORT', '3306'),
  'database' => env('DB_DATABASE', ':memory:'),
  'username' => env('DB_USERNAME', ''),
  'password' => env('DB_PASSWORD', ''),
];
