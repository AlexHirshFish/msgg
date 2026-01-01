<?php

namespace App\Models;

use App\Database\Database;
use DateTime;

class Chat
{
    public int $id;
    public string $type;
    public ?string $name;
    public ?string $avatar;
    public DateTime $created_at;
    public DateTime $updated_at;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                switch ($key) {
                    case 'created_at':
                    case 'updated_at':
                        $this->$key = new DateTime($value);
                        break;
                    default:
                        $this->$key = $value;
                        break;
                }
            }
        }
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return Database::insert('chats', $data);
    }

    public static function findById(int $id): ?Chat
    {
        $data = Database::fetch("SELECT * FROM chats WHERE id = ?", [$id]);
        
        return $data ? new Chat($data) : null;
    }

    public static function createPrivateChat(int $userId1, int $userId2): ?Chat
    {
        // Проверяем, существует ли уже чат между этими пользователями
        $existingChat = self::findPrivateChatBetweenUsers($userId1, $userId2);
        if ($existingChat) {
            return $existingChat;
        }
        
        // Начинаем транзакцию
        Database::beginTransaction();
        
        try {
            // Создаем чат
            $chatId = self::create([
                'type' => 'private'
            ]);
            
            if (!$chatId) {
                Database::rollback();
                return null;
            }
            
            // Добавляем участников
            $chat = new Chat(['id' => $chatId, 'type' => 'private']);
            $chat->addParticipant($userId1);
            $chat->addParticipant($userId2);
            
            Database::commit();
            
            return $chat;
        } catch (\Exception $e) {
            Database::rollback();
            return null;
        }
    }

    public static function findPrivateChatBetweenUsers(int $userId1, int $userId2): ?Chat
    {
        // Находим чат, в котором оба пользователя являются участниками
        $sql = "
            SELECT c.* 
            FROM chats c
            INNER JOIN chat_participants cp1 ON c.id = cp1.chat_id AND cp1.user_id = ?
            INNER JOIN chat_participants cp2 ON c.id = cp2.chat_id AND cp2.user_id = ?
            WHERE c.type = 'private'
            LIMIT 1
        ";
        
        $data = Database::fetch($sql, [$userId1, $userId2]);
        
        return $data ? new Chat($data) : null;
    }

    public function addParticipant(int $userId, string $role = 'member'): bool
    {
        $data = [
            'chat_id' => $this->id,
            'user_id' => $userId,
            'role' => $role,
            'joined_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            Database::insert('chat_participants', $data);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function removeParticipant(int $userId): bool
    {
        return Database::update('chat_participants', 
            ['left_at' => date('Y-m-d H:i:s')], 
            ['chat_id' => $this->id, 'user_id' => $userId]
        ) > 0;
    }

    public function getParticipants(): array
    {
        $sql = "
            SELECT u.*, cp.role, cp.joined_at, cp.left_at
            FROM users u
            INNER JOIN chat_participants cp ON u.id = cp.user_id
            WHERE cp.chat_id = ? AND cp.left_at IS NULL
        ";
        
        $usersData = Database::fetchAll($sql, [$this->id]);
        $users = [];
        
        foreach ($usersData as $userData) {
            $users[] = new User($userData);
        }
        
        return $users;
    }

    public function getMessages(int $limit = 50, int $offset = 0): array
    {
        $sql = "
            SELECT m.*, u.first_name, u.last_name, u.avatar as sender_avatar
            FROM messages m
            LEFT JOIN users u ON m.sender_id = u.id
            WHERE m.chat_id = ?
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $messagesData = Database::fetchAll($sql, [$this->id, $limit, $offset]);
        $messages = [];
        
        foreach ($messagesData as $messageData) {
            $messages[] = new Message($messageData);
        }
        
        return array_reverse($messages); // Возвращаем в хронологическом порядке
    }

    public function sendMessage(array $messageData): ?Message
    {
        $messageData['chat_id'] = $this->id;
        $messageData['created_at'] = date('Y-m-d H:i:s');
        
        try {
            $messageId = Database::insert('messages', $messageData);
            
            if ($messageId) {
                $messageData['id'] = $messageId;
                return new Message($messageData);
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function update(array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return Database::update('chats', $data, ['id' => $this->id]);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s')
        ];
    }
}