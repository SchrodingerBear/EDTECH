<?php
/**
 * Innovatech PH — authentication & authorization middleware.
 * Roles are kept separate via session; the sidebar is rendered per role.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

session_start_secure();

/** Return the logged-in user row (from session cache) or null. */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Role slug of the current user (nullable). */
function current_role(): ?string
{
    return $_SESSION['user']['role_slug'] ?? null;
}

/** Landing page path for a given role slug. */
function role_home(string $role): string
{
    return match ($role) {
        'owner' => 'admin/owner/dashboard',
        'system_admin', 'system_staff' => 'admin/system/dashboard',
        'admin' => 'admin/institution/dashboard',
        'staff' => 'admin/staff/dashboard',
        default => '',
    };
}

/** Institution of current user (nullable for owner/super). */
function current_institution(): ?array
{
    return $_SESSION['user']['institution'] ?? null;
}

/** Require any login. Redirects to the app login. */
function require_login(): void
{
    if (!current_user()) {
        redirect('admin/index');
    }
}

/** Require one of the given role slugs. */
function require_role(string ...$allowed): void
{
    require_login();
    $role = current_role();
    if (!in_array($role, $allowed, true)) {
        http_response_code(403);
        require ROOT_PATH . '/admin/errors/403.php';
        exit;
    }
}

/** Convenience guards. */
function require_owner(): void { require_role('owner'); }
function require_system_admin(): void { require_role('owner', 'system_admin'); }
function require_system_staff(): void { require_role('owner', 'system_admin', 'system_staff'); }
function require_admin(): void { require_role('owner', 'system_admin', 'system_staff', 'admin'); }
function require_admin_staff(): void { require_role('owner', 'system_admin', 'system_staff', 'admin', 'staff'); }

/**
 * Resolve the institution a page should operate on.
 * Owner / system roles may pick any institution via ?inst= (persisted in session);
 * org admin & staff always use their own assigned institution.
 */
function resolve_active_institution(): ?array
{
    $u = current_user();
    if (!$u) {
        return null;
    }
    $role = $u['role_slug'] ?? '';

    // Org admin/staff → their own institution, always.
    if (!in_array($role, ['owner', 'system_admin', 'system_staff'], true)) {
        return $u['institution'] ?? null;
    }

    $sys = db();
    if (isset($_GET['inst'])) {
        $id = (int) $_GET['inst'];
        if ($id > 0) {
            $stmt = $sys->prepare(
                "SELECT id, slug, name, short_name, institution_type, logo_path, cover_image_path,
                        landing_mode, folder_path, is_published, starting_scene_id, starting_floor_plan_id
                 FROM institutions WHERE id = :id AND deleted_at IS NULL"
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if ($row) {
                $_SESSION['_active_inst_id'] = $id;
                return $row;
            }
        }
    }
    if (!empty($_SESSION['_active_inst_id'])) {
        $stmt = $sys->prepare("SELECT id, slug, name, short_name, institution_type, logo_path, cover_image_path,
                                      landing_mode, folder_path, is_published, starting_scene_id, starting_floor_plan_id
                               FROM institutions WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => (int) $_SESSION['_active_inst_id']]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
    }
    return $u['institution'] ?? null;
}

/** True if current user may access the given role-management page key (null set = all). */
function can_page(string $key): bool
{
    $acc = $_SESSION['user']['page_access'] ?? null;
    return $acc === null || in_array($key, $acc, true);
}

/** Like require_role but for owner-controlled page access lists. */
function require_page(string ...$keys): void
{
    require_login();
    $ok = false;
    foreach ($keys as $k) {
        if (can_page($k)) {
            $ok = true;
            break;
        }
    }
    if (!$ok) {
        http_response_code(403);
        require ROOT_PATH . '/admin/errors/403.php';
        exit;
    }
}

/** True if current user holds the permission slug. */
function can(string $permissionSlug): bool
{
    $perms = $_SESSION['user']['permissions'] ?? [];
    return in_array($permissionSlug, $perms, true);
}

/** Log a user in: loads fresh data incl. permissions + institution. */
function do_login(PDO $pdo, int $userId): void
{
    $sql = "SELECT u.*, r.slug AS role_slug, r.name AS role_name
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.id = :id AND u.deleted_at IS NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1) {
        return;
    }

    // permissions for this role
    $pstmt = $pdo->prepare(
        "SELECT p.slug FROM permissions p
         JOIN role_permissions rp ON rp.permission_id = p.id
         WHERE rp.role_id = :rid"
    );
    $pstmt->execute(['rid' => $user['role_id']]);
    $perms = array_column($pstmt->fetchAll(), 'slug');

    // per-account page access (owner-controlled; null = unrestricted)
    $astmt = $pdo->prepare("SELECT page_key FROM user_page_access WHERE user_id = :id");
    $astmt->execute(['id' => $userId]);
    $pageAccess = array_column($astmt->fetchAll(), 'page_key');

    unset($_SESSION['_active_inst_id']);

    // institution (if any)
    $institution = null;
    if (!empty($user['institution_id'])) {
        $istmt = $pdo->prepare(
            "SELECT id, slug, name, short_name, institution_type, logo_path, cover_image_path,
                    landing_mode, folder_path, is_published, starting_scene_id, starting_floor_plan_id
             FROM institutions WHERE id = :id AND deleted_at IS NULL"
        );
        $istmt->execute(['id' => $user['institution_id']]);
        $institution = $istmt->fetch() ?: null;
    }

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'role_id' => (int) $user['role_id'],
        'role_slug' => $user['role_slug'],
        'role_name' => $user['role_name'],
        'email' => $user['email'],
        'username' => $user['username'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'avatar_path' => $user['avatar_path'],
        'institution_id' => $user['institution_id'] ? (int) $user['institution_id'] : null,
        'institution' => $institution,
        'permissions' => $perms,
        'page_access' => $pageAccess ?: null,
    ];

    // touch last_login + rotate session id
    $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id")->execute(['id' => $userId]);
    session_regenerate_id(true);

    // audit
    try {
        $pdo->prepare(
            "INSERT INTO audit_logs (actor_user_id, institution_id, action, module, ip_address, user_agent)
             VALUES (:uid, :iid, 'auth.login', 'auth', :ip, :ua)"
        )->execute([
            'uid' => (int) $user['id'],
            'iid' => $user['institution_id'] ?: null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (Throwable $e) {
        // audit must never break login
    }
}

/** Destroy the session. */
function do_logout(): void
{
    session_start_secure();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}