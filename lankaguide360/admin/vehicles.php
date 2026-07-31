<?php
$pageTitle = 'Vehicles';
require __DIR__ . '/_layout_top.php';

if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM vehicles WHERE id = ?")->execute([(int)$_GET['delete']]);
    flash('success', 'Vehicle deleted.');
    header('Location: vehicles.php');
    exit;
}
$vehicles = $db->query("SELECT * FROM vehicles ORDER BY type, seats")->fetchAll();
$successMsg = flash('success');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h3 class="mb-0">Vehicles</h3><p class="text-muted mb-0 small">Rentable fleet — some with a driver, some self-drive.</p></div>
  <a href="vehicle-edit.php" class="btn btn-lg-primary"><i class="bi bi-plus-lg"></i> Add vehicle</a>
</div>
<?php if ($successMsg): ?><div class="alert alert-success py-2 small"><?= e($successMsg) ?></div><?php endif; ?>
<div class="admin-card">
  <table class="table table-lg align-middle">
    <thead><tr><th>Name</th><th>Type</th><th>Seats</th><th>Region</th><th>Driver</th><th>Owner</th><th>Rate/day</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($vehicles as $v): ?>
      <tr>
        <td class="fw-semibold">
          <div class="d-flex align-items-center gap-2">
            <?php $vt = img_src($v['image_url'] ?? null); ?>
            <?php if ($vt): ?><img class="lg-thumb lg-avatar-sm" style="border-radius:8px" src="<?= e($vt) ?>" alt=""><?php else: ?><i class="bi bi-truck-front text-muted"></i><?php endif; ?>
            <span><?= e($v['name']) ?><div class="small text-muted fw-normal"><?= e($v['registration_no']) ?></div></span>
          </div>
        </td>
        <td class="small text-capitalize"><?= e($v['type']) ?></td>
        <td class="small"><?= (int)$v['seats'] ?></td>
        <td class="small text-muted"><?= e($v['region']) ?></td>
        <td><?= $v['has_driver'] ? '<span class="badge text-bg-info">With driver</span>' : '<span class="badge text-bg-secondary">Self-drive</span>' ?></td>
        <td class="small text-capitalize"><?= e($v['owner_type']) ?></td>
        <td class="small"><?= lkr($v['rate_per_day_lkr']) ?></td>
        <td><span class="badge text-bg-<?= $v['is_active']?'success':'secondary' ?>"><?= $v['is_active']?'Active':'Hidden' ?></span></td>
        <td class="text-end">
          <a href="vehicle-edit.php?id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-lg-outline"><i class="bi bi-pencil"></i></a>
          <a href="vehicles.php?delete=<?= (int)$v['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this vehicle?')"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$vehicles): ?><tr><td colspan="9" class="text-muted small">No vehicles yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
