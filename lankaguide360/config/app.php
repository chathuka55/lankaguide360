<?php
/**
 * LankaGuide 360 — App Bootstrap
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

define('APP_NAME', 'LankaGuide 360');

/**
 * URL prefix when the app lives in a subfolder (e.g. C:\xampp\htdocs\lankaguide360).
 * Auto-detected from DOCUMENT_ROOT; override with BASE_URL env var if needed.
 */
function lgDetectBaseUrl(): string
{
    $override = getenv('BASE_URL');
    if ($override !== false && $override !== '') {
        return rtrim(str_replace('\\', '/', $override), '/');
    }

    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $appRoot = realpath(dirname(__DIR__));

    if ($docRoot && $appRoot) {
        $docRoot = str_replace('\\', '/', $docRoot);
        $appRoot = str_replace('\\', '/', $appRoot);
        if (str_starts_with($appRoot, $docRoot)) {
            $base = substr($appRoot, strlen($docRoot));
            return rtrim($base, '/') ?: '';
        }
    }

    return '';
}

if (!defined('BASE_URL')) {
    define('BASE_URL', lgDetectBaseUrl());
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function asset(string $path): string
{
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function isAdminLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']);
}

function requireAdmin(): void
{
    if (!isAdminLoggedIn()) {
        header('Location: ' . url('admin/login.php'));
        exit;
    }
}

function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

/** Format an amount as Sri Lankan Rupees, e.g. lkr(185000) => "LKR 185,000". */
function lkr($amount): string
{
    return 'LKR ' . number_format((float)$amount, 0);
}

/** Human-friendly booking reference, e.g. LG360-000123 */
function bookingRef(int $id): string
{
    return 'LG360-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
}

/** CSRF token helpers (used by state-changing forms). */
function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

function csrfCheck(): bool
{
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
}

/**
 * Resolve a stored image reference to a usable <img src>.
 * Accepts absolute URLs (http/https), root-relative paths, or a bare
 * filename living under /assets/img. Returns null when empty.
 */
function img_src(?string $ref): ?string
{
    $ref = trim((string)$ref);
    if ($ref === '') return null;
    if (preg_match('#^(https?:)?//#i', $ref) || $ref[0] === '/') return $ref;
    return asset('img/' . ltrim($ref, '/'));
}

/**
 * Deterministic, key-free avatar for a person (guide, driver, user).
 * Uses DiceBear so every profile has a friendly picture even without an
 * uploaded photo. Pass an explicit $photo to override with a real image.
 */
function avatar(?string $photo, string $seed, string $style = 'initials'): string
{
    $real = img_src($photo);
    if ($real) return $real;
    $seed = urlencode($seed !== '' ? $seed : 'Traveller');
    return "https://api.dicebear.com/7.x/{$style}/svg?seed={$seed}&backgroundType=gradientLinear&radius=50";
}

// Load authentication + role helpers (currentUser, requireRole, etc.)
require_once __DIR__ . '/auth.php';
