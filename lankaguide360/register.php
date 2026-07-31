<?php
require_once __DIR__ . '/config/app.php';

if (isLoggedIn()) {
    header('Location: ' . dashboardFor(currentUser()['role']));
    exit;
}

$error = null;
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'country' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email'     => trim($_POST['email'] ?? ''),
        'phone'     => trim($_POST['phone'] ?? ''),
        'country'   => trim($_POST['country'] ?? ''),
    ];
    $password = $_POST['password'] ?? '';
    if (!csrfCheck()) {
        $error = 'Session expired, please try again.';
    } elseif ($old['full_name'] === '' || $old['email'] === '' || strlen($password) < 6) {
        $error = 'Please fill all fields; password must be at least 6 characters.';
    } else {
        [$uid, $err] = registerCustomer($old['full_name'], $old['email'], $password, $old['phone'], $old['country']);
        if ($err) {
            $error = $err;
        } else {
            loginUser($old['email'], $password);
            // Continue a pending trip plan into booking if one exists.
            $dest = !empty($_SESSION['pending_plan_id']) ? url('booking.php') : url('account.php');
            header('Location: ' . $dest);
            exit;
        }
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<section class="section-pad">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-7 col-lg-6">
        <div class="planner-shell p-4 p-lg-5">
          <span class="eyebrow">Join the journey</span>
          <h2 class="h3 mb-3">Create your account</h2>
          <p class="text-muted small mb-4">Register once and we'll remember your travel preferences for faster planning next time.</p>
          <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
          <form method="post">
            <?= csrfField() ?>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label small text-muted">Full name</label>
                <input type="text" name="full_name" class="form-control" required value="<?= e($old['full_name']) ?>">
              </div>
              <div class="col-md-7">
                <label class="form-label small text-muted">Email</label>
                <input type="email" name="email" class="form-control" required value="<?= e($old['email']) ?>">
              </div>
              <div class="col-md-5">
                <label class="form-label small text-muted">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= e($old['phone']) ?>">
              </div>
              <div class="col-md-7">
                <label class="form-label small text-muted">Password</label>
                <input type="password" name="password" class="form-control" required minlength="6">
              </div>
              <div class="col-md-5">
                <label class="form-label small text-muted">Country</label>
                <input type="text" name="country" class="form-control" value="<?= e($old['country']) ?>">
              </div>
            </div>
            <button class="btn btn-lg-primary w-100 mt-4">Create account</button>
          </form>
          <p class="small text-muted mt-3 mb-0">Already have an account? <a href="<?= url('login.php') ?>" class="text-teal fw-semibold">Sign in</a>.</p>
        </div>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
