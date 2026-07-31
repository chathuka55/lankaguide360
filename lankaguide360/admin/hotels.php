<?php
$pageTitle = 'Hotels';
require __DIR__ . '/_layout_top.php';

if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM hotels WHERE id = ?")->execute([(int)$_GET['delete']]);
    flash('success', 'Hotel deleted.');
    header('Location: hotels.php');
    exit;
}
$hotels = $db->query("SELECT * FROM hotels ORDER BY region, star_rating DESC")->fetchAll();
$successMsg = flash('success');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h3 class="mb-0">Hotels</h3><p class="text-muted mb-0 small">Accommodation matched to budget tier &amp; region for itineraries.</p></div>
  <a href="hotel-edit.php" class="btn btn-lg-primary"><i class="bi bi-plus-lg"></i> Add hotel</a>
</div>
<?php if ($successMsg): ?><div class="alert alert-success py-2 small"><?= e($successMsg) ?></div><?php endif; ?>
<div class="admin-card">
  <table class="table table-lg align-middle">
    <thead><tr><th>Name</th><th>Region</th><th>Stars</th><th>Budget</th><th>Per night</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($hotels as $h): ?>
      <tr>
        <td class="fw-semibold">
          <div class="d-flex align-items-center gap-2">
            <?php $ht = img_src($h['image_url'] ?? null); ?>
            <?php if ($ht): ?><img class="lg-thumb lg-avatar-sm" style="border-radius:8px" src="<?= e($ht) ?>" alt=""><?php else: ?><i class="bi bi-building text-muted"></i><?php endif; ?>
            <span><?= e($h['name']) ?><div class="small text-muted fw-normal"><?= e($h['district']) ?></div></span>
          </div>
        </td>
        <td class="small text-muted"><?= e($h['region']) ?></td>
        <td class="small"><?= str_repeat('★', (int)$h['star_rating']) ?></td>
        <td class="small text-capitalize"><?= e($h['budget_tier']) ?></td>
        <td class="small"><?= lkr($h['price_per_night_lkr']) ?></td>
        <td><span class="badge text-bg-<?= $h['is_active']?'success':'secondary' ?>"><?= $h['is_active']?'Active':'Hidden' ?></span></td>
        <td class="text-end">
          <a href="hotel-edit.php?id=<?= (int)$h['id'] ?>" class="btn btn-sm btn-lg-outline"><i class="bi bi-pencil"></i></a>
          <a href="hotels.php?delete=<?= (int)$h['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this hotel?')"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$hotels): ?><tr><td colspan="7" class="text-muted small">No hotels yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
