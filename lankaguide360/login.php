<?php
require_once __DIR__ . '/config/app.php';

// Already signed in? Go to the right place.
if (isLoggedIn()) {
    header('Location: ' . dashboardFor(currentUser()['role']));
    exit;
}

$next = $_GET['next'] ?? '';
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        $error = 'Session expired, please try again.';
    } else {
        $user = loginUser($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($user) {
            $dest = $_POST['next'] ?? '';
            header('Location: ' . ($dest ?: dashboardFor($user['role'])));
            exit;
        }
        $error = 'Invalid email or password.';
    }
}

$pageTitle = 'Sign in';
require __DIR__ . '/includes/header.php';
?>
<section class="section-pad">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-5">
        <div class="planner-shell p-4 p-lg-5">
          <span class="eyebrow">Welcome back</span>
          <h2 class="h3 mb-3">Sign in to LankaGuide 360</h2>
          <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
          <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <div class="mb-3">
              <label class="form-label small text-muted">Email</label>
              <input type="email" name="email" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
              <label class="form-label small text-muted">Password</label>
              <input type="password" name="password" class="form-control" required>
            </div>
            <button class="btn btn-lg-primary w-100">Sign in</button>
          </form>
          <p class="small text-muted mt-3 mb-0">New here? <a href="<?= url('register.php') ?>" class="text-teal fw-semibold">Create an account</a> — or just <a href="<?= url('trip-planner.php') ?>" class="text-teal fw-semibold">plan as a guest</a>.</p>
        </div>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
