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
            $itemData = [
                'name' => $payload['name'] ?? '',
                'category' => $payload['category'] ?? 'other',
                'unit' => $payload['unit'] ?? 'ml',
                'current_stock' => $payload['current_stock'] ?? 0,
                'minimum_stock' => $payload['minimum_stock'] ?? 0,
                'cost_per_unit' => $payload['cost_per_unit'] ?? 0,
                'is_active' => $payload['is_active'] ?? 1,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($action === 'update' && !empty($payload['id'])) {
                $c->update('inventory_items', $itemData, ['id' => $payload['id']]);
                $itemId = $payload['id'];
            } else {
                $itemData['created_at'] = $payload['created_at'] ?? date('Y-m-d H:i:s');
                $itemId = $c->insert('inventory_items', $itemData);
            }
            
            echo json_encode(['success' => true, 'item_id' => $itemId]);
            break;
            
        case 'delete':
            $c->delete('inventory_items', ['id' => $payload['id']]);
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