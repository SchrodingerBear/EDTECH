<?php
/**
 * Lavadora — Laundry Management System
 * Shared helper / database file.
 * Loading this file gives you: db(), db_transaction(), DbCrud (crud()),
 * escaping + URL helpers, flash, redirect, money + status helpers.
 */

require_once __DIR__ . '/config.php';

/* -------------------------------- database --------------------------------- */

/** @var PDO|null */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        $options
    );

    return $pdo;
}

/** Run in a transaction (closure). @return mixed */
function db_transaction(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * DbCrud — safe, short CRUD over PDO. Use via crud().
 */
final class DbCrud
{
    public PDO $pdo;

    private const OPS = ['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'IN', 'NOT IN', 'IS', 'IS NOT'];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? db();
    }

    private static function ident(string $name): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new InvalidArgumentException("Invalid identifier: {$name}");
        }
        return "`{$name}`";
    }

    private static function paramPrefix(): string
    {
        static $n = 0;
        return 'p' . (++$n) . '_';
    }

    private static function buildWhere(array $conditions): array
    {
        $sql = [];
        $params = [];
        foreach ($conditions as $key => $cond) {
            $col = self::ident($key);

            if (is_array($cond)) {
                $op = strtoupper((string) ($cond[0] ?? '='));
                if (!in_array($op, self::OPS, true)) {
                    throw new InvalidArgumentException("Unsupported operator: {$op}");
                }
                if (($op === 'IN' || $op === 'NOT IN') && is_array($cond[1] ?? null)) {
                    $values = array_values($cond[1]);
                    if ($values === []) {
                        $sql[] = ($op === 'IN') ? '1=0' : '1=1';
                        continue;
                    }
                    $marks = [];
                    foreach ($values as $i => $v) {
                        $p = self::paramPrefix() . $key . $i;
                        $marks[] = ':' . $p;
                        $params[$p] = $v;
                    }
                    $sql[] = "{$col} {$op} (" . implode(',', $marks) . ')';
                    continue;
                }
                if ($op === 'IS' || $op === 'IS NOT') {
                    $sql[] = "{$col} {$op} NULL";
                    continue;
                }
                $p = self::paramPrefix() . $key;
                $sql[] = "{$col} {$op} :{$p}";
                $params[$p] = $cond[1];
                continue;
            }

            $p = self::paramPrefix() . $key;
            $sql[] = "{$col} = :{$p}";
            $params[$p] = $cond;
        }
        return [implode(' AND ', $sql), $params];
    }

    public function insert(string $table, array $data): int
    {
        $cols = [];
        $marks = [];
        $params = [];
        foreach ($data as $key => $value) {
            $p = self::paramPrefix() . $key;
            $cols[] = self::ident($key);
            $marks[] = ':' . $p;
            $params[$p] = $value;
        }
        if ($cols === []) {
            throw new InvalidArgumentException('No data to insert.');
        }
        $sql = 'INSERT INTO ' . self::ident($table)
             . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $marks) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $conditions): int
    {
        $set = [];
        $setParams = [];
        foreach ($data as $key => $value) {
            $p = self::paramPrefix() . $key;
            $set[] = self::ident($key) . ' = :' . $p;
            $setParams[$p] = $value;
        }
        if ($set === []) {
            throw new InvalidArgumentException('No data provided.');
        }
        [$where, $whereParams] = self::buildWhere($conditions);
        if ($where === '') {
            throw new InvalidArgumentException('Update requires at least one condition.');
        }
        $stmt = $this->pdo->prepare('UPDATE ' . self::ident($table) . ' SET ' . implode(', ', $set) . ' WHERE ' . $where);
        $stmt->execute($setParams + $whereParams);
        return $stmt->rowCount();
    }

    public function delete(string $table, array $conditions): int
    {
        [$where, $params] = self::buildWhere($conditions);
        if ($where === '') {
            throw new InvalidArgumentException('Delete requires at least one condition.');
        }
        $stmt = $this->pdo->prepare('DELETE FROM ' . self::ident($table) . ' WHERE ' . $where);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function select(string $table, string $columns = '*', array $conditions = [], string $options = ''): array
    {
        $columns = trim($columns);
        if ($columns === '*') {
            $sel = '*';
            $params = [];
        } else {
            $out = [];
            $params = [];
            foreach (array_map('trim', explode(',', $columns)) as $part) {
                if (preg_match('/^([a-zA-Z0-9_]+)(?:\s+AS\s+([a-zA-Z0-9_]+))?$/i', $part, $m)) {
                    $out[] = self::ident($m[1]) . (isset($m[2]) ? ' AS ' . self::ident($m[2]) : '');
                } else {
                    throw new InvalidArgumentException("Invalid columns: {$columns}");
                }
            }
            $sel = implode(', ', $out);
        }
        $sql = 'SELECT ' . $sel . ' FROM ' . self::ident($table);
        if ($conditions !== []) {
            [$where, $whereParams] = self::buildWhere($conditions);
            $sql .= ' WHERE ' . $where;
            $params += $whereParams;
        }
        if (trim($options) !== '') {
            if (!preg_match('/^(ORDER\s+BY\s+[a-zA-Z0-9_,\s.]+(?:ASC|DESC)?|LIMIT\s+\d+(\s*,\s*\d+)?)$/i', trim($options))) {
                throw new InvalidArgumentException("Unsupported SQL tail: {$options}");
            }
            $sql .= ' ' . trim($options);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get(string $table, int|array $idOrConditions): ?array
    {
        $conditions = is_numeric($idOrConditions) ? ['id' => (int) $idOrConditions] : $idOrConditions;
        $rows = $this->select($table, '*', $conditions, 'LIMIT 1');
        return $rows[0] ?? null;
    }

    public function count(string $table, array $conditions = []): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . self::ident($table);
        $params = [];
        if ($conditions !== []) {
            [$where, $params] = self::buildWhere($conditions);
            $sql .= ' WHERE ' . $where;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function sum(string $table, string $column, array $conditions = []): int|float
    {
        $sql = 'SELECT SUM(' . self::ident($column) . ') FROM ' . self::ident($table);
        $params = [];
        if ($conditions !== []) {
            [$where, $params] = self::buildWhere($conditions);
            $sql .= ' WHERE ' . $where;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        return ($total === null || $total === false) ? 0 : $total + 0;
    }

    public function raw(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}

/** Singleton accessor for the CRUD helper. */
function crud(): DbCrud
{
    static $instance = null;
    return $instance ??= new DbCrud();
}

/* --------------------------------- helpers ---------------------------------- */

/** Escape HTML. */
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Full URL for a project-relative path (leading slash). */
function url(string $path = '', bool $absolute = false): string
{
    $path = ltrim($path, '/');
    
    $parts = explode('?', $path, 2);
    $base = $parts[0];
    if ($base !== '' && !str_ends_with($base, '/') && !str_contains(basename($base), '.')) {
        if (is_file(ROOT_PATH . '/' . $base . '.php')) {
            $parts[0] = $base . '.php';
            $path = implode('?', $parts);
        }
    }

    if ($absolute) {
        return BASE_URL . '/' . $path;
    }

    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $callerFile = '';
    foreach ($trace as $t) {
        if (!empty($t['file']) && $t['file'] !== __FILE__) {
            $callerFile = $t['file'];
            break;
        }
    }

    if ($callerFile) {
        $root = rtrim(str_replace('\\', '/', ROOT_PATH), '/');
        $callerFile = str_replace('\\', '/', $callerFile);
        if (str_starts_with($callerFile, $root)) {
            $rel = ltrim(substr($callerFile, strlen($root)), '/');
            $depth = substr_count($rel, '/');
            if ($depth < 0) $depth = 0;
            
            $prefix = $depth > 0 ? str_repeat('../', $depth) : '';
            $result = $prefix . $path;
            return $result === '' ? './' : $result;
        }
    }

    return BASE_URL . '/' . $path;
}

/** Start a guarded session (safe to call multiple times). */
function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

/** One-shot flash message. */
function flash(string $type, string $message): void
{
    session_start_secure();
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** Pull and clear flash messages. */
function pull_flash(): array
{
    session_start_secure();
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

/** Redirect and stop. */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/** Canonical slug from a name. */
function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim($value, '-');
    return $value ?: (string) time();
}

/** Current user's display name. */
function display_name(array $user): string
{
    $name = trim($user['first_name'] ?? '');
    
    return $name !== '' ? $name : ($user['email'] ?? 'User');
}

/** Moderately sized random token. */
function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

/** Safe JSON encode for inline script output. */
function json_enc($value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS);
}

/** Append an audit-log row (never throws). */
function audit(string $action, string $module, ?string $entityType = null, ?int $entityId = null, ?PDO $pdo = null): void
{
    try {
        $noop = $pdo ?? db();
        $uid = function_exists('current_user') ? (int) (current_user()['id'] ?? 0) ?: null : null;
        $stmt = $noop->prepare(
            "INSERT INTO audit_logs (actor_user_id, action, module, entity_type, entity_id, ip_address, user_agent)
             VALUES (:uid, :action, :module, :et, :eid, :ip, :ua)"
        );
        $stmt->execute([
            'uid' => $uid, 'action' => $action, 'module' => $module,
            'et' => $entityType, 'eid' => $entityId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (Throwable $e) {
        // audit must never break the primary operation
    }
}

/* ---------------------------- laundry-specific ------------------------------ */

/** Format a number as PHP peso. */
function peso($amount): string
{
    $amount = (float) ($amount ?? 0);
    if (fmod($amount, 1) == 0) {
        return '₱' . number_format($amount, 0);
    }
    return '₱' . number_format($amount, 2);
}

/** Allowed order workflow statuses (array of slug => label). */
function order_statuses(): array
{
    return [
        'pending'      => 'Pending',
        'washing'      => 'Washing',
        'drying'       => 'Drying',
        'ready'        => 'Ready for Pickup',
        'completed'    => 'Claimed',
        'cancelled'    => 'Cancelled',
    ];
}

/** Badge CSS class for a status slug. */
function status_badge(string $status): string
{
    $map = [
        'pending'     => 'badge-warn',
        'washing'     => 'badge-info',
        'drying'      => 'badge-info',
        'ready'       => 'badge-live',
        'completed'   => 'badge-success',
        'cancelled'   => 'badge-off',
    ];
    return $map[$status] ?? 'badge-surface';
}

/** Payment statuses. */
function payment_statuses(): array
{
    return [
        'unpaid'   => 'Unpaid',
        'partial'  => 'Partial',
        'paid'     => 'Paid',
    ];
}

/** Generate a human-friendly order number: LAV-YYYY-XXXX. */
function next_order_number(?int $lastId = null, ?PDO $pdo = null): string
{
    $raw = $pdo ?? db();
    $id = $lastId;
    if ($id === null) {
        try {
            $stmt = $raw->query('SELECT COALESCE(MAX(id),0)+1 FROM laundry_orders');
            $id = (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            $id = 1;
        }
    }
    return 'LAV-' . date('Y') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

/* ------------------------------- inventory -------------------------------- */

/**
 * Deduct smart-inventory stock when an order is claimed (completed).
 * Uses configured usage_per_kg × quantity (kg) for each order line item.
 * Dispatches inside the caller's transaction. Never throws.
 */
function deduct_inventory_for_order(DbCrud $crud, array $order): void
{
    try {
        $items = $crud->select('order_items', '*', ['order_id' => (int) $order['id']]);
        foreach ($items as $it) {
            $svcId = (int) $it['service_id'];
            $qty = (float) $it['quantity']; // weight in kg for kg-based services
            $usages = $crud->select('inventory_usage', '*', ['service_id' => $svcId]);
            foreach ($usages as $u) {
                $itemId = (int) $u['inventory_item_id'];
                $rate = (float) $u['usage_per_kg'];
                if ($rate <= 0) continue;
                $consume = round($qty * $rate, 2);
                if ($consume <= 0) continue;
                $inv = $crud->get('inventory_items', $itemId);
                if (!$inv) continue;
                $newStock = round((float) $inv['current_stock'] - $consume, 2);
                if ($newStock < 0) $newStock = 0;
                $crud->update('inventory_items', ['current_stock' => $newStock], ['id' => $itemId]);
                $crud->insert('inventory_movements', [
                    'inventory_item_id' => $itemId,
                    'type' => 'out',
                    'quantity' => $consume,
                    'reference' => $order['order_no'] ?? null,
                    'notes' => 'Auto-deducted on claim',
                    'created_by' => current_user()['id'] ?? null,
                ]);
            }
        }
    } catch (Throwable $e) {
        // inventory tracking must never break the status change
    }
}

/* ------------------------------- receipt -------------------------------- */

/** Generate a unique receipt token for an order. */
function generate_receipt_token(int $orderId, string $orderNo, string $createdAt): string
{
    return substr(md5($orderId . $orderNo . $createdAt), 0, 16) . $orderId;
}

/** Get the public receipt URL for an order. */
function get_receipt_url(string $token): string
{
    return url('receipt.php?token=' . $token, true);
}

/** Generate or get existing receipt token for an order. */
function get_or_create_receipt_token(DbCrud $crud, int $orderId): string
{
    $order = $crud->get('laundry_orders', $orderId);
    if (!$order) {
        throw new RuntimeException('Order not found');
    }
    
    // Check if receipt_token column exists
    try {
        $columns = db()->query("SHOW COLUMNS FROM laundry_orders")->fetchAll();
        $columnNames = array_column($columns, 'Field');
        if (!in_array('receipt_token', $columnNames)) {
            throw new RuntimeException('Receipt system not set up');
        }
    } catch (Throwable $e) {
        throw new RuntimeException('Receipt system not set up');
    }
    
    if (!empty($order['receipt_token'])) {
        return $order['receipt_token'];
    }
    
    $token = generate_receipt_token($orderId, $order['order_no'], $order['created_at']);
    $crud->update('laundry_orders', ['receipt_token' => $token], ['id' => $orderId]);
    
    return $token;
}
