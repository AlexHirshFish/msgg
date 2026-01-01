<?php

require_once __DIR__ . '/../bootstrap/app.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\WsServer;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;

class ChatServer implements MessageComponentInterface {
    protected $clients;
    protected $users;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->users = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data) {
            return;
        }

        switch ($data['type']) {
            case 'auth':
                $this->handleAuth($from, $data);
                break;
                
            case 'join_chat':
                $this->handleJoinChat($from, $data);
                break;
                
            case 'leave_chat':
                $this->handleLeaveChat($from, $data);
                break;
                
            case 'message':
                $this->handleMessage($from, $data);
                break;
                
            case 'typing':
                $this->handleTyping($from, $data);
                break;
        }
    }

    private function handleAuth(ConnectionInterface $conn, $data) {
        $token = $data['token'] ?? '';
        
        if (!$token) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Token required']));
            return;
        }

        $user = \App\Models\User::findByJWT($token);
        
        if (!$user) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Invalid token']));
            return;
        }

        $this->users[$conn->resourceId] = [
            'user_id' => $user->id,
            'connection' => $conn,
            'active_chats' => []
        ];

        $conn->send(json_encode([
            'type' => 'auth_success',
            'user' => $user->toArray()
        ]));

        echo "User {$user->id} authenticated\n";
    }

    private function handleJoinChat(ConnectionInterface $conn, $data) {
        $userId = $this->users[$conn->resourceId]['user_id'] ?? null;
        $chatId = $data['chat_id'] ?? 0;

        if (!$userId || !$chatId) {
            return;
        }

        // Проверяем, что пользователь имеет доступ к чату
        $participant = \App\Database\Database::fetch(
            "SELECT id FROM chat_participants WHERE chat_id = ? AND user_id = ? AND left_at IS NULL",
            [$chatId, $userId]
        );

        if (!$participant) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Access denied to chat'
            ]));
            return;
        }

        $this->users[$conn->resourceId]['active_chats'][] = $chatId;

        $conn->send(json_encode([
            'type' => 'chat_joined',
            'chat_id' => $chatId
        ]));

        echo "User {$userId} joined chat {$chatId}\n";
    }

    private function handleLeaveChat(ConnectionInterface $conn, $data) {
        $userId = $this->users[$conn->resourceId]['user_id'] ?? null;
        $chatId = $data['chat_id'] ?? 0;

        if (!$userId || !$chatId) {
            return;
        }

        $user = &$this->users[$conn->resourceId];
        $user['active_chats'] = array_filter($user['active_chats'], function($id) use ($chatId) {
            return $id != $chatId;
        });

        $conn->send(json_encode([
            'type' -> 'chat_left',
            'chat_id' => $chatId
        ]));

        echo "User {$userId} left chat {$chatId}\n";
    }

    private function handleMessage(ConnectionInterface $from, $data) {
        $userId = $this->users[$from->resourceId]['user_id'] ?? null;
        $chatId = $data['chat_id'] ?? 0;
        $content = $data['content'] ?? '';
        $type = $data['type'] ?? 'text';

        if (!$userId || !$chatId || !$content) {
            return;
        }

        // Проверяем, что пользователь находится в этом чате
        if (!in_array($chatId, $this->users[$from->resourceId]['active_chats'])) {
            return;
        }

        // Сохраняем сообщение в базу данных
        $chat = \App\Models\Chat::findById($chatId);
        if (!$chat) {
            return;
        }

        $messageData = [
            'sender_id' => $userId,
            'type' => $type,
            'content' => $content,
            'is_read' => false
        ];

        $message = $chat->sendMessage($messageData);
        if (!$message) {
            return;
        }

        // Отправляем сообщение всем участникам чата
        $messageArray = $message->toArray();
        $messageJson = json_encode([
            'type' => 'new_message',
            'message' => $messageArray
        ]);

        foreach ($this->users as $user) {
            if (in_array($chatId, $user['active_chats']) && $user['user_id'] != $userId) {
                $user['connection']->send($messageJson);
            }
        }

        // Отправляем подтверждение отправителю
        $from->send(json_encode([
            'type' => 'message_sent',
            'message' => $messageArray
        ]));

        echo "Message sent from user {$userId} to chat {$chatId}\n";
    }

    private function handleTyping(ConnectionInterface $from, $data) {
        $userId = $this->users[$from->resourceId]['user_id'] ?? null;
        $chatId = $data['chat_id'] ?? 0;

        if (!$userId || !$chatId) {
            return;
        }

        // Отправляем уведомление о наборе текста другим участникам
        $typingJson = json_encode([
            'type' => 'typing',
            'user_id' => $userId,
            'chat_id' => $chatId
        ]);

        foreach ($this->users as $user) {
            if (in_array($chatId, $user['active_chats']) && $user['user_id'] != $userId) {
                $user['connection']->send($typingJson);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $userId = $this->users[$conn->resourceId]['user_id'] ?? 'unknown';
        
        $this->clients->detach($conn);
        unset($this->users[$conn->resourceId]);

        echo "Connection {$conn->resourceId} (user: {$userId}) closed\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Запуск сервера
$port = config('websocket.port') ?: 8080;
$host = config('websocket.host') ?: '127.0.0.1';

echo "Starting WebSocket server on {$host}:{$port}\n";

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatServer()
        )
    ),
    $port,
    $host
);

$server->run();