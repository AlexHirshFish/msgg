<?php

namespace App\Database;

use PDO;
use PDOException;
use App\Services\LoggerService;

class Database
{
    private static ?PDO $instance = null;
    
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::connect();
        }
        
        return self::$instance;
    }
    
    private static function connect(): PDO
    {
        $config = config('database');
        
        $dsn = "{$config['driver']}:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        
        try {
            $pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );
            
            // Установка кодировки
            $pdo->exec("SET NAMES {$config['charset']} COLLATE {$config['collation']}");
            
            LoggerService::info("Database connection established", [
                'host' => $config['host'],
                'database' => $config['database']
            ]);
            
            return $pdo;
        } catch (PDOException $e) {
            LoggerService::error("Database connection failed", [
                'error' => $e->getMessage(),
                'host' => $config['host'],
                'database' => $config['database']
            ]);
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        try {
            $stmt = self::getInstance()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            LoggerService::error("Database query failed", [
                'error' => $e->getMessage(),
                'sql' => $sql,
                'params' => $params
            ]);
            throw $e;
        }
    }
    
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }
    
    public static function fetch(string $sql, array $params = [])
    {
        return self::query($sql, $params)->fetch();
    }
    
    public static function fetchColumn(string $sql, array $params = [])
    {
        return self::query($sql, $params)->fetchColumn();
    }
    
    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = ':' . implode(', :', $columns);
        $columns = '`' . implode('`, `', $columns) . '`';
        
        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
        
        self::query($sql, $data);
        
        return (int) self::getInstance()->lastInsertId();
    }
    
    public static function update(string $table, array $data, array $conditions): int
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "`{$column}` = :{$column}";
        }
        $setClause = implode(', ', $setParts);
        
        $whereParts = [];
        $whereParams = [];
        foreach ($conditions as $column => $value) {
            $whereParts[] = "`{$column}` = :where_{$column}";
            $whereParams["where_{$column}"] = $value;
        }
        $whereClause = implode(' AND ', $whereParts);
        
        $params = array_merge($data, $whereParams);
        
        $sql = "UPDATE `{$table}` SET {$setClause} WHERE {$whereClause}";
        
        return self::query($sql, $params)->rowCount();
    }
    
    public static function delete(string $table, array $conditions): int
    {
        $whereParts = [];
        $params = [];
        foreach ($conditions as $column => $value) {
            $whereParts[] = "`{$column}` = :{$column}";
            $params[$column] = $value;
        }
        $whereClause = implode(' AND ', $whereParts);
        
        $sql = "DELETE FROM `{$table}` WHERE {$whereClause}";
        
        return self::query($sql, $params)->rowCount();
    }
    
    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }
    
    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }
    
    public static function rollback(): bool
    {
        return self::getInstance()->rollback();
    }
}