<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$c = crud();

try {
    switch ($type) {
        case 'orders':
            $orders = $c->raw(
                "SELECT lo.*, cu.first_name as customer_name, cu.phone as customer_phone
                 FROM laundry_orders lo
                 LEFT JOIN customers cu ON cu.id = lo.customer_id
                 ORDER BY lo.updated_at DESC LIMIT 500"
            )->fetchAll();
            
            $orderIds = array_column($orders, 'id');
            $itemsMap = [];
            if ($orderIds) {
                $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
                $items = $c->raw(
                    "SELECT oi.*, s.name as service_name, s.unit
                     FROM order_items oi
                     JOIN services s ON s.id = oi.service_id
                     WHERE oi.order_id IN ($placeholders)",
                    $orderIds
                )->fetchAll();
                foreach ($items as $item) {
                    $itemsMap[$item['order_id']][] = $item;
                }
            }
            
            foreach ($orders as &$o) {
                $o['items'] = $itemsMap[$o['id']] ?? [];
            }
            echo json_encode($orders);
            break;
            
        case 'customers':
            $customers = $c->select('customers', '*', [], 'ORDER BY updated_at DESC LIMIT 1000');
            echo json_encode($customers);
            break;
            
        case 'inventory':
            $inventory = $c->select('inventory_items', '*', [], 'ORDER BY updated_at DESC LIMIT 500');
            echo json_encode($inventory);
            break;
            
        case 'services':
            $services = $c->select('services', '*', ['is_active' => 1], 'ORDER BY name');
            echo json_encode($services);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid type']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}