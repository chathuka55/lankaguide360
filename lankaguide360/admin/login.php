<?php
require_once __DIR__ . '/../config/app.php';
$db = getDbConnection();

if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = loginUser($email, $password);
    if ($user && $user['role'] === 'admin') {
        header('Location: dashboard.php');
        exit;
    }
    if ($user) {
        // Valid credentials but not an admin — send them to their own area.
        header('Location: ' . dashboardFor($user['role']));
        exit;
    }
    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — LankaGuide 360</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body style="background:var(--teal-900); min-height:100vh; display:flex; align-items:center;">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="text-center mb-4"><span class="lg-brand text-white"><span class="lg-brand-mark">LG</span>360</span></div>
      <div class="admin-card">
        <h5 class="mb-1">Admin console</h5>
        <p class="text-muted small mb-4">Sign in to manage destinations, bookings and chatbot content.</p>
        <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
          <div class="mb-3">
            <label class="form-label small text-muted">Email</label>
            <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? 'admin@lankaguide360.lk') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label small text-muted">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <button class="btn btn-lg-primary w-100">Sign in</button>
        </form>
        <p class="text-muted small mt-3 mb-0">Seeded demo login: <code>admin@lankaguide360.lk</code> / <code>Lanka@123</code> (activate via tools/generate-password-hash.php)</p>
      </div>
      <p class="text-center text-white-50 small mt-3"><a href="../index.php" class="text-white-50">&larr; Back to site</a></p>
    </div>
  </div>
</div>
</body>
</html>
