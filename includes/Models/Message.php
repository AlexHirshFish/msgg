<?php

namespace App\Models;

use App\Database\Database;
use DateTime;

class Message
{
    public int $id;
    public int $chat_id;
    public int $sender_id;
    public string $type;
    public string $content;
    public ?string $file_path;
    public ?string $file_name;
    public ?int $file_size;
    public ?int $duration;
    public bool $is_read;
    public ?int $reply_to_message_id;
    public DateTime $created_at;
    public ?string $first_name;
    public ?string $last_name;
    public ?string $sender_avatar;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                switch ($key) {
                    case 'created_at':
                        $this->$key = new DateTime($value);
                        break;
                    case 'is_read':
                        $this->$key = (bool)$value;
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
        
        return Database::insert('messages', $data);
    }

    public static function findById(int $id): ?Message
    {
        $data = Database::fetch("SELECT * FROM messages WHERE id = ?", [$id]);
        
        return $data ? new Message($data) : null;
    }

    public static function findByChatId(int $chatId, int $limit = 50, int $offset = 0): array
    {
        $sql = "
            SELECT m.*, u.first_name, u.last_name, u.avatar as sender_avatar
            FROM messages m
            LEFT JOIN users u ON m.sender_id = u.id
            WHERE m.chat_id = ?
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $messagesData = Database::fetchAll($sql, [$chatId, $limit, $offset]);
        $messages = [];
        
        foreach ($messagesData as $messageData) {
            $messages[] = new Message($messageData);
        }
        
        return array_reverse($messages); // Возвращаем в хронологическом порядке
    }

    public static function markAsRead(int $messageId, int $userId): bool
    {
        // Проверяем, что сообщение принадлежит чату, в котором участвует пользователь
        $sql = "
            SELECT m.id
            FROM messages m
            INNER JOIN chat_participants cp ON m.chat_id = cp.chat_id
            WHERE m.id = ? AND cp.user_id = ?
        ";
        
        $message = Database::fetch($sql, [$messageId, $userId]);
        
        if (!$message) {
            return false;
        }
        
        return Database::update('messages', ['is_read' => true], ['id' => $messageId]) > 0;
    }

    public static function markAllAsRead(int $chatId, int $userId): bool
    {
        // Проверяем, что пользователь является участником чата
        $participant = Database::fetch(
            "SELECT id FROM chat_participants WHERE chat_id = ? AND user_id = ? AND left_at IS NULL",
            [$chatId, $userId]
        );
        
        if (!$participant) {
            return false;
        }
        
        $sql = "
            UPDATE messages 
            SET is_read = TRUE 
            WHERE chat_id = ? 
            AND sender_id != ? 
            AND is_read = FALSE
        ";
        
        $stmt = Database::getInstance()->prepare($sql);
        return $stmt->execute([$chatId, $userId]);
    }

    public function update(array $data): int
    {
        return Database::update('messages', $data, ['id' => $this->id]);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'chat_id' => $this->chat_id,
            'sender_id' => $this->sender_id,
            'type' => $this->type,
            'content' => $this->content,
            'file_path' => $this->file_path,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'duration' => $this->duration,
            'is_read' => $this->is_read,
            'reply_to_message_id' => $this->reply_to_message_id,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'sender' => [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'avatar' => $this->sender_avatar
            ]
        ];
    }
}