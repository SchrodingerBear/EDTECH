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
    $common = 'xmlns="http://www.w3.org/2000/svg" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
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
    return ORG_ROOT_URL . '/' . rawurlencode($slug) . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
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

/**
 * Deliver an email through the configured SMTP server, or spool it to
 * storage/mail/ when no SMTP is configured (dev fallback, mirrors mail()).
 */
function send_email(string $to, string $subject, string $bodyHtml): bool
{
    $s = null;
    try {
        $s = db()->query("SELECT * FROM platform_settings WHERE id=1")->fetch() ?: [];
    } catch (Throwable $e) {
        $s = [];
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

    if (!empty($s['smtp_host'])) {
        $sentViaSmtp = smtp_send(
            (string) $s['smtp_host'],
            (int) ($s['smtp_port'] ?? 587),
            (string) ($s['smtp_username'] ?? ''),
            (string) base64_decode((string) ($s['smtp_password_enc'] ?? '')),
            $fromName,
            $fromEmail,
            $to,
            $subject,
            $html
        );
        if ($sentViaSmtp) {
            return true;
        }
        // SMTP unreachable/unconfigured properly — fall through to spooling so
        // credential emails are still captured instead of silently lost.
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
        return file_put_contents($file, $msg) !== false;
    } catch (Throwable $e) {
        return false;
    }
}

/** Minimal SMTP client (EHLO / STARTTLS / AUTH LOGIN / DATA). */
function smtp_send(string $host, int $port, string $user, string $pass, string $fromName, string $fromEmail, string $to, string $subject, string $html): bool
{
    $secure = $port === 465 ? 'ssl://' : '';
    $conn = @stream_socket_client($secure . $host . ':' . $port, $errno, $errstr, 20);
    if (!$conn) {
        return false;
    }

    $read = function () use ($conn) {
        $resp = '';
        while ($line = fgets($conn, 515)) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $resp;
    };
    $good = static fn (string $resp): bool => isset($resp[2]) && $resp[0] === '2';
    $login = static fn (string $resp): bool => isset($resp[2]) && $resp[0] === '3';

    $read(); // banner

    fwrite($conn, "EHLO innovatech.local\r\n");
    if (!$good($read())) { fclose($conn); return false; }

    if ($secure === '' && $port !== 25) {
        fwrite($conn, "STARTTLS\r\n");
        if (!$good($read())) { fclose($conn); return false; }
        if (!@stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($conn); return false; }
        fwrite($conn, "EHLO innovatech.local\r\n");
        if (!$good($read())) { fclose($conn); return false; }
    }

    if ($user !== '') {
        fwrite($conn, "AUTH LOGIN\r\n");
        if (!$login($read())) { fclose($conn); return false; }
        fwrite($conn, base64_encode($user) . "\r\n");
        if (!$login($read())) { fclose($conn); return false; }
        fwrite($conn, base64_encode($pass) . "\r\n");
        if (!$good($read())) { fclose($conn); return false; }
    }

    fwrite($conn, "MAIL FROM:<$fromEmail>\r\n");
    if (!$good($read())) { fclose($conn); return false; }
    fwrite($conn, "RCPT TO:<$to>\r\n");
    if (!$good($read())) { fclose($conn); return false; }

    fwrite($conn, "DATA\r\n");
    if (!$good($read())) { fclose($conn); return false; }

    $headers = "From: $fromName <$fromEmail>\r\nTo: <$to>\r\nSubject: $subject\r\n"
             . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    fwrite($conn, $headers . "\r\n" . $html . "\r\n.\r\n");
    $ok = $good($read());
    fwrite($conn, "QUIT\r\n");
    fclose($conn);
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

    // Basic settings
    $cfg['institution_id'] = $iid;
    $cfg['name']           = $inst['name'];
    $cfg['short_name']     = $inst['short_name'] ?? '';
    $cfg['landing_mode']   = $inst['landing_mode'];
    $cfg['require_landscape_mobile'] = (bool) $inst['require_landscape_mobile'];

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

        $hsData = array_map(function($h) {
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
                $out['to_scene_equirect'] = $h['to_scene_equirect'] ?? '';
                $out['to_scene_yaw']     = (float) ($h['to_scene_yaw'] ?? 0);
                $out['to_scene_pitch']   = (float) ($h['to_scene_pitch'] ?? 0);
            }
            return $out;
        }, $hotspots);

        $cfg['scenes'][(int)$sc['id']] = [
            'id'            => (int) $sc['id'],
            'title'         => $sc['title'],
            'equirect_path' => $sc['equirect_path'] ?? '',
            'initial_yaw'   => (float) ($sc['initial_yaw'] ?? 0),
            'initial_pitch' => (float) ($sc['initial_pitch'] ?? 0),
            'hotspots'      => $hsData
        ];
    }

    // Starting 360 scene (for 360_rotation landing)
    if (!empty($inst['starting_scene_id'])) {
        $scene = crud()->get('tour_scenes', $inst['starting_scene_id']);
        if ($scene) {
            $cfg['starting_scene'] = [
                'id'            => (int) $scene['id'],
                'title'         => $scene['title'],
                'equirect_path' => $scene['equirect_path'] ?? '',
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
                'image_path' => $plan['image_path'],
                'object_fit' => $plan['object_fit'] ?? 'contain',
            ];

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

            $cfg['floor_plan_markers'] = array_map(function($m) {
                $out = [
                    'id'          => (int) $m['id'],
                    'label'       => $m['label'],
                    'x'           => (float) $m['x_percent'],
                    'y'           => (float) $m['y_percent'],
                    'size_percent'=> (float) ($m['size_percent'] ?? 4),
                    'popup_title' => $m['popup_title'] ?? '',
                    'popup_html'  => $m['popup_html'] ?? '',
                ];
                // Navigation target
                if (!empty($m['target_scene_id'])) {
                    $out['target_type']     = 'scene';
                    $out['target_scene_id'] = (int) $m['target_scene_id'];
                    $out['scene_title']     = $m['scene_title'] ?? '';
                    $out['scene_equirect']  = $m['scene_equirect'] ?? '';
                    $out['scene_yaw']       = (float) ($m['scene_yaw'] ?? 0);
                    $out['scene_pitch']     = (float) ($m['scene_pitch'] ?? 0);
                } elseif (!empty($m['target_floor_plan_id'])) {
                    $out['target_type']          = 'floor_plan';
                    $out['target_floor_plan_id'] = (int) $m['target_floor_plan_id'];
                    $out['sub_fp_title']         = $m['sub_fp_title'] ?? '';
                    $out['sub_fp_image']         = $m['sub_fp_image'] ?? '';
                } else {
                    $out['target_type'] = 'popup';
                }
                return $out;
            }, $markers);
        }
    }

    file_put_contents($configPath, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    // Regenerate index.html from template (with name substitutions)
    $tplPath    = ROOT_PATH . '/templates/org_pack/index.html';
    $tplCssPath = ROOT_PATH . '/templates/org_pack/assets/style.css';
    $orgDir     = ROOT_PATH . '/' . ltrim($inst['folder_path'], '/');
    if (is_file($tplPath)) {
        $html = file_get_contents($tplPath);
        $html = str_replace('{{NAME}}', htmlspecialchars($inst['name'], ENT_QUOTES), $html);
        $html = str_replace('{{SHORT}}', htmlspecialchars($inst['short_name'] ?: $inst['name'], ENT_QUOTES), $html);
        file_put_contents($orgDir . '/index.html', $html);
    }
    if (is_file($tplCssPath)) {
        copy($tplCssPath, $orgDir . '/assets/style.css');
    }
}
/**
 * Handle universal media picker uploads.
 * If a file is uploaded, moves it to $targetDir and returns the relative path.
 * If a URL/path is provided, returns it as is.
 * Returns null if neither is provided.
 */
function handle_media_picker(string $fieldName, string $targetDir): ?string
{
    $uploadField = $fieldName . '_upload';
    $urlField = $fieldName . '_url';
    
    // Check if a file was uploaded
    if (!empty($_FILES[$uploadField]['name'])) {
        $ext = strtolower(pathinfo($_FILES[$uploadField]['name'], PATHINFO_EXTENSION)) ?: 'jpg';
        $name = random_token(6) . '.' . $ext;
        
        // Ensure directory exists
        $absTargetDir = str_starts_with($targetDir, '/') ? ROOT_PATH . $targetDir : ROOT_PATH . '/' . $targetDir;
        if (!is_dir($absTargetDir)) {
            mkdir($absTargetDir, 0775, true);
        }
        
        $targetFile = rtrim($absTargetDir, '/') . '/' . $name;
        if (move_uploaded_file($_FILES[$uploadField]['tmp_name'], $targetFile)) {
            return ltrim($targetDir, '/') . '/' . $name;
        }
    }
    
    // Check if a URL/path was provided
    if (!empty($_POST[$urlField])) {
        return trim($_POST[$urlField]);
    }
    
    return null;
}
