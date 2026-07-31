<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Workflow.php';
requireRole('dispatcher');

$db = getDbConnection();
$me = currentUser();
$id = (int)($_GET['id'] ?? $_POST['booking_id'] ?? 0);

$stmt = $db->prepare(
    "SELECT b.*, tp.duration_days, tp.budget_tier, tp.interests
     FROM bookings b LEFT JOIN trip_plans tp ON tp.id = b.trip_plan_id WHERE b.id = ?"
);
$stmt->execute([$id]);
$booking = $stmt->fetch();

if (!$booking) {
    require __DIR__ . '/../includes/panel_top.php';
    echo '<div class="admin-card">Booking not found.</div>';
    require __DIR__ . '/../includes/panel_bottom.php';
    exit;
}

$flashMsg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $guideId   = !empty($_POST['guide_id'])   ? (int)$_POST['guide_id']   : null;
    $driverId  = !empty($_POST['driver_id'])  ? (int)$_POST['driver_id']  : null;
    $vehicleId = !empty($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : null;
    $hotelId   = !empty($_POST['hotel_id'])   ? (int)$_POST['hotel_id']   : null;
    $action    = $_POST['action'] ?? 'save';

    $db->prepare(
        "UPDATE bookings SET guide_id=?, driver_id=?, vehicle_id=?, hotel_id=?, dispatcher_id=? WHERE id=?"
    )->execute([$guideId, $driverId, $vehicleId, $hotelId, $me['id'], $id]);

    if ($action === 'send') {
        // Block the resources for the travel dates (simple availability records).
        if ($booking['travel_date']) {
            $end = $booking['end_date'] ?: $booking['travel_date'];
            $avail = $db->prepare(
                "INSERT INTO availability (resource_type, resource_id, date_from, date_to, status, booking_id)
                 VALUES (?,?,?,?, 'booked', ?)"
            );
            foreach ([['guide', $guideId], ['driver', $driverId], ['vehicle', $vehicleId], ['hotel', $hotelId]] as [$type, $rid]) {
                if ($rid) $avail->execute([$type, $rid, $booking['travel_date'], $end, $id]);
            }
        }
        Workflow::advance($db, $id, 'manager_review', (int)$me['id'], 'Resources assigned; forwarded to manager for pricing/approval.');
        header('Location: ' . url('dispatcher/queue.php'));
        exit;
    }

    Workflow::advance($db, $id, 'assigned', (int)$me['id'], 'Resources assigned (draft).');
    $flashMsg = 'Assignment saved.';
    // Reload booking to reflect saved values.
    $stmt->execute([$id]);
    $booking = $stmt->fetch();
}

// Candidate resources.
$guides   = $db->query("SELECT * FROM guides   WHERE is_active=1 ORDER BY is_regional, region, full_name")->fetchAll();
$drivers  = $db->query("SELECT * FROM drivers  WHERE is_active=1 ORDER BY region, full_name")->fetchAll();
$vehicles = $db->query("SELECT * FROM vehicles WHERE is_active=1 AND is_rentable=1 ORDER BY seats, rate_per_day_lkr")->fetchAll();
$hotels   = $db->query("SELECT * FROM hotels   WHERE is_active=1 ORDER BY region, star_rating DESC")->fetchAll();

// Overlapping-availability check for the booking dates.
function conflicts(PDO $db, string $type, ?int $rid, ?string $from, ?string $to, int $excludeBooking): bool {
    if (!$rid || !$from) return false;
    $to = $to ?: $from;
    $q = $db->prepare(
        "SELECT COUNT(*) FROM availability
         WHERE resource_type=? AND resource_id=? AND status='booked' AND booking_id<>?
           AND NOT (date_to < ? OR date_from > ?)"
    );
    $q->execute([$type, $rid, $excludeBooking, $from, $to]);
    return (int)$q->fetchColumn() > 0;
}
$from = $booking['travel_date']; $to = $booking['end_date'] ?: $booking['travel_date'];

$pageTitle = 'Assign resources';
require __DIR__ . '/../includes/panel_top.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="mb-0">Assign — <?= e($booking['reference']) ?></h3>
    <p class="text-muted mb-0"><?= e($booking['full_name']) ?> &middot; <?= (int)$booking['party_size'] ?> travellers &middot; <?= e($booking['travel_date'] ?: 'dates TBC') ?></p>
  </div>
  <div><?= Workflow::badge($booking['workflow_status']) ?></div>
</div>

<?php if ($flashMsg): ?><div class="alert alert-success py-2 small"><?= e($flashMsg) ?></div><?php endif; ?>

<form method="post" class="row g-4">
  <?= csrfField() ?>
  <input type="hidden" name="booking_id" value="<?= (int)$id ?>">

  <div class="col-lg-8">
    <div class="admin-card">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label small text-muted">Guide</label>
          <select name="guide_id" class="form-select">
            <option value="">— none —</option>
            <?php foreach ($guides as $g): ?>
              <option value="<?= (int)$g['id'] ?>" <?= (int)$booking['guide_id']===(int)$g['id']?'selected':'' ?>>
                <?= e($g['full_name']) ?> · <?= $g['is_regional'] ? e($g['region']) : 'Island-wide' ?> · <?= e($g['languages']) ?> · <?= lkr($g['daily_rate_lkr']) ?>/day
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (conflicts($db,'guide',(int)$booking['guide_id'],$from,$to,$id)): ?><div class="text-danger small mt-1"><i class="bi bi-exclamation-triangle"></i> Possible date conflict</div><?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label small text-muted">Driver</label>
          <select name="driver_id" class="form-select">
            <option value="">— none / guide drives —</option>
            <?php foreach ($drivers as $d): ?>
              <option value="<?= (int)$d['id'] ?>" <?= (int)$booking['driver_id']===(int)$d['id']?'selected':'' ?>>
                <?= e($d['full_name']) ?> · <?= e($d['region']) ?> · <?= $d['has_own_vehicle']?'owns vehicle':'no vehicle' ?> · <?= lkr($d['daily_rate_lkr']) ?>/day
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (conflicts($db,'driver',(int)$booking['driver_id'],$from,$to,$id)): ?><div class="text-danger small mt-1"><i class="bi bi-exclamation-triangle"></i> Possible date conflict</div><?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label small text-muted">Vehicle</label>
          <select name="vehicle_id" class="form-select">
            <option value="">— none —</option>
            <?php foreach ($vehicles as $v): ?>
              <option value="<?= (int)$v['id'] ?>" <?= (int)$booking['vehicle_id']===(int)$v['id']?'selected':'' ?>>
                <?= e($v['name']) ?> · <?= (int)$v['seats'] ?> seats · <?= $v['has_driver']?'with driver':'self-drive' ?> · <?= lkr($v['rate_per_day_lkr']) ?>/day
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (conflicts($db,'vehicle',(int)$booking['vehicle_id'],$from,$to,$id)): ?><div class="text-danger small mt-1"><i class="bi bi-exclamation-triangle"></i> Possible date conflict</div><?php endif; ?>
          <?php
            $selectedSeats = null;
            foreach ($vehicles as $vv) { if ((int)$vv['id'] === (int)$booking['vehicle_id']) { $selectedSeats = (int)$vv['seats']; break; } }
            if ($selectedSeats !== null && $selectedSeats < (int)$booking['party_size']):
          ?>
            <div class="text-danger small mt-1"><i class="bi bi-exclamation-triangle"></i> Seats fewer than party size</div>
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label small text-muted">Hotel (primary)</label>
          <select name="hotel_id" class="form-select">
            <option value="">— none —</option>
            <?php foreach ($hotels as $h): ?>
              <option value="<?= (int)$h['id'] ?>" <?= (int)$booking['hotel_id']===(int)$h['id']?'selected':'' ?>>
                <?= e($h['name']) ?> · <?= e($h['region']) ?> · <?= str_repeat('★',(int)$h['star_rating']) ?> · <?= lkr($h['price_per_night_lkr']) ?>/night
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button name="action" value="save" class="btn btn-lg-outline"><i class="bi bi-save"></i> Save draft</button>
        <button name="action" value="send" class="btn btn-lg-primary"><i class="bi bi-send"></i> Forward to manager</button>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="admin-card">
      <h6 class="mb-3">Request details</h6>
      <p class="small mb-1"><strong>Notes:</strong> <?= e($booking['notes'] ?: '—') ?></p>
      <p class="small mb-1"><strong>Budget:</strong> <?= e(ucfirst($booking['budget_tier'] ?? '—')) ?></p>
      <p class="small mb-1"><strong>Dates:</strong> <?= e($from ?: 'TBC') ?><?= $booking['end_date'] ? ' → ' . e($booking['end_date']) : '' ?></p>
      <p class="small mb-0"><strong>Est. total:</strong> <?= lkr($booking['total_lkr']) ?></p>
    </div>
  </div>
</form>
<?php require __DIR__ . '/../includes/panel_bottom.php'; ?>
