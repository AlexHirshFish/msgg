<?php

namespace App\Models;

use App\Database\Database;
use DateTime;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class User
{
    public int $id;
    public string $phone;
    public string $email;
    public string $password;
    public string $first_name;
    public string $last_name;
    public ?string $avatar;
    public ?int $telegram_id;
    public ?string $telegram_username;
    public ?DateTime $phone_verified_at;
    public ?DateTime $email_verified_at;
    public bool $is_active;
    public DateTime $last_seen;
    public DateTime $created_at;
    public DateTime $updated_at;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                switch ($key) {
                    case 'phone_verified_at':
                    case 'email_verified_at':
                    case 'last_seen':
                    case 'created_at':
                    case 'updated_at':
                        $this->$key = $value ? new DateTime($value) : null;
                        break;
                    case 'is_active':
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
        // Хешируем пароль
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Устанавливаем время создания
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return Database::insert('users', $data);
    }

    public static function findByPhone(string $phone): ?User
    {
        $data = Database::fetch("SELECT * FROM users WHERE phone = ?", [$phone]);
        
        return $data ? new User($data) : null;
    }

    public static function findByEmail(string $email): ?User
    {
        $data = Database::fetch("SELECT * FROM users WHERE email = ?", [$email]);
        
        return $data ? new User($data) : null;
    }

    public static function findById(int $id): ?User
    {
        $data = Database::fetch("SELECT * FROM users WHERE id = ?", [$id]);
        
        return $data ? new User($data) : null;
    }

    public static function findByTelegramId(int $telegramId): ?User
    {
        $data = Database::fetch("SELECT * FROM users WHERE telegram_id = ?", [$telegramId]);
        
        return $data ? new User($data) : null;
    }

    public function update(array $data): int
    {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return Database::update('users', $data, ['id' => $this->id]);
    }

    public function verifyPhone(): bool
    {
        $this->phone_verified_at = new DateTime();
        return $this->update(['phone_verified_at' => $this->phone_verified_at->format('Y-m-d H:i:s')]) > 0;
    }

    public function verifyEmail(): bool
    {
        $this->email_verified_at = new DateTime();
        return $this->update(['email_verified_at' => $this->email_verified_at->format('Y-m-d H:i:s')]) > 0;
    }

    public function updateLastSeen(): bool
    {
        $this->last_seen = new DateTime();
        return $this->update(['last_seen' => $this->last_seen->format('Y-m-d H:i:s')]) > 0;
    }

    public function linkTelegram(int $telegramId, ?string $telegramUsername = null): bool
    {
        $updateData = ['telegram_id' => $telegramId];
        if ($telegramUsername) {
            $updateData['telegram_username'] = $telegramUsername;
        }
        
        return $this->update($updateData) > 0;
    }

    public static function generateJWT(int $userId): string
    {
        $payload = [
            'iat' => time(),
            'exp' => time() + config('jwt.expires_in'),
            'sub' => $userId
        ];

        return JWT::encode($payload, config('jwt.secret'), 'HS256');
    }

    public static function validateJWT(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));
            return (array) $decoded;
        } catch (Exception $e) {
            return null;
        }
    }

    public static function findByJWT(string $token): ?User
    {
        $decoded = self::validateJWT($token);
        if (!$decoded) {
            return null;
        }

        return self::findById($decoded['sub'] ?? 0);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'phone' => $this->phone,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'avatar' => $this->avatar,
            'telegram_id' => $this->telegram_id,
            'telegram_username' => $this->telegram_username,
            'phone_verified_at' => $this->phone_verified_at ? $this->phone_verified_at->format('Y-m-d H:i:s') : null,
            'email_verified_at' => $this->email_verified_at ? $this->email_verified_at->format('Y-m-d H:i:s') : null,
            'is_active' => $this->is_active,
            'last_seen' => $this->last_seen->format('Y-m-d H:i:s')
        ];
    }
}