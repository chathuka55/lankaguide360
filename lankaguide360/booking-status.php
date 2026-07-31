<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/Workflow.php';

$db = getDbConnection();
$ref = trim($_GET['ref'] ?? '');
$isNew = isset($_GET['new']);

$booking = null;
if ($ref !== '') {
    $stmt = $db->prepare(
        "SELECT b.*, g.full_name AS guide_name, dr.full_name AS driver_name,
                v.name AS vehicle_name, h.name AS hotel_name
         FROM bookings b
         LEFT JOIN guides g   ON g.id = b.guide_id
         LEFT JOIN drivers dr ON dr.id = b.driver_id
         LEFT JOIN vehicles v ON v.id = b.vehicle_id
         LEFT JOIN hotels h   ON h.id = b.hotel_id
         WHERE b.reference = ?"
    );
    $stmt->execute([$ref]);
    $booking = $stmt->fetch();
}

// Map points for the booking's itinerary (destinations with coordinates).
$mapPoints = [];
if ($booking && $booking['trip_plan_id']) {
    $mp = $db->prepare(
        "SELECT tpi.day_number, d.name, d.region, d.latitude, d.longitude
         FROM trip_plan_items tpi JOIN destinations d ON d.id = tpi.destination_id
         WHERE tpi.trip_plan_id = ? AND d.latitude IS NOT NULL AND d.longitude IS NOT NULL
         ORDER BY tpi.day_number, tpi.sort_order"
    );
    $mp->execute([$booking['trip_plan_id']]);
    foreach ($mp->fetchAll() as $r) {
        $mapPoints[] = [
            'day' => (int)$r['day_number'], 'name' => $r['name'], 'region' => $r['region'],
            'lat' => (float)$r['latitude'], 'lng' => (float)$r['longitude'],
        ];
    }
}

$pageTitle = 'Booking status';
require __DIR__ . '/includes/header.php';
?>
<?php if ($mapPoints): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>
<section class="section-pad" style="padding-top:3rem">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8">

        <?php if ($isNew && $booking): ?>
          <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> Your booking <strong><?= e($booking['reference']) ?></strong> has been submitted. Track its progress below — bookmark this page.</div>
        <?php endif; ?>

        <form method="get" class="planner-shell p-3 mb-4 d-flex gap-2">
          <input type="text" name="ref" class="form-control" placeholder="Enter booking reference e.g. LG360-000001" value="<?= e($ref) ?>">
          <button class="btn btn-lg-primary flex-shrink-0">Track</button>
        </form>

        <?php if ($ref !== '' && !$booking): ?>
          <div class="admin-card text-center py-4"><p class="text-muted mb-0">No booking found for reference <strong><?= e($ref) ?></strong>.</p></div>
        <?php elseif ($booking): $history = Workflow::history($db, (int)$booking['id']); ?>
          <div class="planner-shell p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
              <div>
                <span class="eyebrow">Booking <?= e($booking['reference']) ?></span>
                <h3 class="mb-1"><?= e($booking['full_name']) ?></h3>
                <p class="text-muted small mb-0"><?= e($booking['travel_date'] ?: 'Dates TBC') ?><?= $booking['end_date'] ? ' → ' . e($booking['end_date']) : '' ?> &middot; <?= (int)$booking['party_size'] ?> travellers</p>
              </div>
              <div class="text-end">
                <?= Workflow::badge($booking['workflow_status']) ?>
                <div class="h4 mt-2 mb-0 text-teal"><?= lkr($booking['total_lkr']) ?></div>
              </div>
            </div>

            <?php if ($booking['workflow_status'] === 'awaiting_payment'): ?>
              <a href="<?= url('payment.php?booking=' . (int)$booking['id']) ?>" class="btn btn-lg-primary w-100 mt-3"><i class="bi bi-credit-card"></i> Pay now — <?= lkr($booking['total_lkr']) ?></a>
            <?php endif; ?>

            <div class="row g-2 mt-3 small">
              <?php if ($booking['guide_name']): ?><div class="col-md-6"><i class="bi bi-person-badge text-amber"></i> Guide: <?= e($booking['guide_name']) ?></div><?php endif; ?>
              <?php if ($booking['driver_name']): ?><div class="col-md-6"><i class="bi bi-person-workspace text-amber"></i> Driver: <?= e($booking['driver_name']) ?></div><?php endif; ?>
              <?php if ($booking['vehicle_name']): ?><div class="col-md-6"><i class="bi bi-truck-front text-amber"></i> Vehicle: <?= e($booking['vehicle_name']) ?></div><?php endif; ?>
              <?php if ($booking['hotel_name']): ?><div class="col-md-6"><i class="bi bi-building text-amber"></i> Hotel: <?= e($booking['hotel_name']) ?></div><?php endif; ?>
            </div>
          </div>

          <?php if ($mapPoints): ?>
            <div class="planner-shell p-2 p-lg-3 mb-4">
              <div id="routeMap" style="height:340px;border-radius:var(--radius-md);overflow:hidden"></div>
              <p class="small text-muted mb-0 mt-2 px-2"><i class="bi bi-geo-alt-fill text-amber"></i> Trip route — <?= count($mapPoints) ?> stop(s) in day order.</p>
            </div>
          <?php endif; ?>

          <div class="planner-shell p-4">
            <h5 class="mb-3">Progress</h5>
            <?php foreach ($history as $h): ?>
              <div class="itinerary-day" data-day="●">
                <div class="itinerary-card mb-2">
                  <div class="d-flex justify-content-between">
                    <span class="fw-semibold"><?= e(Workflow::label($h['to_status'])) ?></span>
                    <span class="small text-muted"><?= e(date('d M, H:i', strtotime($h['created_at']))) ?></span>
                  </div>
                  <?php if ($h['note']): ?><div class="small text-muted"><?= e($h['note']) ?><?= $h['actor'] ? ' — ' . e($h['actor']) : '' ?></div><?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php if ($mapPoints): ?>
<script>
(function () {
  const points = <?= json_encode($mapPoints, JSON_UNESCAPED_UNICODE) ?>;
  if (!points.length || typeof L === 'undefined') return;
  const map = L.map('routeMap', { scrollWheelZoom: false });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
  const latlngs = [];
  const green = getComputedStyle(document.documentElement).getPropertyValue('--color-main').trim() || '#008000';
  points.forEach(function (p) {
    const ll = [p.lat, p.lng];
    latlngs.push(ll);
    const icon = L.divIcon({
      className: 'lg-route-pin',
      html: '<span style="background:' + green + ';color:#fff;border:2px solid #fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-weight:600;box-shadow:0 2px 6px rgba(0,0,0,.35)">' + p.day + '</span>',
      iconSize: [28, 28], iconAnchor: [14, 14]
    });
    L.marker(ll, { icon: icon }).addTo(map).bindPopup('<strong>Day ' + p.day + ': ' + p.name + '</strong><br><span style="color:#666">' + p.region + '</span>');
  });
  if (latlngs.length > 1) {
    L.polyline(latlngs, { color: green, weight: 3, opacity: 0.8, dashArray: '6,8' }).addTo(map);
    map.fitBounds(L.latLngBounds(latlngs), { padding: [40, 40] });
  } else {
    map.setView(latlngs[0], 10);
  }
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
