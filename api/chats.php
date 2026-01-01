<?php

require_once __DIR__ . '/../../bootstrap/app.php';

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use App\Models\Contact;

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
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_chats':
            $chats = [];
            $sql = "
                SELECT DISTINCT c.*, 
                       u.first_name, u.last_name, u.avatar, u.phone,
                       cp.role, cp.joined_at,
                       m.content as last_message,
                       m.created_at as last_message_time,
                       (SELECT COUNT(*) FROM messages msg 
                        WHERE msg.chat_id = c.id AND msg.sender_id != ? AND msg.is_read = FALSE) as unread_count
                FROM chats c
                INNER JOIN chat_participants cp ON c.id = cp.chat_id
                LEFT JOIN users u ON (u.id = cp.user_id AND u.id != ?)
                LEFT JOIN messages m ON (m.chat_id = c.id AND m.id = (
                    SELECT MAX(id) FROM messages WHERE chat_id = c.id
                ))
                WHERE cp.user_id = ? AND cp.left_at IS NULL
                ORDER BY COALESCE(m.created_at, c.updated_at) DESC
            ";
            
            $chatsData = \App\Database\Database::fetchAll($sql, [$user->id, $user->id, $user->id]);
            
            foreach ($chatsData as $chatData) {
                $chats[] = [
                    'id' => $chatData['id'],
                    'type' => $chatData['type'],
                    'name' => $chatData['name'] ?: ($chatData['first_name'] . ' ' . $chatData['last_name']),
                    'avatar' => $chatData['avatar'],
                    'phone' => $chatData['phone'],
                    'last_message' => $chatData['last_message'],
                    'last_message_time' => $chatData['last_message_time'],
                    'unread_count' => (int)$chatData['unread_count'],
                    'is_online' => false // TODO: реализовать статус онлайн
                ];
            }
            
            echo json_encode([
                'success' => true,
                'chats' => $chats
            ]);
            break;
            
        case 'get_messages':
            $chatId = $_GET['chat_id'] ?? 0;
            $limit = min($_GET['limit'] ?? 50, 100);
            $offset = $_GET['offset'] ?? 0;
            
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
            
            $messages = Message::findByChatId($chatId, $limit, $offset);
            $formattedMessages = array_map(function($msg) {
                return $msg->toArray();
            }, $messages);
            
            // Помечаем сообщения как прочитанные
            Message::markAllAsRead($chatId, $user->id);
            
            echo json_encode([
                'success' => true,
                'messages' => $formattedMessages
            ]);
            break;
            
        case 'send_message':
            $chatId = $input['chat_id'] ?? 0;
            $content = $input['content'] ?? '';
            $type = $input['type'] ?? 'text';
            
            if (!$chatId || !$content) {
                throw new Exception('Chat ID and content are required');
            }
            
            // Проверяем, что пользователь имеет доступ к чату
            $participant = \App\Database\Database::fetch(
                "SELECT id FROM chat_participants WHERE chat_id = ? AND user_id = ? AND left_at IS NULL",
                [$chatId, $user->id]
            );
            
            if (!$participant) {
                throw new Exception('Access denied');
            }
            
            $chat = Chat::findById($chatId);
            if (!$chat) {
                throw new Exception('Chat not found');
            }
            
            $messageData = [
                'sender_id' => $user->id,
                'type' => $type,
                'content' => $content,
                'is_read' => false
            ];
            
            $message = $chat->sendMessage($messageData);
            
            if (!$message) {
                throw new Exception('Failed to send message');
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message->toArray()
            ]);
            break;
            
        case 'create_private_chat':
            $contactUserId = $input['contact_user_id'] ?? 0;
            
            if (!$contactUserId) {
                throw new Exception('Contact user ID is required');
            }
            
            if ($contactUserId == $user->id) {
                throw new Exception('Cannot create chat with yourself');
            }
            
            $contactUser = User::findById($contactUserId);
            if (!$contactUser) {
                throw new Exception('User not found');
            }
            
            $chat = Chat::createPrivateChat($user->id, $contactUserId);
            
            if (!$chat) {
                throw new Exception('Failed to create chat');
            }
            
            echo json_encode([
                'success' => true,
                'chat' => $chat->toArray()
            ]);
            break;
            
        case 'search_users':
            $query = $_GET['query'] ?? '';
            
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'users' => []]);
                break;
            }
            
            $sql = "
                SELECT id, first_name, last_name, phone, avatar, telegram_username
                FROM users 
                WHERE (phone LIKE ? OR first_name LIKE ? OR last_name LIKE ?)
                AND id != ?
                AND is_active = TRUE
                LIMIT 20
            ";
            
            $searchQuery = "%{$query}%";
            $users = \App\Database\Database::fetchAll($sql, [
                $searchQuery, $searchQuery, $searchQuery, $user->id
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