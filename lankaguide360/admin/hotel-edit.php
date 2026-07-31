<?php
$pageTitle = 'Edit Hotel';
require __DIR__ . '/_layout_top.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$h = ['name'=>'','region'=>'','district'=>'','address'=>'','star_rating'=>3,'budget_tier'=>'medium','price_per_night_lkr'=>15000,'amenities'=>'','rating'=>4.3,'is_active'=>1];
if ($id) {
    $stmt = $db->prepare("SELECT * FROM hotels WHERE id = ?");
    $stmt->execute([$id]);
    if ($found = $stmt->fetch()) $h = $found;
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $region = trim($_POST['region'] ?? '');
    $tier = in_array($_POST['budget_tier'] ?? '', ['low','medium','high'], true) ? $_POST['budget_tier'] : 'medium';
    if ($name === '') $errors[] = 'Name is required.';
    if ($region === '') $errors[] = 'Region is required.';
    if (!$errors) {
        $vals = [
            $name, $region, trim($_POST['district'] ?? ''), trim($_POST['address'] ?? ''),
            max(1,min(5,(int)($_POST['star_rating'] ?? 3))), $tier,
            max(0,(float)($_POST['price_per_night_lkr'] ?? 0)), trim($_POST['amenities'] ?? ''),
            min(5,max(0,(float)($_POST['rating'] ?? 4.3))), isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($id) {
            $db->prepare("UPDATE hotels SET name=?,region=?,district=?,address=?,star_rating=?,budget_tier=?,price_per_night_lkr=?,amenities=?,rating=?,is_active=? WHERE id=?")
               ->execute([...$vals, $id]);
        } else {
            $db->prepare("INSERT INTO hotels (name,region,district,address,star_rating,budget_tier,price_per_night_lkr,amenities,rating,is_active) VALUES (?,?,?,?,?,?,?,?,?,?)")
               ->execute($vals);
        }
        flash('success', 'Hotel saved.');
        header('Location: hotels.php');
        exit;
    }
}
?>
<h3 class="mb-4"><?= $id ? 'Edit' : 'Add' ?> hotel</h3>
<?php foreach ($errors as $err): ?><div class="alert alert-danger py-2 small"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" class="admin-card" style="max-width:720px">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label small text-muted">Name</label><input type="text" name="name" class="form-control" required value="<?= e($h['name']) ?>"></div>
    <div class="col-md-6"><label class="form-label small text-muted">Region</label><input type="text" name="region" class="form-control" required value="<?= e($h['region']) ?>"></div>
    <div class="col-md-6"><label class="form-label small text-muted">District</label><input type="text" name="district" class="form-control" value="<?= e($h['district']) ?>"></div>
    <div class="col-md-6"><label class="form-label small text-muted">Address</label><input type="text" name="address" class="form-control" value="<?= e($h['address']) ?>"></div>
    <div class="col-md-3"><label class="form-label small text-muted">Star rating</label><input type="number" name="star_rating" min="1" max="5" class="form-control" value="<?= (int)$h['star_rating'] ?>"></div>
    <div class="col-md-3"><label class="form-label small text-muted">Budget tier</label>
      <select name="budget_tier" class="form-select">
        <?php foreach (['low','medium','high'] as $t): ?><option value="<?= $t ?>" <?= $h['budget_tier']===$t?'selected':'' ?>><?= ucfirst($t) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3"><label class="form-label small text-muted">Per night (LKR)</label><input type="number" step="0.01" name="price_per_night_lkr" class="form-control" value="<?= e($h['price_per_night_lkr']) ?>"></div>
    <div class="col-md-3"><label class="form-label small text-muted">Rating</label><input type="number" step="0.1" min="0" max="5" name="rating" class="form-control" value="<?= e($h['rating']) ?>"></div>
    <div class="col-12"><label class="form-label small text-muted">Amenities (comma separated)</label><input type="text" name="amenities" class="form-control" value="<?= e($h['amenities']) ?>"></div>
    <div class="col-md-3 form-check ms-2"><input type="checkbox" name="is_active" class="form-check-input" id="iah" <?= $h['is_active']?'checked':'' ?>><label class="form-check-label small" for="iah">Active</label></div>
  </div>
  <div class="mt-4 d-flex gap-2"><button class="btn btn-lg-primary">Save hotel</button><a href="hotels.php" class="btn btn-lg-outline">Cancel</a></div>
</form>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
