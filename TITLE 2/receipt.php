<?php
/**
 * Public Receipt View Page
 * Customers can view their laundry order receipt via a shortened link
 * URL format: receipt.php?token=XYZ
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('Invalid receipt link. Please contact the laundry service.');
}

$c = crud();

// Check if receipt system is set up
try {
    $columns = db()->query("SHOW COLUMNS FROM laundry_orders")->fetchAll();
    $columnNames = array_column($columns, 'Field');
    if (!in_array('receipt_token', $columnNames)) {
        die('Receipt system not yet set up. Please contact the laundry service.');
    }
} catch (Throwable $e) {
    die('Receipt system not yet set up. Please contact the laundry service.');
}

// Find order by receipt token
$order = $c->raw(
    "SELECT lo.*, cu.first_name, cu.phone, cu.email, cu.address
     FROM laundry_orders lo
     JOIN customers cu ON cu.id = lo.customer_id
     WHERE lo.receipt_token = ?",
    [$token]
)->fetch();

if (!$order) {
    die('Receipt not found. Please contact the laundry service.');
}

// Update receipt view tracking (only if columns exist)
try {
    if ($order['receipt_viewed_at'] === null) {
        $c->update('laundry_orders', [
            'receipt_viewed_at' => date('Y-m-d H:i:s'),
            'receipt_view_count' => 1
        ], ['id' => $order['id']]);
    } else {
        $c->raw("UPDATE laundry_orders SET receipt_view_count = receipt_view_count + 1 WHERE id = ?", [$order['id']]);
    }
} catch (Throwable $e) {
    // View tracking is optional, so continue if it fails
}

// Get order items with service details
$items = $c->raw(
    "SELECT oi.*, s.name as service_name, s.unit 
     FROM order_items oi 
     JOIN services s ON s.id = oi.service_id 
     WHERE oi.order_id = ?",
    [$order['id']]
)->fetchAll();

// Get business settings
$settings = $c->get('settings', 1) ?? [];
$businessName = $settings['business_name'] ?? APP_NAME;
$businessPhone = $settings['phone'] ?? '';
$businessAddress = $settings['address'] ?? '';
$businessEmail = $settings['email'] ?? '';

// Calculate progress based on status
$statusProgress = [
    'pending' => 10,
    'washing' => 30,
    'drying' => 50,
    'ready' => 80,
    'completed' => 100,
    'cancelled' => 0
];

$currentProgress = $statusProgress[$order['status']] ?? 0;

// Calculate estimated completion date
$pickupDate = new DateTime($order['pickup_date']);
$estimatedReady = clone $pickupDate;
$estimatedReady->modify('+1 day'); // Default 1-day turnaround

// Order status labels with descriptions
$statusLabels = [
    'pending' => 'Order Received',
    'washing' => 'In Progress - Washing',
    'drying' => 'In Progress - Drying',
    'ready' => 'Ready for Pickup',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled'
];

$statusDescriptions = [
    'pending' => 'Your order has been received and is being processed.',
    'washing' => 'Your clothes are currently being washed.',
    'drying' => 'Your clothes are currently being dried.',
    'ready' => 'Your laundry is ready for pickup!',
    'completed' => 'Your order has been completed and claimed.',
    'cancelled' => 'This order has been cancelled.'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Receipt #<?= h($order['order_no']) ?> - <?= h($businessName) ?></title>
    <link rel="manifest" href="manifest.json">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .receipt-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .receipt-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .receipt-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .receipt-header p {
            opacity: 0.9;
            margin: 0;
            font-size: 0.9rem;
        }
        .receipt-body {
            padding: 30px;
        }
        .order-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            text-align: center;
            margin-bottom: 20px;
        }
        .progress-section {
            margin: 30px 0;
        }
        .progress-bar-custom {
            height: 12px;
            border-radius: 6px;
            background: #e9ecef;
        }
        .progress-bar-fill {
            height: 100%;
            border-radius: 6px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.5s ease;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-washing { background: #d1ecf1; color: #0c5460; }
        .status-drying { background: #d1ecf1; color: #0c5460; }
        .status-ready { background: #d4edda; color: #155724; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .info-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .info-card h5 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        .info-label {
            color: #6c757d;
        }
        .info-value {
            font-weight: 500;
            color: #212529;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table th {
            text-align: left;
            padding: 12px 8px;
            border-bottom: 2px solid #dee2e6;
            color: #495057;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .items-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #dee2e6;
            font-size: 0.9rem;
        }
        .total-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        .total-row.final {
            font-size: 1.2rem;
            font-weight: 700;
            color: #667eea;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #dee2e6;
        }
        .ready-date {
            text-align: center;
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 12px;
            margin: 20px 0;
            font-weight: 600;
        }
        .contact-section {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 0 0 20px 20px;
        }
        .contact-section p {
            margin: 5px 0;
            color: #6c757d;
            font-size: 0.9rem;
        }
        .copy-link {
            background: #e9ecef;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            margin-top: 10px;
        }
        .copy-link:hover {
            background: #dee2e6;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <h1><?= h($businessName) ?></h1>
            <p>Laundry Order Receipt</p>
        </div>
        
        <div class="receipt-body">
            <div class="order-number">
                #<?= h($order['order_no']) ?>
            </div>
            
            <div class="text-center mb-4">
                <span class="status-badge status-<?= h($order['status']) ?>">
                    <?= h($statusLabels[$order['status']] ?? $order['status']) ?>
                </span>
            </div>
            
            <div class="progress-section">
                <div class="progress-bar-custom">
                    <div class="progress-bar-fill" style="width: <?= $currentProgress ?>%"></div>
                </div>
                <p class="text-center mt-2 text-muted small">
                    <?= h($statusDescriptions[$order['status']] ?? '') ?>
                </p>
            </div>
            
            <?php if ($order['status'] === 'ready'): ?>
            <div class="ready-date">
                🎉 Ready for Pickup on <?= date('F j, Y', strtotime($order['pickup_date'])) ?>
            </div>
            <?php else: ?>
            <div class="ready-date" style="background: #d1ecf1; color: #0c5460;">
                📅 Estimated Ready: <?= $estimatedReady->format('F j, Y') ?>
            </div>
            <?php endif; ?>
            
            <div class="info-card">
                <h5>Customer Information</h5>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?= h($hasLastName ? ($order['first_name'] . ' ' . $order['last_name']) : $order['first_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?= h($order['phone']) ?></span>
                </div>
                <?php if ($order['email']): ?>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?= h($order['email']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($order['address']): ?>
                <div class="info-row">
                    <span class="info-label">Address:</span>
                    <span class="info-value"><?= h($order['address']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="info-card">
                <h5>Order Details</h5>
                <div class="info-row">
                    <span class="info-label">Order Date:</span>
                    <span class="info-value"><?= date('F j, Y g:i A', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Pickup Type:</span>
                    <span class="info-value"><?= ucfirst(str_replace('_', ' ', $order['pickup_type'])) ?></span>
                </div>
                <?php if ($order['delivery_address']): ?>
                <div class="info-row">
                    <span class="info-label">Delivery Address:</span>
                    <span class="info-value"><?= h($order['delivery_address']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="info-card">
                <h5>Laundry Items</h5>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= h($item['service_name']) ?></td>
                            <td><?= number_format($item['quantity'], 2) ?> <?= h($item['unit']) ?></td>
                            <td><?= peso($item['unit_price']) ?></td>
                            <td><?= peso($item['line_total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="total-section">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span><?= peso($order['subtotal']) ?></span>
                </div>
                <?php if ($order['delivery_fee'] > 0): ?>
                <div class="total-row">
                    <span>Delivery Fee:</span>
                    <span><?= peso($order['delivery_fee']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($order['discount'] > 0): ?>
                <div class="total-row">
                    <span>Discount:</span>
                    <span>-<?= peso($order['discount']) ?></span>
                </div>
                <?php endif; ?>
                <div class="total-row final">
                    <span>Total:</span>
                    <span><?= peso($order['total']) ?></span>
                </div>
                <div class="total-row">
                    <span>Amount Paid:</span>
                    <span><?= peso($order['amount_paid']) ?></span>
                </div>
                <div class="total-row">
                    <span>Payment Status:</span>
                    <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : ($order['payment_status'] === 'partial' ? 'warning' : 'secondary') ?>">
                        <?= ucfirst($order['payment_status']) ?>
                    </span>
                </div>
            </div>
            
            <?php if ($order['notes']): ?>
            <div class="info-card">
                <h5>Notes</h5>
                <p class="mb-0"><?= h($order['notes']) ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="contact-section">
            <h5><?= h($businessName) ?></h5>
            <?php if ($businessPhone): ?>
            <p>📞 <?= h($businessPhone) ?></p>
            <?php endif; ?>
            <?php if ($businessEmail): ?>
            <p>✉️ <?= h($businessEmail) ?></p>
            <?php endif; ?>
            <?php if ($businessAddress): ?>
            <p>📍 <?= h($businessAddress) ?></p>
            <?php endif; ?>
            <button class="copy-link" onclick="copyReceiptLink()">📋 Copy Receipt Link</button>
        </div>
    </div>
    
    <!-- Offline Modal -->
    <div class="modal fade" id="offlineModal" tabindex="-1" aria-labelledby="offlineModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-warning text-dark">
            <h5 class="modal-title" id="offlineModalLabel">You are Offline</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>It looks like you don't have an active internet connection. Online sharing is currently disabled, but you can still view this receipt and take a screenshot.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyReceiptLink() {
            if (!navigator.onLine) {
                const offlineModal = new bootstrap.Modal(document.getElementById('offlineModal'));
                offlineModal.show();
                return;
            }
            
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                alert('Receipt link copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }

        // Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('./sw.js')
                    .then(registration => {
                        console.log('ServiceWorker registration successful with scope: ', registration.scope);
                    }, err => {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }

        // Offline Detection
        window.addEventListener('offline', () => {
            const offlineModal = new bootstrap.Modal(document.getElementById('offlineModal'));
            offlineModal.show();
            document.querySelector('.copy-link').disabled = true;
        });

        window.addEventListener('online', () => {
            document.querySelector('.copy-link').disabled = false;
        });
        
        // Initial check
        if (!navigator.onLine) {
            document.querySelector('.copy-link').disabled = true;
            // Optionally show modal immediately on load if offline
            setTimeout(() => {
                const offlineModal = new bootstrap.Modal(document.getElementById('offlineModal'));
                offlineModal.show();
            }, 1000);
        }
    </script>
</body>
</html>
