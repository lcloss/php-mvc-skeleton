<?php

namespace app\models;
use Database;
use PDO;

class Model
{
    protected static $table;

    protected static function pdo()
    {
        return Database::getConnection();
    }

    public static function create($data)
    {
        $keys = array_keys($data);
        $cols = implode(',', $keys);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $sql = 'INSERT INTO ' . static::$table . ' (' . $cols . ') VALUES (' . $placeholders . ')';
        try {
            $stmt = self::pdo()->prepare($sql);
            $stmt->execute(array_values($data));
        } catch (\PDOException $e) {
            throw new \Exception('Database Insertion Error: ' . $e->getMessage());
        }
        return self::pdo()->lastInsertId();
    }

    public static function find($id)
    {
        $stmt = self::pdo()->prepare('SELECT * FROM ' . static::$table . ' WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function all()
    {
        $stmt = self::pdo()->query('SELECT * FROM ' . static::$table);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function update($id, $data)
    {
        $sets = [];
        $vals = [];
        foreach ($data as $k => $v) {
            $sets[] = "$k = ?";
            $vals[] = $v;
        }
        $vals[] = $id;
        $sql = 'UPDATE ' . static::$table . ' SET ' . implode(',', $sets) . ' WHERE id = ?';
        try {
            $stmt = self::pdo()->prepare($sql);
            return $stmt->execute($vals);
        } catch(\PDOException $e) {
            throw new \Exception('Database Update Error: ' . $e->getMessage());
        }
    }

    public static function delete($id)
    {
        try {
            $stmt = self::pdo()->prepare('DELETE FROM ' . static::$table . ' WHERE id = ?');
            return $stmt->execute([$id]);
        } catch(\PDOException $e) {
            throw new \Exception('Database Deletion Error: ' . $e->getMessage());
        }
    }
}
