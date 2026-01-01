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