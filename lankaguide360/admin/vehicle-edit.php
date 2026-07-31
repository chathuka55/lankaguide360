<?php
$pageTitle = 'Edit Vehicle';
require __DIR__ . '/_layout_top.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$v = ['name'=>'','type'=>'van','registration_no'=>'','seats'=>4,'region'=>'','has_driver'=>1,'is_rentable'=>1,'owner_type'=>'company','owner_ref_id'=>null,'rate_per_day_lkr'=>8000,'is_active'=>1];
if ($id) {
    $stmt = $db->prepare("SELECT * FROM vehicles WHERE id = ?");
    $stmt->execute([$id]);
    if ($found = $stmt->fetch()) $v = $found;
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $type = in_array($_POST['type'] ?? '', ['car','van','suv','bus','tuktuk','jeep'], true) ? $_POST['type'] : 'car';
    $region = trim($_POST['region'] ?? '');
    $ownerType = in_array($_POST['owner_type'] ?? '', ['company','guide','driver'], true) ? $_POST['owner_type'] : 'company';
    if ($name === '') $errors[] = 'Name is required.';
    if ($region === '') $errors[] = 'Region is required.';
    if (!$errors) {
        $vals = [
            $name, $type, trim($_POST['registration_no'] ?? ''), max(1,(int)($_POST['seats'] ?? 4)), $region,
            isset($_POST['has_driver']) ? 1 : 0, isset($_POST['is_rentable']) ? 1 : 0,
            $ownerType, !empty($_POST['owner_ref_id']) ? (int)$_POST['owner_ref_id'] : null,
            max(0,(float)($_POST['rate_per_day_lkr'] ?? 0)), isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($id) {
            $db->prepare("UPDATE vehicles SET name=?,type=?,registration_no=?,seats=?,region=?,has_driver=?,is_rentable=?,owner_type=?,owner_ref_id=?,rate_per_day_lkr=?,is_active=? WHERE id=?")
               ->execute([...$vals, $id]);
        } else {
            $db->prepare("INSERT INTO vehicles (name,type,registration_no,seats,region,has_driver,is_rentable,owner_type,owner_ref_id,rate_per_day_lkr,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute($vals);
        }
        flash('success', 'Vehicle saved.');
        header('Location: vehicles.php');
        exit;
    }
}
?>
<h3 class="mb-4"><?= $id ? 'Edit' : 'Add' ?> vehicle</h3>
<?php foreach ($errors as $err): ?><div class="alert alert-danger py-2 small"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" class="admin-card" style="max-width:720px">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label small text-muted">Name / model</label><input type="text" name="name" class="form-control" required value="<?= e($v['name']) ?>" placeholder="e.g. Toyota HiAce"></div>
    <div class="col-md-3"><label class="form-label small text-muted">Type</label>
      <select name="type" class="form-select">
        <?php foreach (['car','van','suv','bus','tuktuk','jeep'] as $t): ?><option value="<?= $t ?>" <?= $v['type']===$t?'selected':'' ?>><?= ucfirst($t) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3"><label class="form-label small text-muted">Seats</label><input type="number" name="seats" min="1" class="form-control" value="<?= (int)$v['seats'] ?>"></div>
    <div class="col-md-6"><label class="form-label small text-muted">Registration no.</label><input type="text" name="registration_no" class="form-control" value="<?= e($v['registration_no']) ?>"></div>
    <div class="col-md-6"><label class="form-label small text-muted">Region</label><input type="text" name="region" class="form-control" required value="<?= e($v['region']) ?>"></div>
    <div class="col-md-4"><label class="form-label small text-muted">Owner type</label>
      <select name="owner_type" class="form-select">
        <?php foreach (['company','guide','driver'] as $o): ?><option value="<?= $o ?>" <?= $v['owner_type']===$o?'selected':'' ?>><?= ucfirst($o) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4"><label class="form-label small text-muted">Owner ref id (guide/driver id)</label><input type="number" name="owner_ref_id" class="form-control" value="<?= e($v['owner_ref_id']) ?>"></div>
    <div class="col-md-4"><label class="form-label small text-muted">Rate/day (LKR)</label><input type="number" step="0.01" name="rate_per_day_lkr" class="form-control" value="<?= e($v['rate_per_day_lkr']) ?>"></div>
    <div class="col-md-4 form-check ms-2"><input type="checkbox" name="has_driver" class="form-check-input" id="hd" <?= $v['has_driver']?'checked':'' ?>><label class="form-check-label small" for="hd">Comes with a driver</label></div>
    <div class="col-md-4 form-check"><input type="checkbox" name="is_rentable" class="form-check-input" id="ir" <?= $v['is_rentable']?'checked':'' ?>><label class="form-check-label small" for="ir">Rentable</label></div>
    <div class="col-md-3 form-check"><input type="checkbox" name="is_active" class="form-check-input" id="iav" <?= $v['is_active']?'checked':'' ?>><label class="form-check-label small" for="iav">Active</label></div>
  </div>
  <div class="mt-4 d-flex gap-2"><button class="btn btn-lg-primary">Save vehicle</button><a href="vehicles.php" class="btn btn-lg-outline">Cancel</a></div>
</form>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
