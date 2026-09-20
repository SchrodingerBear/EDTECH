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
            // Handle order upsert
            $orderId = $payload['id'] ?? 0;
            $orderNo = $payload['order_no'] ?? '';
            
            $orderData = [
                'customer_id' => $payload['customer_id'] ?? 0,
                'status' => $payload['status'] ?? 'pending',
                'payment_status' => $payload['payment_status'] ?? 'unpaid',
                'pickup_type' => $payload['pickup_type'] ?? 'walk_in',
                'delivery_address' => $payload['delivery_address'] ?? null,
                'pickup_date' => $payload['pickup_date'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'subtotal' => $payload['subtotal'] ?? 0,
                'delivery_fee' => $payload['delivery_fee'] ?? 0,
                'discount' => $payload['discount'] ?? 0,
                'total' => $payload['total'] ?? 0,
                'amount_paid' => $payload['amount_paid'] ?? 0,
                'assigned_employee_id' => $payload['assigned_employee_id'] ?? null,
                'receipt_token' => $payload['receipt_token'] ?? null,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($orderId > 0) {
                $c->update('laundry_orders', $orderData, ['id' => $orderId]);
            } else {
                $orderData['order_no'] = $orderNo ?: 'LAV-'.date('Y').'-'.str_pad($c->count('laundry_orders')+1, 4, '0', STR_PAD_LEFT);
                $orderData['created_by'] = current_user()['id'] ?? null;
                $orderData['created_at'] = $payload['created_at'] ?? date('Y-m-d H:i:s');
                $orderId = $c->insert('laundry_orders', $orderData);
            }
            
            // Sync order items
            if (!empty($payload['items'])) {
                foreach ($payload['items'] as $item) {
                    $itemData = [
                        'order_id' => $orderId,
                        'service_id' => $item['service_id'] ?? 0,
                        'quantity' => $item['quantity'] ?? 0,
                        'unit_price' => $item['unit_price'] ?? 0,
                        'line_total' => $item['line_total'] ?? 0
                    ];
                    if (!empty($item['id'])) {
                        $c->update('order_items', $itemData, ['id' => $item['id']]);
                    } else {
                        $c->insert('order_items', $itemData);
                    }
                }
            }
            
            echo json_encode(['success' => true, 'order_id' => $orderId]);
            break;
            
        case 'delete':
            $c->delete('order_items', ['order_id' => $payload['id']]);
            $c->delete('laundry_orders', ['id' => $payload['id']]);
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