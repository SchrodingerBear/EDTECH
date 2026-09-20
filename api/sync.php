<?php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'push':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        handlePush($pdo);
        break;
        
    case 'pull':
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        handlePull($pdo);
        break;
        
    case 'pull_all':
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        handlePullAll($pdo);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function handlePush($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['entity'], $input['action'], $input['data'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        return;
    }
    
    $entity = $input['entity'];
    $action = $input['action'];
    $data = $input['data'];
    
    try {
        $pdo->beginTransaction();
        
        switch ($entity) {
            case 'orders':
                $result = syncOrder($pdo, $action, $data);
                break;
            case 'order_items':
                $result = syncOrderItems($pdo, $action, $data);
                break;
            case 'customers':
                $result = syncCustomer($pdo, $action, $data);
                break;
            case 'inventory':
                $result = syncInventory($pdo, $action, $data);
                break;
            case 'inventory_usage':
                $result = syncInventoryUsage($pdo, $action, $data);
                break;
            case 'services':
                $result = syncService($pdo, $action, $data);
                break;
            default:
                throw new Exception("Unknown entity: $entity");
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'data' => $result]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handlePull($pdo) {
    $type = $_GET['type'] ?? '';
    $since = $_GET['since'] ?? '1970-01-01 00:00:00';
    
    try {
        $data = [];
        
        switch ($type) {
            case 'orders':
                $stmt = $pdo->prepare("SELECT * FROM laundry_orders WHERE updated_at > ? ORDER BY updated_at ASC");
                $stmt->execute([$since]);
                $data = $stmt->fetchAll();
                break;
            case 'order_items':
                $stmt = $pdo->prepare("SELECT oi.* FROM order_items oi JOIN laundry_orders lo ON lo.id = oi.order_id WHERE oi.updated_at > ? OR lo.updated_at > ? ORDER BY oi.updated_at ASC");
                $stmt->execute([$since, $since]);
                $data = $stmt->fetchAll();
                break;
            case 'customers':
                $stmt = $pdo->prepare("SELECT * FROM customers WHERE updated_at > ? ORDER BY updated_at ASC");
                $stmt->execute([$since]);
                $data = $stmt->fetchAll();
                break;
            case 'inventory':
                $stmt = $pdo->prepare("SELECT * FROM inventory_items WHERE updated_at > ? ORDER BY updated_at ASC");
                $stmt->execute([$since]);
                $data = $stmt->fetchAll();
                break;
            case 'inventory_usage':
                $stmt = $pdo->prepare("SELECT * FROM inventory_usage WHERE updated_at > ? ORDER BY updated_at ASC");
                $stmt->execute([$since]);
                $data = $stmt->fetchAll();
                break;
            case 'services':
                $stmt = $pdo->prepare("SELECT * FROM services WHERE updated_at > ? ORDER BY updated_at ASC");
                $stmt->execute([$since]);
                $data = $stmt->fetchAll();
                break;
            case 'settings':
                $stmt = $pdo->prepare("SELECT * FROM settings WHERE updated_at > ? ORDER BY updated_at ASC");
                $stmt->execute([$since]);
                $data = $stmt->fetchAll();
                break;
            default:
                throw new Exception("Unknown type: $type");
        }
        
        echo json_encode(['success' => true, 'data' => $data, 'server_time' => date('Y-m-d H:i:s')]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handlePullAll($pdo) {
    try {
        $data = [];
        
        $tables = ['laundry_orders', 'order_items', 'customers', 'inventory_items', 'inventory_usage', 'services', 'settings'];
        
        foreach ($tables as $table) {
            $stmt = $pdo->query("SELECT * FROM $table");
            $data[$table] = $stmt->fetchAll();
        }
        
        echo json_encode(['success' => true, 'data' => $data, 'server_time' => date('Y-m-d H:i:s')]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Sync functions for each entity
function syncOrder($pdo, $action, $data) {
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM laundry_orders WHERE id = ?");
        $stmt->execute([$data['id']]);
        return ['deleted' => true];
    }
    
    $fields = [
        'id', 'order_no', 'customer_id', 'status', 'payment_status', 'pickup_type',
        'delivery_address', 'pickup_date', 'notes', 'subtotal', 'delivery_fee',
        'discount', 'total', 'amount_paid', 'assigned_employee_id', 'created_by',
        'receipt_token', 'created_at', 'updated_at'
    ];
    
    $placeholders = implode(', ', array_map(fn($f) => ":$f", $fields));
    $columns = implode(', ', $fields);
    $updates = implode(', ', array_map(fn($f) => "$f = VALUES($f)", array_filter($fields, fn($f) => $f !== 'id')));
    
    $sql = "INSERT INTO laundry_orders ($columns) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updates";
    $stmt = $pdo->prepare($sql);
    
    $params = [];
    foreach ($fields as $f) {
        $params[":$f"] = $data[$f] ?? null;
    }
    
    $stmt->execute($params);
    
    if ($action === 'create' && !$data['id']) {
        $data['id'] = $pdo->lastInsertId();
    }
    
    return $data;
}

function syncOrderItems($pdo, $action, $data) {
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM order_items WHERE id = ?");
        $stmt->execute([$data['id']]);
        return ['deleted' => true];
    }
    
    $fields = ['id', 'order_id', 'service_id', 'quantity', 'unit_price', 'line_total', 'updated_at'];
    $placeholders = implode(', ', array_map(fn($f) => ":$f", $fields));
    $columns = implode(', ', $fields);
    $updates = implode(', ', array_map(fn($f) => "$f = VALUES($f)", array_filter($fields, fn($f) => $f !== 'id')));
    
    $sql = "INSERT INTO order_items ($columns) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updates";
    $stmt = $pdo->prepare($sql);
    
    $params = [];
    foreach ($fields as $f) {
        $params[":$f"] = $data[$f] ?? null;
    }
    
    $stmt->execute($params);
    
    if ($action === 'create' && !$data['id']) {
        $data['id'] = $pdo->lastInsertId();
    }
    
    return $data;
}

function syncCustomer($pdo, $action, $data) {
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$data['id']]);
        return ['deleted' => true];
    }
    
    $fields = ['id', 'first_name', 'last_name', 'phone', 'email', 'address', 'notes', 'created_at', 'updated_at'];
    $placeholders = implode(', ', array_map(fn($f) => ":$f", $fields));
    $columns = implode(', ', $fields);
    $updates = implode(', ', array_map(fn($f) => "$f = VALUES($f)", array_filter($fields, fn($f) => $f !== 'id')));
    
    $sql = "INSERT INTO customers ($columns) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updates";
    $stmt = $pdo->prepare($sql);
    
    $params = [];
    foreach ($fields as $f) {
        $params[":$f"] = $data[$f] ?? null;
    }
    
    $stmt->execute($params);
    
    if ($action === 'create' && !$data['id']) {
        $data['id'] = $pdo->lastInsertId();
    }
    
    return $data;
}

function syncInventory($pdo, $action, $data) {
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM inventory_items WHERE id = ?");
        $stmt->execute([$data['id']]);
        return ['deleted' => true];
    }
    
    $fields = ['id', 'name', 'category', 'unit', 'current_stock', 'minimum_stock', 'cost_per_unit', 'is_active', 'created_at', 'updated_at'];
    $placeholders = implode(', ', array_map(fn($f) => ":$f", $fields));
    $columns = implode(', ', $fields);
    $updates = implode(', ', array_map(fn($f) => "$f = VALUES($f)", array_filter($fields, fn($f) => $f !== 'id')));
    
    $sql = "INSERT INTO inventory_items ($columns) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updates";
    $stmt = $pdo->prepare($sql);
    
    $params = [];
    foreach ($fields as $f) {
        $params[":$f"] = $data[$f] ?? null;
    }
    
    $stmt->execute($params);
    
    if ($action === 'create' && !$data['id']) {
        $data['id'] = $pdo->lastInsertId();
    }
    
    return $data;
}

function syncInventoryUsage($pdo, $action, $data) {
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM inventory_usage WHERE id = ?");
        $stmt->execute([$data['id']]);
        return ['deleted' => true];
    }
    
    $fields = ['id', 'service_id', 'inventory_item_id', 'usage_per_kg', 'consumption_type', 'created_at', 'updated_at'];
    $placeholders = implode(', ', array_map(fn($f) => ":$f", $fields));
    $columns = implode(', ', $fields);
    $updates = implode(', ', array_map(fn($f) => "$f = VALUES($f)", array_filter($fields, fn($f) => $f !== 'id')));
    
    $sql = "INSERT INTO inventory_usage ($columns) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updates";
    $stmt = $pdo->prepare($sql);
    
    $params = [];
    foreach ($fields as $f) {
        $params[":$f"] = $data[$f] ?? null;
    }
    
    $stmt->execute($params);
    
    if ($action === 'create' && !$data['id']) {
        $data['id'] = $pdo->lastInsertId();
    }
    
    return $data;
}

function syncService($pdo, $action, $data) {
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
        $stmt->execute([$data['id']]);
        return ['deleted' => true];
    }
    
    $fields = ['id', 'name', 'unit', 'price', 'icon', 'description', 'is_active', 'created_at', 'updated_at'];
    $placeholders = implode(', ', array_map(fn($f) => ":$f", $fields));
    $columns = implode(', ', $fields);
    $updates = implode(', ', array_map(fn($f) => "$f = VALUES($f)", array_filter($fields, fn($f) => $f !== 'id')));
    
    $sql = "INSERT INTO services ($columns) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updates";
    $stmt = $pdo->prepare($sql);
    
    $params = [];
    foreach ($fields as $f) {
        $params[":$f"] = $data[$f] ?? null;
    }
    
    $stmt->execute($params);
    
    if ($action === 'create' && !$data['id']) {
        $data['id'] = $pdo->lastInsertId();
    }
    
    return $data;
}