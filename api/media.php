<?php

require_once __DIR__ . '/../../bootstrap/app.php';

use App\Models\User;
use App\Models\Chat;
use App\Models\Message;

header('Content-Type: application/json');

// Разрешаем CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
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
        case 'upload_file':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            $chatId = $_POST['chat_id'] ?? 0;
            
            if (!$chatId) {
                throw new Exception('Chat ID is required');
            }
            
            // Проверяем, что пользователь имеет доступ к чату
            $participant = \App\Database\Database::fetch(
                "SELECT id FROM chat_participants WHERE chat_id = ? AND user_id = ? AND left_at IS NULL",
                [$chatId, $user->id]
            );
            
            if (!$participant) {
                throw new Exception('Access denied');
            }
            
            if (!isset($_FILES['file'])) {
                throw new Exception('No file uploaded');
            }
            
            $file = $_FILES['file'];
            
            // Проверяем размер файла
            $maxFileSize = config('storage.max_file_size');
            if ($file['size'] > $maxFileSize) {
                throw new Exception('File too large');
            }
            
            // Проверяем тип файла
            $allowedTypes = config('storage.allowed_types');
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedTypes)) {
                throw new Exception('File type not allowed');
            }
            
            // Создаем директорию для хранения
            $uploadDir = config('storage.path') . '/attachments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Генерируем уникальное имя файла
            $filename = uniqid() . '_' . time() . '.' . $fileExtension;
            $filePath = $uploadDir . $filename;
            
            // Перемещаем файл
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Failed to upload file');
            }
            
            // Сохраняем сообщение в базу
            $chat = Chat::findById($chatId);
            if (!$chat) {
                unlink($filePath); // Удаляем файл если чат не найден
                throw new Exception('Chat not found');
            }
            
            $messageData = [
                'sender_id' => $user->id,
                'type' => in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'file',
                'content' => $file['name'],
                'file_path' => $filePath,
                'file_name' => $file['name'],
                'file_size' => $file['size'],
                'is_read' => false
            ];
            
            $message = $chat->sendMessage($messageData);
            
            if (!$message) {
                unlink($filePath); // Удаляем файл если сообщение не сохранилось
                throw new Exception('Failed to save message');
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message->toArray()
            ]);
            break;
            
        case 'upload_voice':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            $chatId = $_POST['chat_id'] ?? 0;
            $duration = $_POST['duration'] ?? 0;
            
            if (!$chatId) {
                throw new Exception('Chat ID is required');
            }
            
            // Проверяем, что пользователь имеет доступ к чату
            $participant = \App\Database\Database::fetch(
                "SELECT id FROM chat_participants WHERE chat_id = ? AND user_id = ? AND left_at IS NULL",
                [$chatId, $user->id]
            );
            
            if (!$participant) {
                throw new Exception('Access denied');
            }
            
            if (!isset($_FILES['voice'])) {
                throw new Exception('No voice file uploaded');
            }
            
            $file = $_FILES['voice'];
            
            // Проверяем тип файла (должен быть audio)
            $allowedTypes = ['mp3', 'wav', 'ogg', 'aac', 'm4a'];
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedTypes)) {
                throw new Exception('Voice file type not allowed');
            }
            
            // Создаем директорию для голосовых сообщений
            $uploadDir = config('storage.path') . '/voices/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Генерируем уникальное имя файла
            $filename = uniqid() . '_' . time() . '.' . $fileExtension;
            $filePath = $uploadDir . $filename;
            
            // Перемещаем файл
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Failed to upload voice message');
            }
            
            // Сохраняем сообщение в базу
            $chat = Chat::findById($chatId);
            if (!$chat) {
                unlink($filePath);
                throw new Exception('Chat not found');
            }
            
            $messageData = [
                'sender_id' => $user->id,
                'type' => 'voice',
                'content' => 'Voice message',
                'file_path' => $filePath,
                'file_name' => $file['name'],
                'file_size' => $file['size'],
                'duration' => $duration,
                'is_read' => false
            ];
            
            $message = $chat->sendMessage($messageData);
            
            if (!$message) {
                unlink($filePath);
                throw new Exception('Failed to save voice message');
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message->toArray()
            ]);
            break;
            
        case 'download_file':
            $filePath = $_GET['path'] ?? '';
            
            if (!$filePath) {
                throw new Exception('File path is required');
            }
            
            // Проверяем, что файл существует
            if (!file_exists($filePath)) {
                throw new Exception('File not found');
            }
            
            // Проверяем права доступа к файлу
            // TODO: реализовать проверку, что пользователь имеет доступ к чату с этим файлом
            
            // Отправляем файл
            header('Content-Type: ' . mime_content_type($filePath));
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Content-Length: ' . filesize($filePath));
            
            readfile($filePath);
            exit();
            
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