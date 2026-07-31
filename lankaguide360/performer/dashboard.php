<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Workflow.php';
requireRole('guide', 'driver');

$db = getDbConnection();
$me = currentUser();

// Resolve this performer's resource id (guide or driver row linked to their user).
$resourceId = null;
if ($me['role'] === 'guide') {
    $r = $db->prepare("SELECT id FROM guides WHERE user_id = ?");
    $r->execute([$me['id']]);
    $resourceId = $r->fetchColumn();
    $col = 'guide_id';
} else {
    $r = $db->prepare("SELECT id FROM drivers WHERE user_id = ?");
    $r->execute([$me['id']]);
    $resourceId = $r->fetchColumn();
    $col = 'driver_id';
}

$assignments = [];
if ($resourceId) {
    $stmt = $db->prepare(
        "SELECT b.*, h.name AS hotel_name
         FROM bookings b
         LEFT JOIN hotels h ON h.id = b.hotel_id
         WHERE b.$col = ?
           AND b.workflow_status IN ('assigned','manager_review','approved','awaiting_payment','paid','confirmed','in_progress','completed')
         ORDER BY FIELD(b.workflow_status,'confirmed','paid','in_progress','approved','awaiting_payment','assigned','manager_review','completed'), b.travel_date ASC"
    );
    $stmt->execute([$resourceId]);
    $assignments = $stmt->fetchAll();
}

$pageTitle = 'My assignments';
require __DIR__ . '/../includes/panel_top.php';
?>
<div class="mb-4">
  <h3 class="mb-0">My assignments</h3>
  <p class="text-muted mb-0">Trips assigned to you as a <?= e($me['role']) ?>. Update milestones as the trip progresses.</p>
</div>

<?php if (!$resourceId): ?>
  <div class="admin-card"><p class="text-muted mb-0">Your login isn't linked to a <?= e($me['role']) ?> profile yet. Ask an admin to link it.</p></div>
<?php elseif (!$assignments): ?>
  <div class="admin-card"><p class="text-muted mb-0">No assignments yet.</p></div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($assignments as $b): ?>
      <div class="col-md-6 col-xl-4">
        <div class="admin-card h-100">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="fw-semibold"><?= e($b['reference']) ?></span>
            <?= Workflow::badge($b['workflow_status']) ?>
          </div>
          <div class="mb-1"><?= e($b['full_name']) ?></div>
          <div class="small text-muted mb-1"><i class="bi bi-calendar-event"></i> <?= e($b['travel_date'] ?: 'TBC') ?><?= $b['end_date'] ? ' → ' . e($b['end_date']) : '' ?></div>
          <div class="small text-muted mb-1"><i class="bi bi-people"></i> <?= (int)$b['party_size'] ?> travellers</div>
          <?php if ($b['hotel_name']): ?><div class="small text-muted mb-2"><i class="bi bi-building"></i> <?= e($b['hotel_name']) ?></div><?php endif; ?>
          <a href="<?= url('performer/trip.php?id=' . (int)$b['id']) ?>" class="btn btn-sm btn-lg-primary py-1 w-100">Open trip</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/panel_bottom.php'; ?>
