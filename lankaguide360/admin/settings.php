<?php
$pageTitle = 'Settings';
require __DIR__ . '/_layout_top.php';

$counts = [
    'Destinations' => $db->query("SELECT COUNT(*) FROM destinations")->fetchColumn(),
    'Guides'       => $db->query("SELECT COUNT(*) FROM guides")->fetchColumn(),
    'Drivers'      => $db->query("SELECT COUNT(*) FROM drivers")->fetchColumn(),
    'Vehicles'     => $db->query("SELECT COUNT(*) FROM vehicles")->fetchColumn(),
    'Hotels'       => $db->query("SELECT COUNT(*) FROM hotels")->fetchColumn(),
    'Users'        => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
];
?>
<h3 class="mb-4">System settings</h3>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="admin-card">
      <h6 class="mb-3">Application</h6>
      <table class="table table-sm small">
        <tbody>
          <tr><td>App name</td><td class="text-end"><?= e(APP_NAME) ?></td></tr>
          <tr><td>Base URL</td><td class="text-end"><code><?= e(BASE_URL ?: '/') ?></code></td></tr>
          <tr><td>Database</td><td class="text-end"><code><?= e(DB_NAME) ?></code> @ <code><?= e(DB_HOST) ?></code></td></tr>
          <tr><td>Currency</td><td class="text-end">LKR (Sri Lankan Rupee)</td></tr>
          <tr><td>PHP version</td><td class="text-end"><?= e(PHP_VERSION) ?></td></tr>
        </tbody>
      </table>
      <p class="small text-muted mb-0">Configuration lives in <code>config/db.php</code> and <code>config/app.php</code> (overridable with environment variables for Docker/Kubernetes).</p>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="admin-card">
      <h6 class="mb-3">Content overview</h6>
      <?php foreach ($counts as $label => $n): ?>
        <div class="d-flex justify-content-between border-bottom py-2 small"><span><?= e($label) ?></span><span class="fw-semibold"><?= (int)$n ?></span></div>
      <?php endforeach; ?>
    </div>
    <div class="admin-card mt-3">
      <h6 class="mb-2">Security reminder</h6>
      <p class="small text-muted mb-0">Delete <code>tools/generate-password-hash.php</code> after activating demo logins, and replace the demo credentials in <code>k8s/secret.yaml</code> before any real deployment.</p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
