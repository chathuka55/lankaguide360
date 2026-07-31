<?php
require_once __DIR__ . '/config/app.php';
$pageTitle = 'LankaGuide 360 — Intelligent Sri Lanka Trip Planning';

$db = getDbConnection();
$featured = $db->query(
    "SELECT * FROM destinations WHERE is_active=1 ORDER BY rating DESC LIMIT 6"
)->fetchAll();

// Slides for the hero slideshow — only destinations that already have a photo
$slides = $db->query(
    "SELECT name, region, district, image_url FROM destinations
     WHERE is_active=1 AND image_url IS NOT NULL AND image_url <> ''
     ORDER BY rating DESC LIMIT 5"
)->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="hero-carousel">
  <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4500">
    <div class="carousel-inner">
      <?php if (!$slides): ?>
        <div class="carousel-item active"><div class="hero-slide-mask" style="position:absolute;inset:0;background:linear-gradient(135deg,var(--color-royal),var(--green-700))"></div></div>
      <?php endif; ?>
      <?php foreach ($slides as $i => $s): ?>
      <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
        <img class="hero-slide-img" src="<?= e(img_src($s['image_url'])) ?>" alt="<?= e($s['name']) ?>" <?= $i === 0 ? '' : 'loading="lazy"' ?>>
        <div class="hero-slide-mask"></div>
        <span class="slide-caption"><i class="bi bi-geo-alt-fill"></i> <?= e($s['name']) ?><?= $s['region'] ? ' · ' . e($s['region']) : '' ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if (count($slides) > 1): ?>
    <div class="carousel-indicators">
      <?php foreach ($slides as $i => $s): ?>
      <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $i ?>" <?= $i === 0 ? 'class="active" aria-current="true"' : '' ?> aria-label="Slide <?= $i + 1 ?>"></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="hero-overlay">
    <div class="container">
      <div class="row">
        <div class="col-lg-7">
          <span class="eyebrow hero-eyebrow">Intelligent Tourism Management System</span>
          <h1>Sri Lanka, <em>planned intelligently</em> around you.</h1>
          <p class="lead">LankaGuide 360 blends destination data, your travel preferences, and a built-in tourism assistant to build a route across the island — beaches, hill country, wildlife and heritage — in minutes.</p>
          <div class="d-flex gap-3 flex-wrap mt-4">
            <a href="<?= url('trip-planner.php') ?>" class="lg-btn-cta"><i class="bi bi-compass"></i>&nbsp; Start planning</a>
            <a href="<?= url('destinations.php') ?>" class="btn btn-lg-outline" style="border-color:#fff;color:#fff">Browse destinations</a>
          </div>
          <div class="hero-stats">
            <div><div class="stat-num">12+</div><div class="stat-label">Curated destinations</div></div>
            <div><div class="stat-num">8</div><div class="stat-label">Travel interests</div></div>
            <div><div class="stat-num">24/7</div><div class="stat-label">Bot360 support</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section-pad">
  <div class="container">
    <div class="d-flex justify-content-between align-items-end flex-wrap section-head mx-auto" style="max-width:100%">
      <div>
        <span class="eyebrow">Handpicked</span>
        <h2>Top-rated destinations right now</h2>
        <p>Scored on visitor rating and matched against Sri Lanka's eight core travel interests.</p>
      </div>
      <a href="<?= url('destinations.php') ?>" class="btn btn-lg-outline">View all destinations</a>
    </div>

    <div class="row g-4">
      <?php foreach ($featured as $d): ?>
      <div class="col-md-6 col-lg-4">
        <a href="<?= url('destination-details.php?slug=' . urlencode($d['slug'])) ?>" class="dest-card d-block">
          <?php $img = img_src($d['image_url']); ?>
          <div class="img-wrap<?= $img ? ' has-photo' : '' ?>">
            <?php if ($img): ?><img src="<?= e($img) ?>" alt="<?= e($d['name']) ?>" class="img-cover" loading="lazy"><?php endif; ?>
            <span class="badge-region"><?= e($d['region']) ?></span>
            <?php if (!$img): ?><i class="bi bi-image"></i><?php endif; ?>
          </div>
          <div class="body">
            <h5><?= e($d['name']) ?></h5>
            <p class="text-muted small mb-0"><?= e($d['short_description']) ?></p>
            <div class="meta">
              <span><i class="bi bi-geo-alt"></i> <?= e($d['district']) ?></span>
              <span class="rating"><i class="bi bi-star-fill"></i> <?= number_format((float)$d['rating'],1) ?></span>
            </div>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section-pad bg-sand">
  <div class="container">
    <div class="section-head mx-auto text-center" style="max-width:640px">
      <span class="eyebrow">How it works</span>
      <h2>Three steps to your island route</h2>
    </div>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="admin-card h-100">
          <div class="step-num">1</div><strong>Tell us your interests</strong>
          <p class="text-muted small mt-2 mb-0">Beaches, wildlife, culture, adventure — pick what matters, plus your budget and trip length.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="admin-card h-100">
          <div class="step-num">2</div><strong>Get a scored itinerary</strong>
          <p class="text-muted small mt-2 mb-0">Our recommendation engine matches destinations to your preferences and lays out a day-by-day route.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="admin-card h-100">
          <div class="step-num">3</div><strong>Ask Bot360, then book</strong>
          <p class="text-muted small mt-2 mb-0">Chat with Bot360, our tourism assistant, for questions, and submit an optional booking request when ready.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
