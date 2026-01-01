<?php

namespace App\Services;

use App\Database\Database;
use App\Models\User;
use DateTime;

class AuthService
{
    public static function sendVerificationCode(string $phone, string $type = 'registration'): bool
    {
        // Генерируем 6-значный код
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Устанавливаем срок действия (15 минут)
        $expiresAt = new DateTime('+15 minutes');
        
        // Сохраняем код в базу
        $data = [
            'phone' => $phone,
            'code' => $code,
            'type' => $type,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s')
        ];
        
        try {
            Database::insert('verification_codes', $data);
            
            // Здесь должна быть интеграция с SMS-провайдером
            // Для примера просто логируем код
            error_log("Verification code for {$phone}: {$code}");
            
            return true;
        } catch (\Exception $e) {
            error_log("Failed to send verification code: " . $e->getMessage());
            return false;
        }
    }
    
    public static function verifyCode(string $phone, string $code, string $type = 'registration'): bool
    {
        $sql = "
            SELECT * FROM verification_codes 
            WHERE phone = ? AND code = ? AND type = ? AND used_at IS NULL AND expires_at > NOW()
            ORDER BY created_at DESC 
            LIMIT 1
        ";
        
        $verification = Database::fetch($sql, [$phone, $code, $type]);
        
        if (!$verification) {
            return false;
        }
        
        // Помечаем код как использованный
        Database::update('verification_codes', 
            ['used_at' => date('Y-m-d H:i:s')], 
            ['id' => $verification['id']]
        );
        
        return true;
    }
    
    public static function register(array $data): ?User
    {
        // Валидация данных
        if (!self::validateRegistrationData($data)) {
            return null;
        }
        
        // Проверяем, существует ли пользователь с таким телефоном или email
        if (User::findByPhone($data['phone']) || User::findByEmail($data['email'])) {
            return null;
        }
        
        // Проверяем код верификации
        if (!self::verifyCode($data['phone'], $data['verification_code'], 'registration')) {
            return null;
        }
        
        // Создаем пользователя
        $userId = User::create([
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => $data['password'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name']
        ]);
        
        if (!$userId) {
            return null;
        }
        
        // Подтверждаем телефон
        $user = User::findById($userId);
        $user->verifyPhone();
        
        return $user;
    }
    
    public static function login(string $identifier, string $password): ?array
    {
        // Ищем пользователя по телефону или email
        $user = is_numeric(str_replace(['+', '-', ' ', '(', ')'], '', $identifier)) 
            ? User::findByPhone($identifier) 
            : User::findByEmail($identifier);
        
        if (!$user || !password_verify($password, $user->password)) {
            return null;
        }
        
        // Генерируем JWT токен
        $token = User::generateJWT($user->id);
        
        // Обновляем время последнего входа
        $user->updateLastSeen();
        
        return [
            'user' => $user->toArray(),
            'token' => $token
        ];
    }
    
    public static function loginWithTelegram(int $telegramId, string $firstName, string $lastName = '', ?string $username = null): ?array
    {
        // Ищем пользователя по Telegram ID
        $user = User::findByTelegramId($telegramId);
        
        if (!$user) {
            // Создаем нового пользователя если его нет
            $userId = User::create([
                'phone' => "tg_{$telegramId}", // Временный номер
                'email' => "{$telegramId}@telegram.local",
                'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'telegram_id' => $telegramId,
                'telegram_username' => $username
            ]);
            
            if (!$userId) {
                return null;
            }
            
            $user = User::findById($userId);
        } else {
            // Обновляем информацию Telegram если она изменилась
            $updateData = [];
            if ($user->first_name !== $firstName) $updateData['first_name'] = $firstName;
            if ($user->last_name !== $lastName) $updateData['last_name'] = $lastName;
            if ($user->telegram_username !== $username) $updateData['telegram_username'] = $username;
            
            if (!empty($updateData)) {
                $user->update($updateData);
            }
        }
        
        // Генерируем JWT токен
        $token = User::generateJWT($user->id);
        
        // Обновляем время последнего входа
        $user->updateLastSeen();
        
        return [
            'user' => $user->toArray(),
            'token' => $token
        ];
    }
    
    private static function validateRegistrationData(array $data): bool
    {
        $required = ['phone', 'email', 'password', 'first_name', 'last_name', 'verification_code'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        
        // Валидация email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Валидация пароля (минимум 6 символов)
        if (strlen($data['password']) < 6) {
            return false;
        }
        
        return true;
    }
}