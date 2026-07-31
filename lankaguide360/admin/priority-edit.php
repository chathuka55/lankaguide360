<?php
$pageTitle = 'Edit Trip Priority';
require __DIR__ . '/_layout_top.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$priority = ['name'=>'','slug'=>'','description'=>'','icon'=>'bi-sliders'];
$weights = []; // category_id => weight

if ($id) {
    $stmt = $db->prepare("SELECT * FROM trip_priorities WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $priority = $found;

    $wStmt = $db->prepare("SELECT category_id, weight FROM priority_categories WHERE priority_id = ?");
    $wStmt->execute([$id]);
    foreach ($wStmt->fetchAll() as $row) {
        $weights[$row['category_id']] = (int)$row['weight'];
    }
}

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?: strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)), '-');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?: 'bi-sliders');
    $postWeights = $_POST['weight'] ?? []; // [category_id => weight]

    if ($name === '') $errors[] = 'Name is required.';

    if (!$errors) {
        if ($id) {
            $stmt = $db->prepare("UPDATE trip_priorities SET name=?, slug=?, description=?, icon=? WHERE id=?");
            $stmt->execute([$name,$slug,$description,$icon,$id]);
        } else {
            $stmt = $db->prepare("INSERT INTO trip_priorities (name, slug, description, icon) VALUES (?,?,?,?)");
            $stmt->execute([$name,$slug,$description,$icon]);
            $id = (int)$db->lastInsertId();
        }

        $db->prepare("DELETE FROM priority_categories WHERE priority_id = ?")->execute([$id]);
        $insertW = $db->prepare("INSERT INTO priority_categories (priority_id, category_id, weight) VALUES (?,?,?)");
        foreach ($postWeights as $catId => $weight) {
            $weight = (int)$weight;
            if ($weight > 0) {
                $insertW->execute([$id, (int)$catId, max(1, min(5, $weight))]);
            }
        }

        flash('success', 'Trip priority saved.');
        header('Location: priorities.php');
        exit;
    }
}
?>

<h3 class="mb-4"><?= $id ? 'Edit' : 'Add' ?> trip priority</h3>

<?php foreach ($errors as $err): ?><div class="alert alert-danger py-2 small"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="admin-card" style="max-width:720px">
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label small text-muted">Name</label>
      <input type="text" name="name" class="form-control" required value="<?= e($priority['name']) ?>" placeholder="e.g. Relaxation & Leisure">
    </div>
    <div class="col-md-6">
      <label class="form-label small text-muted">Slug (optional — auto-generated)</label>
      <input type="text" name="slug" class="form-control" value="<?= e($priority['slug']) ?>">
    </div>
    <div class="col-md-8">
      <label class="form-label small text-muted">Description (shown to visitors)</label>
      <input type="text" name="description" class="form-control" value="<?= e($priority['description']) ?>" placeholder="One short sentence">
    </div>
    <div class="col-md-4">
      <label class="form-label small text-muted">Bootstrap icon class</label>
      <input type="text" name="icon" class="form-control" value="<?= e($priority['icon']) ?>" placeholder="e.g. bi-cup-hot">
    </div>
  </div>

  <hr class="my-4">
  <label class="form-label small text-muted d-block mb-2">Category boost weights (0 = no effect, 5 = strong boost)</label>
  <div class="row g-3">
    <?php foreach ($categories as $c): ?>
      <div class="col-md-6">
        <div class="d-flex justify-content-between align-items-center border rounded-3 p-2 px-3">
          <span class="small"><i class="bi <?= e($c['icon'] ?: 'bi-geo') ?>"></i> <?= e($c['name']) ?></span>
          <select name="weight[<?= (int)$c['id'] ?>]" class="form-select form-select-sm" style="width:90px">
            <?php $w = $weights[$c['id']] ?? 0; ?>
            <?php for ($i = 0; $i <= 5; $i++): ?>
              <option value="<?= $i ?>" <?= $w===$i?'selected':'' ?>><?= $i ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-4 d-flex gap-2">
    <button class="btn btn-lg-primary">Save priority</button>
    <a href="priorities.php" class="btn btn-lg-outline">Cancel</a>
  </div>
</form>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
