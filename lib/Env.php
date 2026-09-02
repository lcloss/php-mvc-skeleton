<?php
class Env {
  private static $vars = [];
  public static function load($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      if (strpos(trim($line), '#') === 0) continue;
      $parts = explode('=', $line, 2);
      if (count($parts) === 2) {
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $value = trim($value, "\"' ");
        self::$vars[$key] = $value;
        $_ENV[$key] = $value;
        putenv($key.'='.$value);
      }
    }
  }
  public static function get($key, $default = null) {
    if (array_key_exists($key, self::$vars)) return self::$vars[$key];
    $v = getenv($key);
    return $v !== false ? $v : $default;
  }
  public static function set($key, $value) {
    self::$vars[$key] = $value;
    $_ENV[$key] = $value;
    putenv($key.'='.$value);
  }
}
function env($key, $default = null) { return Env::get($key, $default); }
