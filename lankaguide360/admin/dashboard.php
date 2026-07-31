<?php
$pageTitle = 'Dashboard';
require __DIR__ . '/_layout_top.php';

$destCount = $db->query("SELECT COUNT(*) FROM destinations WHERE is_active=1")->fetchColumn();
$planCount = $db->query("SELECT COUNT(*) FROM trip_plans")->fetchColumn();
$bookingCount = $db->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();
$chatCount = $db->query("SELECT COUNT(*) FROM chatbot_logs")->fetchColumn();

$recentBookings = $db->query("SELECT * FROM bookings ORDER BY created_at DESC LIMIT 5")->fetchAll();
$topDestinations = $db->query(
    "SELECT d.name, COUNT(tpi.id) AS picks FROM trip_plan_items tpi
     JOIN destinations d ON d.id = tpi.destination_id
     GROUP BY d.id ORDER BY picks DESC LIMIT 5"
)->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="mb-0">Welcome back, <?= e($_SESSION['admin_name'] ?? 'Admin') ?></h3>
    <p class="text-muted mb-0">Here's how LankaGuide 360 is doing.</p>
  </div>
  <a href="destination-edit.php" class="btn btn-lg-primary"><i class="bi bi-plus-lg"></i> Add destination</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="admin-card"><div class="eyebrow">Active destinations</div><div class="fs-3 fw-semibold"><?= (int)$destCount ?></div></div></div>
  <div class="col-md-3"><div class="admin-card"><div class="eyebrow">Trip plans generated</div><div class="fs-3 fw-semibold"><?= (int)$planCount ?></div></div></div>
  <div class="col-md-3"><div class="admin-card"><div class="eyebrow">Pending bookings</div><div class="fs-3 fw-semibold"><?= (int)$bookingCount ?></div></div></div>
  <div class="col-md-3"><div class="admin-card"><div class="eyebrow">Chatbot conversations</div><div class="fs-3 fw-semibold"><?= (int)$chatCount ?></div></div></div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="admin-card">
      <h6 class="mb-3">Recent booking requests</h6>
      <table class="table table-lg align-middle">
        <thead><tr><th>Name</th><th>Email</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recentBookings as $b): ?>
          <tr>
            <td><?= e($b['full_name']) ?></td>
            <td class="small text-muted"><?= e($b['email']) ?></td>
            <td class="small"><?= e($b['travel_date'] ?: '—') ?></td>
            <td><span class="badge text-bg-<?= $b['status']==='pending'?'warning':($b['status']==='confirmed'?'success':'secondary') ?>"><?= e($b['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recentBookings): ?><tr><td colspan="4" class="text-muted small">No booking requests yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
      <a href="bookings.php" class="small">View all bookings &rarr;</a>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="admin-card">
      <h6 class="mb-3">Most-recommended destinations</h6>
      <?php foreach ($topDestinations as $t): ?>
        <div class="d-flex justify-content-between border-bottom py-2 small">
          <span><?= e($t['name']) ?></span><span class="text-muted"><?= (int)$t['picks'] ?> picks</span>
        </div>
      <?php endforeach; ?>
      <?php if (!$topDestinations): ?><p class="text-muted small mb-0">No trip plans generated yet.</p><?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
