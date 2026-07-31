<?php
$pageTitle = 'Users';
require __DIR__ . '/_layout_top.php';

$me = currentUser();

// Update role / active status inline.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $uid = (int)$_POST['user_id'];
    if ($uid !== (int)$me['id']) { // don't let an admin lock themselves out
        if (isset($_POST['role'])) {
            $role = in_array($_POST['role'], ['customer','guide','driver','dispatcher','manager','admin'], true) ? $_POST['role'] : 'customer';
            $db->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $uid]);
        }
        if (isset($_POST['toggle_active'])) {
            $db->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ?")->execute([$uid]);
        }
    }
    header('Location: users.php');
    exit;
}

$roleFilter = $_GET['role'] ?? '';
$sql = "SELECT * FROM users";
$params = [];
if (in_array($roleFilter, ['customer','guide','driver','dispatcher','manager','admin'], true)) {
    $sql .= " WHERE role = ?";
    $params[] = $roleFilter;
}
$sql .= " ORDER BY role, full_name";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h3 class="mb-0">Users</h3><p class="text-muted mb-0 small">Customers and staff. Change roles or disable accounts.</p></div>
  <form method="get" class="d-flex gap-2">
    <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="">All roles</option>
      <?php foreach (['customer','guide','driver','dispatcher','manager','admin'] as $r): ?>
        <option value="<?= $r ?>" <?= $roleFilter===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>
<div class="admin-card">
  <table class="table table-lg align-middle">
    <thead><tr><th>Name</th><th>Email</th><th>Country</th><th>Role</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td class="fw-semibold"><?= e($u['full_name']) ?><?= $u['is_guest'] ? ' <span class="badge text-bg-light">guest</span>' : '' ?></td>
        <td class="small text-muted"><?= e($u['email']) ?></td>
        <td class="small"><?= e($u['country'] ?: '—') ?></td>
        <td>
          <form method="post" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <select name="role" class="form-select form-select-sm" style="width:auto;display:inline-block" onchange="this.form.submit()" <?= (int)$u['id']===(int)$me['id']?'disabled':'' ?>>
              <?php foreach (['customer','guide','driver','dispatcher','manager','admin'] as $r): ?>
                <option value="<?= $r ?>" <?= $u['role']===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td><span class="badge text-bg-<?= $u['is_active']?'success':'secondary' ?>"><?= $u['is_active']?'Active':'Disabled' ?></span></td>
        <td class="text-end">
          <?php if ((int)$u['id'] !== (int)$me['id']): ?>
          <form method="post" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <button name="toggle_active" value="1" class="btn btn-sm btn-lg-outline py-1"><?= $u['is_active']?'Disable':'Enable' ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$users): ?><tr><td colspan="6" class="text-muted small">No users.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
