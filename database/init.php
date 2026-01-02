<?php

require_once __DIR__ . '/../bootstrap/app.php';

use App\Database\Database;
use App\Services\LoggerService;

try {
    $pdo = Database::getInstance();
    
    // Проверяем, существует ли база данных
    $dbName = config('database.database');
    
    // Проверяем, существуют ли таблицы
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        LoggerService::info("Database is empty, creating schema");
        
        // Читаем SQL файл схемы
        $schemaFile = __DIR__ . '/schema.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            
            // Разбиваем на отдельные операторы
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $pdo->exec($statement);
                }
            }
            
            LoggerService::info("Database schema created successfully");
        } else {
            LoggerService::error("Schema file not found: {$schemaFile}");
        }
    } else {
        LoggerService::info("Database already exists", ['tables_count' => count($tables)]);
    }
} catch (Exception $e) {
    LoggerService::error("Database initialization error", [
        'error' => $e->getMessage()
    ]);
    throw $e;
}