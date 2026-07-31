<?php
/**
 * One-off utility: run this on your own PHP server (php -S, XAMPP, etc.)
 * to set working passwords for the seeded demo accounts, or to hash a new one.
 *
 * The database ships with a PLACEHOLDER password hash that will NOT verify.
 * Enter a password below (default demo password: Lanka@123), click Generate,
 * and run the printed SQL in MySQL Workbench to activate every demo login.
 *
 * Delete this file once you're done — it should not ship to production.
 */

// The seeded demo accounts (one password is applied to all of them here).
$demoEmails = [
    'admin@lankaguide360.lk',
    'manager@lankaguide360.lk',
    'dispatcher@lankaguide360.lk',
    'nimal.guide@lankaguide360.lk',
    'kamala.guide@lankaguide360.lk',
    'sunil.driver@lankaguide360.lk',
    'aslam.driver@lankaguide360.lk',
    'customer@example.com',
];

$hash = null;
$password = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_BCRYPT);
    }
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Password Hash Generator — LankaGuide 360</title>
<style>
body{font-family:system-ui;max-width:680px;margin:3rem auto;padding:0 1rem;color:#123}
input{padding:.55rem;width:100%;box-sizing:border-box}
button{padding:.55rem 1.1rem;margin-top:.6rem;background:#1B7F4B;color:#fff;border:0;border-radius:8px;cursor:pointer}
code{background:#f2f4f2;padding:.7rem;display:block;word-break:break-all;margin-top:.6rem;border-radius:6px;white-space:pre-wrap}
h2{color:#0E5A34}
</style>
</head><body>
<h2>Activate demo logins</h2>
<p>Enter a password (the docs assume <strong>Lanka@123</strong>), generate the hash, then run the SQL below in MySQL Workbench. This sets the same password for every seeded account.</p>
<form method="post">
  <input type="text" name="password" value="<?= htmlspecialchars($password ?: 'Lanka@123') ?>" placeholder="Type a password" required>
  <button type="submit">Generate hash + SQL</button>
</form>
<?php if ($hash): ?>
  <p style="margin-top:1.4rem"><strong>Generated bcrypt hash:</strong></p>
  <code><?= htmlspecialchars($hash) ?></code>

  <p style="margin-top:1.4rem"><strong>Run this to activate ALL demo accounts:</strong></p>
  <code>USE lankaguide360;
UPDATE users SET password_hash = '<?= htmlspecialchars($hash) ?>'
WHERE email IN (
  '<?= implode("',\n  '", array_map('htmlspecialchars', $demoEmails)) ?>'
);</code>

  <p style="margin-top:1.4rem"><strong>Or set just the admin:</strong></p>
  <code>UPDATE users SET password_hash = '<?= htmlspecialchars($hash) ?>' WHERE email = 'admin@lankaguide360.lk';</code>
<?php endif; ?>
</body></html>
