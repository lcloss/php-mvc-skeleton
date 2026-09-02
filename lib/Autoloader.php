<?php
spl_autoload_register(function ($class) {
  $base = dirname(__DIR__);
  $paths = [
    '/app/controllers/' . $class . '.php',
    '/app/controllers/middlewares/' . $class . '.php',
    '/app/models/' . $class . '.php',
    '/lib/' . $class . '.php',
  ];
  foreach ($paths as $rel) {
    $file = $base . $rel;
    if (file_exists($file)) { require_once $file; return; }
  }
});
