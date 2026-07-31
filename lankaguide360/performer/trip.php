<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Workflow.php';
requireRole('guide', 'driver');

$db = getDbConnection();
$me = currentUser();
$id = (int)($_GET['id'] ?? $_POST['booking_id'] ?? 0);

$stmt = $db->prepare(
    "SELECT b.*, h.name AS hotel_name, v.name AS vehicle_name
     FROM bookings b
     LEFT JOIN hotels h ON h.id = b.hotel_id
     LEFT JOIN vehicles v ON v.id = b.vehicle_id
     WHERE b.id = ?"
);
$stmt->execute([$id]);
$b = $stmt->fetch();

$flash = null;
if ($b && $_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $milestone = $_POST['milestone'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $valid = ['accepted','declined','started','picked_up','arrived','completed'];

    if (in_array($milestone, $valid, true)) {
        // Optional photo upload.
        $photoUrl = null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','gif'];
            if (in_array($ext, $allowed, true) && $_FILES['photo']['size'] <= 12 * 1024 * 1024) {
                $dir = __DIR__ . '/../assets/uploads';
                if (!is_dir($dir)) @mkdir($dir, 0775, true);
                $fname = 'trip' . $id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir . '/' . $fname)) {
                    $photoUrl = 'assets/uploads/' . $fname;
                }
            }
        }

        $db->prepare(
            "INSERT INTO trip_milestones (booking_id, performer_id, milestone, note, photo_url)
             VALUES (?,?,?,?,?)"
        )->execute([$id, $me['id'], $milestone, $note ?: null, $photoUrl]);

        // Reflect certain milestones onto the booking workflow.
        if ($milestone === 'started') {
            Workflow::advance($db, $id, 'in_progress', (int)$me['id'], 'Trip started by performer.');
        } elseif ($milestone === 'completed') {
            Workflow::advance($db, $id, 'completed', (int)$me['id'], 'Trip completed by performer.');
        }
        $flash = 'Milestone "' . $milestone . '" recorded.';
        $stmt->execute([$id]);
        $b = $stmt->fetch();
    }
}

$milestones = [];
if ($b) {
    $m = $db->prepare("SELECT * FROM trip_milestones WHERE booking_id = ? ORDER BY created_at DESC, id DESC");
    $m->execute([$id]);
    $milestones = $m->fetchAll();
}

$pageTitle = 'Trip';
require __DIR__ . '/../includes/panel_top.php';

if (!$b) {
    echo '<div class="admin-card">Trip not found.</div>';
    require __DIR__ . '/../includes/panel_bottom.php';
    exit;
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="mb-0">Trip <?= e($b['reference']) ?></h3>
    <p class="text-muted mb-0"><?= e($b['full_name']) ?> &middot; <?= (int)$b['party_size'] ?> travellers &middot; <?= e($b['travel_date'] ?: 'TBC') ?></p>
  </div>
  <?= Workflow::badge($b['workflow_status']) ?>
</div>

<?php if ($flash): ?><div class="alert alert-success py-2 small"><?= e($flash) ?></div><?php endif; ?>

<div class="row g-4">
  <div class="col-lg-5">
    <form method="post" enctype="multipart/form-data" class="admin-card">
      <?= csrfField() ?>
      <input type="hidden" name="booking_id" value="<?= (int)$id ?>">
      <h6 class="mb-3">Update milestone</h6>
      <div class="mb-3">
        <label class="form-label small text-muted">Milestone</label>
        <select name="milestone" class="form-select" required>
          <option value="accepted">Accept assignment</option>
          <option value="declined">Decline assignment</option>
          <option value="started">Trip started</option>
          <option value="picked_up">Picked up customer</option>
          <option value="arrived">Arrived at destination</option>
          <option value="completed">Trip completed</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label small text-muted">Note (optional)</label>
        <textarea name="note" rows="2" class="form-control"></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label small text-muted">Photo / report (optional)</label>
        <input type="file" name="photo" accept="image/*" class="form-control">
      </div>
      <button class="btn btn-lg-primary w-100"><i class="bi bi-flag"></i> Record milestone</button>
    </form>

    <div class="admin-card mt-3">
      <h6 class="mb-2">Trip details</h6>
      <p class="small mb-1"><i class="bi bi-truck-front text-amber"></i> Vehicle: <?= e($b['vehicle_name'] ?: '—') ?></p>
      <p class="small mb-1"><i class="bi bi-building text-amber"></i> Hotel: <?= e($b['hotel_name'] ?: '—') ?></p>
      <p class="small mb-0"><i class="bi bi-chat-left-text text-amber"></i> Notes: <?= e($b['notes'] ?: '—') ?></p>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="admin-card">
      <h6 class="mb-3">Milestone timeline</h6>
      <?php if (!$milestones): ?>
        <p class="text-muted small mb-0">No milestones recorded yet.</p>
      <?php else: ?>
        <?php foreach ($milestones as $m): ?>
          <div class="itinerary-day" data-day="●">
            <div class="itinerary-card mb-2">
              <div class="d-flex justify-content-between">
                <span class="fw-semibold text-capitalize"><?= e(str_replace('_',' ',$m['milestone'])) ?></span>
                <span class="small text-muted"><?= e(date('d M, H:i', strtotime($m['created_at']))) ?></span>
              </div>
              <?php if ($m['note']): ?><div class="small text-muted"><?= e($m['note']) ?></div><?php endif; ?>
              <?php if ($m['photo_url']): ?><img src="<?= url($m['photo_url']) ?>" alt="Trip photo" class="img-fluid rounded mt-2" style="max-height:180px"><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/panel_bottom.php'; ?>
