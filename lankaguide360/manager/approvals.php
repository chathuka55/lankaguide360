<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Workflow.php';
requireRole('manager');

$db = getDbConnection();

$pending = $db->query(
    "SELECT b.*, g.full_name AS guide_name, v.name AS vehicle_name
     FROM bookings b
     LEFT JOIN guides g ON g.id = b.guide_id
     LEFT JOIN vehicles v ON v.id = b.vehicle_id
     WHERE b.workflow_status = 'manager_review'
     ORDER BY b.updated_at ASC"
)->fetchAll();

$recent = $db->query(
    "SELECT * FROM bookings
     WHERE workflow_status IN ('approved','awaiting_payment','paid','confirmed','rejected')
     ORDER BY updated_at DESC LIMIT 8"
)->fetchAll();

$pageTitle = 'Approvals';
require __DIR__ . '/../includes/panel_top.php';
?>
<div class="mb-4">
  <h3 class="mb-0">Manager approvals</h3>
  <p class="text-muted mb-0">Review dispatcher assignments, adjust pricing and send bookings to payment.</p>
</div>

<div class="admin-card mb-4">
  <h6 class="mb-3">Awaiting your review</h6>
  <table class="table table-lg align-middle">
    <thead><tr><th>Reference</th><th>Customer</th><th>Guide / Vehicle</th><th>Est. total</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pending as $b): ?>
      <tr>
        <td class="fw-semibold"><?= e($b['reference']) ?></td>
        <td><?= e($b['full_name']) ?><div class="small text-muted"><?= (int)$b['party_size'] ?> travellers</div></td>
        <td class="small"><?= e($b['guide_name'] ?: '—') ?><br><?= e($b['vehicle_name'] ?: '—') ?></td>
        <td><?= lkr($b['total_lkr']) ?></td>
        <td class="text-end"><a href="<?= url('manager/pricing.php?id=' . (int)$b['id']) ?>" class="btn btn-sm btn-lg-primary py-1">Review</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$pending): ?><tr><td colspan="5" class="text-muted small">Nothing awaiting review.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="admin-card">
  <h6 class="mb-3">Recently processed</h6>
  <table class="table table-lg align-middle">
    <thead><tr><th>Reference</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $b): ?>
      <tr>
        <td class="fw-semibold"><?= e($b['reference']) ?></td>
        <td><?= e($b['full_name']) ?></td>
        <td><?= lkr($b['total_lkr']) ?></td>
        <td><?= Workflow::badge($b['workflow_status']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$recent): ?><tr><td colspan="4" class="text-muted small">No processed bookings yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/panel_bottom.php'; ?>
