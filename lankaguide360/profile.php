<?php
require_once __DIR__ . '/config/app.php';
requireLogin();

$db   = getDbConnection();
$user = currentUser();
$pageTitle = 'My profile — LankaGuide 360';

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        $errors[] = 'Your session expired — please try again.';
    } else {
        $action = $_POST['action'] ?? 'details';

        if ($action === 'details') {
            $name    = trim($_POST['full_name'] ?? '');
            $email   = trim($_POST['email'] ?? '');
            $phone   = trim($_POST['phone'] ?? '');
            $country = trim($_POST['country'] ?? '');

            if ($name === '')  $errors[] = 'Please enter your name.';
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';

            if (!$errors) {
                // Email must stay unique across users.
                $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
                $chk->execute([$email, $user['id']]);
                if ($chk->fetch()) {
                    $errors[] = 'That email is already used by another account.';
                }
            }

            if (!$errors) {
                $upd = $db->prepare(
                    "UPDATE users SET full_name = ?, email = ?, phone = ?, country = ? WHERE id = ?"
                );
                $upd->execute([$name, $email, $phone ?: null, $country ?: null, $user['id']]);
                $_SESSION['user_name'] = $name;
                if ($user['role'] === 'admin') $_SESSION['admin_name'] = $name;
                $success = 'Your profile details were updated.';
                $user = currentUser(); // will be stale (cached); re-fetch below
                $r = $db->prepare("SELECT * FROM users WHERE id = ?");
                $r->execute([$_SESSION['uid']]);
                $user = $r->fetch();
            }
        }

        if ($action === 'password') {
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            $hasPassword = !empty($user['password_hash']);
            if ($hasPassword && !password_verify($current, $user['password_hash'])) {
                $errors[] = 'Your current password is incorrect.';
            }
            if (strlen($new) < 6) {
                $errors[] = 'New password must be at least 6 characters.';
            }
            if ($new !== $confirm) {
                $errors[] = 'New password and confirmation do not match.';
            }
            if (!$errors) {
                $upd = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $upd->execute([password_hash($new, PASSWORD_BCRYPT), $user['id']]);
                $success = 'Your password was changed.';
            }
        }
    }
}

$roleLabels = [
    'customer' => 'Traveller', 'guide' => 'Tour guide', 'driver' => 'Driver',
    'dispatcher' => 'Dispatcher', 'manager' => 'Manager', 'admin' => 'Administrator',
];

include __DIR__ . '/includes/header.php';
?>

<section class="section-pad" style="padding-top:3rem">
  <div class="container" style="max-width:960px">
    <div class="section-head">
      <span class="eyebrow">Account</span>
      <h2>My profile</h2>
      <p>Update your personal details and password. Your picture is generated automatically from your name.</p>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-4">
        <div class="admin-card text-center">
          <img class="lg-avatar lg-avatar-lg mx-auto" src="<?= e(avatar($user['photo_url'] ?? null, $user['full_name'])) ?>" alt="">
          <h5 class="mt-3 mb-1"><?= e($user['full_name']) ?></h5>
          <span class="tag-pill"><?= e($roleLabels[$user['role']] ?? $user['role']) ?></span>
          <p class="small text-muted mt-3 mb-0"><i class="bi bi-envelope"></i> <?= e($user['email']) ?></p>
          <?php if (!empty($user['created_at'])): ?>
            <p class="small text-muted mb-0"><i class="bi bi-calendar3"></i> Joined <?= e(date('M Y', strtotime($user['created_at']))) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="admin-card mb-4">
          <h5 class="mb-3">Personal details</h5>
          <form method="post" class="row g-3">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="details">
            <div class="col-md-6">
              <label class="form-label small text-muted">Full name</label>
              <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small text-muted">Email</label>
              <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small text-muted">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" placeholder="+94 …">
            </div>
            <div class="col-md-6">
              <label class="form-label small text-muted">Country</label>
              <input type="text" name="country" class="form-control" value="<?= e($user['country'] ?? '') ?>" placeholder="Sri Lanka">
            </div>
            <div class="col-12">
              <button class="btn btn-lg-primary"><i class="bi bi-save"></i> Save details</button>
            </div>
          </form>
        </div>

        <div class="admin-card">
          <h5 class="mb-3">Change password</h5>
          <form method="post" class="row g-3">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="password">
            <?php if (!empty($user['password_hash'])): ?>
            <div class="col-12">
              <label class="form-label small text-muted">Current password</label>
              <input type="password" name="current_password" class="form-control" required>
            </div>
            <?php endif; ?>
            <div class="col-md-6">
              <label class="form-label small text-muted">New password</label>
              <input type="password" name="new_password" class="form-control" minlength="6" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small text-muted">Confirm new password</label>
              <input type="password" name="confirm_password" class="form-control" minlength="6" required>
            </div>
            <div class="col-12">
              <button class="btn btn-lg-outline"><i class="bi bi-shield-lock"></i> Update password</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
