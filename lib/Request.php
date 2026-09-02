<?php
class Request {
  private $method;
  private $path;
  private $query;
  private $post;
  private $files;
  private $json;
  private $headers;
  private $input;

    public function __construct() {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Normaliza o path: garante barra inicial, remove barra final (exceto raiz)
        $this->path = $path === '/' ? '/' : rtrim($path, '/');

        if ($this->path !== '/' && $this->path[0] !== '/') {
            $this->path = '/' . $this->path;
        }

        $this->query = $_GET ?? [];
        $this->json = json_decode(file_get_contents('php://input'), true) ?? [];

        $this->post = $_POST ?? [];
        $this->files = $_FILES ?? [];
        $this->headers = $this->getHeaders();
        $this->input = array_merge($this->query, $this->post);
    }

  public function method() {
    return $this->method;
  }

  public function path() {
    return $this->path;
  }

  public function get($key = null, $default = null) {
    if ($key === null) {
      return $this->query;
    }
    return $this->query[$key] ?? $default;
  }

  public function post($key = null, $default = null) {
    if ($key === null) {
      return $this->post;
    }
    return $this->post[$key] ?? $default;
  }

  public function input($key = null, $default = null) {
    if ($key === null) {
      return $this->input;
    }
    return $this->input[$key] ?? $default;
  }

  public function file($key = null) {
    if ($key === null) {
      return $this->files;
    }
    return $this->files[$key] ?? null;
  }

  public function has($key) {
    return isset($this->input[$key]);
  }

  public function hasFile($key) {
    return isset($this->files[$key]);
  }

  public function all() {
    return $this->input;
  }

  public function only($keys) {
    $keys = is_array($keys) ? $keys : func_get_args();
    $result = [];
    foreach ($keys as $key) {
      if (isset($this->input[$key])) {
        $result[$key] = $this->input[$key];
      }
    }
    return $result;
  }

  public function except($keys) {
    $keys = is_array($keys) ? $keys : func_get_args();
    $result = $this->input;
    foreach ($keys as $key) {
      unset($result[$key]);
    }
    return $result;
  }

  public function header($key = null, $default = null) {
    if ($key === null) {
      return $this->headers;
    }
    $key = strtolower($key);
    return $this->headers[$key] ?? $default;
  }

  public function isMethod($method) {
    return strtoupper($this->method) === strtoupper($method);
  }

  private function getHeaders() {
    $headers = [];
    foreach ($_SERVER as $key => $value) {
      if (strpos($key, 'HTTP_') === 0) {
        $header = str_replace('_', '-', substr($key, 5));
        $headers[strtolower($header)] = $value;
      }
    }
    return $headers;
  }

  public function json() {
    return $this->json;
  }
}

