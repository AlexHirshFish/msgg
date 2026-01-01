<?php

require_once __DIR__ . '/../../bootstrap/app.php';

use App\Services\AuthService;
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

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'send_verification':
            $phone = $input['phone'] ?? '';
            
            if (empty($phone)) {
                throw new Exception('Phone number is required');
            }
            
            $success = AuthService::sendVerificationCode($phone, 'registration');
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Verification code sent' : 'Failed to send verification code'
            ]);
            break;
            
        case 'register':
            $required = ['phone', 'email', 'password', 'first_name', 'last_name', 'verification_code'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    throw new Exception("Field '{$field}' is required");
                }
            }
            
            $user = AuthService::register($input);
            
            if (!$user) {
                throw new Exception('Registration failed');
            }
            
            $token = User::generateJWT($user->id);
            
            echo json_encode([
                'success' => true,
                'user' => $user->toArray(),
                'token' => $token
            ]);
            break;
            
        case 'login':
            $identifier = $input['identifier'] ?? '';
            $password = $input['password'] ?? '';
            
            if (empty($identifier) || empty($password)) {
                throw new Exception('Identifier and password are required');
            }
            
            $result = AuthService::login($identifier, $password);
            
            if (!$result) {
                throw new Exception('Invalid credentials');
            }
            
            echo json_encode([
                'success' => true,
                'user' => $result['user'],
                'token' => $result['token']
            ]);
            break;
            
        case 'telegram_login':
            $telegramId = $input['telegram_id'] ?? 0;
            $firstName = $input['first_name'] ?? '';
            
            if (!$telegramId || empty($firstName)) {
                throw new Exception('Telegram ID and first name are required');
            }
            
            $result = AuthService::loginWithTelegram(
                $telegramId,
                $firstName,
                $input['last_name'] ?? '',
                $input['username'] ?? null
            );
            
            if (!$result) {
                throw new Exception('Telegram login failed');
            }
            
            echo json_encode([
                'success' => true,
                'user' => $result['user'],
                'token' => $result['token']
            ]);
            break;
            
        case 'verify_token':
            $token = $input['token'] ?? '';
            
            if (empty($token)) {
                throw new Exception('Token is required');
            }
            
            $user = User::findByJWT($token);
            
            if (!$user) {
                throw new Exception('Invalid token');
            }
            
            echo json_encode([
                'success' => true,
                'user' => $user->toArray()
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