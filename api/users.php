<?php

require_once __DIR__ . '/../../bootstrap/app.php';

use App\Models\User;

header('Content-Type: application/json');

// Разрешаем CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Проверяем авторизацию
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user = User::findByJWT($token);
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit();
}

try {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'search_by_phone':
            $phone = $_GET['phone'] ?? '';
            
            if (strlen($phone) < 7) { // Минимум 7 цифр
                echo json_encode(['success' => true, 'users' => []]);
                break;
            }
            
            // Очищаем номер от всех символов кроме цифр
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            
            // Ищем пользователей с похожим номером телефона
            $sql = "
                SELECT id, first_name, last_name, phone, avatar, telegram_username
                FROM users 
                WHERE phone LIKE ?
                AND id != ?
                AND is_active = TRUE
                LIMIT 10
            ";
            
            $phonePattern = "%{$cleanPhone}%";
            $users = \App\Database\Database::fetchAll($sql, [$phonePattern, $user->id]);
            
            echo json_encode([
                'success' => true,
                'users' => $users
            ]);
            break;
            
        case 'search_by_name':
            $query = $_GET['query'] ?? '';
            
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'users' => []]);
                break;
            }
            
            // Поиск по имени или фамилии
            $sql = "
                SELECT id, first_name, last_name, phone, avatar, telegram_username
                FROM users 
                WHERE (first_name LIKE ? OR last_name LIKE ?)
                AND id != ?
                AND is_active = TRUE
                LIMIT 20
            ";
            
            $searchQuery = "%{$query}%";
            $users = \App\Database\Database::fetchAll($sql, [
                $searchQuery, $searchQuery, $user->id
            ]);
            
            echo json_encode([
                'success' => true,
                'users' => $users
            ]);
            break;
            
        default:
            throw new Exception('Unknown action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}