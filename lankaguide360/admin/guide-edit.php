<?php
$pageTitle = 'Edit Guide';
require __DIR__ . '/_layout_top.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$g = ['full_name'=>'','email'=>'','phone'=>'','region'=>'','languages'=>'English','is_regional'=>1,'has_own_vehicle'=>0,'daily_rate_lkr'=>8000,'rating'=>4.5,'bio'=>'','is_active'=>1];
if ($id) {
    $stmt = $db->prepare("SELECT * FROM guides WHERE id = ?");
    $stmt->execute([$id]);
    if ($found = $stmt->fetch()) $g = $found;
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $region = trim($_POST['region'] ?? '');
    $languages = trim($_POST['languages'] ?? 'English');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $isRegional = isset($_POST['is_regional']) ? 1 : 0;
    $hasVehicle = isset($_POST['has_own_vehicle']) ? 1 : 0;
    $rate = max(0, (float)($_POST['daily_rate_lkr'] ?? 0));
    $rating = min(5, max(0, (float)($_POST['rating'] ?? 4.5)));
    $bio = trim($_POST['bio'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    if ($name === '') $errors[] = 'Name is required.';
    if ($region === '') $errors[] = 'Region is required.';
    if (!$errors) {
        if ($id) {
            $db->prepare("UPDATE guides SET full_name=?,email=?,phone=?,region=?,languages=?,is_regional=?,has_own_vehicle=?,daily_rate_lkr=?,rating=?,bio=?,is_active=? WHERE id=?")
               ->execute([$name,$email,$phone,$region,$languages,$isRegional,$hasVehicle,$rate,$rating,$bio,$isActive,$id]);
        } else {
            $db->prepare("INSERT INTO guides (full_name,email,phone,region,languages,is_regional,has_own_vehicle,daily_rate_lkr,rating,bio,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$name,$email,$phone,$region,$languages,$isRegional,$hasVehicle,$rate,$rating,$bio,$isActive]);
        }
        flash('success', 'Guide saved.');
        header('Location: guides.php');
        exit;
    }
}
?>
<h3 class="mb-4"><?= $id ? 'Edit' : 'Add' ?> guide</h3>
<?php foreach ($errors as $err): ?><div class="alert alert-danger py-2 small"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" class="admin-card" style="max-width:720px">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label small text-muted">Full name</label><input type="text" name="full_name" class="form-control" required value="<?= e($g['full_name']) ?>"></div>
    <div class="col-md-6"><label class="form-label small text-muted">Region</label><input type="text" name="region" class="form-control" required value="<?= e($g['region']) ?>" placeholder="e.g. Hill Country or Island-wide"></div>
    <div class="col-md-6"><label class="form-label small text-muted">Email</label><input type="email" name="email" class="form-control" value="<?= e($g['email']) ?>"></div>
    <div class="col-md-6"><label class="form-label small text-muted">Phone</label><input type="text" name="phone" class="form-control" value="<?= e($g['phone']) ?>"></div>
    <div class="col-md-6"><label class="form-label small text-muted">Languages (comma separated)</label><input type="text" name="languages" class="form-control" value="<?= e($g['languages']) ?>"></div>
    <div class="col-md-3"><label class="form-label small text-muted">Daily rate (LKR)</label><input type="number" step="0.01" name="daily_rate_lkr" class="form-control" value="<?= e($g['daily_rate_lkr']) ?>"></div>
    <div class="col-md-3"><label class="form-label small text-muted">Rating</label><input type="number" step="0.1" min="0" max="5" name="rating" class="form-control" value="<?= e($g['rating']) ?>"></div>
    <div class="col-12"><label class="form-label small text-muted">Bio</label><textarea name="bio" rows="2" class="form-control"><?= e($g['bio']) ?></textarea></div>
    <div class="col-md-4 form-check ms-2"><input type="checkbox" name="is_regional" class="form-check-input" id="isReg" <?= $g['is_regional']?'checked':'' ?>><label class="form-check-label small" for="isReg">Regional (uncheck for island-wide)</label></div>
    <div class="col-md-4 form-check"><input type="checkbox" name="has_own_vehicle" class="form-check-input" id="hasVeh" <?= $g['has_own_vehicle']?'checked':'' ?>><label class="form-check-label small" for="hasVeh">Owns a vehicle</label></div>
    <div class="col-md-3 form-check"><input type="checkbox" name="is_active" class="form-check-input" id="isAct" <?= $g['is_active']?'checked':'' ?>><label class="form-check-label small" for="isAct">Active</label></div>
  </div>
  <div class="mt-4 d-flex gap-2"><button class="btn btn-lg-primary">Save guide</button><a href="guides.php" class="btn btn-lg-outline">Cancel</a></div>
</form>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
