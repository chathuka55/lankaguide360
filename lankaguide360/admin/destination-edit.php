<?php
$pageTitle = 'Edit Destination';
require __DIR__ . '/_layout_top.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$dest = ['name'=>'','slug'=>'','region'=>'','district'=>'','location_id'=>null,'short_description'=>'','full_description'=>'',
         'best_season'=>'','avg_visit_hours'=>2,'entry_fee_lkr'=>0,'budget_tier'=>'medium','popularity_tier'=>'popular',
         'latitude'=>'','longitude'=>'','rating'=>4.0,'is_active'=>1];
$selectedCats = [];
$locations = $db->query("SELECT * FROM locations WHERE is_active = 1 ORDER BY region, name")->fetchAll();

if ($id) {
    $stmt = $db->prepare("SELECT * FROM destinations WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $dest = $found;
    $catStmt = $db->prepare("SELECT category_id FROM destination_categories WHERE destination_id = ?");
    $catStmt->execute([$id]);
    $selectedCats = $catStmt->fetchAll(PDO::FETCH_COLUMN);
}

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?: strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)), '-');
    $region = trim($_POST['region'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $locationId = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;
    $shortDesc = trim($_POST['short_description'] ?? '');
    $fullDesc = trim($_POST['full_description'] ?? '');
    $bestSeason = trim($_POST['best_season'] ?? '');
    $avgHours = (float)($_POST['avg_visit_hours'] ?? 2);
    $entryFee = (float)($_POST['entry_fee_lkr'] ?? 0);
    $budgetTier = in_array($_POST['budget_tier'] ?? '', ['low','medium','high'], true) ? $_POST['budget_tier'] : 'medium';
    $popularityTier = in_array($_POST['popularity_tier'] ?? '', ['popular','hidden_gem'], true) ? $_POST['popularity_tier'] : 'popular';
    $lat = $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
    $lng = $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
    $rating = (float)($_POST['rating'] ?? 4.0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $cats = $_POST['categories'] ?? [];

    if ($name === '') $errors[] = 'Name is required.';
    if ($region === '') $errors[] = 'Region is required.';
    if ($shortDesc === '') $errors[] = 'Short description is required.';

    if (!$errors) {
        if ($id) {
            $stmt = $db->prepare(
                "UPDATE destinations SET name=?, slug=?, region=?, district=?, location_id=?, short_description=?, full_description=?,
                 best_season=?, avg_visit_hours=?, entry_fee_lkr=?, budget_tier=?, popularity_tier=?, latitude=?, longitude=?, rating=?, is_active=?
                 WHERE id=?"
            );
            $stmt->execute([$name,$slug,$region,$district,$locationId,$shortDesc,$fullDesc,$bestSeason,$avgHours,$entryFee,$budgetTier,$popularityTier,$lat,$lng,$rating,$isActive,$id]);
        } else {
            $stmt = $db->prepare(
                "INSERT INTO destinations (name, slug, region, district, location_id, short_description, full_description, best_season,
                 avg_visit_hours, entry_fee_lkr, budget_tier, popularity_tier, latitude, longitude, rating, is_active, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([$name,$slug,$region,$district,$locationId,$shortDesc,$fullDesc,$bestSeason,$avgHours,$entryFee,$budgetTier,$popularityTier,$lat,$lng,$rating,$isActive,$_SESSION['admin_id']]);
            $id = (int)$db->lastInsertId();
        }

        $db->prepare("DELETE FROM destination_categories WHERE destination_id = ?")->execute([$id]);
        $catStmt = $db->prepare("INSERT INTO destination_categories (destination_id, category_id, weight) VALUES (?,?,4)");
        foreach ($cats as $catId) {
            $catStmt->execute([$id, (int)$catId]);
        }

        flash('success', 'Destination saved.');
        header('Location: destinations.php');
        exit;
    }
}
?>

<h3 class="mb-4"><?= $id ? 'Edit' : 'Add' ?> destination</h3>

<?php foreach ($errors as $err): ?><div class="alert alert-danger py-2 small"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="admin-card">
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label small text-muted">Name</label>
      <input type="text" name="name" class="form-control" required value="<?= e($dest['name']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label small text-muted">Slug (URL, optional — auto-generated)</label>
      <input type="text" name="slug" class="form-control" value="<?= e($dest['slug']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label small text-muted">Region</label>
      <input type="text" name="region" class="form-control" required value="<?= e($dest['region']) ?>" placeholder="e.g. Hill Country">
    </div>
    <div class="col-md-6">
      <label class="form-label small text-muted">District</label>
      <input type="text" name="district" class="form-control" value="<?= e($dest['district']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label small text-muted">Nearest city/town</label>
      <select name="location_id" class="form-select">
        <option value="">— None —</option>
        <?php $lastRegion = null; foreach ($locations as $loc): ?>
          <?php if ($loc['region'] !== $lastRegion): if ($lastRegion !== null) echo '</optgroup>'; echo '<optgroup label="' . e($loc['region']) . '">'; $lastRegion = $loc['region']; endif; ?>
          <option value="<?= (int)$loc['id'] ?>" <?= (string)$dest['location_id']===(string)$loc['id']?'selected':'' ?>><?= e($loc['name']) ?></option>
        <?php endforeach; if ($lastRegion !== null) echo '</optgroup>'; ?>
      </select>
      <div class="form-text">Used to match "Cities/Towns to Visit" on the trip planner.</div>
    </div>
    <div class="col-md-6">
      <label class="form-label small text-muted">Popularity</label>
      <select name="popularity_tier" class="form-select">
        <option value="popular" <?= $dest['popularity_tier']==='popular'?'selected':'' ?>>Popular attraction</option>
        <option value="hidden_gem" <?= $dest['popularity_tier']==='hidden_gem'?'selected':'' ?>>Hidden gem</option>
      </select>
      <div class="form-text">Controls the "Places you'd like to visit" filter on the trip planner.</div>
    </div>
    <div class="col-12">
      <label class="form-label small text-muted">Short description (used on cards)</label>
      <input type="text" name="short_description" class="form-control" required maxlength="255" value="<?= e($dest['short_description']) ?>">
    </div>
    <div class="col-12">
      <label class="form-label small text-muted">Full description</label>
      <textarea name="full_description" rows="5" class="form-control"><?= e($dest['full_description']) ?></textarea>
    </div>
    <div class="col-md-3">
      <label class="form-label small text-muted">Best season</label>
      <input type="text" name="best_season" class="form-control" value="<?= e($dest['best_season']) ?>" placeholder="e.g. Dec–Mar">
    </div>
    <div class="col-md-3">
      <label class="form-label small text-muted">Avg. visit hours</label>
      <input type="number" step="0.5" name="avg_visit_hours" class="form-control" value="<?= e((string)$dest['avg_visit_hours']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small text-muted">Entry fee (LKR)</label>
      <input type="number" step="0.01" name="entry_fee_lkr" class="form-control" value="<?= e((string)$dest['entry_fee_lkr']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small text-muted">Budget tier</label>
      <select name="budget_tier" class="form-select">
        <?php foreach (['low','medium','high'] as $tier): ?>
          <option value="<?= $tier ?>" <?= $dest['budget_tier']===$tier?'selected':'' ?>><?= ucfirst($tier) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small text-muted">Latitude</label>
      <input type="text" name="latitude" class="form-control" value="<?= e((string)$dest['latitude']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small text-muted">Longitude</label>
      <input type="text" name="longitude" class="form-control" value="<?= e((string)$dest['longitude']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small text-muted">Rating (0–5)</label>
      <input type="number" step="0.1" min="0" max="5" name="rating" class="form-control" value="<?= e((string)$dest['rating']) ?>">
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <div class="form-check">
        <input type="checkbox" name="is_active" class="form-check-input" id="isActive" <?= $dest['is_active']?'checked':'' ?>>
        <label class="form-check-label small" for="isActive">Visible on site</label>
      </div>
    </div>

    <div class="col-12">
      <label class="form-label small text-muted d-block">Travel interest categories</label>
      <?php foreach ($categories as $c): ?>
        <div class="form-check form-check-inline">
          <input type="checkbox" class="form-check-input" name="categories[]" value="<?= (int)$c['id'] ?>" id="cat<?= (int)$c['id'] ?>"
            <?= in_array($c['id'], $selectedCats, true) ? 'checked' : '' ?>>
          <label class="form-check-label small" for="cat<?= (int)$c['id'] ?>"><?= e($c['name']) ?></label>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="mt-4 d-flex gap-2">
    <button class="btn btn-lg-primary">Save destination</button>
    <a href="destinations.php" class="btn btn-lg-outline">Cancel</a>
  </div>
</form>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
