<?php
$pageTitle = 'Edit Driver';
require __DIR__ . '/_layout_top.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$d = ['full_name'=>'','email'=>'','phone'=>'','region'=>'','license_no'=>'','languages'=>'English','has_own_vehicle'=>0,'daily_rate_lkr'=>5000,'rating'=>4.5,'is_active'=>1];
if ($id) {
    $stmt = $db->prepare("SELECT * FROM drivers WHERE id = ?");
    $stmt->execute([$id]);
    if ($found = $stmt->fetch()) $d = $found;
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $region = trim($_POST['region'] ?? '');
    if ($name === '') $errors[] = 'Name is required.';
    if ($region === '') $errors[] = 'Region is required.';
    if (!$errors) {
        $vals = [
            $name, trim($_POST['email'] ?? ''), trim($_POST['phone'] ?? ''), $region,
            trim($_POST['license_no'] ?? ''), trim($_POST['languages'] ?? 'English'),
            isset($_POST['has_own_vehicle']) ? 1 : 0, max(0,(float)($_POST['daily_rate_lkr'] ?? 0)),
            min(5,max(0,(float)($_POST['rating'] ?? 4.5))), isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($id) {
            $db->prepare("UPDATE drivers SET full_name=?,email=?,phone=?,region=?,license_no=?,languages=?,has_own_vehicle=?,daily_rate_lkr=?,rating=?,is_active=? WHERE id=?")
               ->execute([...$vals, $id]);
        } else {
            $db->prepare("INSERT INTO drivers (full_name,email,phone,region,license_no,languages,has_own_vehicle,daily_rate_lkr,rating,is_active) VALUES (?,?,?,?,?,?,?,?,?,?)")
               ->execute($vals);
        }
        flash('success', 'Driver saved.');
        header('Location: drivers.php');
        exit;
    }
}
?>
<h3 class="mb-4"><?= $id ? 'Edit' : 'Add' ?> driver</h3>
<?php foreach ($errors as $err): ?><div class="alert alert-danger py-2 small"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" class="admin-card" style="max-width:720px">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label small text-muted">Full name</label><input type="text" name="full_name" class="form-control" required value="<?= e($d['full_name']) ?>"></div>
    <div class="col-md-6"><label class="form-label small text-muted">Region</label><input type="text" name="region" class="form-control" required value="<?= e($d['region']) ?>"></div>
    <div class="col-md-6"><label class="form-label small text-muted">Email</label><input type="email" name="email" class="form-control" value="<?= e($d['email']) ?>"></div>
    <div class="col-md-6"><label class="form-label small text-muted">Phone</label><input type="text" name="phone" class="form-control" value="<?= e($d['phone']) ?>"></div>
    <div class="col-md-6"><label class="form-label small text-muted">Licence no.</label><input type="text" name="license_no" class="form-control" value="<?= e($d['license_no']) ?>"></div>
    <div class="col-md-6"><label class="form-label small text-muted">Languages</label><input type="text" name="languages" class="form-control" value="<?= e($d['languages']) ?>"></div>
    <div class="col-md-3"><label class="form-label small text-muted">Daily rate (LKR)</label><input type="number" step="0.01" name="daily_rate_lkr" class="form-control" value="<?= e($d['daily_rate_lkr']) ?>"></div>
    <div class="col-md-3"><label class="form-label small text-muted">Rating</label><input type="number" step="0.1" min="0" max="5" name="rating" class="form-control" value="<?= e($d['rating']) ?>"></div>
    <div class="col-md-3 form-check ms-2"><input type="checkbox" name="has_own_vehicle" class="form-check-input" id="hv" <?= $d['has_own_vehicle']?'checked':'' ?>><label class="form-check-label small" for="hv">Owns a vehicle</label></div>
    <div class="col-md-2 form-check"><input type="checkbox" name="is_active" class="form-check-input" id="ia" <?= $d['is_active']?'checked':'' ?>><label class="form-check-label small" for="ia">Active</label></div>
  </div>
  <div class="mt-4 d-flex gap-2"><button class="btn btn-lg-primary">Save driver</button><a href="drivers.php" class="btn btn-lg-outline">Cancel</a></div>
</form>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
