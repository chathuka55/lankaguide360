<?php
$pageTitle = 'Trip Priorities';
require __DIR__ . '/_layout_top.php';

if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM trip_priorities WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    flash('success', 'Trip priority deleted.');
    header('Location: priorities.php');
    exit;
}

$priorities = $db->query(
    "SELECT p.*, GROUP_CONCAT(c.name ORDER BY pc.weight DESC SEPARATOR ', ') AS boosted_categories
     FROM trip_priorities p
     LEFT JOIN priority_categories pc ON pc.priority_id = p.id
     LEFT JOIN categories c ON c.id = pc.category_id
     GROUP BY p.id ORDER BY p.name"
)->fetchAll();
$successMsg = flash('success');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="mb-0">Trip priorities</h3>
    <p class="text-muted mb-0 small">What visitors say matters most (e.g. Relaxation, Adventure) — each one gently boosts matching categories in the trip planner's scoring.</p>
  </div>
  <a href="priority-edit.php" class="btn btn-lg-primary"><i class="bi bi-plus-lg"></i> Add priority</a>
</div>

<?php if ($successMsg): ?><div class="alert alert-success py-2 small"><?= e($successMsg) ?></div><?php endif; ?>

<div class="admin-card">
  <table class="table table-lg align-middle">
    <thead><tr><th>Name</th><th>Description</th><th>Boosts</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($priorities as $p): ?>
      <tr>
        <td class="fw-semibold"><i class="bi <?= e($p['icon'] ?: 'bi-sliders') ?> text-amber"></i> <?= e($p['name']) ?></td>
        <td class="small text-muted"><?= e($p['description']) ?></td>
        <td class="small"><?= e($p['boosted_categories'] ?: '—') ?></td>
        <td class="text-end">
          <a href="priority-edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-lg-outline"><i class="bi bi-pencil"></i></a>
          <a href="priorities.php?delete=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this trip priority?')"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$priorities): ?><tr><td colspan="4" class="text-muted small">No trip priorities yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
