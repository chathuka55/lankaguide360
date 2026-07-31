<?php
$pageTitle = 'Bookings';
require __DIR__ . '/_layout_top.php';
require_once __DIR__ . '/../includes/Workflow.php';

// Allow admin to force a workflow status (override), with audit trail.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck() && isset($_POST['booking_id'], $_POST['workflow_status'])) {
    Workflow::advance($db, (int)$_POST['booking_id'], $_POST['workflow_status'], (int)(currentUser()['id']), 'Status set by admin.');
    header('Location: bookings.php');
    exit;
}

$bookings = $db->query(
    "SELECT b.*, d.name AS destination_name, g.full_name AS guide_name
     FROM bookings b
     LEFT JOIN destinations d ON d.id = b.destination_id
     LEFT JOIN guides g ON g.id = b.guide_id
     ORDER BY b.created_at DESC"
)->fetchAll();
?>
<h3 class="mb-4">Bookings</h3>

<div class="admin-card">
  <table class="table table-lg align-middle">
    <thead><tr><th>Ref</th><th>Customer</th><th>Guide</th><th>Dates</th><th>Total</th><th>Workflow</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($bookings as $b): ?>
      <tr>
        <td class="fw-semibold"><?= e($b['reference'] ?: bookingRef((int)$b['id'])) ?></td>
        <td><?= e($b['full_name']) ?><div class="small text-muted"><?= e($b['email']) ?></div></td>
        <td class="small"><?= e($b['guide_name'] ?: '—') ?></td>
        <td class="small"><?= e($b['travel_date'] ?: '—') ?></td>
        <td class="small"><?= lkr($b['total_lkr']) ?></td>
        <td>
          <form method="post" class="d-flex gap-1">
            <?= csrfField() ?>
            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
            <select name="workflow_status" class="form-select form-select-sm" style="min-width:150px" onchange="this.form.submit()">
              <?php foreach (array_keys(Workflow::STATUSES) as $s): ?>
                <option value="<?= $s ?>" <?= $b['workflow_status']===$s?'selected':'' ?>><?= e(Workflow::label($s)) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td class="text-end"><a href="<?= url('booking-status.php?ref=' . urlencode($b['reference'] ?: bookingRef((int)$b['id']))) ?>" target="_blank" class="btn btn-sm btn-lg-outline py-1">View</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$bookings): ?><tr><td colspan="7" class="text-muted small">No bookings yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
