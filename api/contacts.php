<?php

require_once __DIR__ . '/../../bootstrap/app.php';

use App\Models\Contact;
use App\Models\User;

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
        case 'get_contacts':
            $contacts = Contact::findByUserId($user->id);
            $formattedContacts = array_map(function($contact) {
                return $contact->toArray();
            }, $contacts);
            
            echo json_encode([
                'success' => true,
                'contacts' => $formattedContacts
            ]);
            break;
            
        case 'add_contact':
            $contactUserId = $input['contact_user_id'] ?? 0;
            $nickname = $input['nickname'] ?? null;
            
            if (!$contactUserId) {
                throw new Exception('Contact user ID is required');
            }
            
            $success = Contact::addContact($user->id, $contactUserId, $nickname);
            
            if (!$success) {
                throw new Exception('Failed to add contact');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Contact added successfully'
            ]);
            break;
            
        case 'remove_contact':
            $contactUserId = $input['contact_user_id'] ?? 0;
            
            if (!$contactUserId) {
                throw new Exception('Contact user ID is required');
            }
            
            $success = Contact::removeContact($user->id, $contactUserId);
            
            if (!$success) {
                throw new Exception('Failed to remove contact');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Contact removed successfully'
            ]);
            break;
            
        case 'search_contacts':
            $query = $_GET['query'] ?? '';
            
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'contacts' => []]);
                break;
            }
            
            $contacts = Contact::searchContacts($user->id, $query);
            $formattedContacts = array_map(function($contact) {
                return $contact->toArray();
            }, $contacts);
            
            echo json_encode([
                'success' => true,
                'contacts' => $formattedContacts
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