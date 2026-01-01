<?php
/**
 * Основной конфигурационный файл приложения
 */

// Загрузка переменных окружения
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

return [
    'app' => [
        'name' => $_ENV['APP_NAME'] ?? 'Messenger',
        'env' => $_ENV['APP_ENV'] ?? 'local',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
    ],
    
    'database' => [
        'driver' => $_ENV['DB_CONNECTION'] ?? 'mysql',
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'database' => $_ENV['DB_DATABASE'] ?? 'messenger',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],
    
    'telegram' => [
        'bot_token' => $_ENV['TELEGRAM_BOT_TOKEN'] ?? '',
        'bot_name' => $_ENV['TELEGRAM_BOT_NAME'] ?? '',
    ],
    
    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'] ?? 'your-secret-key-here',
        'expires_in' => $_ENV['JWT_EXPIRES_IN'] ?? 86400, // 24 часа
    ],
    
    'storage' => [
        'path' => $_ENV['STORAGE_PATH'] ?? __DIR__ . '/storage',
        'max_file_size' => $_ENV['MAX_FILE_SIZE'] ?? 10485760, // 10MB
        'allowed_types' => explode(',', $_ENV['ALLOWED_FILE_TYPES'] ?? 'jpg,jpeg,png,gif,mp3,wav,pdf,doc,docx,txt'),
    ],
    
    'websocket' => [
        'host' => $_ENV['WEBSOCKET_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['WEBSOCKET_PORT'] ?? 8080,
    ],
];