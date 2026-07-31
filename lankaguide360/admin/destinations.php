<?php
$pageTitle = 'Destinations';
require __DIR__ . '/_layout_top.php';

if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM destinations WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    flash('success', 'Destination deleted.');
    header('Location: destinations.php');
    exit;
}

$destinations = $db->query("SELECT * FROM destinations ORDER BY created_at DESC")->fetchAll();
$successMsg = flash('success');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h3 class="mb-0">Destinations</h3>
  <a href="destination-edit.php" class="btn btn-lg-primary"><i class="bi bi-plus-lg"></i> Add destination</a>
</div>

<?php if ($successMsg): ?><div class="alert alert-success py-2 small"><?= e($successMsg) ?></div><?php endif; ?>

<div class="admin-card">
  <table class="table table-lg align-middle">
    <thead><tr><th>Name</th><th>Region</th><th>Budget</th><th>Popularity</th><th>Rating</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($destinations as $d): ?>
      <tr>
        <td class="fw-semibold">
          <div class="d-flex align-items-center gap-2">
            <?php $dt = img_src($d['image_url'] ?? null); ?>
            <?php if ($dt): ?><img class="lg-thumb lg-avatar-sm" style="border-radius:8px" src="<?= e($dt) ?>" alt=""><?php else: ?><i class="bi bi-image text-muted"></i><?php endif; ?>
            <span><?= e($d['name']) ?></span>
          </div>
        </td>
        <td class="small text-muted"><?= e($d['region']) ?></td>
        <td class="small text-capitalize"><?= e($d['budget_tier']) ?></td>
        <td><span class="tag-pill"><?= $d['popularity_tier']==='popular' ? 'Popular' : 'Hidden gem' ?></span></td>
        <td class="small"><i class="bi bi-star-fill text-amber"></i> <?= number_format((float)$d['rating'],1) ?></td>
        <td><span class="badge text-bg-<?= $d['is_active']?'success':'secondary' ?>"><?= $d['is_active']?'Active':'Hidden' ?></span></td>
        <td class="text-end">
          <a href="destination-edit.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-lg-outline"><i class="bi bi-pencil"></i></a>
          <a href="destinations.php?delete=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this destination?')"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
