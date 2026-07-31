<?php
$pageTitle = 'Reports';
require __DIR__ . '/_layout_top.php';
require_once __DIR__ . '/../includes/Workflow.php';

$totalRevenue = $db->query("SELECT COALESCE(SUM(amount_lkr),0) FROM payments WHERE status='paid'")->fetchColumn();
$bookingCount = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$confirmed    = $db->query("SELECT COUNT(*) FROM bookings WHERE workflow_status IN ('paid','confirmed','in_progress','completed')")->fetchColumn();
$avgValue     = $db->query("SELECT COALESCE(AVG(total_lkr),0) FROM bookings WHERE total_lkr > 0")->fetchColumn();

$byStatus = $db->query("SELECT workflow_status, COUNT(*) c, COALESCE(SUM(total_lkr),0) v FROM bookings GROUP BY workflow_status")->fetchAll();
$topGuides = $db->query(
    "SELECT g.full_name, COUNT(b.id) c FROM bookings b JOIN guides g ON g.id=b.guide_id GROUP BY g.id ORDER BY c DESC LIMIT 5"
)->fetchAll();
$topDest = $db->query(
    "SELECT d.name, COUNT(tpi.id) c FROM trip_plan_items tpi JOIN destinations d ON d.id=tpi.destination_id GROUP BY d.id ORDER BY c DESC LIMIT 5"
)->fetchAll();
$revByRegion = $db->query(
    "SELECT h.region, COALESCE(SUM(p.amount_lkr),0) v FROM payments p JOIN bookings b ON b.id=p.booking_id
     JOIN hotels h ON h.id=b.hotel_id WHERE p.status='paid' GROUP BY h.region ORDER BY v DESC"
)->fetchAll();
?>
<h3 class="mb-4">Reports &amp; analytics</h3>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="admin-card"><div class="eyebrow">Revenue (paid)</div><div class="fs-4 fw-semibold"><?= lkr($totalRevenue) ?></div></div></div>
  <div class="col-md-3"><div class="admin-card"><div class="eyebrow">Total bookings</div><div class="fs-4 fw-semibold"><?= (int)$bookingCount ?></div></div></div>
  <div class="col-md-3"><div class="admin-card"><div class="eyebrow">Confirmed+</div><div class="fs-4 fw-semibold"><?= (int)$confirmed ?></div></div></div>
  <div class="col-md-3"><div class="admin-card"><div class="eyebrow">Avg booking value</div><div class="fs-4 fw-semibold"><?= lkr($avgValue) ?></div></div></div>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="admin-card">
      <h6 class="mb-3">Bookings by status</h6>
      <table class="table table-sm align-middle small">
        <thead><tr><th>Status</th><th>Count</th><th class="text-end">Value</th></tr></thead>
        <tbody>
        <?php foreach ($byStatus as $s): ?>
          <tr><td><?= Workflow::badge($s['workflow_status']) ?></td><td><?= (int)$s['c'] ?></td><td class="text-end"><?= lkr($s['v']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="admin-card">
      <h6 class="mb-3">Revenue by region</h6>
      <?php if (!$revByRegion): ?><p class="text-muted small mb-0">No paid bookings yet.</p><?php endif; ?>
      <?php foreach ($revByRegion as $r): ?>
        <div class="d-flex justify-content-between border-bottom py-2 small"><span><?= e($r['region']) ?></span><span class="fw-semibold"><?= lkr($r['v']) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="admin-card">
      <h6 class="mb-3">Top guides (by bookings)</h6>
      <?php if (!$topGuides): ?><p class="text-muted small mb-0">No assignments yet.</p><?php endif; ?>
      <?php foreach ($topGuides as $g): ?>
        <div class="d-flex justify-content-between border-bottom py-2 small"><span><?= e($g['full_name']) ?></span><span class="text-muted"><?= (int)$g['c'] ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="admin-card">
      <h6 class="mb-3">Most-recommended destinations</h6>
      <?php if (!$topDest): ?><p class="text-muted small mb-0">No plans yet.</p><?php endif; ?>
      <?php foreach ($topDest as $d): ?>
        <div class="d-flex justify-content-between border-bottom py-2 small"><span><?= e($d['name']) ?></span><span class="text-muted"><?= (int)$d['c'] ?> picks</span></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
