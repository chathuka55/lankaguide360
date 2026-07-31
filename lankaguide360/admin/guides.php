<?php
$pageTitle = 'Guides';
require __DIR__ . '/_layout_top.php';

if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM guides WHERE id = ?")->execute([(int)$_GET['delete']]);
    flash('success', 'Guide deleted.');
    header('Location: guides.php');
    exit;
}

$guides = $db->query("SELECT * FROM guides ORDER BY is_regional, region, full_name")->fetchAll();
$successMsg = flash('success');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h3 class="mb-0">Guides</h3><p class="text-muted mb-0 small">Regional and island-wide guides. Some own a vehicle.</p></div>
  <a href="guide-edit.php" class="btn btn-lg-primary"><i class="bi bi-plus-lg"></i> Add guide</a>
</div>
<?php if ($successMsg): ?><div class="alert alert-success py-2 small"><?= e($successMsg) ?></div><?php endif; ?>
<div class="admin-card">
  <table class="table table-lg align-middle">
    <thead><tr><th>Name</th><th>Region</th><th>Languages</th><th>Type</th><th>Own vehicle</th><th>Rate/day</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($guides as $g): ?>
      <tr>
        <td class="fw-semibold">
          <div class="d-flex align-items-center gap-2">
            <img class="lg-avatar lg-avatar-sm" src="<?= e(avatar($g['photo_url'] ?? null, $g['full_name'], 'avataaars')) ?>" alt="">
            <span><?= e($g['full_name']) ?><div class="small text-muted fw-normal"><?= e($g['phone']) ?></div></span>
          </div>
        </td>
        <td class="small text-muted"><?= e($g['region']) ?></td>
        <td class="small"><?= e($g['languages']) ?></td>
        <td><span class="badge text-bg-<?= $g['is_regional']?'secondary':'info' ?>"><?= $g['is_regional']?'Regional':'Island-wide' ?></span></td>
        <td><?= $g['has_own_vehicle'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<span class="text-muted small">—</span>' ?></td>
        <td class="small"><?= lkr($g['daily_rate_lkr']) ?></td>
        <td><span class="badge text-bg-<?= $g['is_active']?'success':'secondary' ?>"><?= $g['is_active']?'Active':'Hidden' ?></span></td>
        <td class="text-end">
          <a href="guide-edit.php?id=<?= (int)$g['id'] ?>" class="btn btn-sm btn-lg-outline"><i class="bi bi-pencil"></i></a>
          <a href="guides.php?delete=<?= (int)$g['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this guide?')"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$guides): ?><tr><td colspan="8" class="text-muted small">No guides yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
