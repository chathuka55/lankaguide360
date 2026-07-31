<?php
$pageTitle = 'Locations';
require __DIR__ . '/_layout_top.php';

if (isset($_GET['delete'])) {
    // Destinations referencing this location keep their FK set to NULL (see schema ON DELETE SET NULL)
    $stmt = $db->prepare("DELETE FROM locations WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    flash('success', 'Location deleted.');
    header('Location: locations.php');
    exit;
}

$locations = $db->query(
    "SELECT l.*, (SELECT COUNT(*) FROM destinations d WHERE d.location_id = l.id) AS destination_count
     FROM locations l ORDER BY l.region, l.name"
)->fetchAll();
$successMsg = flash('success');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="mb-0">Locations</h3>
    <p class="text-muted mb-0 small">Cities and towns used for Starting Location, Main Destination and Cities/Towns to Visit on the trip planner.</p>
  </div>
  <a href="location-edit.php" class="btn btn-lg-primary"><i class="bi bi-plus-lg"></i> Add location</a>
</div>

<?php if ($successMsg): ?><div class="alert alert-success py-2 small"><?= e($successMsg) ?></div><?php endif; ?>

<div class="admin-card">
  <table class="table table-lg align-middle">
    <thead><tr><th>Name</th><th>Type</th><th>Region</th><th>Hub</th><th>Linked destinations</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($locations as $l): ?>
      <tr>
        <td class="fw-semibold"><?= e($l['name']) ?></td>
        <td class="small text-capitalize"><?= e($l['type']) ?></td>
        <td class="small text-muted"><?= e($l['region']) ?></td>
        <td><?php if ($l['is_hub']): ?><span class="badge text-bg-info">Hub</span><?php else: ?><span class="text-muted small">—</span><?php endif; ?></td>
        <td class="small"><?= (int)$l['destination_count'] ?></td>
        <td><span class="badge text-bg-<?= $l['is_active']?'success':'secondary' ?>"><?= $l['is_active']?'Active':'Hidden' ?></span></td>
        <td class="text-end">
          <a href="location-edit.php?id=<?= (int)$l['id'] ?>" class="btn btn-sm btn-lg-outline"><i class="bi bi-pencil"></i></a>
          <a href="locations.php?delete=<?= (int)$l['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this location? Linked destinations will keep their other data but lose this location link.')"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$locations): ?><tr><td colspan="7" class="text-muted small">No locations yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
