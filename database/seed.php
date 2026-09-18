<?php
/**
 * Lavadora — demo data seeder.
 * Run from CLI:  php database/seed.php
 * (or via browser if DB is already configured).
 */

require_once __DIR__ . '/../includes/functions.php';

$c = crud();

echo "Lavadora demo seeder\n";

// Only seed if empty
if ($c->count('customers') > 0) {
    echo "Customers already exist — skipping.\n";
    exit(0);
}

$customers = [
    ['Juan', 'Dela Cruz', '0917-111-2222', 'juan@example.com', '123 Luna St, Poblacion, Manila'],
    ['Maria', 'Santos', '0918-333-4444', 'maria@example.com', '45 Rizal Ave, Quezon City'],
    ['Pedro', 'Reyes', '0919-555-6666', null, '8 Magsaysay Rd, Makati'],
    ['Ana', 'Garcia', '0920-777-8888', 'ana@example.com', '210 Aguinaldo St, Pasig'],
    ['Luis', 'Mendoza', '0921-999-0000', null, '33 Bonifacio Dr, Taguig'],
    ['Carla', 'Torres', '0922-123-4567', 'carla@example.com', '77 Mabini St, Mandaluyong'],
];

$customerIds = [];
foreach ($customers as [$f, $l, $p, $e, $a]) {
    $customerIds[] = $c->insert('customers', [
        'first_name' => $f, 'last_name' => $l, 'phone' => $p, 'email' => $e, 'address' => $a,
    ]);
}
echo "Seeded " . count($customerIds) . " customers.\n";

$employees = [
    ['Rosa', 'Lim', 'Wash Operator', 13000],
    ['Ben', 'Cruz', 'Ironer', 12500],
    ['Nina', 'Sison', 'Rider', 14000],
];
$empIds = [];
foreach ($employees as [$f, $l, $pos, $sal]) {
    $empIds[] = $c->insert('employees', [
        'first_name' => $f, 'last_name' => $l, 'position' => $pos, 'salary' => $sal,
        'hire_date' => date('Y-m-d', strtotime('-1 year +' . count($empIds) . ' months')),
    ]);
}
echo "Seeded " . count($empIds) . " employees.\n";

$services = $c->select('services', 'id,price,unit', ['is_active' => 1]);
if (!$services) {
    echo "No services in DB. Import schema.sql first.\n";
    exit(1);
}

// Create sample orders over the last 30 days
$statuses = ['pending', 'washing', 'drying', 'ready', 'completed', 'completed', 'completed'];
$count = 0;
for ($i = 1; $i <= 40; $i++) {
    $custId = $customerIds[array_rand($customerIds)];
    $empId = $empIds[array_rand($empIds)];
    $status = $statuses[array_rand($statuses)];
    $daysAgo = random_int(0, 29);

    // pick 1-3 services
    $picked = (array) array_rand(array_keys($services), random_int(1, min(3, count($services))));
    $subtotal = 0;
    $items = [];
    foreach ($picked as $key) {
        $svc = $services[$key];
        $qty = $svc['unit'] === 'kg' ? mt_rand(2, 15) : mt_rand(1, 4);
        $line = round($qty * (float) $svc['price'], 2);
        $subtotal += $line;
        $items[] = ['service_id' => (int) $svc['id'], 'quantity' => $qty, 'unit_price' => (float) $svc['price'], 'line_total' => $line];
    }

    $delivery = random_int(0, 1) ? (float) ($c->get('settings', 1)['delivery_fee'] ?? 0) : 0;
    $discount = random_int(0, 3) === 0 ? round($subtotal * 0.1, 2) : 0;
    $total = round($subtotal + $delivery - $discount, 2);
    $paid = $status === 'completed' ? $total : ($status === 'ready' && random_int(0, 1) ? $total : 0);
    $payStatus = abs($paid - $total) < 0.005 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

    $createdAt = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days " . random_int(8, 18) . " hours"));

    $orderId = db_transaction(function (PDO $pdo) use ($c, $items, $custId, $empId, $status, $subtotal, $delivery, $discount, $total, $paid, $payStatus) {
        $crud = new DbCrud($pdo);
        $id = $crud->insert('laundry_orders', [
            'order_no' => next_order_number(null, $pdo),
            'customer_id' => $custId,
            'assigned_employee_id' => $empId,
            'status' => $status,
            'payment_status' => $payStatus,
            'pickup_type' => random_int(0, 1) ? 'walk_in' : 'delivery',
            'pickup_date' => $status === 'pending' ? date('Y-m-d', strtotime('+1 day')) : null,
            'subtotal' => $subtotal,
            'delivery_fee' => $delivery,
            'discount' => $discount,
            'total' => $total,
            'amount_paid' => $paid,
        ]);
        foreach ($items as $it) {
            $crud->insert('order_items', $it + ['order_id' => $id]);
        }
        return $id;
    });

    $c->raw("UPDATE laundry_orders SET created_at = :c WHERE id = :id", ['c' => $createdAt, 'id' => $orderId]);
    $count++;
}
echo "Seeded {$count} sample orders.\n";
echo "Done. Login with owner@lavadora.local / password\n";
