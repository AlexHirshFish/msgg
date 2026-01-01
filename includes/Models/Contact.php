<?php

namespace App\Models;

use App\Database\Database;
use DateTime;

class Contact
{
    public int $id;
    public int $user_id;
    public int $contact_user_id;
    public ?string $nickname;
    public DateTime $added_at;
    public ?array $contact_info;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                switch ($key) {
                    case 'added_at':
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
        $data['added_at'] = date('Y-m-d H:i:s');
        
        return Database::insert('contacts', $data);
    }

    public static function findByUserId(int $userId): array
    {
        $sql = "
            SELECT c.*, u.first_name, u.last_name, u.phone, u.avatar
            FROM contacts c
            INNER JOIN users u ON c.contact_user_id = u.id
            WHERE c.user_id = ?
            ORDER BY c.added_at DESC
        ";
        
        $contactsData = Database::fetchAll($sql, [$userId]);
        $contacts = [];
        
        foreach ($contactsData as $contactData) {
            $contact = new Contact($contactData);
            $contact->contact_info = [
                'first_name' => $contactData['first_name'],
                'last_name' => $contactData['last_name'],
                'phone' => $contactData['phone'],
                'avatar' => $contactData['avatar']
            ];
            $contacts[] = $contact;
        }
        
        return $contacts;
    }

    public static function findByUserAndContact(int $userId, int $contactUserId): ?Contact
    {
        $data = Database::fetch(
            "SELECT * FROM contacts WHERE user_id = ? AND contact_user_id = ?",
            [$userId, $contactUserId]
        );
        
        return $data ? new Contact($data) : null;
    }

    public static function addContact(int $userId, int $contactUserId, ?string $nickname = null): bool
    {
        // Проверяем, что пользователи не совпадают
        if ($userId === $contactUserId) {
            return false;
        }
        
        // Проверяем существование обоих пользователей
        $userExists = Database::fetch("SELECT id FROM users WHERE id = ?", [$userId]);
        $contactUserExists = Database::fetch("SELECT id FROM users WHERE id = ?", [$contactUserId]);
        
        if (!$userExists || !$contactUserExists) {
            return false;
        }
        
        // Проверяем, не добавлен ли контакт уже
        $existing = self::findByUserAndContact($userId, $contactUserId);
        if ($existing) {
            return false;
        }
        
        $data = [
            'user_id' => $userId,
            'contact_user_id' => $contactUserId,
            'nickname' => $nickname,
            'added_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            Database::insert('contacts', $data);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function removeContact(int $userId, int $contactUserId): bool
    {
        return Database::delete('contacts', [
            'user_id' => $userId,
            'contact_user_id' => $contactUserId
        ]) > 0;
    }

    public static function searchContacts(int $userId, string $query): array
    {
        $sql = "
            SELECT c.*, u.first_name, u.last_name, u.phone, u.avatar
            FROM contacts c
            INNER JOIN users u ON c.contact_user_id = u.id
            WHERE c.user_id = ? 
            AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ?)
            ORDER BY u.first_name, u.last_name
        ";
        
        $searchQuery = "%{$query}%";
        $contactsData = Database::fetchAll($sql, [$userId, $searchQuery, $searchQuery, $searchQuery]);
        $contacts = [];
        
        foreach ($contactsData as $contactData) {
            $contact = new Contact($contactData);
            $contact->contact_info = [
                'first_name' => $contactData['first_name'],
                'last_name' => $contactData['last_name'],
                'phone' => $contactData['phone'],
                'avatar' => $contactData['avatar']
            ];
            $contacts[] = $contact;
        }
        
        return $contacts;
    }

    public function update(array $data): int
    {
        return Database::update('contacts', $data, ['id' => $this->id]);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'contact_user_id' => $this->contact_user_id,
            'nickname' => $this->nickname,
            'added_at' => $this->added_at->format('Y-m-d H:i:s'),
            'contact_info' => $this->contact_info ?? null
        ];
    }
}