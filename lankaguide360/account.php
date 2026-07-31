<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/Workflow.php';
requireRole('customer');

$db = getDbConnection();
$user = currentUser();

$stmt = $db->prepare(
    "SELECT b.*, d.name AS dest_name
     FROM bookings b
     LEFT JOIN destinations d ON d.id = b.destination_id
     WHERE b.user_id = ?
     ORDER BY b.created_at DESC"
);
$stmt->execute([$user['id']]);
$bookings = $stmt->fetchAll();

$pageTitle = 'My account';
require __DIR__ . '/includes/header.php';
?>
<section class="section-pad">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
      <div>
        <span class="eyebrow">My account</span>
        <h2 class="h3 mb-0">Ayubowan, <?= e(explode(' ', $user['full_name'])[0]) ?></h2>
        <p class="text-muted mb-0 small"><?= e($user['email']) ?><?= $user['country'] ? ' &middot; ' . e($user['country']) : '' ?></p>
      </div>
      <a href="<?= url('trip-planner.php') ?>" class="btn btn-lg-primary"><i class="bi bi-compass"></i> Plan a new trip</a>
    </div>

    <div class="planner-shell p-4">
      <h5 class="mb-3">My bookings</h5>
      <?php if (!$bookings): ?>
        <p class="text-muted mb-0">You have no bookings yet. <a href="<?= url('trip-planner.php') ?>" class="text-teal">Start planning</a>.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-lg align-middle">
            <thead><tr><th>Reference</th><th>Trip</th><th>Dates</th><th>Party</th><th>Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($bookings as $b): ?>
              <tr>
                <td class="fw-semibold"><?= e($b['reference'] ?: bookingRef((int)$b['id'])) ?></td>
                <td class="small"><?= e($b['dest_name'] ?: 'Custom itinerary') ?></td>
                <td class="small"><?= e($b['travel_date'] ?: '—') ?><?= $b['end_date'] ? ' → ' . e($b['end_date']) : '' ?></td>
                <td class="small"><?= (int)$b['party_size'] ?></td>
                <td class="small"><?= lkr($b['total_lkr']) ?></td>
                <td><?= Workflow::badge($b['workflow_status']) ?></td>
                <td class="text-end">
                  <a href="<?= url('booking-status.php?ref=' . urlencode($b['reference'] ?: bookingRef((int)$b['id']))) ?>" class="btn btn-sm btn-lg-outline py-1">View</a>
                  <?php if ($b['workflow_status'] === 'awaiting_payment'): ?>
                    <a href="<?= url('payment.php?booking=' . (int)$b['id']) ?>" class="btn btn-sm btn-lg-primary py-1">Pay now</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
