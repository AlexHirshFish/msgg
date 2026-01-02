<?php

require_once __DIR__ . '/bootstrap/app.php';

use App\Services\LoggerService;

// Логируем начало запроса
LoggerService::info("Request started", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'ip' => $_SERVER['REMOTE_ADDR']
]);

// Определяем маршрут
$requestUri = $_SERVER['REQUEST_URI'];

// Убираем query string
$path = parse_url($requestUri, PHP_URL_PATH);

if ($path === '/' || $path === '') {
    // Корневой маршрут - перенаправляем на login.html
    header('Location: /public/login.html');
    exit();
} elseif (strpos($path, '/api/') === 0) {
    // API маршруты
    $apiFile = __DIR__ . $path;
    if (file_exists($apiFile)) {
        require_once $apiFile;
        exit();
    } else {
        // Проверяем, может быть это API endpoint
        $pathParts = explode('/', trim($path, '/'));
        if (isset($pathParts[1]) && $pathParts[0] === 'api') {
            $apiFile = __DIR__ . '/api/' . $pathParts[1] . '.php';
            if (file_exists($apiFile)) {
                require_once $apiFile;
                exit();
            }
        }
    }
} elseif (strpos($path, '/public/') === 0) {
    // Публичные файлы
    $publicFile = __DIR__ . $path;
    if (file_exists($publicFile)) {
        $extension = pathinfo($publicFile, PATHINFO_EXTENSION);
        $mimeType = match($extension) {
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => 'application/octet-stream'
        };
        
        header("Content-Type: {$mimeType}");
        readfile($publicFile);
        exit();
    }
} elseif ($path === '/login' || $path === '/login.html') {
    // Маршрут для логина
    $loginFile = __DIR__ . '/public/login.html';
    if (file_exists($loginFile)) {
        header('Content-Type: text/html');
        readfile($loginFile);
        exit();
    }
} elseif ($path === '/messenger' || $path === '/messenger.html') {
    // Маршрут для мессенджера
    $messengerFile = __DIR__ . '/public/messenger.html';
    if (file_exists($messengerFile)) {
        header('Content-Type: text/html');
        readfile($messengerFile);
        exit();
    }
}

// Если ничего не найдено, возвращаем 404
http_response_code(404);
header('Content-Type: application/json');
LoggerService::warning("404 Not Found", ['path' => $path]);
echo json_encode([
    'success' => false,
    'error' => 'Not Found',
    'path' => $path
]);