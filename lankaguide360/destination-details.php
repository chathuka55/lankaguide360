<?php
require_once __DIR__ . '/config/app.php';
$db = getDbConnection();

$slug = trim($_GET['slug'] ?? '');
$stmt = $db->prepare("SELECT * FROM destinations WHERE slug = ? AND is_active = 1");
$stmt->execute([$slug]);
$dest = $stmt->fetch();

if (!$dest) {
    http_response_code(404);
    $pageTitle = 'Destination not found — LankaGuide 360';
    include __DIR__ . '/includes/header.php';
    echo '<div class="container section-pad text-center"><h2>Destination not found</h2><p class="text-muted">It may have been removed or the link is incorrect.</p><a href="' . url('destinations.php') . '" class="btn btn-lg-primary">Back to destinations</a></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $dest['name'] . ' — LankaGuide 360';

$catStmt = $db->prepare(
    "SELECT c.name FROM categories c JOIN destination_categories dc ON dc.category_id=c.id
     WHERE dc.destination_id = ? ORDER BY dc.weight DESC"
);
$catStmt->execute([$dest['id']]);
$cats = $catStmt->fetchAll(PDO::FETCH_COLUMN);

$actStmt = $db->prepare("SELECT * FROM activities WHERE destination_id = ?");
$actStmt->execute([$dest['id']]);
$activities = $actStmt->fetchAll();

$similarStmt = $db->prepare(
    "SELECT DISTINCT d2.* FROM destinations d2
     JOIN destination_categories dc2 ON dc2.destination_id = d2.id
     JOIN destination_categories dc1 ON dc1.category_id = dc2.category_id
     WHERE dc1.destination_id = ? AND d2.id != ? AND d2.is_active=1
     ORDER BY d2.rating DESC LIMIT 3"
);
$similarStmt->execute([$dest['id'], $dest['id']]);
$similar = $similarStmt->fetchAll();

$nearestLocation = null;
if ($dest['location_id']) {
    $locStmt = $db->prepare("SELECT name FROM locations WHERE id = ?");
    $locStmt->execute([$dest['location_id']]);
    $nearestLocation = $locStmt->fetchColumn();
}

$hasGeo = $dest['latitude'] !== null && $dest['longitude'] !== null;
$gmaps = $hasGeo
    ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($dest['latitude'] . ',' . $dest['longitude'])
    : null;

include __DIR__ . '/includes/header.php';
?>
<?php if ($hasGeo): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>

<section class="hero" style="background:linear-gradient(160deg,var(--teal-900) 0%, var(--teal-700) 100%); padding:5.5rem 0 2.75rem">
  <div class="container">
    <a href="<?= url('destinations.php') ?>" class="text-white-50 small"><i class="bi bi-arrow-left"></i> All destinations</a>
    <span class="eyebrow hero-eyebrow d-block mt-3"><?= e($dest['region']) ?> · <?= e($dest['district']) ?></span>
    <h1 style="font-size:clamp(2rem,4vw,3rem)"><?= e($dest['name']) ?></h1>
    <div class="d-flex gap-3 flex-wrap text-white-50 small mb-4">
      <span><i class="bi bi-star-fill text-amber"></i> <?= number_format((float)$dest['rating'],1) ?> rating</span>
      <span><i class="bi bi-clock"></i> ~<?= rtrim(rtrim(number_format((float)$dest['avg_visit_hours'],1),'0'),'.') ?>h visit</span>
      <span><i class="bi bi-cash-coin"></i> LKR <?= number_format((float)$dest['entry_fee_lkr'],0) ?> entry</span>
      <span><i class="bi bi-calendar-event"></i> Best: <?= e($dest['best_season'] ?: 'Year-round') ?></span>
      <span><i class="bi <?= $dest['popularity_tier']==='popular'?'bi-star':'bi-gem' ?>"></i> <?= $dest['popularity_tier']==='popular' ? 'Popular attraction' : 'Hidden gem' ?></span>
      <?php if ($nearestLocation): ?><span><i class="bi bi-signpost-split"></i> Near <?= e($nearestLocation) ?></span><?php endif; ?>
    </div>
  </div>
</section>

<section class="section-pad" style="padding-top:2rem">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-8">
        <?php $heroImg = img_src($dest['image_url']); ?>
        <div class="img-wrap rounded-lg mb-4<?= $heroImg ? ' has-photo' : '' ?>" style="aspect-ratio:16/8;background:linear-gradient(135deg,var(--teal-500),var(--tea-600));display:flex;align-items:center;justify-content:center;color:#fff">
          <?php if ($heroImg): ?><img src="<?= e($heroImg) ?>" alt="<?= e($dest['name']) ?>" class="img-cover"><?php else: ?><i class="bi bi-image" style="font-size:3rem"></i><?php endif; ?>
        </div>
        <h4>About <?= e($dest['name']) ?></h4>
        <p class="text-muted"><?= nl2br(e($dest['full_description'])) ?></p>

        <div class="mb-4">
          <?php foreach ($cats as $c): ?><span class="tag-pill"><?= e($c) ?></span><?php endforeach; ?>
        </div>

        <?php if ($activities): ?>
        <h5 class="mt-5">Things to do here</h5>
        <div class="row g-3">
          <?php foreach ($activities as $a): ?>
          <div class="col-md-6">
            <div class="admin-card">
              <strong><?= e($a['title']) ?></strong>
              <p class="text-muted small mb-1"><?= e($a['description']) ?></p>
              <span class="match-score"><?= $a['duration_hours'] ?>h · LKR <?= number_format((float)$a['price_lkr'],0) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="col-lg-4">
        <div class="hero-card mb-4">
          <h6>Plan a visit</h6>
          <p class="text-muted small">Ready to include <?= e($dest['name']) ?> in your route?</p>
          <a href="<?= url('trip-planner.php') ?>" class="btn btn-lg-primary w-100 mb-2"><i class="bi bi-compass"></i> Add to trip planner</a>
          <a href="<?= url('booking.php?destination=' . urlencode($dest['slug'])) ?>" class="btn btn-lg-outline w-100">Request a booking</a>
        </div>

        <?php if ($hasGeo): ?>
        <div class="hero-card mb-4">
          <h6>Location</h6>
          <div id="destMap" style="height:220px;border-radius:var(--radius-md);overflow:hidden;margin:.4rem 0 .8rem"></div>
          <p class="small text-muted mb-2"><i class="bi bi-geo-alt-fill text-amber"></i> <?= e($dest['district']) ?>, <?= e($dest['region']) ?> · <?= number_format((float)$dest['latitude'],4) ?>, <?= number_format((float)$dest['longitude'],4) ?></p>
          <a href="<?= e($gmaps) ?>" target="_blank" rel="noopener" class="btn btn-lg-outline w-100"><i class="bi bi-google"></i> View on Google Maps</a>
        </div>
        <?php endif; ?>

        <?php if ($similar): ?>
        <h6 class="text-muted mb-3">You might also like</h6>
        <?php foreach ($similar as $s): ?>
        <?php $sImg = img_src($s['image_url']); ?>
        <a href="<?= url('destination-details.php?slug=' . urlencode($s['slug'])) ?>" class="d-flex align-items-center gap-3 mb-3 text-decoration-none">
          <div class="img-wrap<?= $sImg ? ' has-photo' : '' ?>" style="width:64px;height:64px;border-radius:12px;flex-shrink:0;background:linear-gradient(135deg,var(--teal-500),var(--tea-600));display:flex;align-items:center;justify-content:center;color:#fff">
            <?php if ($sImg): ?><img src="<?= e($sImg) ?>" alt="<?= e($s['name']) ?>" class="img-cover"><?php else: ?><i class="bi bi-image"></i><?php endif; ?>
          </div>
          <div>
            <div class="fw-semibold" style="color:var(--ink-900)"><?= e($s['name']) ?></div>
            <div class="small text-muted"><?= e($s['region']) ?></div>
          </div>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php if ($hasGeo): ?>
<script>
(function () {
  if (typeof L === 'undefined') return;
  const lat = <?= json_encode((float)$dest['latitude']) ?>, lng = <?= json_encode((float)$dest['longitude']) ?>;
  const green = getComputedStyle(document.documentElement).getPropertyValue('--color-main').trim() || '#008000';
  const map = L.map('destMap', { scrollWheelZoom: false }).setView([lat, lng], 11);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18, attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);
  const icon = L.divIcon({
    className: 'lg-route-pin',
    html: '<span style="background:' + green + ';color:#fff;border:2px solid #fff;border-radius:50% 50% 50% 0;transform:rotate(-45deg);width:26px;height:26px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.35)"><i class="bi bi-geo-alt-fill" style="transform:rotate(45deg)"></i></span>',
    iconSize: [26, 26], iconAnchor: [13, 26]
  });
  L.marker([lat, lng], { icon: icon }).addTo(map)
   .bindPopup('<strong><?= e(addslashes($dest['name'])) ?></strong>');
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
