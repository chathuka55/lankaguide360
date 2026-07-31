<?php
/**
 * LankaGuide 360 — Authentication & role helpers.
 * Works against the unified `users` table (customer/guide/driver/dispatcher/
 * manager/admin). Include via config/app.php (auto-loaded).
 */

require_once __DIR__ . '/app.php';

/** Return the currently logged-in user row, or null. */
function currentUser(): ?array
{
    static $cache = false;
    if (empty($_SESSION['uid'])) {
        return null;
    }
    if ($cache !== false && $cache && (int)$cache['id'] === (int)$_SESSION['uid']) {
        return $cache;
    }
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['uid']]);
    $user = $stmt->fetch() ?: null;
    if (!$user) {
        unset($_SESSION['uid']);
    }
    $cache = $user;
    return $user;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

/** True if the current user holds any of the given roles. */
function hasRole(string ...$roles): bool
{
    $u = currentUser();
    return $u !== null && in_array($u['role'], $roles, true);
}

/** Attempt a login. Returns the user row on success, null on failure. */
function loginUser(string $email, string $password): ?array
{
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([trim($email)]);
    $u = $stmt->fetch();
    if ($u && !empty($u['password_hash']) && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        $_SESSION['user_role'] = $u['role'];
        $_SESSION['user_name'] = $u['full_name'];
        // Back-compat with the existing admin console (requireAdmin()).
        if ($u['role'] === 'admin') {
            $_SESSION['admin_id'] = (int)$u['id'];
            $_SESSION['admin_name'] = $u['full_name'];
        }
        return $u;
    }
    return null;
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Register a new customer. Returns [userId, error]. */
function registerCustomer(string $name, string $email, string $password, ?string $phone = null, ?string $country = null): array
{
    $db = getDbConnection();
    $email = trim($email);
    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        return [null, 'That email is already registered. Try signing in instead.'];
    }
    $stmt = $db->prepare(
        "INSERT INTO users (full_name, email, password_hash, phone, country, role, is_guest)
         VALUES (?,?,?,?,?, 'customer', 0)"
    );
    $stmt->execute([
        trim($name), $email, password_hash($password, PASSWORD_BCRYPT),
        $phone ?: null, $country ?: null,
    ]);
    return [(int)$db->lastInsertId(), null];
}

/**
 * Find-or-create a lightweight guest user (no password). Used by the guest
 * checkout so a guest only fills their details once.
 */
function findOrCreateGuest(string $name, string $email, ?string $phone = null, ?string $country = null): int
{
    $db = getDbConnection();
    $email = trim($email);
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if ($row) {
        return (int)$row['id'];
    }
    $ins = $db->prepare(
        "INSERT INTO users (full_name, email, phone, country, role, is_guest)
         VALUES (?,?,?,?, 'customer', 1)"
    );
    $ins->execute([trim($name), $email, $phone ?: null, $country ?: null]);
    return (int)$db->lastInsertId();
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . url('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '')));
        exit;
    }
}

/** Gate a page to specific roles; redirects to login otherwise. */
function requireRole(string ...$roles): void
{
    if (!hasRole(...$roles)) {
        header('Location: ' . url('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '')));
        exit;
    }
}

/** The landing page for a given role after login. */
function dashboardFor(string $role): string
{
    switch ($role) {
        case 'admin':      return url('admin/dashboard.php');
        case 'manager':    return url('manager/approvals.php');
        case 'dispatcher': return url('dispatcher/queue.php');
        case 'guide':
        case 'driver':     return url('performer/dashboard.php');
        default:           return url('account.php');
    }
}
