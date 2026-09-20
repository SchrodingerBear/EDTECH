<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['action'], $data['payload'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$action = $data['action'];
$payload = $data['payload'];

try {
    $c = crud();
    
    switch ($action) {
        case 'create':
        case 'update':
            $customerData = [
                'first_name' => $payload['first_name'] ?? '',
                'last_name' => $payload['last_name'] ?? '',
                'phone' => $payload['phone'] ?? '',
                'email' => $payload['email'] ?? null,
                'address' => $payload['address'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($action === 'update' && !empty($payload['id'])) {
                $c->update('customers', $customerData, ['id' => $payload['id']]);
                $customerId = $payload['id'];
            } else {
                $customerData['created_at'] = $payload['created_at'] ?? date('Y-m-d H:i:s');
                $customerId = $c->insert('customers', $customerData);
            }
            
            echo json_encode(['success' => true, 'customer_id' => $customerId]);
            break;
            
        case 'delete':
            $c->delete('customers', ['id' => $payload['id']]);
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}