<?php
require_once __DIR__ . '/config.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    http_response_code(400);
    echo json_encode(['error' => 'Token required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT lo.*, cu.first_name, cu.last_name, cu.phone, cu.email, cu.address
        FROM laundry_orders lo
        JOIN customers cu ON cu.id = lo.customer_id
        WHERE lo.receipt_token = ?
    ");
    $stmt->execute([$token]);
    $order = $stmt->fetch();
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Receipt not found']);
        exit;
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, s.name as service_name, s.unit
        FROM order_items oi
        JOIN services s ON s.id = oi.service_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order['id']]);
    $items = $stmt->fetchAll();
    
    // Get business settings
    $settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch() ?? [];
    
    $order['items'] = $items;
    $order['business'] = [
        'name' => $settings['business_name'] ?? 'Lavadora Laundry',
        'phone' => $settings['phone'] ?? '',
        'email' => $settings['email'] ?? '',
        'address' => $settings['address'] ?? ''
    ];
    
    // Update view tracking
    try {
        $pdo->prepare("UPDATE laundry_orders SET receipt_view_count = receipt_view_count + 1, receipt_viewed_at = NOW() WHERE id = ?")->execute([$order['id']]);
    } catch (Exception $e) {
        // Ignore view tracking errors
    }
    
    echo json_encode(['success' => true, 'data' => $order]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}