<?php

use app\controllers\middlewares\AuthMiddleware;
use app\controllers\middlewares\WebMiddleware;

class Router {
  private $routes;
  public function __construct($routes) { $this->routes = $routes; }
  
  /**
   * Converte um padrão de rota com parâmetros em regex e extrai os nomes dos parâmetros
   * Exemplo: /app/membro/{id}/edit -> regex e ['id']
   */
  private function parseRoutePattern($pattern) {
    $paramNames = [];
    $parts = [];
    $currentPos = 0;
    
    // Encontra todos os parâmetros {nome} no padrão
    if (preg_match_all('/\{(\w+)\}/', $pattern, $matches, PREG_OFFSET_CAPTURE)) {
      foreach ($matches[0] as $index => $match) {
        $paramName = $matches[1][$index][0];
        $paramNames[] = $paramName;
        $matchStart = $match[1];
        $matchLength = strlen($match[0]);
        
        // Adiciona a parte antes do parâmetro (escapada)
        if ($matchStart > $currentPos) {
          $parts[] = preg_quote(substr($pattern, $currentPos, $matchStart - $currentPos), '#');
        }
        
        // Adiciona o regex de captura para o parâmetro
        $parts[] = '([^/]+)';
        
        $currentPos = $matchStart + $matchLength;
      }
    }
    
    // Adiciona a parte final (se houver)
    if ($currentPos < strlen($pattern)) {
      $parts[] = preg_quote(substr($pattern, $currentPos), '#');
    }
    
    // Se não havia parâmetros, escapa o padrão inteiro
    if (empty($paramNames)) {
      $parts = [preg_quote($pattern, '#')];
    }
    
    $regex = implode('', $parts);
    
    return [
      'regex' => '#^' . $regex . '$#',
      'paramNames' => $paramNames
    ];
  }
  
  /**
   * Verifica se uma rota corresponde ao path e extrai os parâmetros
   */
  private function matchRoute($routePattern, $path) {
    // Se a rota não tem parâmetros, compara diretamente
    if (strpos($routePattern, '{') === false) {
      return $routePattern === $path ? [] : null;
    }
    
    // Se tem parâmetros, usa regex
    $parsed = $this->parseRoutePattern($routePattern);
    if (preg_match($parsed['regex'], $path, $matches) === 1) {
      array_shift($matches); // Remove o match completo, mantém apenas os grupos
      return array_combine($parsed['paramNames'], $matches);
    }
    
    return null;
  }
  
  /**
   * Obtém os parâmetros do método do controller usando Reflection
   */
  private function getMethodParameters($className, $methodName) {
    try {
      $reflection = new ReflectionMethod($className, $methodName);
      $params = [];
      foreach ($reflection->getParameters() as $param) {
        $params[] = $param->getName();
      }
      return $params;
    } catch (ReflectionException $e) {
      return [];
    }
  }
  
  public function dispatch() {
    // Cria a instância Request
    $request = new Request();
    
    $method = $request->method();
    $path = $request->path();
    
    foreach ($this->routes as $route) {
      if (strtoupper($route['method']) !== strtoupper($method)) {
        continue;
      }
      
      // Tenta fazer match da rota (com ou sem parâmetros)
      $routeParams = $this->matchRoute($route['path'], $path);
      if ($routeParams === null) {
        continue;
      }
      
      $middlewares = $route['middlewares'] ?? [];
      
      // Aplica o app\controllers\middlewares\WebMiddleware primeiro para injetar a Request
      $webMiddleware = new WebMiddleware();
      if (!$webMiddleware->handle($request)) return;
      
      // Aplica os outros middlewares
      foreach ($middlewares as $mw) {
        if ($mw === 'auth') { $m = new AuthMiddleware(); if (!$m->handle($request)) return; }
      }
      
      $handler = $route['handler'];
      if (is_string($handler) && strpos($handler, '@') !== false) {
        list($cls, $meth) = explode('@', $handler, 2);
        $controller = new $cls();
        
        // Obtém os parâmetros esperados pelo método
        $methodParams = $this->getMethodParameters($cls, $meth);
        
        // Prepara os argumentos na ordem correta
        $args = [];
        foreach ($methodParams as $paramName) {
          if (isset($routeParams[$paramName])) {
            $args[] = $routeParams[$paramName];
          }
        }
        
        return call_user_func_array([$controller, $meth], $args);
      }
      if (is_array($handler)) {
        $controller = is_string($handler[0]) ? new $handler[0]() : $handler[0];
        $meth = $handler[1];
        
        // Obtém os parâmetros esperados pelo método
        $methodParams = $this->getMethodParameters(get_class($controller), $meth);
        
        // Prepara os argumentos na ordem correta
        $args = [];
        foreach ($methodParams as $paramName) {
          if (isset($routeParams[$paramName])) {
            $args[] = $routeParams[$paramName];
          }
        }
        
        return call_user_func_array([$controller, $meth], $args);
      }
    }
    http_response_code(404);
    echo 'Not Found';
  }
}
