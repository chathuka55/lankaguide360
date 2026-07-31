<?php
/**
 * fetch_images.php — Populate photo columns using free, key-free image APIs.
 *
 * • destinations / hotels / vehicles  → real photos from Wikimedia Commons
 *   (searches like "Ella Sri Lanka" and stores the returned thumbnail URL).
 * • guides / drivers / users          → friendly DiceBear avatars (deterministic).
 *
 * Wikimedia is used because it needs no API key and its images are freely
 * licensed. Rows that already have an image are skipped unless --force is given.
 *
 * Run:   php tools/fetch_images.php            (from the project root)
 *        php tools/fetch_images.php --force    (re-fetch everything)
 *
 * Safe to re-run; it only UPDATEs image/photo columns.
 */

require_once __DIR__ . '/../config/db.php';

$force = in_array('--force', $argv ?? [], true);
$db = getDbConnection();

$cli = (php_sapi_name() === 'cli');
$nl  = $cli ? "\n" : "<br>\n";
function out(string $s) { global $nl; echo $s . $nl; @ob_flush(); @flush(); }

/** Query Wikimedia Commons for the first matching image thumbnail URL. */
function wikimediaImage(string $query, int $width = 1200): ?string
{
    $endpoint = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
        'action'      => 'query',
        'generator'   => 'search',
        'gsrnamespace'=> 6,            // File namespace
        'gsrsearch'   => $query,
        'gsrlimit'    => 5,
        'prop'        => 'imageinfo',
        'iiprop'      => 'url|mime',
        'iiurlwidth'  => $width,
        'format'      => 'json',
    ]);

    $json = httpGet($endpoint);
    if ($json === null) return null;

    $data = json_decode($json, true);
    $pages = $data['query']['pages'] ?? [];
    foreach ($pages as $page) {
        $info = $page['imageinfo'][0] ?? null;
        if (!$info) continue;
        $mime = $info['mime'] ?? '';
        // skip SVG / audio / pdf — we want photos
        if (str_contains($mime, 'svg') || !str_starts_with($mime, 'image/')) continue;
        return $info['thumburl'] ?? $info['url'] ?? null;
    }
    return null;
}

/** Minimal HTTP GET with a proper User-Agent (Wikimedia requires one). */
function httpGet(string $url): ?string
{
    $ua = 'LankaGuide360/1.0 (educational project; contact: student@example.com)';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($res !== false && $code === 200) ? $res : null;
    }

    $ctx = stream_context_create(['http' => [
        'header'  => "User-Agent: {$ua}\r\n",
        'timeout' => 20,
    ], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $res = @file_get_contents($url, false, $ctx);
    return $res !== false ? $res : null;
}

/** DiceBear avatar URL (key-free, deterministic). */
function dicebear(string $seed, string $style = 'initials'): string
{
    return "https://api.dicebear.com/7.x/{$style}/svg?seed=" . urlencode($seed)
         . '&backgroundType=gradientLinear&radius=50';
}

// ---- destinations / hotels / vehicles: real Wikimedia photos ----
// Each entry returns an ordered list of candidate search queries; the first
// one that returns a photo wins, so we can gracefully fall back to broader terms.
$photoTables = [
    'destinations' => fn($r) => [
        $r['name'] . ' Sri Lanka',
        $r['district'] . ' Sri Lanka',
        $r['region'] . ' Sri Lanka landscape',
    ],
    'hotels' => fn($r) => [
        $r['name'] . ' Sri Lanka',
        'hotel ' . $r['district'] . ' Sri Lanka',
        'resort ' . $r['region'] . ' Sri Lanka',
        'Sri Lanka luxury resort',
    ],
    'vehicles' => fn($r) => [
        $r['name'] . ' ' . $r['type'],
        $r['name'],
        ucfirst($r['type']) . ' vehicle',
    ],
];

foreach ($photoTables as $table => $queryFn) {
    $rows = $db->query("SELECT * FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
    $col  = 'image_url';
    out("== {$table} (" . count($rows) . " rows) ==");
    foreach ($rows as $r) {
        if (!$force && !empty($r[$col])) { out("  · skip #{$r['id']} {$r['name']} (has image)"); continue; }
        $url = null; $used = '';
        foreach ($queryFn($r) as $q) {
            $q = trim($q);
            if ($q === '') continue;
            $url = wikimediaImage($q);
            usleep(300000); // be polite to the API
            if ($url) { $used = $q; break; }
        }
        if ($url) {
            $db->prepare("UPDATE {$table} SET {$col}=? WHERE id=?")->execute([$url, $r['id']]);
            out("  ✓ #{$r['id']} {$r['name']}  ←  {$used}");
        } else {
            out("  ✗ #{$r['id']} {$r['name']}  (no photo found)");
        }
    }
}

// ---- guides / drivers: deterministic avatars ----
$avatarTables = [
    'guides'  => ['col' => 'photo_url', 'style' => 'avataaars'],
    'drivers' => ['col' => 'photo_url', 'style' => 'avataaars'],
];
foreach ($avatarTables as $table => $cfg) {
    $rows = $db->query("SELECT id, full_name, {$cfg['col']} FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
    out("== {$table} avatars (" . count($rows) . " rows) ==");
    foreach ($rows as $r) {
        if (!$force && !empty($r[$cfg['col']])) continue;
        $seed = $r['full_name'] ?: ($table . $r['id']);
        $url = dicebear($seed, $cfg['style']);
        $db->prepare("UPDATE {$table} SET {$cfg['col']}=? WHERE id=?")->execute([$url, $r['id']]);
    }
    out("  ✓ avatars set");
}

out("Done.");
