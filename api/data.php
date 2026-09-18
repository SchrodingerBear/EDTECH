<?php
/**
 * api/data.php — Initial Data Seed Endpoint
 * 
 * Called by app.js on first boot when IndexedDB is empty.
 * Downloads all existing orders, customers, services, and settings
 * into the browser's IndexedDB for offline use.
 * 
 * This is a READ-ONLY endpoint. It does not modify any data.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Basic auth check — only logged in admin can seed data
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action !== 'initial_seed') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    $c = crud();

    // ----- ORDERS -----
    $orders = $c->raw(
        "SELECT lo.*, cu.first_name, cu.phone, cu.email, cu.address
         FROM laundry_orders lo
         JOIN customers cu ON cu.id = lo.customer_id
         ORDER BY lo.created_at DESC
         LIMIT 500"
    )->fetchAll();

    // Attach items to each order
    $orderIds = array_column($orders, 'id');
    $itemsMap = [];
    if (!empty($orderIds)) {
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

    $ordersOut = [];
    foreach ($orders as $order) {
        $ordersOut[] = array_merge($order, [
            'items'          => $itemsMap[$order['id']] ?? [],
            'synced'         => true,
            'sync_timestamp' => date('c'),
        ]);
    }

    // ----- CUSTOMERS -----
    $customers = $c->raw(
        "SELECT * FROM customers ORDER BY first_name ASC LIMIT 1000"
    )->fetchAll();
    $customersOut = array_map(fn($cu) => array_merge($cu, ['synced' => true, 'sync_timestamp' => date('c')]), $customers);

    // ----- SERVICES -----
    $services = $c->raw("SELECT * FROM services WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

    // ----- INVENTORY -----
    $inventoryItems = $c->raw("SELECT * FROM inventory_items")->fetchAll();
    $inventoryItemsOut = array_map(fn($item) => array_merge($item, ['synced' => true, 'sync_timestamp' => date('c')]), $inventoryItems);

    $inventoryMovements = $c->raw("SELECT * FROM inventory_movements ORDER BY created_at DESC LIMIT 500")->fetchAll();
    $inventoryMovementsOut = array_map(fn($mov) => array_merge($mov, ['synced' => true, 'sync_timestamp' => date('c')]), $inventoryMovements);

    // ----- SETTINGS -----
    $settingsRow = $c->get('settings', 1) ?? [];
    $settings = [
        'business_name'    => $settingsRow['business_name'] ?? APP_NAME,
        'business_phone'   => $settingsRow['phone'] ?? '',
        'business_address' => $settingsRow['address'] ?? '',
        'business_email'   => $settingsRow['email'] ?? '',
    ];

    echo json_encode([
        'success'   => true,
        'orders'    => $ordersOut,
        'customers' => $customersOut,
        'services'  => $services,
        'inventory_items' => $inventoryItemsOut,
        'inventory_movements' => $inventoryMovementsOut,
        'settings'  => $settings,
        'seeded_at' => date('c'),
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
