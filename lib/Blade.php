<?php
/**
 * Blade Template Engine
 * 
 * A lightweight Blade template engine implementation that supports:
 * - Template inheritance (@extends, @section, @yield)
 * - Control structures (@if, @foreach)
 * - Variable output ({{ }}, {!! !!})
 * - Includes (@include)
 * - Asset management (@asset)
 * - Static method calls
 * 
 * @package Blade
 */
class Blade {
  /**
   * The base path where Blade template files are stored
   * @var string
   */
  private $viewsPath;
  
  /**
   * Constructor: Initialize the Blade template engine with a views directory path
   * 
   * If no path is provided, it will attempt to read 'views_path' from config/app.php.
   * The config file must be located at the project root in /config/app.php.
   * 
   * @param string|null $viewsPath Optional. The path to the directory containing Blade template files.
   *                                If not provided, reads from config/app.php['views_path']
   */
  public function __construct($viewsPath = null) {
    if ($viewsPath === null) {
      // Load config/app.php to get the default views path
      $configPath = dirname(__DIR__) . '/config/app.php';
      if (file_exists($configPath)) {
        $config = require $configPath;
        $viewsPath = $config['views_path'] ?? dirname(__DIR__) . '/views';
      } else {
        // Fallback to default path if config file doesn't exist
        $viewsPath = dirname(__DIR__) . '/views';
      }
    }
    
    // Remove trailing slash if present to ensure consistent path formatting
    $this->viewsPath = rtrim($viewsPath, '/');
  }
  
  /**
   * Set the views path (override the default config)
   * 
   * Allows changing the views directory path after instantiation.
   * Useful for testing or when you need to override the default configuration.
   * 
   * @param string $viewsPath The path to the directory containing Blade template files
   * @return void
   */
  public function setPath($viewsPath) {
    // Remove trailing slash if present to ensure consistent path formatting
    $this->viewsPath = rtrim($viewsPath, '/');
  }
  
  /**
   * Get the full file path for a given view name
   * 
   * Constructs the complete file path by combining:
   * - The base views directory path
   * - The view name (supports dot syntax like 'layouts.front' -> 'layouts/front')
   * - The .blade.php extension
   * 
   * Example: path('home') returns '/path/to/views/home.blade.php'
   * Example: path('layouts.front') returns '/path/to/views/layouts/front.blade.php'
   * 
   * @param string $view The view name (without .blade.php extension), supports dot syntax
   * @return string The full file path to the Blade template
   */
  private function path($view) {
    // Convert dot syntax to directory separator (e.g., 'layouts.front' -> 'layouts/front')
    $viewPath = str_replace('.', '/', $view);
    return $this->viewsPath . '/' . $viewPath . '.blade.php';
  }
  
  /**
   * Extract and parse @extends directive from view content
   * 
   * Looks for @extends directive in the format:
   * - @extends('layouts.front')
   * - @extends("layouts.front")
   * 
   * Removes the directive from content and returns the layout name.
   * Supports dot syntax which will be converted to path.
   * 
   * @param string $content The view content to parse (passed by reference)
   * @return array Array with 'layout' (string|null) and 'content' (string without @extends)
   */
  private function extractExtends(&$content) {
    $layout = null;
    
    // Pattern to match @extends with parentheses: @extends('layouts.front') or @extends("layouts.front")
    $pattern = '/@extends\s*\(\s*[\'"](.+?)[\'"]\s*\)\s*\r?\n?/';
    
    if (preg_match($pattern, $content, $matches)) {
      $layout = trim($matches[1]);
      // Remove the @extends directive from content
      $content = preg_replace($pattern, '', $content);
    }
    
    return [
      'layout' => $layout,
      'content' => $content
    ];
  }
  
  /**
   * Extract sections from view content
   * 
   * Looks for @section('name') ... @endsection patterns and extracts the content.
   * Removes the @section and @endsection directives from content.
   * 
   * @param string $content The view content to parse (passed by reference)
   * @return array Array with section names as keys and their content as values
   */
  private function extractSections(&$content) {
    $sections = [];
    
    // Pattern to match @section('name') ... @endsection
    // Supports single and double quotes, with optional whitespace
    // The pattern uses non-greedy matching and dot-all flag to capture multiline content
    $pattern = '/@section\s*\(\s*[\'"](.+?)[\'"]\s*\)\s*\r?\n(.*?)@endsection/s';
    
    if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $sectionName = trim($match[1]);
        $sectionContent = $match[2];
        
        // Store the section content (trim leading/trailing whitespace)
        $sections[$sectionName] = $sectionContent;
        
        // Remove the @section ... @endsection block from content
        $content = str_replace($match[0], '', $content);
      }
    }
    
    return $sections;
  }
  
  /**
   * Get variable value from data array
   * 
   * Removes the $ prefix from variable names if present and retrieves the value.
   * 
   * @param string $varName Variable name (with or without $ prefix)
   * @param array $data Data array containing variables
   * @return mixed The variable value or null if not found
   */
  private function getVariableValue($varName, $data) {
    // Remove $ prefix if present
    $varName = ltrim($varName, '$');
    return $data[$varName] ?? null;
  }
  
  /**
   * Parse inline array expression
   * 
   * Parses array literals like [1, 2, 3] or ['a', 'b', 'c'].
   * Handles quoted strings, numbers, and respects nested quotes.
   * 
   * @param string $expression The expression to parse (e.g., "[1, 2, 'a']")
   * @return array|null Parsed array or null if not an array expression
   */
  private function parseInlineArray($expression) {
    // Check if expression is an array literal
    if (!preg_match('/^\s*\[(.*?)\]\s*$/', $expression, $matches)) {
      return null;
    }
    
    $items = [];
    $content = trim($matches[1]);
    
    if (empty($content)) {
      return $items; // Empty array
    }
    
    // Split by comma, respecting quoted strings
    $parts = $this->splitArrayExpression($content);
    
    // Process each part
    foreach ($parts as $part) {
      $part = trim($part);
      
      // Remove quotes if present (handles both single and double quotes)
      if ($this->isQuotedString($part)) {
        $items[] = substr($part, 1, -1);
      } else {
        // Numeric or variable - convert to appropriate type
        $items[] = $this->parseArrayItem($part);
      }
    }
    
    return $items;
  }
  
  /**
   * Split array expression by commas, respecting quoted strings
   * 
   * @param string $content The array content (without brackets)
   * @return array Array of parts
   */
  private function splitArrayExpression($content) {
    $parts = [];
    $current = '';
    $inQuotes = false;
    $quoteChar = '';
    
    for ($i = 0; $i < strlen($content); $i++) {
      $char = $content[$i];
      $isEscaped = ($i > 0 && $content[$i - 1] === '\\');
      
      // Handle quote characters
      if (($char === '"' || $char === "'") && !$isEscaped) {
        if (!$inQuotes) {
          $inQuotes = true;
          $quoteChar = $char;
        } elseif ($char === $quoteChar) {
          $inQuotes = false;
          $quoteChar = '';
        }
        $current .= $char;
      } 
      // Handle comma separator (only when not in quotes)
      elseif ($char === ',' && !$inQuotes) {
        $parts[] = trim($current);
        $current = '';
      } else {
        $current .= $char;
      }
    }
    
    // Add the last part if exists
    if (!empty($current)) {
      $parts[] = trim($current);
    }
    
    return $parts;
  }
  
  /**
   * Check if a string is quoted (single or double quotes)
   * 
   * @param string $str The string to check
   * @return bool True if the string is quoted
   */
  private function isQuotedString($str) {
    $len = strlen($str);
    if ($len < 2) {
      return false;
    }
    
    return (($str[0] === '"' && $str[$len - 1] === '"') ||
            ($str[0] === "'" && $str[$len - 1] === "'"));
  }
  
  /**
   * Parse an array item (numeric or variable)
   * 
   * @param string $part The part to parse
   * @return mixed Parsed value (int, float, or string)
   */
  private function parseArrayItem($part) {
    if (is_numeric($part)) {
      // Convert to int or float as appropriate
      return (intval($part) == $part) ? intval($part) : floatval($part);
    }
    
    // Return as string (could be a variable name)
    return $part;
  }
  
  /**
   * Parse function call arguments
   * 
   * Splits function arguments by comma, respecting quotes and nested parentheses.
   * 
   * @param string $argsStr The arguments string
   * @return array Array of argument strings
   */
  private function parseFunctionArguments($argsStr) {
    $args = [];
    $current = '';
    $depth = 0;
    $inQuotes = false;
    $quoteChar = '';
    
    for ($i = 0; $i < strlen($argsStr); $i++) {
      $char = $argsStr[$i];
      $isEscaped = ($i > 0 && $argsStr[$i - 1] === '\\');
      
      // Handle quotes
      if (($char === '"' || $char === "'") && !$isEscaped) {
        if (!$inQuotes) {
          $inQuotes = true;
          $quoteChar = $char;
        } elseif ($char === $quoteChar) {
          $inQuotes = false;
          $quoteChar = '';
        }
        $current .= $char;
      }
      // Handle parentheses (track nesting depth)
      elseif ($char === '(' && !$inQuotes) {
        $depth++;
        $current .= $char;
      }
      elseif ($char === ')' && !$inQuotes) {
        if ($depth > 0) {
          $depth--;
        }
        $current .= $char;
      }
      // Handle comma separator (only at top level, not in quotes)
      elseif ($char === ',' && !$inQuotes && $depth === 0) {
        $args[] = trim($current);
        $current = '';
      } else {
        $current .= $char;
      }
    }
    
    // Add the last argument if exists
    if (strlen(trim($current)) > 0) {
      $args[] = trim($current);
    }
    
    return $args;
  }
  
  /**
   * Evaluate an expression and return its value
   * 
   * Supports:
   * - Inline arrays: [1, 2, 3]
   * - Function calls: count($arr), array_key_exists('key', $arr), json_encode($data)
   * - Variables: $var, $var['key'], $var[0]
   * - Constants: JSON_PRETTY_PRINT, etc.
   * 
   * @param string $expression The expression to evaluate
   * @param array $data The data array containing variables
   * @return mixed The evaluated value or null
   */
  private function evaluateExpression($expression, $data) {
    $expression = trim($expression);
    
    // Handle inline arrays
    $array = $this->parseInlineArray($expression);
    if ($array !== null) {
      return $array;
    }
    
    // Handle function calls: functionName(arg1, arg2, ...)
    $functionResult = $this->evaluateFunctionCall($expression, $data);
    if ($functionResult !== null) {
      return $functionResult;
    }
    
    // Handle constants (e.g., JSON_PRETTY_PRINT)
    if (defined($expression)) {
      return constant($expression);
    }
    
    // Handle variables with optional array access: $var['key'][0] or $var[$i]
    $variableResult = $this->evaluateVariable($expression, $data);
    if ($variableResult !== null) {
      return $variableResult;
    }
    
    return null;
  }
  
  /**
   * Evaluate a function call expression
   * 
   * Supports specific functions (count, array_key_exists, old) and generic PHP functions
   * like json_encode, strlen, etc.
   * 
   * @param string $expression The function call expression
   * @param array $data The data array
   * @return mixed Function result or null if not a function call
   */
  private function evaluateFunctionCall($expression, $data) {
    // Match function pattern: functionName(arg1, arg2, ...)
    if (!preg_match('/^([a-z_]\w*)\s*\((.*)\)$/i', $expression, $matches)) {
      return null;
    }
    
    $functionName = strtolower($matches[1]);
    $argsStr = $matches[2];
    $args = $this->parseFunctionArguments($argsStr);
    
    // Handle count() function
    if ($functionName === 'count') {
      $value = isset($args[0]) ? $this->evaluateExpression($args[0], $data) : null;
      return is_array($value) ? count($value) : 0;
    }
    
    // Handle array_key_exists() function
    if ($functionName === 'array_key_exists') {
      return $this->evaluateArrayKeyExists($args, $data);
    }
    
    // Handle old() function for form validation
    if ($functionName === 'old') {
      return $this->evaluateOld($args, $data);
    }
    
    // Handle generic PHP functions (json_encode, strlen, etc.)
    if (function_exists($functionName)) {
      // Evaluate all arguments
      $evaluatedArgs = [];
      foreach ($args as $arg) {
        $evaluatedArgs[] = $this->evaluateExpression($arg, $data);
      }
      
      // Call the function with evaluated arguments
      try {
        return call_user_func_array($functionName, $evaluatedArgs);
      } catch (Exception $e) {
        // If function call fails, return null
        return null;
      }
    }
    
    return null;
  }
  
  /**
   * Evaluate array_key_exists function call
   * 
   * @param array $args Function arguments
   * @param array $data The data array
   * @return bool True if key exists in array
   */
  private function evaluateArrayKeyExists($args, $data) {
    $keyArg = $args[0] ?? null;
    $arrArg = $args[1] ?? null;
    
    if ($keyArg === null || $arrArg === null) {
      return false;
    }
    
    // Parse key argument (could be quoted string or expression)
    $key = null;
    if ($this->isQuotedString($keyArg)) {
      $key = substr($keyArg, 1, -1);
    } else {
      $key = $this->evaluateExpression($keyArg, $data);
    }
    
    // Parse array argument
    $arr = $this->evaluateExpression($arrArg, $data);
    
    return is_array($arr) ? array_key_exists($key, $arr) : false;
  }
  
  /**
   * Evaluate old() function call for form validation
   * 
   * Retrieves old input values from session for form repopulation.
   * Usage: old('field_name') or old('field_name', 'default')
   * 
   * @param array $args Function arguments
   * @param array $data The data array
   * @return mixed Old input value or default/null
   */
  private function evaluateOld($args, $data) {
    $keyArg = $args[0] ?? null;
    $defaultArg = $args[1] ?? null;
    
    if ($keyArg === null) {
      return null;
    }
    
    // Parse key argument (could be quoted string or expression)
    $key = null;
    if ($this->isQuotedString($keyArg)) {
      $key = substr($keyArg, 1, -1);
    } else {
      $key = $this->evaluateExpression($keyArg, $data);
    }
    
    // Get old input from session
    $oldInput = $_SESSION['old_input'] ?? [];
    
    if (isset($oldInput[$key])) {
      return $oldInput[$key];
    }
    
    // Return default value if provided
    if ($defaultArg !== null) {
      return $this->evaluateExpression($defaultArg, $data);
    }
    
    return null;
  }
  
  /**
   * Evaluate a variable expression with optional array access
   * 
   * Supports: $var, $var['key'], $var[0], $var[$index]
   * 
   * @param string $expression The variable expression
   * @param array $data The data array
   * @return mixed Variable value or null
   */
  private function evaluateVariable($expression, $data) {
    // Match variable pattern: $varName or varName followed by optional array access
    if (!preg_match('/^\$?([A-Za-z_]\w*)(.*)$/', $expression, $matches)) {
      return null;
    }
    
    $varName = $matches[1];
    $rest = $matches[2];
    $value = $this->getVariableValue($varName, $data);
    
    // Handle array access: ['key'], ["key"], [0], [$index]
    while (!empty($rest) && preg_match('/^\[\s*(?:\'([^\']*)\'|\"([^\"]*)\"|(\d+)|\$?([A-Za-z_]\w*))\s*\](.*)$/', $rest, $arrayMatches)) {
      // Determine the key value
      $key = null;
      if ($arrayMatches[1] !== '') {
        // Single-quoted string key
        $key = $arrayMatches[1];
      } elseif ($arrayMatches[2] !== '') {
        // Double-quoted string key
        $key = $arrayMatches[2];
      } elseif (strlen($arrayMatches[3]) > 0) {
        // Numeric key
        $key = (int)$arrayMatches[3];
      } elseif ($arrayMatches[4] !== '') {
        // Variable key
        $key = $this->getVariableValue($arrayMatches[4], $data);
      }
      
      $rest = $arrayMatches[5];
      
      // Access the array element if it exists
      if (is_array($value) && array_key_exists($key, $value)) {
        $value = $value[$key];
      } else {
        $value = null;
        break;
      }
    }
    
    return $value;
  }
  
  /**
   * Find operator position in condition string
   * 
   * Searches for logical operators (||, &&) respecting quotes and parentheses.
   * 
   * @param string $condition The condition string
   * @param string $operator The operator to find ('||' or '&&')
   * @return int Position of operator or -1 if not found
   */
  private function findOperatorPosition($condition, $operator) {
    $len = strlen($condition);
    $operatorLen = strlen($operator);
    $pos = -1;
    $depth = 0;
    $inQuotes = false;
    $quoteChar = '';
    
    for ($i = 0; $i <= $len - $operatorLen; $i++) {
      $char = $condition[$i];
      $isEscaped = ($i > 0 && $condition[$i - 1] === '\\');
      
      // Handle quotes
      if (($char === '"' || $char === "'") && !$isEscaped) {
        if (!$inQuotes) {
          $inQuotes = true;
          $quoteChar = $char;
        } elseif ($char === $quoteChar) {
          $inQuotes = false;
          $quoteChar = '';
        }
      }
      // Handle parentheses (track nesting depth)
      elseif ($char === '(' && !$inQuotes) {
        $depth++;
      }
      elseif ($char === ')' && !$inQuotes) {
        if ($depth > 0) {
          $depth--;
        }
      }
      // Check for operator at top level (not in quotes, depth 0)
      if (!$inQuotes && $depth === 0) {
        $substr = substr($condition, $i, $operatorLen);
        if ($substr === $operator) {
          $pos = $i;
          break;
        }
      }
    }
    
    return $pos;
  }
  
  /**
   * Evaluate a comparison expression
   * 
   * Handles operators: ===, !==, ==, !=, <=, >=, <, >
   * 
   * @param string $leftExpr Left side expression
   * @param string $operator Comparison operator
   * @param string $rightExpr Right side expression
   * @param array $data The data array
   * @return bool Comparison result
   */
  private function evaluateComparison($leftExpr, $operator, $rightExpr, $data) {
    $left = $this->evaluateExpression($leftExpr, $data);
    $right = $this->parseRightExpression($rightExpr, $data);
    
    // Strict comparisons
    if ($operator === '===') {
      return $left === $right;
    }
    if ($operator === '!==') {
      return $left !== $right;
    }
    
    // Loose comparisons
    if ($operator === '==') {
      return $left == $right;
    }
    if ($operator === '!=') {
      return $left != $right;
    }
    
    // Numeric comparisons
    return $this->evaluateNumericComparison($left, $right, $operator);
  }
  
  /**
   * Parse the right side of a comparison expression
   * 
   * Handles quoted strings, numbers, and variable expressions.
   * 
   * @param string $rightExpr The right expression
   * @param array $data The data array
   * @return mixed Parsed value
   */
  private function parseRightExpression($rightExpr, $data) {
    $rightExpr = trim($rightExpr);
    
    // Check if it's a quoted string
    if ($this->isQuotedString($rightExpr)) {
      return substr($rightExpr, 1, -1);
    }
    
    // Check if it's a number
    if (is_numeric($rightExpr)) {
      return (intval($rightExpr) == $rightExpr) ? intval($rightExpr) : floatval($rightExpr);
    }
    
    // Try to evaluate as expression
    $evaluated = $this->evaluateExpression($rightExpr, $data);
    return $evaluated !== null ? $evaluated : $rightExpr;
  }
  
  /**
   * Evaluate numeric comparison (<, >, <=, >=)
   * 
   * Converts values to numbers for comparison, or strings if not numeric.
   * 
   * @param mixed $left Left value
   * @param mixed $right Right value
   * @param string $operator Comparison operator
   * @return bool Comparison result
   */
  private function evaluateNumericComparison($left, $right, $operator) {
    // Convert to numbers if both are numeric
    if (is_numeric($left) && is_numeric($right)) {
      $leftNum = (intval($left) == $left) ? intval($left) : floatval($left);
      $rightNum = (intval($right) == $right) ? intval($right) : floatval($right);
    } else {
      // Convert to strings for comparison
      $leftNum = is_scalar($left) ? (string)$left : '';
      $rightNum = is_scalar($right) ? (string)$right : '';
    }
    
    switch ($operator) {
      case '<':
        return $leftNum < $rightNum;
      case '>':
        return $leftNum > $rightNum;
      case '<=':
        return $leftNum <= $rightNum;
      case '>=':
        return $leftNum >= $rightNum;
      default:
        return false;
    }
  }
  
  /**
   * Evaluate a condition expression
   * 
   * Supports:
   * - Logical operators: ||, &&
   * - Negation: !
   * - Comparisons: ===, !==, ==, !=, <=, >=, <, >
   * - Variables: $var
   * - Function calls
   * 
   * @param string $condition The condition to evaluate
   * @param array $data The data array
   * @return bool The condition result
   */
  private function evaluateCondition($condition, $data) {
    $condition = trim($condition);
    
    // Remove outer parentheses if present
    $condition = preg_replace('/^\((.*)\)$/', '$1', $condition);
    $condition = trim($condition);
    
    // Handle logical OR operator (||) - lowest precedence
    $orPos = $this->findOperatorPosition($condition, '||');
    if ($orPos !== -1) {
      $left = substr($condition, 0, $orPos);
      $right = substr($condition, $orPos + 2);
      return $this->evaluateCondition($left, $data) || $this->evaluateCondition($right, $data);
    }
    
    // Handle logical AND operator (&&) - higher precedence than OR
    $andPos = $this->findOperatorPosition($condition, '&&');
    if ($andPos !== -1) {
      $left = substr($condition, 0, $andPos);
      $right = substr($condition, $andPos + 2);
      return $this->evaluateCondition($left, $data) && $this->evaluateCondition($right, $data);
    }
    
    // Handle negation operator (!)
    if (preg_match('/^!\s*(.+)$/', $condition, $matches)) {
      return !$this->evaluateCondition($matches[1], $data);
    }
    
    // Handle simple variable check: $var (truthy check)
    if (preg_match('/^\$?(\w+)$/', $condition, $matches)) {
      $var = $this->getVariableValue($matches[1], $data);
      return $var && $var !== '' && $var !== 0 && $var !== false && (!is_array($var) || !empty($var));
    }
    
    // Handle comparison operators: ===, !==, ==, !=, <=, >=, <, >
    if (preg_match('/^(.*?)\s*(===|!==|==|!=|<=|>=|<|>)\s*(.*?)$/', $condition, $matches)) {
      $leftExpr = trim($matches[1]);
      $operator = $matches[2];
      $rightExpr = trim($matches[3]);
      return $this->evaluateComparison($leftExpr, $operator, $rightExpr, $data);
    }
    
    // Handle function calls
    if (preg_match('/^([a-z_]\w*)\s*\((.*)\)$/i', $condition, $funcMatch)) {
      $val = $this->evaluateExpression($condition, $data);
      if (is_bool($val)) {
        return $val;
      }
      return $val ? true : false;
    }
    
    return false;
  }
  
  /**
   * Process static method calls in template
   * 
   * Handles patterns like {{ ClassName::methodName('arg1', 'arg2') }}
   * 
   * @param string $content The template content
   * @param array $data The data array
   * @return string Content with static method calls processed
   */
  private function processStaticMethodCalls($content, $data) {
    return preg_replace_callback('/{{\s*([A-Z]\w+)::(\w+)\s*\(([^)]*)\)\s*}}/', function($matches) use ($data) {
      $className = $matches[1];
      $methodName = $matches[2];
      $argsStr = trim($matches[3]);
      
      // Parse arguments (simple string arguments)
      $args = [];
      if (!empty($argsStr)) {
        // Remove quotes and extract arguments
        $argsStr = preg_replace('/[\'"]/', '', $argsStr);
        $args = array_map('trim', explode(',', $argsStr));
      }
      
      // Execute the static method call
      try {
        if (class_exists($className)) {
          $reflection = new ReflectionClass($className);
          if ($reflection->hasMethod($methodName)) {
            $method = $reflection->getMethod($methodName);
            if ($method->isStatic()) {
              return $method->invokeArgs(null, $args);
            }
          }
        }
      } catch (Exception $e) {
        // If execution fails, return empty string
        return '';
      }
      
      return '';
    }, $content);
  }
  
  /**
   * Process unescaped variables in template
   * 
   * Handles patterns like {{ $var }} or {{ $var['key'] }} - raw output (not HTML escaped)
   * 
   * @param string $content The template content
   * @param array $data The data array
   * @return string Content with variables processed
   */
  private function processUnescapedVariables($content, $data) {
    return preg_replace_callback('/{{\s*(.+?)\s*}}/', function($matches) use ($data) {
      $value = $this->evaluateExpression($matches[1], $data);
      return is_scalar($value) ? (string)$value : '';
    }, $content);
  }
  
  /**
   * Process escaped variables in template
   * 
   * Handles patterns like {!! $var !!} - HTML escaped (safe output)
   * 
   * @param string $content The template content
   * @param array $data The data array
   * @return string Content with escaped variables processed
   */
  private function processEscapedVariables($content, $data) {
    return preg_replace_callback('/{!!\s*(.+?)\s*!!}/', function($matches) use ($data) {
      $value = $this->evaluateExpression($matches[1], $data);
      return htmlspecialchars(is_scalar($value) ? (string)$value : '', ENT_QUOTES, 'UTF-8');
    }, $content);
  }
  
  /**
   * Process variables in template content
   * 
   * Processes static method calls, unescaped variables ({{ }}), and escaped variables ({!! !!}).
   * 
   * @param string $content The template content
   * @param array $data The data array
   * @return string Content with all variables processed
   */
  private function processVariables($content, $data) {
    // Process static method calls first {{ ClassName::method() }}
    $content = $this->processStaticMethodCalls($content, $data);
    
    // Process unescaped variables {{ $var['key'] }} - raw output
    $content = $this->processUnescapedVariables($content, $data);
    
    // Process escaped variables {!! $var['key'] !!} - HTML escaped (safe)
    $content = $this->processEscapedVariables($content, $data);
    
    return $content;
  }
  
  /**
   * Parse @foreach header to extract array, item, and key variables
   * 
   * @param string $header The @foreach header line
   * @param array $data The data array
   * @return array Array with 'arrayVar', 'itemVar', 'keyVar' keys
   */
  private function parseForeachHeader($header, $data) {
    $arrayVar = null;
    $itemVar = null;
    $keyVar = null;
    
    // Pattern 1: @foreach($array as $item) or @foreach($array as $key => $item)
    if (preg_match('/^@foreach\s*\((.+)\)$/', $header, $matches)) {
      $expression = trim($matches[1]);
      
      // Match: arrayExpr as $itemVar or arrayExpr as $keyVar => $itemVar
      if (preg_match('/^(.+?)\s+as\s+\$?(\w+)(?:\s*=>\s+\$?(\w+))?$/', $expression, $exprMatches)) {
        $arrayExpr = trim($exprMatches[1]);
        
        // Determine key and item variables
        if (!empty($exprMatches[3])) {
          $keyVar = $exprMatches[2];
          $itemVar = $exprMatches[3];
        } else {
          $itemVar = $exprMatches[2];
        }
        
        // Try to parse as inline array first, then evaluate as expression
        $arrayVar = $this->parseInlineArray($arrayExpr);
        if ($arrayVar === null) {
          $arrayVar = $this->evaluateExpression($arrayExpr, $data);
        }
      }
    }
    // Pattern 2: @foreach arrayVar as itemVar or @foreach arrayVar as keyVar => itemVar
    elseif (preg_match('/^@foreach\s*(\w+)\s+as\s+(\w+)(?:\s*=>\s*(\w+))?$/', $header, $exprMatches)) {
      $arrayVar = $this->getVariableValue($exprMatches[1], $data);
      
      if (!empty($exprMatches[3])) {
        $keyVar = $exprMatches[2];
        $itemVar = $exprMatches[3];
      } else {
        $itemVar = $exprMatches[2];
      }
    }
    
    return [
      'arrayVar' => $arrayVar,
      'itemVar' => $itemVar,
      'keyVar' => $keyVar
    ];
  }
  
  /**
   * Find the matching @endforeach for a @foreach directive
   * 
   * Handles nested @foreach directives by tracking depth.
   * 
   * @param string $content The template content
   * @param int $startPos Starting position after @foreach header
   * @return int Position of the matching @endforeach
   */
  private function findMatchingEndforeach($content, $startPos) {
    $pos = $startPos;
    $depth = 1;
    $len = strlen($content);
    
    while ($pos < $len && $depth > 0) {
      $nextForeach = strpos($content, '@foreach', $pos);
      $nextEnd = strpos($content, '@endforeach', $pos);
      
      if ($nextEnd === false) {
        $nextEnd = $len;
      }
      
      // If we find another @foreach before @endforeach, increase depth
      if ($nextForeach !== false && $nextForeach < $nextEnd) {
        $depth++;
        $pos = $nextForeach + 9; // Length of '@foreach'
      } else {
        // Found matching @endforeach
        $depth--;
        $pos = $nextEnd + 11; // Length of '@endforeach'
      }
    }
    
    return $pos - 11; // Return position of start of @endforeach
  }
  
  /**
   * Process @foreach directives in template
   * 
   * Handles @foreach($array as $item) ... @endforeach patterns.
   * Supports nested foreach loops and key-value iteration.
   * 
   * @param string $content The template content
   * @param array $data The data array (passed by reference to allow variable updates)
   * @return string Content with @foreach directives processed
   */
  private function processForeach($content, &$data) {
    $output = '';
    $offset = 0;
    
    while (true) {
      // Find next @foreach directive
      $start = strpos($content, '@foreach', $offset);
      if ($start === false) {
        // No more @foreach directives, append remaining content
        $output .= substr($content, $offset);
        break;
      }
      
      // Append content before @foreach
      $output .= substr($content, $offset, $start - $offset);
      
      // Find end of @foreach header (end of line)
      $headerEnd = strpos($content, "\n", $start);
      if ($headerEnd === false) {
        // No newline found, append remaining and break
        $output .= substr($content, $start);
        break;
      }
      
      // Extract header line
      $header = substr($content, $start, $headerEnd - $start);
      $header = rtrim($header, "\r");
      
      // Parse foreach header to get array, item, and key variables
      $foreachInfo = $this->parseForeachHeader($header, $data);
      $arrayVar = $foreachInfo['arrayVar'];
      $itemVar = $foreachInfo['itemVar'];
      $keyVar = $foreachInfo['keyVar'];
      
      // If parsing failed, skip this @foreach
      if ($arrayVar === null || $itemVar === null) {
        $offset = $headerEnd + 1;
        continue;
      }
      
      // Find matching @endforeach
      $blockEnd = $this->findMatchingEndforeach($content, $headerEnd + 1);
      $blockContent = substr($content, $headerEnd + 1, $blockEnd - ($headerEnd + 1));
      
      // Skip if array is not actually an array
      if (!is_array($arrayVar)) {
        $offset = $blockEnd + 11; // Skip past @endforeach
        continue;
      }
      
      // Process the loop
      $result = '';
      $lastValue = null;
      $lastKey = null;
      
      foreach ($arrayVar as $key => $value) {
        // Create loop data with current item
        $loopData = $data;
        $loopData[$itemVar] = $value;
        $lastValue = $value;
        
        // Add key variable if specified
        if ($keyVar !== null) {
          $loopData[$keyVar] = $key;
          $lastKey = $key;
        }
        
        // Process block content with loop data
        $processedBlock = $this->processDirectives($blockContent, $loopData);
        $processedBlock = $this->processVariables($processedBlock, $loopData);
        $result .= $processedBlock;
      }
      
      // Update data with last values (for use after loop)
      if ($lastValue !== null) {
        $data[$itemVar] = $lastValue;
      }
      if ($keyVar !== null && $lastKey !== null) {
        $data[$keyVar] = $lastKey;
      }
      
      $output .= $result;
      $offset = $blockEnd + 11; // Move past @endforeach
    }
    
    return $output;
  }
  
  /**
   * Parse @if condition from template
   * 
   * Extracts the condition from @if directive, handling both parenthesized and non-parenthesized forms.
   * 
   * @param string $content The template content
   * @param int $startPos Position of @if directive
   * @return array Array with 'condition' and 'headerEnd' keys
   */
  private function parseIfCondition($content, $startPos) {
    $len = strlen($content);
    $pos = $startPos + 3; // Skip '@if'
    
    // Skip whitespace
    while ($pos < $len && ($content[$pos] === ' ' || $content[$pos] === "\t")) {
      $pos++;
    }
    
    $condition = '';
    
    // Check if condition is in parentheses
    if ($pos < $len && $content[$pos] === '(') {
      $pos++; // Skip opening parenthesis
      $condStart = $pos;
      $parens = 1;
      
      // Find matching closing parenthesis
      while ($pos < $len && $parens > 0) {
        $char = $content[$pos];
        if ($char === '(') {
          $parens++;
        } elseif ($char === ')') {
          $parens--;
        }
        $pos++;
      }
      
      $condEnd = $pos - 1;
      $condition = trim(substr($content, $condStart, $condEnd - $condStart));
      $headerEnd = $pos; // Right after closing parenthesis
    } else {
      // No parentheses: read to end of line
      $lineEnd = strpos($content, "\n", $pos);
      if ($lineEnd === false) {
        $lineEnd = $len;
      }
      $condition = trim(substr($content, $pos, $lineEnd - $pos));
      $headerEnd = $lineEnd;
    }
    
    return [
      'condition' => $condition,
      'headerEnd' => $headerEnd
    ];
  }
  
  /**
   * Find matching @endif and optional @else for @if directive
   * 
   * Handles nested @if directives and finds @else at the same depth.
   * 
   * @param string $content The template content
   * @param int $startPos Starting position after @if header
   * @return array Array with 'endPos' and 'elsePos' keys
   */
  private function findMatchingEndif($content, $startPos) {
    $pos = $startPos;
    $depth = 1;
    $len = strlen($content);
    $elsePos = null;
    
    while ($pos < $len && $depth > 0) {
      $nextIf = strpos($content, '@if', $pos);
      $nextElse = strpos($content, '@else', $pos);
      $nextEnd = strpos($content, '@endif', $pos);
      
      // Collect all candidates
      $candidates = [];
      if ($nextIf !== false) {
        $candidates[] = $nextIf;
      }
      if ($nextElse !== false) {
        $candidates[] = $nextElse;
      }
      if ($nextEnd !== false) {
        $candidates[] = $nextEnd;
      }
      
      if (empty($candidates)) {
        $next = $len;
      } else {
        $next = min($candidates);
      }
      
      // Process the next directive
      if ($next === $nextIf) {
        // Nested @if - increase depth
        $depth++;
        $pos = $nextIf + 3;
      } elseif ($next === $nextElse && $depth === 1 && $elsePos === null) {
        // @else at current depth - capture it
        $elsePos = $nextElse;
        $pos = $nextElse + 5;
      } elseif ($next === $nextEnd) {
        // Matching @endif - decrease depth
        $depth--;
        $pos = $nextEnd + 6;
      } else {
        $pos++;
      }
    }
    
    return [
      'endPos' => $pos - 6, // Position of start of @endif
      'elsePos' => $elsePos
    ];
  }
  
  /**
   * Process @if directives in template
   * 
   * Handles @if ... @else ... @endif patterns.
   * Supports nested @if directives and complex conditions.
   * 
   * @param string $content The template content
   * @param array $data The data array (passed by reference)
   * @return string Content with @if directives processed
   */
  private function processIf($content, &$data) {
    $output = '';
    $offset = 0;
    $len = strlen($content);
    
    while (true) {
      // Find next @if directive
      $start = strpos($content, '@if', $offset);
      if ($start === false) {
        // No more @if directives, append remaining content
        $output .= substr($content, $offset);
        break;
      }
      
      // Append content before @if
      $output .= substr($content, $offset, $start - $offset);
      
      // Parse condition from @if header
      $ifInfo = $this->parseIfCondition($content, $start);
      $condition = $ifInfo['condition'];
      $headerEnd = $ifInfo['headerEnd'];
      
      // Find matching @endif and optional @else
      $endifInfo = $this->findMatchingEndif($content, $headerEnd);
      $endPos = $endifInfo['endPos'];
      $elsePos = $endifInfo['elsePos'];
      
      // Extract if and else blocks
      if ($elsePos !== null) {
        $ifBlock = substr($content, $headerEnd, $elsePos - $headerEnd);
        $elseBlockStart = $elsePos + 5; // Right after '@else'
        $elseBlock = substr($content, $elseBlockStart, $endPos - $elseBlockStart);
      } else {
        $ifBlock = substr($content, $headerEnd, $endPos - $headerEnd);
        $elseBlock = '';
      }
      
      // Evaluate condition and compile appropriate block
      if ($this->evaluateCondition($condition, $data)) {
        $result = $this->processDirectives($ifBlock, $data);
        $result = $this->processVariables($result, $data);
        $output .= $result;
      } else {
        $result = $this->processDirectives($elseBlock, $data);
        $result = $this->processVariables($result, $data);
        $output .= $result;
      }
      
      $offset = $endPos + 6; // Move past @endif
    }
    
    return $output;
  }
  
  /**
   * Remove remaining @section and @endsection directives
   * 
   * Cleans up any section directives that weren't extracted earlier.
   * This ensures they don't appear in the final HTML output.
   * 
   * @param string $content The template content
   * @return string Content with section directives removed
   */
  private function processSections($content) {
    // Remove any remaining @section and @endsection directives
    $content = preg_replace('/@section\s*\(\s*[\'"](.+?)[\'"]\s*\)\s*\r?\n?/s', '', $content);
    $content = preg_replace('/@endsection\s*\r?\n?/s', '', $content);
    return $content;
  }
  
  /**
   * Process @include directives
   * 
   * Replaces @include('view.name') with the compiled content of the included view.
   * Supports dot syntax for directory separators (e.g., 'partials.counter-check' -> 'partials/counter-check.blade.php').
   * The included view is compiled with the same data, allowing for nested includes and directives.
   * 
   * @param string $content The content to process
   * @param array $data The data array to pass to included views (passed by reference)
   * @return string The content with @include directives replaced
   */
  private function processInclude($content, &$data) {
    // Pattern to match @include('view.name') or @include("view.name")
    return preg_replace_callback('/@include\s*\(\s*[\'"](.+?)[\'"]\s*\)/', function($matches) use (&$data) {
      $viewName = trim($matches[1]);
      $includePath = $this->path($viewName);
      
      // Check if the file exists
      if (!file_exists($includePath)) {
        // Return empty string if file doesn't exist
        return '';
      }
      
      // Read the included file content
      $includeContent = file_get_contents($includePath);
      
      // Compile the included content recursively (so it can contain other directives)
      return $this->compileContent($includeContent, $data);
    }, $content);
  }
  
  /**
   * Process @yield directives
   * 
   * Replaces @yield('name') with the content of the section if it exists,
   * or an empty string if the section doesn't exist.
   * 
   * @param string $content The content to process
   * @param array $data The data array containing sections (passed by reference)
   * @return string The content with @yield directives replaced
   */
  private function processYield($content, &$data) {
    // Pattern to match @yield('name') or @yield("name")
    return preg_replace_callback('/@yield\s*\(\s*[\'"](.+?)[\'"]\s*\)/', function($matches) use ($data) {
      $sectionName = trim($matches[1]);
      // Return the section content if it exists, otherwise empty string
      return isset($data[$sectionName]) ? $data[$sectionName] : '';
    }, $content);
  }
  
  /**
   * Process @asset directives
   * 
   * Replaces @asset('js/main.js') or @asset('css/style.css') with Vite client script, 
   * entry script tag, and CSS tags.
   * The asset path should include the full relative path from the assets directory.
   * 
   * @param string $content The content to process
   * @param array $data The data array (not used but kept for consistency, passed by reference)
   * @return string The content with @asset directives replaced
   */
  private function processAsset($content, &$data) {
    // Pattern to match @asset('js/main.js') or @asset("css/style.css")
    return preg_replace_callback('/@asset\s*\(\s*[\'"](.+?)[\'"]\s*\)/', function($matches) {
      $assetPath = trim($matches[1]);
      $output = '';
      
      // Only process if Vite class exists
      if (!class_exists('Vite')) {
        return '';
      }
      
      // Add Vite client script (for dev mode)
      $output .= Vite::client();
      
      // Add the entry point script tag (required for both dev and production)
      $output .= Vite::scriptTag($assetPath);
      
      // Add CSS tags (for production only, dev mode injects CSS via Vite)
      $cssTags = Vite::cssTags($assetPath);
      if ($cssTags) {
        $output .= $cssTags;
      }
      
      return $output;
    }, $content);
  }
  
  /**
   * Process @csrf directive
   * 
   * Replaces @csrf with a hidden input field containing the CSRF token.
   * 
   * @param string $content The content to process
   * @param array $data The data array (not used but kept for consistency, passed by reference)
   * @return string The content with @csrf directives replaced
   */
  private function processCsrf($content, &$data) {
    // Pattern to match @csrf (with optional whitespace)
    return preg_replace_callback('/@csrf\s*\r?\n?/', function() {
      if (!class_exists('CSRF')) {
        return '';
      }
      return CSRF::field();
    }, $content);
  }
  
  /**
   * Process all directives in template content
   * 
   * Processes directives in order:
   * 1. @include (so included files can contain other directives)
   * 2. @foreach (can contain @if)
   * 3. @if
   * 4. @asset
   * 5. @csrf
   * 6. @yield
   * 7. Remove remaining @section directives
   * 
   * Variables are processed separately after directives.
   * 
   * @param string $content The template content
   * @param array $data The data array (passed by reference)
   * @return string Content with directives processed
   */
  private function processDirectives($content, &$data) {
    // Process directives in order
    $content = $this->processInclude($content, $data);
    $content = $this->processForeach($content, $data);
    $content = $this->processIf($content, $data);
    $content = $this->processAsset($content, $data);
    $content = $this->processCsrf($content, $data);
    $content = $this->processYield($content, $data);
    
    // Remove any remaining section directives
    $content = $this->processSections($content);
    
    return $content;
  }
  
  /**
   * Compile template content (without reading from file)
   * 
   * Processes directives and variables in the given content string.
   * This is the main compilation method that orchestrates the processing.
   * 
   * @param string $content The raw template content
   * @param array $data Data to pass to the template (passed by reference)
   * @return string The compiled content
   */
  private function compileContent($content, &$data) {
    // Process directives (@foreach, @if, @include, etc.) first
    $content = $this->processDirectives($content, $data);
    
    // Then process variables in the remaining content
    $content = $this->processVariables($content, $data);
    
    return $content;
  }
  
  /**
   * Compile a template file from disk
   * 
   * Reads a template file and compiles it. This is a convenience method
   * that combines file reading with compilation.
   * 
   * @param string $templatePath Full path to the template file
   * @param array $data Data to pass to the template
   * @return string The compiled content
   */
  private function compile($templatePath, $data) {
    $content = file_get_contents($templatePath);
    return $this->compileContent($content, $data);
  }
  
  /**
   * Render a Blade view template
   * 
   * Main public method to render a Blade template. If the view contains 
   * @extends directive, it will be used as the layout.
   * The layout parameter is kept for backward compatibility but is deprecated.
   * 
   * @param string $view The view name (without .blade.php extension), supports dot syntax
   * @param array $data Data to pass to the view (and layout if @extends is used)
   * @param string|null $layout DEPRECATED: Layout name. Use @extends in view instead.
   * @param array $layoutData DEPRECATED: Data to pass to layout. Use data array instead.
   * @return string The rendered HTML content
   */
  public function render($view, $data = [], $layout = null, $layoutData = []) {
    $viewPath = $this->path($view);
    $viewContent = file_get_contents($viewPath);
    
    // Extract @extends directive from view content
    $extends = $this->extractExtends($viewContent);
    $layoutName = $extends['layout'] ?? $layout;
    $viewContentWithoutExtends = $extends['content'];
    
    // Extract sections from view content
    $sections = $this->extractSections($viewContentWithoutExtends);
    
    // Compile any remaining view content (outside of sections)
    $remainingContent = $this->compileContent($viewContentWithoutExtends, $data);
    
    // If sections were found, compile each section content
    $compiledSections = [];
    foreach ($sections as $sectionName => $sectionContent) {
      $compiledSections[$sectionName] = $this->compileContent($sectionContent, $data);
    }
    
    // Determine the main content to pass to layout
    // Use 'content' section if it exists, otherwise use remaining content
    $content = $compiledSections['content'] ?? $remainingContent;
    
    // If layout is specified (from @extends or parameter), wrap content in layout
    if ($layoutName) {
      $layoutPath = $this->path($layoutName);
      $layoutContent = file_get_contents($layoutPath);
      
      // Merge layout data with view data and add content
      // Also add all compiled sections for potential use in layout
      $mergedData = array_merge($data, $layoutData, ['content' => $content], $compiledSections);
      
      // Compile the layout with the view content
      return $this->compileContent($layoutContent, $mergedData);
    }
    
    return $content;
  }
}
