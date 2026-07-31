<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Workflow.php';
requireRole('manager');

$db = getDbConnection();
$me = currentUser();
$id = (int)($_GET['id'] ?? $_POST['booking_id'] ?? 0);

$stmt = $db->prepare(
    "SELECT b.*, g.full_name AS guide_name, g.daily_rate_lkr AS guide_rate,
            dr.full_name AS driver_name, dr.daily_rate_lkr AS driver_rate,
            v.name AS vehicle_name, v.rate_per_day_lkr AS vehicle_rate,
            h.name AS hotel_name, h.price_per_night_lkr AS hotel_rate,
            tp.duration_days
     FROM bookings b
     LEFT JOIN guides g   ON g.id = b.guide_id
     LEFT JOIN drivers dr ON dr.id = b.driver_id
     LEFT JOIN vehicles v ON v.id = b.vehicle_id
     LEFT JOIN hotels h   ON h.id = b.hotel_id
     LEFT JOIN trip_plans tp ON tp.id = b.trip_plan_id
     WHERE b.id = ?"
);
$stmt->execute([$id]);
$b = $stmt->fetch();

if (!$b) {
    require __DIR__ . '/../includes/panel_top.php';
    echo '<div class="admin-card">Booking not found.</div>';
    require __DIR__ . '/../includes/panel_bottom.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $action = $_POST['action'] ?? '';
    $base = max(0, (float)($_POST['base_price_lkr'] ?? $b['base_price_lkr']));
    $discount = max(0, (float)($_POST['discount_lkr'] ?? 0));
    $total = max(0, $base - $discount);
    $note = trim($_POST['note'] ?? '');

    $db->prepare("UPDATE bookings SET base_price_lkr=?, discount_lkr=?, total_lkr=?, manager_id=? WHERE id=?")
       ->execute([$base, $discount, $total, $me['id'], $id]);

    if ($action === 'approve') {
        Workflow::advance($db, $id, 'awaiting_payment', (int)$me['id'],
            trim('Approved. ' . ($discount > 0 ? 'Discount ' . lkr($discount) . ' applied. ' : '') . $note));
    } elseif ($action === 'reject') {
        Workflow::advance($db, $id, 'rejected', (int)$me['id'], $note ?: 'Rejected by manager.');
    }
    header('Location: ' . url('manager/approvals.php'));
    exit;
}

$pageTitle = 'Review & price';
require __DIR__ . '/../includes/panel_top.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="mb-0">Review — <?= e($b['reference']) ?></h3>
    <p class="text-muted mb-0"><?= e($b['full_name']) ?> &middot; <?= (int)$b['party_size'] ?> travellers &middot; <?= (int)($b['duration_days'] ?? 0) ?> days</p>
  </div>
  <?= Workflow::badge($b['workflow_status']) ?>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="admin-card">
      <h6 class="mb-3">Assigned resources</h6>
      <table class="table table-sm align-middle small">
        <tbody>
          <tr><td><i class="bi bi-person-badge text-amber"></i> Guide</td><td><?= e($b['guide_name'] ?: '—') ?></td><td class="text-end"><?= $b['guide_rate'] ? lkr($b['guide_rate']).'/day' : '' ?></td></tr>
          <tr><td><i class="bi bi-person-workspace text-amber"></i> Driver</td><td><?= e($b['driver_name'] ?: '—') ?></td><td class="text-end"><?= $b['driver_rate'] ? lkr($b['driver_rate']).'/day' : '' ?></td></tr>
          <tr><td><i class="bi bi-truck-front text-amber"></i> Vehicle</td><td><?= e($b['vehicle_name'] ?: '—') ?></td><td class="text-end"><?= $b['vehicle_rate'] ? lkr($b['vehicle_rate']).'/day' : '' ?></td></tr>
          <tr><td><i class="bi bi-building text-amber"></i> Hotel</td><td><?= e($b['hotel_name'] ?: '—') ?></td><td class="text-end"><?= $b['hotel_rate'] ? lkr($b['hotel_rate']).'/night' : '' ?></td></tr>
        </tbody>
      </table>
      <p class="small text-muted mb-0"><strong>Customer notes:</strong> <?= e($b['notes'] ?: '—') ?></p>
    </div>
  </div>

  <div class="col-lg-5">
    <form method="post" class="admin-card">
      <?= csrfField() ?>
      <input type="hidden" name="booking_id" value="<?= (int)$id ?>">
      <h6 class="mb-3">Pricing (LKR)</h6>
      <div class="mb-3">
        <label class="form-label small text-muted">Base price</label>
        <input type="number" step="0.01" name="base_price_lkr" class="form-control" value="<?= e(number_format((float)$b['base_price_lkr'],2,'.','')) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label small text-muted">Discount</label>
        <input type="number" step="0.01" name="discount_lkr" class="form-control" value="<?= e(number_format((float)$b['discount_lkr'],2,'.','')) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label small text-muted">Note to customer (optional)</label>
        <textarea name="note" rows="2" class="form-control"></textarea>
      </div>
      <div class="d-flex gap-2">
        <button name="action" value="approve" class="btn btn-lg-primary flex-grow-1"><i class="bi bi-check2-circle"></i> Approve &amp; send to payment</button>
      </div>
      <button name="action" value="reject" class="btn btn-link text-danger w-100 mt-2"><i class="bi bi-x-circle"></i> Reject booking</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/panel_bottom.php'; ?>
