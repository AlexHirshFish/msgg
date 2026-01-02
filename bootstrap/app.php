<?php
/**
 * Автозагрузчик приложения
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Загрузка конфигурации
$config = require_once __DIR__ . '/../config/app.php';

// Глобальные функции
function config($key = null) {
    static $config;
    if ($config === null) {
        $config = require __DIR__ . '/../config/app.php';
    }
    
    if ($key === null) {
        return $config;
    }
    
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return null;
        }
        $value = $value[$k];
    }
    
    return $value;
}

function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

// Обработка ошибок
if (config('app.debug')) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Обработчик ошибок
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    
    $logMessage = "Error: {$message} in {$file} on line {$line}";
    error_log($logMessage);
    
    // Логируем в наш сервис
    if (class_exists('\\App\\Services\\LoggerService')) {
        \App\Services\LoggerService::error($logMessage, [
            'severity' => $severity,
            'file' => $file,
            'line' => $line
        ]);
    }
    
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

// Обработчик исключений
set_exception_handler(function ($exception) {
    $logMessage = "Uncaught exception: " . $exception->getMessage();
    error_log($logMessage);
    
    // Логируем в наш сервис
    if (class_exists('\\App\\Services\\LoggerService')) {
        \App\Services\LoggerService::error($logMessage, [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
    
    // Возвращаем HTTP 500 ошибку
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Internal Server Error',
        'message' => config('app.debug') ? $exception->getMessage() : 'An error occurred'
    ]);
});