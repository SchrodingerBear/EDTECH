<?php
/**
 * Innovatech PH — the single shared helper / database file.
 * Loading this file gives you: db(), db_transaction(), the DbCrud helper
 * (crud()), escaping + URL helpers, emails, audit, and image utilities.
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
 *
 *   $id  = crud()->insert('institutions', ['name' => 'X', 'city' => 'Manila']);
 *   crud()->update('institutions', ['city' => 'Pasig'], ['id' => 3]);
 *   crud()->delete('users', ['id' => 9]);
 *   $rows = crud()->select('institutions', '*', ['is_active' => 1], 'ORDER BY name');
 *   $one  = crud()->get('institutions', 3);
 *   $n    = crud()->count('users', ['role_id' => 2]);
 *   crud()->raw('UPDATE … 1 - field')->execute();  // joins / raw SQL escape hatch
 *
 * Safety: identifiers must match /^[a-zA-Z0-9_]+$/ (then backticked), values are
 * always bound, and WHERE operators are allowlisted. Use crud()->raw() for SQL
 * (joins, increments) that the fluent API can't express.
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

    /** Build WHERE from ['col' => value | [op, value] | ['IN', [...]] | ['IS', null]]. */
    private static function buildWhere(array $conditions): array
    {
        $sql = [];
        $params = [];
        foreach ($conditions as $key => $cond) {
            $col = self::ident($key);

            if (is_array($cond)) {
                $op = strtoupper((string) $cond[0] ?? '=');
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

    /** @return int last inserted id */
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

    /** @return int affected rows */
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

    /** @return int affected rows */
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

    /** @return array<int,array<string,mixed>> */
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
            if (!preg_match('/^(ORDER\s+BY\s+[a-zA-Z0-9_,\s.]+(ASC|DESC)?|LIMIT\s+\d+(\s*,\s*\d+)?)$/i', trim($options))) {
                throw new InvalidArgumentException("Unsupported SQL tail: {$options}");
            }
            $sql .= ' ' . trim($options);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Convenience: one row by id (int) or conditions, or null. */
    public function get(string $table, int|array $idOrConditions): ?array
    {
        $conditions = is_numeric($idOrConditions) ? ['id' => (int) $idOrConditions] : $idOrConditions;
        $rows = $this->select($table, '*', $conditions, 'LIMIT 1');
        return $rows[0] ?? null;
    }

    /** @return int */
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

    /** @return int|float */
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

    /** Raw SQL with bound params (joins, increments). Returned PDOStatement is ready to ->fetch(). */
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

/** Escape HTML. */
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Full URL for a project-relative path (leading slash). */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return BASE_URL . '/' . $path;
}

/** Inline SVG icon (public-site set). */
function icon(string $name, int $size = 19): string
{
    $s = (int) $size;
    $strokeWidth = $name === 'menu' ? 2.5 : 2; // Thicker stroke for menu icon
    $common = 'xmlns="http://www.w3.org/2000/svg" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $strokeWidth . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    $paths = [
        'move3d' => '<path d="M5 3v16h16"/><path d="m5 19 6-6"/><path d="m2 6 3-3 3 3"/><path d="m18 16 3 3-3 3"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'arrow-up-right' => '<path d="M7 7h10v10"/><path d="M7 17 17 7"/>',
        'sparkles' => '<path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/>',
        'navigation' => '<polygon points="3 11 22 2 13 21 11 13 3 11"/>',
        'compass' => '<circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>',
        'chart' => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
        'layers' => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
        'moon' => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'menu' => '<path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'play' => '<polygon points="6 3 20 12 6 21 6 3"/>',
        'message' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
    ];
    return '<svg ' . $common . '>' . ($paths[$name] ?? '') . '</svg>';
}

/** URL to a file under organizations/{slug}/… */
function org_url(string $slug, string $sub = ''): string
{
    $cleanSlug = trim($slug, '/\\');
    $cleanSub = trim($sub, '/\\');
    $path = 'organizations/' . $cleanSlug;
    if ($cleanSub !== '') {
        $path .= '/' . $cleanSub;
    }
    return url($path);
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

/** Canonical slug from a name: "Immaculada Concepcion College" → immaculada-concepcion-college */
function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim($value, '-');
    return $value ?: (string) time();
}

/** Make a folder name unique: append -2, -3, … while folder exists. */
function unique_folder(string $base, string $root): string
{
    $candidate = $base;
    $i = 2;
    while (is_dir($root . '/' . $candidate)) {
        $candidate = $base . '-' . $i;
        $i++;
    }
    return $candidate;
}

/** Recursively delete a directory tree. */
function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

/** Recursively copy a directory tree. */
function rcopy(string $src, string $dst): bool
{
    if (!is_dir($src)) {
        return false;
    }
    if (!is_dir($dst) && !mkdir($dst, 0775, true) && !is_dir($dst)) {
        return false;
    }
    foreach (scandir($src) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $from = $src . '/' . $entry;
        $to = $dst . '/' . $entry;
        if (is_dir($from)) {
            if (!rcopy($from, $to)) {
                return false;
            }
        } elseif (!copy($from, $to)) {
            return false;
        }
    }
    return true;
}

/** Human-readable bytes. */
function human_bytes(int $bytes, int $decimals = 1): string
{
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int) floor(log($bytes, 1024));
    return round($bytes / (1024 ** $i), $decimals) . ' ' . $units[$i];
}

/** Current user's display name. */
function display_name(array $user): string
{
    $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    return $name !== '' ? $name : ($user['email'] ?? 'User');
}

/** Moderately sized random token. */
function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

/** Append an audit-log row (never throws). */
function audit(string $action, string $module, ?string $entityType = null, ?int $entityId = null, ?PDO $pdo = null, ?int $institutionId = null): void
{
    try {
        $noop = $pdo ?? db();
        $uid = function_exists('current_user') ? (int) (current_user()['id'] ?? 0) ?: null : null;
        $iid = $institutionId ?? (function_exists('current_institution') ? (int) (current_institution()['id'] ?? 0) ?: null : null);
        $stmt = $noop->prepare(
            "INSERT INTO audit_logs (actor_user_id, institution_id, action, module, entity_type, entity_id, ip_address, user_agent)
             VALUES (:uid, :iid, :action, :module, :et, :eid, :ip, :ua)"
        );
        $stmt->execute([
            'uid' => $uid, 'iid' => $iid, 'action' => $action, 'module' => $module,
            'et' => $entityType, 'eid' => $entityId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (Throwable $e) {
        // audit must never break the primary operation
    }
}

/** Safe JSON encode for inline script output. */
function json_enc($value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS);
}

/* -------------------------------- email ------------------------------------ */

/** Append a line to the email debug log (storage/logs/email.log). Never throws. */
function email_log(string $message): void
{
    try {
        $dir = ROOT_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @chmod($dir, 0777);
        $file = $dir . '/email.log';
        // The file may pre-exist owned by another user; allow appends by any user (web server runs as www-data).
        @chmod($file, 0666);
        $line = '[' . date('Y-m-d H:i:s') . '] ' . trim($message) . PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND);
    } catch (Throwable $e) {
        // logging must never break sending
    }
}

/** Last $n lines of storage/logs/email.log as an array (newest last). */
function email_log_tail(int $n = 40): array
{
    $file = ROOT_PATH . '/storage/logs/email.log';
    if (!is_file($file)) {
        return [];
    }
    $all = file($file, FILE_IGNORE_NEW_LINES);
    return is_array($all) ? array_slice($all, -$n) : [];
}

/**
 * Deliver an email through the configured SMTP server, or spool it to
 * storage/mail/ when no SMTP is configured (dev fallback, mirrors mail()).
 * Every attempt (decision + SMTP conversation) is written to
 * storage/logs/email.log so failures are repeatable and debuggable.
 */
function send_email(string $to, string $subject, string $bodyHtml): bool
{
    $s = null;
    try {
        $s = db()->query("SELECT * FROM platform_settings WHERE id=1")->fetch() ?: [];
    } catch (Throwable $e) {
        $s = [];
        email_log('send_email: platform_settings unreadable: ' . $e->getMessage());
    }

    $fromName = trim((string) ($s['smtp_from_name'] ?? ''));
    $fromEmail = trim((string) ($s['smtp_from_email'] ?? ''));
    if ($fromEmail === '') {
        $fromEmail = 'noreply@innovatech.ph';
    }
    if ($fromName === '') {
        $fromName = APP_NAME;
    }

    $subject = str_replace(["\r", "\n"], ' ', $subject);
    $html = "<!DOCTYPE html><html><body style='font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1b2a33'>" . $bodyHtml . "</body></html>";

    // Validate the SMTP config before dialing, then attempt delivery.
    $host = trim((string) ($s['smtp_host'] ?? ''));
    $port = (int) ($s['smtp_port'] ?? 465);
    if ($host !== '' && $port > 0) {
        $smtpUser = (string) ($s['smtp_username'] ?? '');
        $smtpPass = (string) base64_decode((string) ($s['smtp_password_enc'] ?? ''));
        if ($smtpUser === '' || $smtpPass === '') {
            email_log("send_email: SMTP host {$host}:{$port} is set but username/password are missing — spooling instead.");
        } else {
            $trace = [];
            $sentViaSmtp = smtp_send($host, $port, $smtpUser, $smtpPass, $fromName, $fromEmail, $to, $subject, $html, $trace);
            foreach ($trace as $line) {
                email_log($line);
            }
            if ($sentViaSmtp) {
                email_log("send_email: OK via SMTP {$host}:{$port} -> {$to} (\"{$subject}\")");
                return true;
            }
            email_log("send_email: SMTP {$host}:{$port} rejected/failed -> {$to} (\"{$subject}\"); spooling instead.");
        }
    } else {
        email_log("send_email: no SMTP configured — spooling -> {$to} (\"{$subject}\")");
    }

    // Spool to storage/mail/ when SMTP is not configured (or just failed) so
    // the flow still works in dev, and the owner can turn on SMTP later.
    try {
        $dir = ROOT_PATH . '/storage/mail';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @chmod($dir, 0777);
        if (!is_dir($dir) || !is_writable($dir)) {
            $tmp = sys_get_temp_dir() . '/innovatech-mail';
            @mkdir($tmp, 0777, true);
            @chmod($tmp, 0777);
            $dir = is_writable($tmp) ? $tmp : $dir;
        }
        $file = sprintf('%s/%s-%s.eml', $dir, date('Y-m-d-His'), bin2hex(random_bytes(3)));
        $msg = "From: $fromName <$fromEmail>\r\nTo: <$to>\r\nSubject: $subject\r\n"
             . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n$html";
        $ok = file_put_contents($file, $msg) !== false;
        email_log($ok ? "send_email: OK spooled -> {$file}" : "send_email: spool FAILED (could not write {$file})");
        return $ok;
    } catch (Throwable $e) {
        email_log('send_email: spool exception: ' . $e->getMessage());
        return false;
    }
}

/**
 * Minimal SMTP client (EHLO / STARTTLS / AUTH LOGIN / DATA).
 * Port 465 uses implicit TLS; 587/25 upgrade via STARTTLS.
 * Every conversation line is appended to &$log (optional) for debugging.
 */
function smtp_send(string $host, int $port, string $user, string $pass, string $fromName, string $fromEmail, string $to, string $subject, string $html, ?array &$log = null): bool
{
    $lines = [];
    $step  = static function (string $m) use (&$lines) { $lines[] = '[client] ' . $m; };
    $srv   = static function (string $m) use (&$lines) { $lines[] = '[server] ' . trim($m); };
    $err   = static function (string $m) use (&$lines) { $lines[] = '[error] ' . $m; };

    if (!in_array($port, [25, 465, 587], true)) {
        $err("unsupported SMTP port {$port}");
        $log = $lines;
        return false;
    }
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $err("invalid from/to address (from={$fromEmail}, to={$to})");
        $log = $lines;
        return false;
    }

    $secure = $port === 465 ? 'ssl://' : '';
    $step("connecting to {$secure}{$host}:{$port}...");
    $conn = @stream_socket_client($secure . $host . ':' . $port, $errno, $errstr, 20);
    if (!$conn) {
        $err("connect failed: {$errstr} ({$errno})");
        $log = $lines;
        return false;
    }
    stream_set_timeout($conn, 30);

    $read = function () use ($conn, $srv) {
        $respStr = '';
        while ($line = fgets($conn, 515)) {
            $respStr .= $line;
            $srv($line);
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $respStr;
    };
    $good       = static fn (string $r): bool => isset($r[2]) && $r[0] === '2';
    $loginReply = static fn (string $r): bool => isset($r[2]) && $r[0] === '3';

    $banner = $read(); // banner
    if ($banner === '') {
        $err('no banner; server did not respond');
        fclose($conn);
        $log = $lines;
        return false;
    }

    $write = function (string $cmd) use ($conn, $step) {
        $step($cmd);
        fwrite($conn, $cmd . "\r\n");
    };

    $write('EHLO innovatech.local');
    if (!$good($read())) { fclose($conn); $log = $lines; return false; }

    if ($secure === '' && $port !== 25) {
        $write('STARTTLS');
        if (!$good($read())) { fclose($conn); $log = $lines; return false; }
        if (!@stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $err('STARTTLS handshake failed');
            fclose($conn);
            $log = $lines;
            return false;
        }
        $write('EHLO innovatech.local');
        if (!$good($read())) { fclose($conn); $log = $lines; return false; }
    }

    if ($user !== '') {
        $write('AUTH LOGIN');
        if (!$loginReply($read())) { fclose($conn); $log = $lines; return false; }
        $write(base64_encode($user));
        if (!$loginReply($read())) { fclose($conn); $log = $lines; return false; }
        $step('[auth] password sent');
        fwrite($conn, base64_encode($pass) . "\r\n");
        if (!$good($read())) {
            $err('AUTH failed — check the SMTP username / password');
            fclose($conn);
            $log = $lines;
            return false;
        }
        $step('authenticated');
    }

    $write("MAIL FROM:<{$fromEmail}>");
    if (!$good($read())) { fclose($conn); $log = $lines; return false; }
    $write("RCPT TO:<{$to}>");
    if (!$good($read())) { fclose($conn); $log = $lines; return false; }

    $write('DATA');
    // The server answers DATA with a 3xx "ready for message input" prompt,
    // not a 2xx — accept either so we proceed to transmit the body.
    $dataReply = trim($read());
    if (!isset($dataReply[0]) || !in_array($dataReply[0], ['2', '3'], true)) {
        fclose($conn);
        $log = $lines;
        return false;
    }

    $headers = "From: {$fromName} <{$fromEmail}>\r\nTo: <{$to}>\r\nSubject: {$subject}\r\n"
             . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $step('sending message body (' . strlen($html) . ' bytes)');
    fwrite($conn, $headers . "\r\n" . $html . "\r\n.\r\n");
    $ok = $good($read());
    if (!$ok) {
        $err('server rejected the message body');
    }
    $write('QUIT');
    fclose($conn);
    if ($log !== null) {
        $log = $lines;
    }
    return $ok;
}

/**
 * Send login credentials to an account using the matching email template.
 * Template placeholders: {{name}} {{email}} {{username}} {{password}} {{role}}
 * {{institution}} {{link}} {{login_link}}.
 */
function send_credentials(array $user, string $roleSlug, string $password, ?string $institution = null): bool
{
    $slug = match ($roleSlug) {
        'system_admin', 'system_staff', 'owner' => 'admin_invite',
        'staff' => 'staff_invite',
        default => 'admin_invite',
    };
    try {
        $tpl = db()->prepare("SELECT subject, body_html, is_active FROM email_templates WHERE slug=:s");
        $tpl->execute(['s' => $slug]);
        $row = $tpl->fetch();
        if (!$row || !(int) $row['is_active']) {
            return false;
        }
    } catch (Throwable $e) {
        return false;
    }

    $vars = [
        '{{name}}' => display_name($user),
        '{{email}}' => $user['email'] ?? '',
        '{{username}}' => $user['username'] ?? '',
        '{{password}}' => $password,
        '{{role}}' => ucfirst(str_replace('_', ' ', $roleSlug)),
        '{{institution}}' => $institution ?: '—',
        '{{link}}' => url('admin/index'),
        '{{login_link}}' => url('admin/index'),
    ];
    $subject = strtr((string) $row['subject'], $vars);
    $body = strtr((string) $row['body_html'], $vars);
    return send_email((string) $user['email'], $subject, $body);
}

/**
 * Notify a support-ticket submitter about a status change.
 * Template placeholders: {{name}} {{email}} {{ticket_id}} {{ticket_subject}}
 * {{ticket_message}} {{ticket_status}} {{link}}.
 * Returns [sent(bool), to(string)] so callers can report clearly.
 */
function send_ticket_status_email(array $ticket): array
{
    $to = trim((string) ($ticket['contact_email'] ?? ''));
    if ($to === '') {
        return [false, ''];
    }
    try {
        $tpl = db()->prepare("SELECT subject, body_html, is_active FROM email_templates WHERE slug='support_ticket_status' LIMIT 1");
        $tpl->execute();
        $row = $tpl->fetch();
        if (!$row || !(int) $row['is_active']) {
            return [false, $to];
        }
        $name = '';
        if (!empty($ticket['created_by'])) {
            $u = crud()->get('users', (int) $ticket['created_by']);
            $name = $u ? display_name($u) : '';
        }
        $vars = [
            '{{name}}'            => $name !== '' ? $name : 'there',
            '{{email}}'           => $to,
            '{{ticket_id}}'       => (string) (int) ($ticket['id'] ?? 0),
            '{{ticket_subject}}'  => $ticket['subject'] ?? '',
            '{{ticket_message}}'  => $ticket['message'] ?? '',
            '{{ticket_status}}'   => str_replace('_', ' ', strtolower((string) ($ticket['status'] ?? 'open'))),
            '{{link}}'            => url('admin/index'),
        ];
        $subject = strtr((string) $row['subject'], $vars);
        $body = strtr((string) $row['body_html'], $vars);
        return [send_email($to, $subject, $body), $to];
    } catch (Throwable $e) {
        return [false, $to];
    }
}

/** Notify the platform support desk + all active owners when a support ticket opens. */
function send_ticket_notification_email(array $ticket, ?string $institutionName = null): array
{
    try {
        $tpl = db()->prepare("SELECT subject, body_html, is_active FROM email_templates WHERE slug='new_ticket' LIMIT 1");
        $tpl->execute();
        $row = $tpl->fetch();
        if (!$row || !(int) $row['is_active']) {
            return [false, ''];
        }
    } catch (Throwable $e) {
        return [false, ''];
    }

    $recipients = [];
    try {
        $s = db()->query("SELECT contact_email FROM platform_settings WHERE id=1 LIMIT 1")->fetch() ?: [];
        $contact = trim((string) ($s['contact_email'] ?? ''));
        if ($contact !== '') {
            $recipients[$contact] = true;
        }
    } catch (Throwable $e) {
    }
    try {
        $stmt = db()->prepare(
            "SELECT u.* FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'owner' AND u.is_active = 1"
        );
        $stmt->execute();
        foreach ($stmt->fetchAll() as $o) {
            $em = trim((string) ($o['email'] ?? ''));
            if ($em !== '') {
                $recipients[$em] = true;
            }
        }
    } catch (Throwable $e) {
    }
    if (!$recipients) {
        return [false, ''];
    }

    $name = '';
    if (!empty($ticket['created_by'])) {
        $u = crud()->get('users', (int) $ticket['created_by']);
        $name = $u ? display_name($u) : '';
    }
    $vars = [
        '{{name}}'           => $name !== '' ? $name : 'A workspace user',
        '{{email}}'          => (string) ($ticket['contact_email'] ?? ''),
        '{{ticket_id}}'      => (string) (int) ($ticket['id'] ?? 0),
        '{{ticket_subject}}' => (string) ($ticket['subject'] ?? ''),
        '{{ticket_message}}' => (string) ($ticket['message'] ?? ''),
        '{{priority}}'       => ucfirst((string) ($ticket['priority'] ?? 'normal')),
        '{{institution}}'    => (string) ($institutionName ?? ''),
        '{{link}}'           => url('admin/support'),
    ];
    $subject = strtr((string) $row['subject'], $vars);
    $body = strtr((string) $row['body_html'], $vars);

    $ok = [];
    foreach (array_keys($recipients) as $to) {
        $ok[] = send_email($to, $subject, $body);
    }
    $toList = implode(', ', array_keys($recipients));
    return [!in_array(false, $ok, true), $toList];
}

/**
 * Offline built-in AI description generator (swappable for an LLM call later).
 * Structured, constrained generation — 2-3 short, natural sentences that never
 * invent facts: identity (+location if known), what it offers, then a visitor
 * note. $info may be a plain string (extra context) or an array with keys:
 * type, location, facilities (string|array), purpose, details, context.
 */
function ai_generate_description(string $name, string $institution = '', array|string $info = []): string
{
    $info = is_array($info) ? $info : ['context' => trim((string) $info)];

    $label = [
        'building' => 'building', 'room' => 'room', 'facility' => 'facility',
        'campus_area' => 'campus area', 'area' => 'campus area',
        'tour_scene' => '360° tour stop', 'waypoint' => 'campus waypoint',
        'hotspot' => 'tour hotspot', 'institution' => 'campus',
    ][strtolower((string) ($info['type'] ?? ''))] ?? 'campus space';

    $location = trim((string) ($info['location'] ?? ''));
    $facilities = $info['facilities'] ?? '';
    if (is_array($facilities)) {
        $facilities = trim(implode(', ', array_values(array_filter(array_map('trim', $facilities), fn($f) => $f !== ''))));
    } else {
        $facilities = trim((string) $facilities);
    }
    $purpose = trim((string) ($info['purpose'] ?? ''));
    $details = trim((string) ($info['details'] ?? ''));
    $context = trim((string) ($info['context'] ?? ''));

    $sentences = [];

    if ($location !== '' && $institution !== '') {
        $sentences[] = "{$name} is a {$label} located at {$location} on the {$institution} campus.";
    } elseif ($location !== '') {
        $sentences[] = "{$name} is a {$label} located at {$location}.";
    } elseif ($institution !== '') {
        $sentences[] = "{$name} is a {$label} of {$institution}.";
    } else {
        $sentences[] = "{$name} is a {$label} of the campus.";
    }

    if ($facilities !== '') {
        $sentences[] = $purpose !== ''
            ? "It offers {$facilities}, making it ideal for {$purpose}."
            : "It provides {$facilities}, set up to support the campus community.";
    } elseif ($details !== '') {
        $sentences[] = $purpose !== ''
            ? "Designed {$details}, it is used mainly for {$purpose}."
            : "Designed {$details}, it keeps the campus community's daily needs covered.";
    } elseif ($purpose !== '') {
        $sentences[] = "It serves the campus community, set up for {$purpose}.";
    }

    $closers = [
        'Visitors can explore it through the 360° tour and find it instantly on the campus floor plan.',
        'Find it on the campus map, or step inside through the interactive 360° tour.',
        'It appears on the campus floor plan and is featured in the 360° tour for easy navigation.',
    ];
    $sentences[] = $closers[array_rand($closers)];

    if ($context !== '') {
        $sentences[] = ucfirst(rtrim(trim(strip_tags($context)), '.')) . '.';
    }

    return implode("\n\n", $sentences);
}

/**
 * Stitch six cubemap faces into one equirectangular panorama (pure PHP/GD).
 * $faces = ['front' => path, 'back' => path, 'left' => …, 'right' => …, 'up' => …, 'down' => …]
 * Uses the standard cubemap→lat/long lookup (nearest + bilinear sampling).
 */
function cubemap_to_equirect(array $faces, string $outPath, int $width = 2048, int $height = 1024): bool
{
    $src = [];
    foreach (['front', 'back', 'left', 'right', 'up', 'down'] as $face) {
        if (empty($faces[$face]) || !is_file($faces[$face])) {
            return false;
        }
        $loaded = false;
        foreach ([imagecreatefromjpeg(...), imagecreatefrompng(...), imagecreatefromgif(...)] as $loader) {
            try {
                $img = @$loader($faces[$face]);
                if ($img) { $src[$face] = $img; $loaded = true; break; }
            } catch (Throwable $e) {
            }
        }
        if (!$loaded && function_exists('imagecreatefromwebp')) {
            try { $img = @imagecreatefromwebp($faces[$face]); if ($img) { $src[$face] = $img; $loaded = true; } } catch (Throwable $e) {}
        }
        if (!$loaded) {
            foreach ($src as $im) { imagedestroy($im); }
            return false;
        }
    }

    $faceSize = imagesx($src['front']);
    if (imagesy($src['front']) !== $faceSize) {
        // faces should be square; bail safely
        foreach ($src as $im) { imagedestroy($im); }
        return false;
    }

    $out = imagecreatetruecolor($width, $height);
    imagealphablending($out, false);
    imagesavealpha($out, true);

    // pre-transform faces into GD-compatible orientation and cache as needed
    $faceDir = [
        'front' => [1, -1], 'back' => [-1, -1], 'left' => [-1, -1], 'right' => [1, -1],
        'up' => [1, 1], 'down' => [1, -1],
    ];

    $byte = function ($v) {
        return $v < 0 ? 0 : ($v > 255 ? 255 : (int) $v);
    };

    for ($y = 0; $y < $height; $y++) {
        // phi: from -90 (top) to +90 (bottom)
        $phi = (pi() * ($y + 0.5)) / $height - pi() / 2;
        $cosPhi = cos($phi);
        $sinPhi = sin($phi);
        for ($x = 0; $x < $width; $x++) {
            $theta = (2 * pi() * ($x + 0.5)) / $width - pi();
            $dx = $cosPhi * cos($theta);
            $dy = $sinPhi;
            $dz = $cosPhi * sin($theta);

            $ax = abs($dx); $ay = abs($dy); $az = abs($dz);
            $uc = 0.0; $vc = 0.0; $faceSel = 'front';
            if ($az >= $ax && $az >= $ay) { $faceSel = $dz >= 0 ? 'front' : 'back'; $uc = $dz >= 0 ? $dx : -$dx; $vc = -$dy; }
            elseif ($ax >= $ay) { $faceSel = $dx >= 0 ? 'right' : 'left'; $uc = $dx >= 0 ? -$dz : $dz; $vc = -$dy; }
            else { $faceSel = $dy >= 0 ? 'up' : 'down'; $uc = $dx; $vc = $dy >= 0 ? $dz : -$dz; }

            $u = (($uc + 1) * 0.5) * ($faceSize - 1);
            $v = (($vc + 1) * 0.5) * ($faceSize - 1);
            $u0 = (int) floor($u); $v0 = (int) floor($v);
            $u1 = min($u0 + 1, $faceSize - 1); $v1 = min($v0 + 1, $faceSize - 1);
            $fu = $u - $u0; $fv = $v - $v0;

            $im = $src[$faceSel];
            $c00 = imagecolorat($im, $u0, $v0);
            $c10 = imagecolorat($im, $u1, $v0);
            $c01 = imagecolorat($im, $u0, $v1);
            $c11 = imagecolorat($im, $u1, $v1);

            $r = $byte((1 - $fu) * ((1 - $fv) * (($c00 >> 16) & 0xFF) + $fv * (($c01 >> 16) & 0xFF)) + $fu * ((1 - $fv) * (($c10 >> 16) & 0xFF) + $fv * (($c11 >> 16) & 0xFF)));
            $g = $byte((1 - $fu) * ((1 - $fv) * (($c00 >> 8) & 0xFF) + $fv * (($c01 >> 8) & 0xFF)) + $fu * ((1 - $fv) * (($c10 >> 8) & 0xFF) + $fv * (($c11 >> 8) & 0xFF)));
            $b = $byte((1 - $fu) * ((1 - $fv) * ($c00 & 0xFF) + $fv * ($c01 & 0xFF)) + $fu * ((1 - $fv) * ($c10 & 0xFF) + $fv * ($c11 & 0xFF)));
            imagesetpixel($out, $x, $y, ($r << 16) | ($g << 8) | $b);
        }
    }

    $ok = imagejpeg($out, $outPath, 90);
    imagedestroy($out);
    foreach ($src as $im) { imagedestroy($im); }
    return (bool) $ok;
}

/**
 * Sync institution settings to its config.json file
 */
function sync_institution_config(int $iid): void
{
    $pdo = db();
    $inst = crud()->get('institutions', $iid);
    if (!$inst || empty($inst['folder_path'])) return;

    $configPath = ROOT_PATH . '/' . ltrim($inst['folder_path'], '/') . '/config.json';
    if (!is_file($configPath)) return;

    $cfg = json_decode((string)file_get_contents($configPath), true);
    if (!is_array($cfg)) $cfg = [];

    $stripOrg = function(string $path) use ($inst) {
        $prefix = 'organizations/' . $inst['slug'] . '/';
        if (str_starts_with($path, $prefix)) {
            return substr($path, strlen($prefix));
        }
        return $path;
    };

    $cfg = [
        'institution_id' => (int) $inst['id'],
        'name'           => $inst['name'],
        'short_name'     => $inst['short_name'],
        'landing_mode'   => $inst['landing_mode'] ?: '360_rotation',
        'require_landscape_mobile' => (bool) $inst['require_landscape_mobile'],
        'published'      => (bool) (int) $inst['is_published'],
    ];

    // Theme
    $theme = crud()->get('institution_themes', ['institution_id' => $iid]);
    if ($theme) {
        $tj = json_decode($theme['theme_json'] ?? 'null', true) ?: [];
        $cfg['theme'] = [
            'primary'         => $theme['primary_color'],
            'secondary'       => $theme['secondary_color'],
            'accent'          => $theme['accent_color'],
            'popup_animation' => $theme['popup_animation'],
            'marker_glow'     => (bool) ($tj['marker_glow'] ?? false),
        ];
    }

    // All scenes with their hotspots
    $scenesStmt = $pdo->prepare("SELECT * FROM tour_scenes WHERE institution_id = ? AND deleted_at IS NULL ORDER BY sort_order, id");
    $scenesStmt->execute([$iid]);
    $scenesList = $scenesStmt->fetchAll(PDO::FETCH_ASSOC);

    $cfg['scenes'] = [];
    foreach ($scenesList as $sc) {
        $hsStmt = $pdo->prepare(
            "SELECT sh.*, ts.equirect_path AS to_scene_equirect, ts.title AS to_scene_title, ts.initial_yaw AS to_scene_yaw, ts.initial_pitch AS to_scene_pitch
             FROM scene_hotspots sh
             LEFT JOIN tour_scenes ts ON ts.id = sh.to_scene_id
             WHERE sh.from_scene_id = ? AND sh.institution_id = ?"
        );
        $hsStmt->execute([(int)$sc['id'], $iid]);
        $hotspots = $hsStmt->fetchAll(PDO::FETCH_ASSOC);

        $hsData = array_map(function($h) use ($stripOrg) {
            $out = [
                'id'            => (int) $h['id'],
                'label'         => $h['label'],
                'hotspot_type'  => $h['hotspot_type'],
                'yaw'           => (float) $h['yaw'],
                'pitch'         => (float) $h['pitch'],
                'body_html'     => $h['body_html'] ?? '',
            ];
            if (!empty($h['to_scene_id'])) {
                $out['to_scene_id']      = (int) $h['to_scene_id'];
                $out['to_scene_title']   = $h['to_scene_title'] ?? '';
                $out['to_scene_equirect'] = $stripOrg($h['to_scene_equirect'] ?? '');
                $out['to_scene_yaw']     = (float) ($h['to_scene_yaw'] ?? 0);
                $out['to_scene_pitch']   = (float) ($h['to_scene_pitch'] ?? 0);
            }
            return $out;
        }, $hotspots);

        $cfg['scenes'][(int)$sc['id']] = [
            'id'                  => (int) $sc['id'],
            'title'               => $sc['title'],
            'featured_image_path' => $stripOrg($sc['featured_image_path'] ?? ''),
            'equirect_path'       => $stripOrg($sc['equirect_path'] ?? ''),
            'initial_yaw'         => (float) ($sc['initial_yaw'] ?? 0),
            'initial_pitch'       => (float) ($sc['initial_pitch'] ?? 0),
            'hotspots'            => $hsData
        ];
    }

    // Starting 360 scene (for 360_rotation landing)
    if (!empty($inst['starting_scene_id'])) {
        $scene = crud()->get('tour_scenes', $inst['starting_scene_id']);
        if ($scene) {
            $cfg['starting_scene'] = [
                'id'            => (int) $scene['id'],
                'title'         => $scene['title'],
                'equirect_path' => $stripOrg($scene['equirect_path'] ?? ''),
                'initial_yaw'   => (float) ($scene['initial_yaw'] ?? 0),
                'initial_pitch' => (float) ($scene['initial_pitch'] ?? 0),
            ];
        }
    }

    // Starting floor plan + its markers (for floor_plan landing)
    if (!empty($inst['starting_floor_plan_id'])) {
        $plan = crud()->get('floor_plans', $inst['starting_floor_plan_id']);
        if ($plan && !empty($plan['image_path'])) {
            $cfg['starting_floor_plan'] = [
                'id'         => (int) $plan['id'],
                'title'      => $plan['title'],
                'image_path' => $stripOrg($plan['image_path']),
                'object_fit' => $plan['object_fit'] ?? 'contain',
                'north_angle'=> (float) ($plan['north_angle'] ?? 0),
                'building_id'=> isset($plan['building_id']) ? (int) $plan['building_id'] : null,
                'floor_level'=> $plan['floor_level'] ?? null,
            ];

            // Navigation waypoints (walking graph) for this floor plan
            $cfg['fp_waypoints'] = [];
            $wstmt = $pdo->prepare("SELECT * FROM fp_waypoints WHERE floor_plan_id=? AND institution_id=? ORDER BY sort_order, id");
            $wstmt->execute([(int) $plan['id'], $iid]);
            foreach ($wstmt->fetchAll(PDO::FETCH_ASSOC) as $wp) {
                $cfg['fp_waypoints'][] = [
                    'id' => (int) $wp['id'],
                    'label' => $wp['label'],
                    'x' => (float) $wp['x_percent'],
                    'y' => (float) $wp['y_percent'],
                    'type' => $wp['type'] === 'corner' ? 'corner' : 'normal',
                ];
            }

            // Navigation paths (ordered waypoint references)
            $cfg['fp_paths'] = [];
            $pstmt = $pdo->prepare("SELECT * FROM fp_navigation_paths WHERE floor_plan_id=? AND institution_id=? ORDER BY id");
            $pstmt->execute([(int) $plan['id'], $iid]);
            foreach ($pstmt->fetchAll(PDO::FETCH_ASSOC) as $path) {
                $cfg['fp_paths'][] = [
                    'id' => (int) $path['id'],
                    'name' => $path['name'],
                    'nodes' => array_values(array_filter(array_map('intval', json_decode($path['nodes_json'] ?? '[]', true) ?: []))),
                ];
            }

            // Exit connections
            $cfg['fp_connections'] = [];
            $cstmt = $pdo->prepare("SELECT * FROM fp_connections WHERE from_floor_plan_id=? AND institution_id=? ORDER BY id");
            $cstmt->execute([(int) $plan['id'], $iid]);
            foreach ($cstmt->fetchAll(PDO::FETCH_ASSOC) as $con) {
                $cfg['fp_connections'][] = [
                    'id' => (int) $con['id'],
                    'from_marker_id' => (int) $con['from_marker_id'],
                    'to_marker_id' => $con['to_marker_id'] ? (int) $con['to_marker_id'] : null,
                    'to_floor_plan_id' => $con['to_floor_plan_id'] ? (int) $con['to_floor_plan_id'] : null,
                    'to_scene_id' => $con['to_scene_id'] ? (int) $con['to_scene_id'] : null,
                    'to_building_id' => $con['to_building_id'] ? (int) $con['to_building_id'] : null,
                    'note' => $con['note'] ?? '',
                ];
            }

            // Markers for this floor plan
            $stmt = $pdo->prepare(
                "SELECT fm.*,
                        ts.equirect_path AS scene_equirect, ts.initial_yaw AS scene_yaw, ts.initial_pitch AS scene_pitch, ts.title AS scene_title,
                        fp2.image_path AS sub_fp_image, fp2.title AS sub_fp_title, fp2.id AS sub_fp_id
                 FROM floor_plan_markers fm
                 LEFT JOIN tour_scenes ts ON ts.id = fm.target_scene_id
                 LEFT JOIN floor_plans fp2 ON fp2.id = fm.target_floor_plan_id
                 WHERE fm.floor_plan_id = ? AND fm.institution_id = ?
                 ORDER BY fm.sort_order, fm.id"
            );
            $stmt->execute([(int) $plan['id'], $iid]);
            $markers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $cfg['floor_plan_markers'] = array_map(function($m) use ($stripOrg) {
                $out = [
                    'id'          => (int) $m['id'],
                    'label'       => $m['label'],
                    'x'           => (float) $m['x_percent'],
                    'y'           => (float) $m['y_percent'],
                    'size_percent'=> (float) ($m['size_percent'] ?? 4),
                    'marker_type' => in_array($m['marker_type'] ?? '', ['scene', 'entrance', 'exit'], true) ? $m['marker_type'] : 'scene',
                    'facing_angle'=> (float) ($m['facing_angle'] ?? 0),
                    'popup_title' => $m['popup_title'] ?? '',
                    'popup_html'  => $m['popup_html'] ?? '',
                ];
                if (!empty($m['marker_image_path'])) {
                    $out['marker_image']  = $stripOrg($m['marker_image_path']);
                    $out['marker_image_url'] = media_url($m['marker_image_path']);
                }
                // Navigation target
                if (!empty($m['target_scene_id'])) {
                    $out['target_type']     = 'scene';
                    $out['target_scene_id'] = (int) $m['target_scene_id'];
                    $out['scene_title']     = $m['scene_title'] ?? '';
                    $out['scene_equirect']  = $stripOrg($m['scene_equirect'] ?? '');
                    $out['scene_yaw']       = (float) ($m['scene_yaw'] ?? 0);
                    $out['scene_pitch']     = (float) ($m['scene_pitch'] ?? 0);
                } elseif (!empty($m['target_floor_plan_id'])) {
                    $out['target_type']          = 'floor_plan';
                    $out['target_floor_plan_id'] = (int) $m['target_floor_plan_id'];
                    $out['sub_fp_title']         = $m['sub_fp_title'] ?? '';
                    $out['sub_fp_image']         = $stripOrg($m['sub_fp_image'] ?? '');
                } else {
                    $out['target_type'] = 'popup';
                }
                return $out;
            }, $markers);
        }
    }

    file_put_contents($configPath, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    // Regenerate index.php from template (PHP template doesn't need templating - reads config.json dynamically)
    $tplPath    = ROOT_PATH . '/templates/org_pack/index.php';
    $tplCssPath = ROOT_PATH . '/templates/org_pack/assets/style.css';
    $orgDir     = ROOT_PATH . '/' . ltrim($inst['folder_path'], '/');
    if (is_file($tplPath)) {
        copy($tplPath, $orgDir . '/index.php');
    }
    if (is_file($tplCssPath)) {
        copy($tplCssPath, $orgDir . '/assets/style.css');
    }
}
/** Turn a stored media path or absolute URL into a browser URL (never double-prefix). */
function media_url(string $path, string $fallback = ''): string
{
    $path = normalize_media_path($path);
    if ($path === '') {
        return $fallback !== '' ? media_url($fallback) : '';
    }
    if (preg_match('#^(https?:)?//#i', $path) === 1) {
        return str_starts_with($path, '//') ? 'https:' . $path : $path;
    }
    return url($path);
}

/** Strip this app's origin so we store project-relative paths, not localhost URLs. */
function normalize_media_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    $path = str_replace('\\', '/', $path);
    $decoded = rawurldecode($path);
    $base = rtrim((string) BASE_URL, '/');
    $bases = array_unique(array_filter([
        $base,
        rawurldecode($base),
        str_replace(' ', '%20', $base),
        str_replace('%20', ' ', $base),
    ]));
    foreach ([$path, $decoded] as $candidate) {
        foreach ($bases as $b) {
            if ($b !== '' && str_starts_with($candidate, $b . '/')) {
                return ltrim(substr($candidate, strlen($b)), '/');
            }
        }
    }
    return $decoded;
}

/** Image files under project-relative folders (for the owner media library). */
function list_image_library(array $relativeDirs, int $limit = 240): array
{
    static $cache = [];
    $cacheKey = implode('|', $relativeDirs) . ':' . $limit;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $out = [];
    $exts = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'avif'];
    $skipDir = ['node_modules', '.git', 'vendor', 'manager', 'old'];
    foreach ($relativeDirs as $dir) {
        $abs = ROOT_PATH . '/' . trim(str_replace('\\', '/', $dir), '/');
        if (!is_dir($abs)) {
            continue;
        }
        try {
            $rdi = new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS);
            $filter = new RecursiveCallbackFilterIterator($rdi, static function ($current) use ($skipDir) {
                if ($current->isDir()) {
                    return !in_array(strtolower($current->getFilename()), $skipDir, true);
                }
                return true;
            });
            $rii = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);
            foreach ($rii as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, $exts, true)) {
                    continue;
                }
                $full = str_replace('\\', '/', $file->getPathname());
                $rel = ltrim(substr($full, strlen(str_replace('\\', '/', ROOT_PATH))), '/');
                if (str_contains($rel, '/aframe.min') || str_contains($rel, '/node_modules/')) {
                    continue;
                }
                $out[] = [
                    'name' => $file->getFilename(),
                    'path' => $rel,
                    'url'  => media_url($rel),
                ];
                if (count($out) >= $limit) {
                    $cache[$cacheKey] = $out;
                    return $out;
                }
            }
        } catch (Throwable $e) {
            continue;
        }
    }
    $cache[$cacheKey] = $out;
    return $out;
}

function directory_bytes(string $abs): int
{
    if (!is_dir($abs)) {
        return 0;
    }
    $bytes = 0;
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $file) {
            if ($file->isFile()) {
                $bytes += (int) $file->getSize();
            }
        }
    } catch (Throwable $e) {
        return $bytes;
    }
    return $bytes;
}

function format_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $n = (float) max(0, $bytes);
    $i = 0;
    while ($n >= 1024 && $i < count($units) - 1) {
        $n /= 1024;
        $i++;
    }
    $decimals = ($i === 0 || $n >= 10) ? 0 : 1;
    return number_format($n, $decimals) . ' ' . $units[$i];
}

/**
 * Folders the web server may write media into (first writable wins).
 * @return list<string>
 */
function upload_dir_candidates(string $preferred = 'public/uploads'): array
{
    $preferred = trim(str_replace('\\', '/', $preferred), '/');
    $list = [$preferred, 'public/uploads', 'assets/uploads', 'storage/uploads'];
    return array_values(array_unique(array_filter($list)));
}

/**
 * Create or reuse a writable media folder. Returns the project-relative path used.
 */
function ensure_writable_dir(string $preferredRelative = 'public/uploads'): string
{
    $errors = [];
    foreach (upload_dir_candidates($preferredRelative) as $rel) {
        $abs = ROOT_PATH . '/' . $rel;
        if (!is_dir($abs)) {
            error_clear_last();
            $ok = @mkdir($abs, 0777, true);
            if (!$ok && !is_dir($abs)) {
                $err = error_get_last();
                $phpErr = $err ? $err['message'] : 'unknown error';
                $parent = dirname($abs);
                $parentExists = is_dir($parent) ? 'yes' : 'no';
                $parentWritable = is_writable($parent) ? 'yes' : 'no';
                $errors[] = "$rel (mkdir failed. parent_exists=$parentExists, parent_writable=$parentWritable, error=$phpErr)";
                continue;
            }
        }
        @chmod($abs, 0777);
        if (is_dir($abs) && is_writable($abs)) {
            $probe = $abs . '/.write-test';
            if (@file_put_contents($probe, 'ok') !== false) {
                @unlink($probe);
                return $rel;
            }
            $errors[] = $rel . ' (folder exists but is not writable by PHP)';
            continue;
        }
        $errors[] = $rel . ' (not writable)';
    }
    $user = function_exists('posix_getpwuid') && function_exists('posix_geteuid')
        ? ((posix_getpwuid(posix_geteuid())['name'] ?? '') ?: ('uid ' . posix_geteuid()))
        : (get_current_user() ?: 'the PHP user');
    throw new RuntimeException(
        'Could not create a writable upload folder for ' . $user . ". Tried:\n- "
        . implode("\n- ", $errors)
        . "\n\nFix folder permissions (see Help Center → Server environment) or pick a project file / paste a path instead."
    );
}

/**
 * Project file sizes used by the owner/system storage chart.
 * @return array{storage: array<string,int>, storageUsed: int, diskFree: int, diskTotal: int}
 */
function project_storage_stats(): array
{
    $storage = [
        'organizations' => directory_bytes(ORG_ROOT),
        'public'        => directory_bytes(ROOT_PATH . '/public'),
        'assets'        => directory_bytes(ROOT_PATH . '/assets'),
    ];
    $diskFree = @disk_free_space(ROOT_PATH);
    $diskTotal = @disk_total_space(ROOT_PATH);
    return [
        'storage'     => $storage,
        'storageUsed' => array_sum($storage),
        'diskFree'    => is_numeric($diskFree) ? (int) $diskFree : 0,
        'diskTotal'   => is_numeric($diskTotal) ? (int) $diskTotal : 0,
    ];
}

/**
 * PHP / write-permission checks for the owner Help Center.
 * @return list<array{label:string, ok:bool, detail:string}>
 */
function platform_environment_checks(): array
{
    $phpMin = '8.1.0';
    $rows = [];
    $rows[] = [
        'label'  => 'PHP version',
        'ok'     => version_compare(PHP_VERSION, $phpMin, '>='),
        'detail' => PHP_VERSION . ' (need ' . $phpMin . '+)',
    ];
    foreach (['pdo_mysql' => 'MySQL PDO', 'fileinfo' => 'Fileinfo (uploads)', 'mbstring' => 'mbstring', 'json' => 'JSON', 'openssl' => 'OpenSSL'] as $ext => $label) {
        $on = extension_loaded($ext);
        $rows[] = ['label' => $label, 'ok' => $on, 'detail' => $on ? 'loaded' : 'missing — enable in php.ini'];
    }
    $gd = extension_loaded('gd') || extension_loaded('imagick');
    $rows[] = [
        'label'  => 'Image processing (GD or Imagick)',
        'ok'     => $gd,
        'detail' => $gd ? (extension_loaded('gd') ? 'GD' : 'Imagick') : 'optional, but recommended for thumbnails',
    ];
    $fileUploads = filter_var(ini_get('file_uploads'), FILTER_VALIDATE_BOOLEAN);
    $rows[] = ['label' => 'file_uploads', 'ok' => (bool) $fileUploads, 'detail' => $fileUploads ? 'On' : 'Off in php.ini'];
    $rows[] = ['label' => 'upload_max_filesize', 'ok' => true, 'detail' => (string) ini_get('upload_max_filesize')];
    $rows[] = ['label' => 'post_max_size', 'ok' => true, 'detail' => (string) ini_get('post_max_size')];
    $tmp = (string) (ini_get('upload_tmp_dir') ?: sys_get_temp_dir());
    $rows[] = [
        'label'  => 'Temp upload dir',
        'ok'     => $tmp !== '' && is_dir($tmp) && is_writable($tmp),
        'detail' => $tmp . (is_writable($tmp) ? ' (writable)' : ' (not writable)'),
    ];
    $paths = [
        'Project root'     => ROOT_PATH,
        'public/'          => ROOT_PATH . '/public',
        'public/uploads/'  => ROOT_PATH . '/public/uploads',
        'assets/'          => ROOT_PATH . '/assets',
        'assets/uploads/'  => ROOT_PATH . '/assets/uploads',
        'storage/uploads/' => ROOT_PATH . '/storage/uploads',
        'organizations/'   => ORG_ROOT,
    ];
    foreach ($paths as $label => $abs) {
        $exists = is_dir($abs);
        $writable = $exists && is_writable($abs);
        $detail = $exists ? ($writable ? 'writable' : 'exists, not writable') : 'missing';
        if ($exists) {
            $owner = '';
            if (function_exists('posix_getpwuid') && ($st = @stat($abs))) {
                $pw = posix_getpwuid((int) $st['uid']);
                $owner = $pw['name'] ?? ('uid ' . $st['uid']);
                $detail .= ' · owner ' . $owner . ' · mode ' . substr(sprintf('%o', (int) $st['mode']), -4);
            }
        }
        $rows[] = ['label' => $label, 'ok' => $writable, 'detail' => $detail];
    }
    $sapi = PHP_SAPI;
    $user = function_exists('posix_getpwuid') && function_exists('posix_geteuid')
        ? ((posix_getpwuid(posix_geteuid())['name'] ?? '') ?: ('uid ' . posix_geteuid()))
        : get_current_user();
    $rows[] = ['label' => 'PHP SAPI / user', 'ok' => true, 'detail' => $sapi . ' as ' . $user];
    return $rows;
}

/**
 * Handle universal media picker uploads.
 * Returns a project-relative path, an external URL, or '' when the field was cleared.
 * Returns null when this form did not include the picker.
 */
function handle_media_picker(string $fieldName, string $targetDir): ?string
{
    $uploadField = $fieldName . '_upload';
    $urlField = $fieldName . '_url';
    $allowed = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'avif'];
    $postedUrl = array_key_exists($urlField, $_POST)
        ? normalize_media_path(trim((string) $_POST[$urlField]))
        : null;

    $file = $_FILES[$uploadField] ?? null;
    $hasUpload = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($hasUpload) {
        try {
            $err = (int) $file['error'];
            if ($err !== UPLOAD_ERR_OK) {
                $map = [
                    UPLOAD_ERR_INI_SIZE   => 'The image is larger than the server upload limit (upload_max_filesize).',
                    UPLOAD_ERR_FORM_SIZE  => 'The image is too large.',
                    UPLOAD_ERR_PARTIAL    => 'The image upload was interrupted.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Server temp folder is missing.',
                    UPLOAD_ERR_CANT_WRITE => 'Could not write the uploaded image.',
                ];
                throw new RuntimeException($map[$err] ?? 'Image upload failed.');
            }
            $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                throw new RuntimeException('Unsupported image type. Use PNG, JPG, WebP, GIF, SVG, or AVIF.');
            }
            $usedDir = ensure_writable_dir($targetDir);
            $name = random_token(8) . '.' . $ext;
            $absTargetDir = ROOT_PATH . '/' . $usedDir;
            $targetFile = rtrim($absTargetDir, '/') . '/' . $name;
            $tmp = (string) $file['tmp_name'];
            $moved = is_uploaded_file($tmp) && move_uploaded_file($tmp, $targetFile);
            if (!$moved) {
                throw new RuntimeException('Could not save the uploaded image into ' . $usedDir . '.');
            }
            @chmod($targetFile, 0666);
            return $usedDir . '/' . $name;
        } catch (Throwable $e) {
            // Throw so the caller knows the explicit upload attempt failed
            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage());
        }
    }

    if ($postedUrl === null) {
        return null;
    }

    return $postedUrl;
}
