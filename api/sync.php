<?php
/**
 * api/sync.php — The Sync Endpoint
 * 
 * Receives batched changes from sync-manager.js and writes them to MySQL.
 * This is the ONLY place the JS talks to the database for writes.
 * 
 * All writes are safe — they use INSERT ... ON DUPLICATE KEY UPDATE
 * so re-syncing the same record is idempotent (no duplicates).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['entity_type'], $data['action'], $data['payload'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$entityType = $data['entity_type'];
$action     = $data['action'];
$payload    = $data['payload'];

try {
    $pdo = db();

    switch ($entityType) {

        // ===== ORDERS =====
        case 'order':
            if ($action === 'create') {
                $stmt = $pdo->prepare("
                    INSERT INTO laundry_orders
                        (id, order_no, customer_id, status, pickup_type, pickup_date, delivery_address,
                         notes, subtotal, delivery_fee, discount, total, amount_paid, payment_status,
                         receipt_token, created_at, updated_at)
                    VALUES
                        (:id, :order_no, :customer_id, :status, :pickup_type, :pickup_date, :delivery_address,
                         :notes, :subtotal, :delivery_fee, :discount, :total, :amount_paid, :payment_status,
                         :receipt_token, :created_at, :updated_at)
                    ON DUPLICATE KEY UPDATE
                        status = VALUES(status),
                        updated_at = VALUES(updated_at)
                ");
                $stmt->execute([
                    ':id'               => $payload['id'],
                    ':order_no'         => $payload['order_no'],
                    ':customer_id'      => $payload['customer_id'],
                    ':status'           => $payload['status'],
                    ':pickup_type'      => $payload['pickup_type'],
                    ':pickup_date'      => $payload['pickup_date'],
                    ':delivery_address' => $payload['delivery_address'] ?? null,
                    ':notes'            => $payload['notes'] ?? '',
                    ':subtotal'         => $payload['subtotal'],
                    ':delivery_fee'     => $payload['delivery_fee'] ?? 0,
                    ':discount'         => $payload['discount'] ?? 0,
                    ':total'            => $payload['total'],
                    ':amount_paid'      => $payload['amount_paid'] ?? 0,
                    ':payment_status'   => $payload['payment_status'] ?? 'unpaid',
                    ':receipt_token'    => $payload['receipt_token'],
                    ':created_at'       => $payload['created_at'],
                    ':updated_at'       => $payload['updated_at'],
                ]);

                // Sync order items
                if (!empty($payload['items'])) {
                    foreach ($payload['items'] as $item) {
                        $istmt = $pdo->prepare("
                            INSERT INTO order_items (order_id, service_id, quantity, unit_price, line_total)
                            VALUES (:order_id, :service_id, :quantity, :unit_price, :line_total)
                            ON DUPLICATE KEY UPDATE
                                quantity = VALUES(quantity), line_total = VALUES(line_total)
                        ");
                        $istmt->execute([
                            ':order_id'   => $payload['id'],
                            ':service_id' => $item['service_id'],
                            ':quantity'   => $item['quantity'],
                            ':unit_price' => $item['unit_price'],
                            ':line_total' => $item['line_total'],
                        ]);
                    }
                }

            } elseif ($action === 'update') {
                // Partial update — only touch fields that were sent
                $allowed = ['status', 'amount_paid', 'payment_status', 'notes', 'updated_at'];
                $sets = [];
                $params = [':id' => $payload['id']];
                foreach ($allowed as $field) {
                    if (array_key_exists($field, $payload)) {
                        $sets[] = "`$field` = :$field";
                        $params[":$field"] = $payload[$field];
                    }
                }
                if (!empty($sets)) {
                    $pdo->prepare("UPDATE laundry_orders SET " . implode(', ', $sets) . " WHERE id = :id")
                        ->execute($params);
                }
            }
            break;

        // ===== CUSTOMERS =====
        case 'customer':
            if ($action === 'create') {
                $stmt = $pdo->prepare("
                    INSERT INTO customers (id, first_name, phone, email, address, notes, created_at, updated_at)
                    VALUES (:id, :first_name, :phone, :email, :address, :notes, :created_at, :updated_at)
                    ON DUPLICATE KEY UPDATE
                        first_name = VALUES(first_name),
                        phone = VALUES(phone),
                        updated_at = VALUES(updated_at)
                ");
                $stmt->execute([
                    ':id'         => $payload['id'],
                    ':first_name' => $payload['first_name'],
                    ':phone'      => $payload['phone'],
                    ':email'      => $payload['email'] ?? null,
                    ':address'    => $payload['address'] ?? null,
                    ':notes'      => $payload['notes'] ?? '',
                    ':created_at' => $payload['created_at'],
                    ':updated_at' => $payload['updated_at'],
                ]);
            } elseif ($action === 'update') {
                $allowed = ['first_name', 'phone', 'email', 'address', 'notes', 'updated_at'];
                $sets = [];
                $params = [':id' => $payload['id']];
                foreach ($allowed as $field) {
                    if (array_key_exists($field, $payload)) {
                        $sets[] = "`$field` = :$field";
                        $params[":$field"] = $payload[$field];
                    }
                }
                if (!empty($sets)) {
                    $pdo->prepare("UPDATE customers SET " . implode(', ', $sets) . " WHERE id = :id")
                        ->execute($params);
                }
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Unknown entity type: $entityType"]);
            exit;
    }

    echo json_encode(['success' => true, 'synced_at' => date('c')]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sync error: ' . $e->getMessage()]);
}
