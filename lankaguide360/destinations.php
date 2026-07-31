<?php
require_once __DIR__ . '/config/app.php';
$pageTitle = 'Destinations — LankaGuide 360';
$db = getDbConnection();

$region = trim($_GET['region'] ?? '');
$categorySlug = trim($_GET['category'] ?? '');
$budget = trim($_GET['budget'] ?? '');
$popularity = trim($_GET['popularity'] ?? '');
$search = trim($_GET['q'] ?? '');

$sql = "SELECT DISTINCT d.* FROM destinations d
        LEFT JOIN destination_categories dc ON dc.destination_id = d.id
        LEFT JOIN categories c ON c.id = dc.category_id
        WHERE d.is_active = 1";
$params = [];

if ($region !== '') { $sql .= " AND d.region = ?"; $params[] = $region; }
if ($budget !== '')  { $sql .= " AND d.budget_tier = ?"; $params[] = $budget; }
if ($popularity !== '' && in_array($popularity, ['popular','hidden_gem'], true)) { $sql .= " AND d.popularity_tier = ?"; $params[] = $popularity; }
if ($categorySlug !== '') { $sql .= " AND c.slug = ?"; $params[] = $categorySlug; }
if ($search !== '') { $sql .= " AND (d.name LIKE ? OR d.short_description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$sql .= " ORDER BY d.rating DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$destinations = $stmt->fetchAll();

$regions = $db->query("SELECT DISTINCT region FROM destinations WHERE is_active=1 ORDER BY region")->fetchAll(PDO::FETCH_COLUMN);
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="section-pad" style="padding-top:3rem">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">Explore</span>
      <h2>All destinations</h2>
      <p>Filter by region, interest or budget to narrow down the island.</p>
    </div>

    <form class="row g-3 align-items-end admin-card mb-5" method="get">
      <div class="col-md-3">
        <label class="form-label small text-muted">Search</label>
        <input type="text" name="q" class="form-control" placeholder="e.g. beach, temple…" value="<?= e($search) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted">Region</label>
        <select name="region" class="form-select">
          <option value="">All regions</option>
          <?php foreach ($regions as $r): ?>
            <option value="<?= e($r) ?>" <?= $region===$r?'selected':'' ?>><?= e($r) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted">Interest</label>
        <select name="category" class="form-select">
          <option value="">All interests</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= e($c['slug']) ?>" <?= $categorySlug===$c['slug']?'selected':'' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted">Budget</label>
        <select name="budget" class="form-select">
          <option value="">Any</option>
          <option value="low" <?= $budget==='low'?'selected':'' ?>>Low</option>
          <option value="medium" <?= $budget==='medium'?'selected':'' ?>>Medium</option>
          <option value="high" <?= $budget==='high'?'selected':'' ?>>High</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted">Popularity</label>
        <select name="popularity" class="form-select">
          <option value="">Any</option>
          <option value="popular" <?= $popularity==='popular'?'selected':'' ?>>Popular</option>
          <option value="hidden_gem" <?= $popularity==='hidden_gem'?'selected':'' ?>>Hidden gem</option>
        </select>
      </div>
      <div class="col-md-1">
        <button class="btn btn-lg-primary w-100"><i class="bi bi-search"></i></button>
      </div>
    </form>

    <p class="text-muted small mb-4"><?= count($destinations) ?> destination<?= count($destinations)===1?'':'s' ?> found</p>

    <div class="row g-4">
      <?php foreach ($destinations as $d): ?>
      <div class="col-md-6 col-lg-4">
        <a href="<?= url('destination-details.php?slug=' . urlencode($d['slug'])) ?>" class="dest-card d-block">
          <?php $img = img_src($d['image_url']); ?>
          <div class="img-wrap<?= $img ? ' has-photo' : '' ?>">
            <?php if ($img): ?><img src="<?= e($img) ?>" alt="<?= e($d['name']) ?>" class="img-cover" loading="lazy"><?php endif; ?>
            <span class="badge-region"><?= e($d['region']) ?></span>
            <?php if (!$img): ?><i class="bi bi-image"></i><?php endif; ?>
          </div>
          <div class="body">
            <h5><?= e($d['name']) ?> <span class="tag-pill" style="vertical-align:middle"><?= $d['popularity_tier']==='popular' ? 'Popular' : 'Hidden gem' ?></span></h5>
            <p class="text-muted small mb-0"><?= e($d['short_description']) ?></p>
            <div class="meta">
              <span class="text-capitalize"><i class="bi bi-wallet2"></i> <?= e($d['budget_tier']) ?> budget</span>
              <span class="rating"><i class="bi bi-star-fill"></i> <?= number_format((float)$d['rating'],1) ?></span>
            </div>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
      <?php if (!$destinations): ?>
        <div class="col-12 text-center text-muted py-5">No destinations match those filters yet — try widening your search.</div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
