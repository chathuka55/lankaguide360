<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Workflow.php';
requireRole('dispatcher');

$db = getDbConnection();
$me = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck() && ($_POST['action'] ?? '') === 'pickup') {
    $bid = (int)$_POST['booking_id'];
    $db->prepare("UPDATE bookings SET dispatcher_id = ? WHERE id = ?")->execute([$me['id'], $bid]);
    Workflow::advance($db, $bid, 'dispatching', (int)$me['id'], 'Dispatcher picked up the request.');
    header('Location: ' . url('dispatcher/assign.php?id=' . $bid));
    exit;
}

$open = $db->query(
    "SELECT b.*, tp.duration_days, tp.budget_tier
     FROM bookings b LEFT JOIN trip_plans tp ON tp.id = b.trip_plan_id
     WHERE b.workflow_status IN ('submitted','dispatching','assigned')
     ORDER BY FIELD(b.workflow_status,'submitted','dispatching','assigned'), b.created_at ASC"
)->fetchAll();

$pageTitle = 'Request queue';
require __DIR__ . '/../includes/panel_top.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="mb-0">Booking request queue</h3>
    <p class="text-muted mb-0">Assign guides, drivers and vehicles, then forward to a manager.</p>
  </div>
</div>

<div class="admin-card">
  <table class="table table-lg align-middle">
    <thead><tr><th>Reference</th><th>Customer</th><th>Trip</th><th>Dates</th><th>Party</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($open as $b): ?>
      <tr>
        <td class="fw-semibold"><?= e($b['reference']) ?></td>
        <td><?= e($b['full_name']) ?><div class="small text-muted"><?= e($b['email']) ?></div></td>
        <td class="small"><?= (int)($b['duration_days'] ?? 0) ?> days &middot; <?= e(ucfirst($b['budget_tier'] ?? '—')) ?></td>
        <td class="small"><?= e($b['travel_date'] ?: 'TBC') ?></td>
        <td class="small"><?= (int)$b['party_size'] ?></td>
        <td><?= Workflow::badge($b['workflow_status']) ?></td>
        <td class="text-end">
          <?php if ($b['workflow_status'] === 'submitted'): ?>
            <form method="post" class="d-inline">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="pickup">
              <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
              <button class="btn btn-sm btn-lg-primary py-1"><i class="bi bi-hand-index"></i> Pick up</button>
            </form>
          <?php else: ?>
            <a href="<?= url('dispatcher/assign.php?id=' . (int)$b['id']) ?>" class="btn btn-sm btn-lg-outline py-1">Open</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$open): ?><tr><td colspan="7" class="text-muted small">No open requests. Great work!</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/panel_bottom.php'; ?>
