<?php
$pageTitle = 'Drivers';
require __DIR__ . '/_layout_top.php';

if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM drivers WHERE id = ?")->execute([(int)$_GET['delete']]);
    flash('success', 'Driver deleted.');
    header('Location: drivers.php');
    exit;
}
$drivers = $db->query("SELECT * FROM drivers ORDER BY region, full_name")->fetchAll();
$successMsg = flash('success');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h3 class="mb-0">Drivers</h3><p class="text-muted mb-0 small">Some drivers own a vehicle; others drive company/rented vehicles.</p></div>
  <a href="driver-edit.php" class="btn btn-lg-primary"><i class="bi bi-plus-lg"></i> Add driver</a>
</div>
<?php if ($successMsg): ?><div class="alert alert-success py-2 small"><?= e($successMsg) ?></div><?php endif; ?>
<div class="admin-card">
  <table class="table table-lg align-middle">
    <thead><tr><th>Name</th><th>Region</th><th>Licence</th><th>Own vehicle</th><th>Rate/day</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($drivers as $d): ?>
      <tr>
        <td class="fw-semibold">
          <div class="d-flex align-items-center gap-2">
            <img class="lg-avatar lg-avatar-sm" src="<?= e(avatar($d['photo_url'] ?? null, $d['full_name'], 'avataaars')) ?>" alt="">
            <span><?= e($d['full_name']) ?><div class="small text-muted fw-normal"><?= e($d['phone']) ?></div></span>
          </div>
        </td>
        <td class="small text-muted"><?= e($d['region']) ?></td>
        <td class="small"><?= e($d['license_no']) ?></td>
        <td><?= $d['has_own_vehicle'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<span class="text-muted small">—</span>' ?></td>
        <td class="small"><?= lkr($d['daily_rate_lkr']) ?></td>
        <td><span class="badge text-bg-<?= $d['is_active']?'success':'secondary' ?>"><?= $d['is_active']?'Active':'Hidden' ?></span></td>
        <td class="text-end">
          <a href="driver-edit.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-lg-outline"><i class="bi bi-pencil"></i></a>
          <a href="drivers.php?delete=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this driver?')"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$drivers): ?><tr><td colspan="7" class="text-muted small">No drivers yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
