<?php
$pageTitle = 'Edit Location';
require __DIR__ . '/_layout_top.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$loc = ['name'=>'','slug'=>'','type'=>'town','region'=>'','is_hub'=>0,'is_active'=>1];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM locations WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $loc = $found;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?: strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)), '-');
    $type = in_array($_POST['type'] ?? '', ['city','town'], true) ? $_POST['type'] : 'town';
    $region = trim($_POST['region'] ?? '');
    $isHub = isset($_POST['is_hub']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') $errors[] = 'Name is required.';
    if ($region === '') $errors[] = 'Region is required.';

    if (!$errors) {
        if ($id) {
            $stmt = $db->prepare("UPDATE locations SET name=?, slug=?, type=?, region=?, is_hub=?, is_active=? WHERE id=?");
            $stmt->execute([$name,$slug,$type,$region,$isHub,$isActive,$id]);
        } else {
            $stmt = $db->prepare("INSERT INTO locations (name, slug, type, region, is_hub, is_active) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$name,$slug,$type,$region,$isHub,$isActive]);
        }
        flash('success', 'Location saved.');
        header('Location: locations.php');
        exit;
    }
}
?>

<h3 class="mb-4"><?= $id ? 'Edit' : 'Add' ?> location</h3>

<?php foreach ($errors as $err): ?><div class="alert alert-danger py-2 small"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="admin-card" style="max-width:640px">
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label small text-muted">Name</label>
      <input type="text" name="name" class="form-control" required value="<?= e($loc['name']) ?>" placeholder="e.g. Kandy">
    </div>
    <div class="col-md-6">
      <label class="form-label small text-muted">Slug (optional — auto-generated)</label>
      <input type="text" name="slug" class="form-control" value="<?= e($loc['slug']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label small text-muted">Type</label>
      <select name="type" class="form-select">
        <option value="city" <?= $loc['type']==='city'?'selected':'' ?>>City</option>
        <option value="town" <?= $loc['type']==='town'?'selected':'' ?>>Town</option>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label small text-muted">Region</label>
      <input type="text" name="region" class="form-control" required value="<?= e($loc['region']) ?>" placeholder="e.g. Hill Country">
    </div>
    <div class="col-md-6 d-flex align-items-center">
      <div class="form-check">
        <input type="checkbox" name="is_hub" class="form-check-input" id="isHub" <?= $loc['is_hub']?'checked':'' ?>>
        <label class="form-check-label small" for="isHub">Common arrival hub (e.g. Colombo, airport town)</label>
      </div>
    </div>
    <div class="col-md-6 d-flex align-items-center">
      <div class="form-check">
        <input type="checkbox" name="is_active" class="form-check-input" id="isActive" <?= $loc['is_active']?'checked':'' ?>>
        <label class="form-check-label small" for="isActive">Available on the trip planner</label>
      </div>
    </div>
  </div>
  <div class="mt-4 d-flex gap-2">
    <button class="btn btn-lg-primary">Save location</button>
    <a href="locations.php" class="btn btn-lg-outline">Cancel</a>
  </div>
</form>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
