<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/ResourceMatcher.php';

$db = getDbConnection();
$planId = (int)($_GET['plan'] ?? 0);

// Load the saved plan.
$stmt = $db->prepare("SELECT * FROM trip_plans WHERE id = ?");
$stmt->execute([$planId]);
$plan = $stmt->fetch();
if (!$plan) {
    http_response_code(404);
    $pageTitle = 'Plan not found';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section-pad"><div class="container"><div class="admin-card text-center py-5"><h4>Plan not found</h4><p class="text-muted">This trip plan no longer exists. <a href="' . url('trip-planner.php') . '" class="text-teal">Plan a new trip</a>.</p></div></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Allow live editing of travellers / dates from this page.
$travelers = max(1, (int)($_GET['travelers'] ?? $plan['travelers'] ?? 2));
$startDate = trim($_GET['start'] ?? '');
$endDate   = trim($_GET['end'] ?? '');

// Rebuild the plan result (day-by-day destinations) from stored items.
$itemsStmt = $db->prepare(
    "SELECT tpi.day_number, tpi.match_score, d.*
     FROM trip_plan_items tpi
     JOIN destinations d ON d.id = tpi.destination_id
     WHERE tpi.trip_plan_id = ?
     ORDER BY tpi.day_number ASC, tpi.sort_order ASC"
);
$itemsStmt->execute([$planId]);
$rows = $itemsStmt->fetchAll();

$planResult = ['items' => [], 'meta' => []];
foreach ($rows as $r) {
    $planResult['items'][] = [
        'day' => (int)$r['day_number'],
        'destination' => $r,
        'score' => (float)$r['match_score'],
    ];
}

// Match guide / driver / vehicle / hotels + price it (LKR).
$matcher = new ResourceMatcher($db);
$package = $matcher->match($planResult, $plan['budget_tier'], (int)$plan['duration_days'], $travelers, 'English');
$p = $package['pricing'];

// Cache the authoritative package so booking.php prices consistently.
$_SESSION['package'][$planId] = [
    'travelers'  => $travelers,
    'start'      => $startDate,
    'end'        => $endDate,
    'guide_id'   => $package['guide']['id']   ?? null,
    'driver_id'  => $package['driver']['id']  ?? null,
    'vehicle_id' => $package['vehicle']['id'] ?? null,
    'hotel_id'   => $package['hotels'][0]['id'] ?? null,
    'pricing'    => $p,
];

// Build the ordered list of map points (destinations that have coordinates).
$mapPoints = [];
foreach ($planResult['items'] as $item) {
    $d = $item['destination'];
    if ($d['latitude'] !== null && $d['longitude'] !== null) {
        $mapPoints[] = [
            'day'  => (int)$item['day'],
            'name' => $d['name'],
            'region' => $d['region'],
            'lat'  => (float)$d['latitude'],
            'lng'  => (float)$d['longitude'],
        ];
    }
}

$pageTitle = 'Your itinerary & package';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<section class="section-pad" style="padding-top:3rem">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">Step 2 &middot; Review &amp; edit</span>
      <h2>Your <?= (int)$plan['duration_days'] ?>-day Sri Lanka package</h2>
      <p>Here's your suggested itinerary with a guide, transport and hotels — all priced in LKR. Adjust the details, then submit your booking.</p>
    </div>

    <?php if ($mapPoints): ?>
      <div class="planner-shell p-2 p-lg-3 mb-4">
        <div id="routeMap" style="height:380px;border-radius:var(--radius-md);overflow:hidden"></div>
        <p class="small text-muted mb-0 mt-2 px-2"><i class="bi bi-geo-alt-fill text-amber"></i> Your route across <?= count($mapPoints) ?> stop(s), in day order. Click a marker for details.</p>
      </div>
    <?php endif; ?>

    <div class="row g-4">
      <!-- Left: day-by-day itinerary -->
      <div class="col-lg-7">
        <form method="get" class="planner-shell p-3 p-lg-4 mb-4">
          <input type="hidden" name="plan" value="<?= (int)$planId ?>">
          <div class="row g-3 align-items-end">
            <div class="col-4">
              <label class="form-label small text-muted">Travellers</label>
              <input type="number" name="travelers" min="1" max="40" class="form-control" value="<?= (int)$travelers ?>">
            </div>
            <div class="col-4">
              <label class="form-label small text-muted">Start date</label>
              <input type="date" name="start" class="form-control" value="<?= e($startDate) ?>">
            </div>
            <div class="col-4">
              <label class="form-label small text-muted">End date</label>
              <input type="date" name="end" class="form-control" value="<?= e($endDate) ?>">
            </div>
          </div>
          <button class="btn btn-lg-outline btn-sm mt-3"><i class="bi bi-arrow-repeat"></i> Update &amp; reprice</button>
          <a href="<?= url('trip-planner.php') ?>" class="btn btn-link btn-sm mt-3 text-muted">Change preferences</a>
        </form>

        <h5 class="mb-3">Day-by-day route</h5>
        <?php foreach ($planResult['items'] as $item): $d = $item['destination']; ?>
          <div class="itinerary-day" data-day="<?= (int)$item['day'] ?>">
            <div class="itinerary-card mb-2">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <a href="<?= url('destination-details.php?slug=' . urlencode($d['slug'])) ?>" class="fw-semibold text-decoration-none" style="color:var(--ink-900)"><?= e($d['name']) ?></a>
                  <div class="small text-muted"><?= e($d['region']) ?> &middot; <?= e($d['short_description']) ?></div>
                </div>
                <span class="rating flex-shrink-0"><i class="bi bi-star-fill"></i> <?= number_format((float)$d['rating'], 1) ?></span>
              </div>
              <div class="d-flex justify-content-between mt-2">
                <span class="match-score">match <?= number_format((float)$item['score'], 1) ?></span>
                <span class="small text-muted"><?= $d['entry_fee_lkr'] > 0 ? 'Entry ' . lkr($d['entry_fee_lkr']) . ' pp' : 'Free entry' ?></span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Right: resources + pricing -->
      <div class="col-lg-5">
        <div class="planner-shell p-4 mb-4">
          <h5 class="mb-3">Your team &amp; transport</h5>

          <?php if ($package['guide']): $g = $package['guide']; ?>
            <div class="d-flex align-items-start gap-3 border-bottom py-3">
              <img class="lg-avatar" src="<?= e(avatar($g['photo_url'] ?? null, $g['full_name'], 'avataaars')) ?>" alt="<?= e($g['full_name']) ?>">
              <div>
                <div class="fw-semibold"><?= e($g['full_name']) ?> <span class="tag-pill"><?= $g['is_regional'] ? e($g['region']) : 'Island-wide' ?></span></div>
                <div class="small text-muted"><?= e($g['languages']) ?> &middot; <i class="bi bi-star-fill text-amber"></i> <?= number_format((float)$g['rating'],1) ?></div>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($package['vehicle']): $v = $package['vehicle']; $vImg = img_src($v['image_url'] ?? null); ?>
            <div class="d-flex align-items-start gap-3 border-bottom py-3">
              <?php if ($vImg): ?><img class="lg-thumb" src="<?= e($vImg) ?>" alt="<?= e($v['name']) ?>"><?php else: ?><i class="bi bi-truck-front fs-4 text-amber"></i><?php endif; ?>
              <div>
                <div class="fw-semibold"><?= e($v['name']) ?> <span class="tag-pill"><?= e(ucfirst($v['type'])) ?></span></div>
                <div class="small text-muted"><?= (int)$v['seats'] ?> seats &middot; <?= $v['has_driver'] ? 'with driver' : 'self-drive' ?></div>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($package['driver']): $dr = $package['driver']; ?>
            <div class="d-flex align-items-start gap-3 border-bottom py-3">
              <img class="lg-avatar" src="<?= e(avatar($dr['photo_url'] ?? null, $dr['full_name'], 'avataaars')) ?>" alt="<?= e($dr['full_name']) ?>">
              <div>
                <div class="fw-semibold"><?= e($dr['full_name']) ?> <span class="tag-pill">Driver</span></div>
                <div class="small text-muted"><?= e($dr['region']) ?> &middot; <?= e($dr['languages']) ?></div>
              </div>
            </div>
          <?php endif; ?>

          <?php foreach ($package['hotels'] as $h): $hImg = img_src($h['image_url'] ?? null); ?>
            <div class="d-flex align-items-start gap-3 border-bottom py-3">
              <?php if ($hImg): ?><img class="lg-thumb" src="<?= e($hImg) ?>" alt="<?= e($h['name']) ?>"><?php else: ?><i class="bi bi-building fs-4 text-amber"></i><?php endif; ?>
              <div>
                <div class="fw-semibold"><?= e($h['name']) ?> <span class="tag-pill"><?= str_repeat('★', (int)$h['star_rating']) ?></span></div>
                <div class="small text-muted"><?= e($h['region']) ?> &middot; <?= (int)$h['nights'] ?> night(s) &middot; <?= lkr($h['price_per_night_lkr']) ?>/night</div>
              </div>
            </div>
          <?php endforeach; ?>

          <?php if (!empty($package['notes'])): ?>
            <div class="small text-muted mt-3">
              <?php foreach ($package['notes'] as $n): ?><div><i class="bi bi-info-circle"></i> <?= e($n) ?></div><?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="planner-shell p-4">
          <h5 class="mb-3">Price estimate (LKR)</h5>
          <table class="table table-sm align-middle mb-2">
            <tbody class="small">
              <?php if ($p['guide']   > 0): ?><tr><td>Guide (<?= (int)$plan['duration_days'] ?> days)</td><td class="text-end"><?= lkr($p['guide']) ?></td></tr><?php endif; ?>
              <?php if ($p['driver']  > 0): ?><tr><td>Driver (<?= (int)$plan['duration_days'] ?> days)</td><td class="text-end"><?= lkr($p['driver']) ?></td></tr><?php endif; ?>
              <?php if ($p['vehicle'] > 0): ?><tr><td>Vehicle (<?= (int)$plan['duration_days'] ?> days)</td><td class="text-end"><?= lkr($p['vehicle']) ?></td></tr><?php endif; ?>
              <?php if ($p['hotel']   > 0): ?><tr><td>Hotels (<?= (int)$p['rooms'] ?> room/s)</td><td class="text-end"><?= lkr($p['hotel']) ?></td></tr><?php endif; ?>
              <?php if ($p['entry_fees'] > 0): ?><tr><td>Attraction entry (× <?= (int)$travelers ?>)</td><td class="text-end"><?= lkr($p['entry_fees']) ?></td></tr><?php endif; ?>
              <tr><td>Service fee (5%)</td><td class="text-end"><?= lkr($p['service_fee']) ?></td></tr>
            </tbody>
            <tfoot>
              <tr class="fw-bold border-top"><td>Estimated total</td><td class="text-end text-teal"><?= lkr($p['total']) ?></td></tr>
            </tfoot>
          </table>
          <p class="text-muted" style="font-size:.75rem">Final pricing is confirmed by our team after a dispatcher assigns resources and a manager reviews. No payment is taken until then.</p>
          <a href="<?= url('booking.php?plan=' . (int)$planId) ?>" class="btn btn-lg-primary w-100"><i class="bi bi-bag-check"></i> Submit booking request</a>
        </div>
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
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  const latlngs = [];
  const green = getComputedStyle(document.documentElement).getPropertyValue('--color-main').trim() || '#008000';

  points.forEach(function (p) {
    const ll = [p.lat, p.lng];
    latlngs.push(ll);
    // Numbered day marker.
    const icon = L.divIcon({
      className: 'lg-route-pin',
      html: '<span style="background:' + green + ';color:#fff;border:2px solid #fff;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;font-weight:600;box-shadow:0 2px 6px rgba(0,0,0,.35)">' + p.day + '</span>',
      iconSize: [30, 30],
      iconAnchor: [15, 15]
    });
    const gmaps = 'https://www.google.com/maps/search/?api=1&query=' + p.lat + ',' + p.lng;
    L.marker(ll, { icon: icon }).addTo(map)
      .bindPopup('<strong>Day ' + p.day + ': ' + p.name + '</strong><br><span style="color:#666">' + p.region + '</span>'
        + '<br><a href="' + gmaps + '" target="_blank" rel="noopener">View on Google Maps →</a>');
  });

  // Route line connecting the stops in order.
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
