<?php
require_once __DIR__ . '/includes/bootstrap.php';

$booked = false;
$bookingError = null;
$bookingRef = null;

/* ------------------------------ BOOKING POST ------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'booking') {
    try {
        $items = $_POST['items'] ?? [];
        $serviceIds = $_POST['service_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];

        $orderItems = [];
        $subtotal = 0;
        foreach ($items as $i => $svcId) {
            $svcId = (int) $svcId;
            $svc = crud()->get('services', $svcId);
            if (!$svc) continue;
            $qty = (float) ($quantities[$i] ?? 1);
            if ($qty <= 0) continue;
            $line = round($qty * (float) $svc['price'], 2);
            $subtotal += $line;
            $orderItems[] = ['service_id' => $svcId, 'quantity' => $qty, 'unit_price' => (float) $svc['price'], 'line_total' => $line];
        }
        if (!$orderItems) {
            throw new RuntimeException('Please add at least one service to your booking request.');
        }

        $fname = trim($_POST['first_name'] ?? '');
        $lname = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '') ?: null;
        $notes = trim($_POST['notes'] ?? '') ?: null;
        $pickupDate = !empty($_POST['pickup_date']) ? $_POST['pickup_date'] : null;

        if ($fname === '' || $lname === '' || $phone === '') {
            throw new RuntimeException('Please provide your name and contact number.');
        }

        // Find or create customer
        $customer = null;
        $custRows = crud()->select('customers', '*', ['phone' => $phone], 'LIMIT 1');
        if ($custRows) {
            $customer = $custRows[0];
            crud()->update('customers', [
                'first_name' => $customer['first_name'] ?: $fname,
                'last_name' => $customer['last_name'] ?: $lname,
                'address' => $address ?: $customer['address'],
            ], ['id' => $customer['id']]);
        } else {
            $customerId = crud()->insert('customers', [
                'first_name' => $fname, 'last_name' => $lname, 'phone' => $phone,
                'address' => $address, 'notes' => $notes,
            ]);
            $customer = crud()->get('customers', $customerId);
        }

        $deliveryFee = $landing['delivery_fee'] ?? 0;
        $total = round($subtotal + (float) $deliveryFee, 2);

        $orderId = db_transaction(function (PDO $pdo) use ($customer, $orderItems, $subtotal, $deliveryFee, $total, $address, $notes, $pickupDate, $phone) {
            $crud = new DbCrud($pdo);
            $orderNo = next_order_number(null, $pdo);
            $id = $crud->insert('laundry_orders', [
                'order_no' => $orderNo,
                'customer_id' => (int) $customer['id'],
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'pickup_type' => 'delivery',
                'delivery_address' => $address,
                'pickup_date' => $pickupDate,
                'notes' => $notes,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'discount' => 0,
                'total' => $total,
                'amount_paid' => 0,
            ]);
            foreach ($orderItems as $it) {
                $crud->insert('order_items', $it + ['order_id' => $id]);
            }
            return $id;
        });

        audit('order.booking', 'orders', 'order', $orderId);
        $bookingRef = next_order_number($orderId);
        $booked = true;
    } catch (Throwable $e) {
        $bookingError = $e->getMessage();
    }
}

$hero = $landing['hero_image_path'] ? $landing['hero_image_path'] : 'assets/laundry-hero.svg';
$logo = $landing['logo_path'] ? $landing['logo_path'] : 'admin/assets/img/logo.svg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light dark">
  <title><?= h($landing['business_name']) ?> | Fresh, clean laundry at your doorstep</title>
  <meta name="description" content="Professional laundry services — wash, dry, fold, iron and dry cleaning with free pickup and delivery.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
  <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body>
<main id="top" class="page">

  <?php if (!empty($landing['announcement'])): ?>
    <div class="announcement"><?= h($landing['announcement']) ?></div>
  <?php endif; ?>

  <header class="site-header">
    <div class="wrap header-inner">
      <a class="logo" href="#top" aria-label="<?= h($landing['business_name']) ?> home">
        <img class="logo-mark" src="<?= url($logo) ?>" alt="">
        <span><?= h($landing['business_name']) ?> <span class="accent">.</span></span>
      </a>
      <nav class="nav-desktop" aria-label="Primary">
        <a href="#services">Services</a>
        <a href="#how">How it works</a>
        <a href="#pricing">Pricing</a>
        <a href="#about">About</a>
        <a href="#book">Book now</a>
      </nav>
      <div class="header-actions">
        <a class="btn btn-primary" href="<?= h($landing['login_url']) ?>">Staff Login</a>
      </div>
      <button type="button" class="menu-toggle" id="menu-toggle" aria-label="Toggle menu" aria-expanded="false" aria-controls="mobile-nav">☰</button>
    </div>
    <nav class="nav-mobile wrap" id="mobile-nav" aria-label="Mobile">
      <a href="#services" data-close-menu>Services</a>
      <a href="#how" data-close-menu>How it works</a>
      <a href="#pricing" data-close-menu>Pricing</a>
      <a href="#about" data-close-menu>About</a>
      <a href="#book" data-close-menu>Book now</a>
      <div class="nav-mobile-actions">
        <a class="btn btn-primary" href="<?= h($landing['login_url']) ?>" data-close-menu>Staff Login</a>
      </div>
    </nav>
  </header>

  <section class="wrap hero">
    <div class="hero-blob" aria-hidden="true"></div>
    <div class="hero-copy">
      <div class="pill">✓ <?= h($landing['tagline']) ?></div>
      <h1><?= h($landing['hero_headline']) ?> <span class="accent">at your door</span></h1>
      <p class="lede"><?= h($landing['hero_sub']) ?></p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="#book">Schedule a pickup <?= html_entity_decode('&rarr;') ?></a>
        <a class="btn btn-ghost" href="#services">View services</a>
      </div>
    </div>
    <div class="hero-card">
      <img src="<?= url($hero) ?>" alt="<?= h($landing['business_name']) ?> laundry service">
      <div class="hero-caption">
        <div>
          <p class="kicker">Same-day service</p>
          <p><strong>Fresh and folded, on time.</strong></p>
        </div>
        <div class="play">✓</div>
      </div>
    </div>
  </section>

  <section class="stats">
    <div class="wrap stats-grid">
      <div><p class="stat-value">1-Day</p><p class="stat-label">Turnaround</p></div>
      <div><p class="stat-value">100%</p><p class="stat-label">Free pickup & delivery</p></div>
      <div><p class="stat-value">24/7</p><p class="stat-label">Online booking</p></div>
      <div><p class="stat-value">₱60</p><p class="stat-label">Wash & fold per kg</p></div>
    </div>
  </section>

  <section id="services" class="wrap section">
    <div class="section-intro">
      <p class="eyebrow">Our menu</p>
      <h2 class="section-title">Everything your laundry needs.</h2>
      <p class="lede">From everyday wash & fold to delicate dry cleaning, we've got you covered.</p>
    </div>
    <div class="service-grid">
      <?php foreach ($services as $sv): ?>
        <article class="service-card">
          <div class="service-icon"><?= h($sv['name'][0] ?? 'L') ?></div>
          <h3><?= h($sv['name']) ?></h3>
          <p><?= h($sv['description'] ?? '') ?></p>
          <div class="service-price"><?= peso($sv['price']) ?> <span>/ <?= h($sv['unit']) ?></span></div>
        </article>
      <?php endforeach; ?>
      <?php if (!$services): ?>
        <p class="lede">Service pricing coming soon.</p>
      <?php endif; ?>
    </div>
  </section>

  <section id="how" class="how section">
    <div class="wrap how-grid">
      <div>
        <p class="eyebrow">Simple &amp; effortless</p>
        <h2 class="section-title">How it works.</h2>
        <p class="lede">Getting your laundry done has never been easier.</p>
      </div>
      <div class="steps">
        <div class="step"><span class="step-num">1</span><div><h3>Book online</h3><p>Choose your services and schedule a pickup.</p></div></div>
        <div class="step"><span class="step-num">2</span><div><h3>We pick up</h3><p>Our rider collects your clothes at your door.</p></div></div>
        <div class="step"><span class="step-num">3</span><div><h3>We wash &amp; fold</h3><p>Professional cleaning and care for every load.</p></div></div>
        <div class="step"><span class="step-num">4</span><div><h3>Delivered fresh</h3><p>Fresh, folded laundry back to you within 24 hours.</p></div></div>
      </div>
    </div>
  </section>

  <section id="pricing" class="wrap section">
    <div class="section-intro">
      <p class="eyebrow">Transparent pricing</p>
      <h2 class="section-title">No hidden fees.</h2>
      <p class="lede">Pay only for what you need. Free pickup &amp; delivery on delivery orders.</p>
    </div>
    <div class="price-table-wrap">
      <table class="price-table">
        <thead><tr><th>Service</th><th>Unit</th><th class="text-end">Price</th></tr></thead>
        <tbody>
          <?php foreach ($services as $sv): ?>
            <tr><td><?= h($sv['name']) ?></td><td>per <?= h($sv['unit']) ?></td><td class="text-end fw-bold"><?= peso($sv['price']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section id="about" class="wrap section">
    <div class="about-grid">
      <div>
        <p class="eyebrow">Why <?= h($landing['business_name']) ?>?</p>
        <h2 class="section-title">A better way to do laundry.</h2>
        <div class="why-grid">
          <div><h3>Fast</h3><p>1-day turnaround on most services.</p></div>
          <div><h3>Careful</h3><p>Gentle on fabrics, strong on clean.</p></div>
          <div><h3>Convenient</h3><p>Free pickup &amp; delivery to your door.</p></div>
          <div><h3>Fair</h3><p>Simple, transparent per-kg pricing.</p></div>
        </div>
      </div>
      <div class="contact-card">
        <h3>Contact us</h3>
        <p><strong>Phone:</strong> <?= h($landing['phone']) ?></p>
        <p><strong>Email:</strong> <?= h($landing['contact_email']) ?></p>
        <p><strong>Address:</strong> <?= h($landing['address']) ?></p>
        <p class="muted small">Available 7 days a week.</p>
      </div>
    </div>
  </section>

  <section id="book" class="wrap section">
    <div class="cta-box">
      <div class="cta-inner">
        <div>
          <p class="eyebrow">Ready for fresh clothes?</p>
          <h2>Book your pickup today.</h2>
          <p class="lede">Fill in your details and the services you need — we'll handle the rest.</p>
        </div>
      </div>
      <div class="book-form">
        <?php if ($booked): ?>
          <div class="book-success">
            <div class="success-icon">✓</div>
            <h3>Booking received!</h3>
            <p>Your booking reference is <strong><?= h($bookingRef) ?></strong>. Our team will contact you shortly to confirm your pickup.</p>
            <a class="btn btn-primary" href="#top">Book another</a>
          </div>
        <?php else: ?>
          <?php if ($bookingError): ?>
            <div class="form-error"><?= h($bookingError) ?></div>
          <?php endif; ?>
          <form method="post" id="booking-form">
            <input type="hidden" name="form" value="booking">
            <div class="form-row">
              <div class="field"><label>First name *</label><input name="first_name" required></div>
              <div class="field"><label>Last name *</label><input name="last_name" required></div>
            </div>
            <div class="form-row">
              <div class="field"><label>Contact number *</label><input name="phone" required placeholder="0917 123 4567"></div>
              <div class="field"><label>Pickup date</label><input type="date" name="pickup_date"></div>
            </div>
            <div class="field"><label>Delivery address *</label><input name="address" required placeholder="House/unit no., street, barangay, city"></div>
            <div class="field"><label>Notes (optional)</label><textarea name="notes" rows="2"></textarea></div>

            <div class="field">
              <label>Services requested *</label>
              <div id="book-items">
                <div class="book-item-row">
                  <select class="book-service" name="service_id[]">
                    <option value="">Select a service</option>
                    <?php foreach ($services as $sv): ?>
                      <option value="<?= (int) $sv['id'] ?>" data-price="<?= h($sv['price']) ?>"><?= h($sv['name'] . ' — ' . $sv['unit']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <input class="book-qty" type="number" name="quantity[]" min="0.01" step="0.01" value="1" placeholder="Qty">
                  <button type="button" class="book-remove" title="Remove">×</button>
                </div>
              </div>
              <button type="button" id="book-add" class="btn btn-ghost-sm">+ Add another service</button>
            </div>

            <div class="book-total">
              <span>Estimated total</span>
              <strong id="book-total">₱0</strong>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Submit booking request</button>
            <p class="muted small center">This sends a booking request — our team confirms availability before pickup.</p>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <footer class="site-footer">
    <div class="wrap footer-inner">
      <a class="logo" href="#top">
        <img class="logo-mark" src="<?= url($logo) ?>" alt="">
        <span><?= h($landing['business_name']) ?></span>
      </a>
      <div class="footer-links">
        <a href="#services">Services</a>
        <a href="#book">Book</a>
        <a href="mailto:<?= h($landing['contact_email']) ?>"><?= h($landing['contact_email']) ?></a>
      </div>
      <p class="copy">&copy; <?= date('Y') ?> <?= h($landing['business_name']) ?></p>
    </div>
  </footer>
</main>

<script>
  window.BOOK_SERVICES = <?= json_enc(array_map(fn($s) => ['id' => (int)$s['id'], 'name' => $s['name'], 'price' => (float)$s['price'], 'unit' => $s['unit']], $services)) ?>;
</script>
<script src="assets/js/landing.js"></script>
</body>
</html>
