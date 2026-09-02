<?php
class Database {
  private static $pdo;
  public static function getConnection() {
    if (self::$pdo) return self::$pdo;
    $driver = env('DB_CONNECTION', 'sqlite');
    if ($driver === 'sqlite') {
      $dsn = 'sqlite:' . env('DB_DATABASE', ':memory:');
      self::$pdo = new PDO($dsn);
    } elseif ($driver === 'mysql') {
      $host = env('DB_HOST', '127.0.0.1');
      $port = env('DB_PORT', '3306');
      $dbname = env('DB_DATABASE', 'app');
      $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
      self::$pdo = new PDO($dsn, env('DB_USERNAME', ''), env('DB_PASSWORD', ''));
    } elseif ($driver === 'pgsql') {
      $host = env('DB_HOST', '127.0.0.1');
      $port = env('DB_PORT', '5432');
      $dbname = env('DB_DATABASE', 'app');
      $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
      self::$pdo = new PDO($dsn, env('DB_USERNAME', ''), env('DB_PASSWORD', ''));
    } else {
      throw new RuntimeException('Unsupported DB driver');
    }
    self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return self::$pdo;
  }

  public static function checkIfTableExists($driver, $tableName) {
      $pdo = self::getConnection();
      if ($driver === 'sqlite') {
          $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=:table");
          $stmt->execute([':table' => $tableName]);
          return $stmt->fetchColumn() !== false;
      } elseif ($driver === 'mysql') {
          $stmt = $pdo->prepare("SHOW TABLES LIKE :table");
          $stmt->execute([':table' => $tableName]);
          return $stmt->fetchColumn() !== false;
      } elseif ($driver === 'pgsql') {
          $stmt = $pdo->prepare("SELECT to_regclass(:table) IS NOT NULL AS exists");
          $stmt->execute([':table' => $tableName]);
          return (bool)$stmt->fetchColumn();
      } else {
          throw new RuntimeException('Unsupported DB driver');
      }
  }

  public static function createTables($sql) {
      if (empty($sql)) {
          return;
      }
      $pdo = self::getConnection();
      $statements = is_array($sql) ? $sql : [$sql];
      try {
          if (!$pdo->inTransaction()) {
              $pdo->beginTransaction();
              $useTransaction = true;
          } else {
              $useTransaction = false;
          }
          foreach ($statements as $stmt) {
              $stmt = trim((string)$stmt);
              if ($stmt === '') {
                  continue;
              }
              if (!preg_match('/^\s*CREATE\s+/i', $stmt)) {
                  throw new InvalidArgumentException('createTables() accepts only CREATE statements.');
              }
              $pdo->exec($stmt);
          }
      } catch (Exception $e) {
          if (isset($useTransaction) && $useTransaction && $pdo->inTransaction()) {
              $pdo->rollBack();
          }
          throw new RuntimeException($e->getMessage(), (int)$e->getCode(), $e);
      }
    }

    public static function execute($sql) {
        if (empty($sql)) {
            return;
        }
        $pdo = self::getConnection();
        $statements = is_array($sql) ? $sql : [$sql];
        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $useTransaction = true;
            } else {
                $useTransaction = false;
            }
            foreach ($statements as $stmt) {
                $stmt = trim((string)$stmt);
                if ($stmt === '') {
                    continue;
                }
                $pdo->exec($stmt);
            }
            if ($useTransaction) {
                $pdo->commit();
            }
        } catch (Exception $e) {
            if (isset($useTransaction) && $useTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    public static function executePrepare($sql, $params = []) {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
