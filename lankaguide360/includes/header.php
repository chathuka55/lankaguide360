<?php
require_once __DIR__ . '/../config/app.php';
$pageTitle = $pageTitle ?? APP_NAME . ' — Intelligent Sri Lanka Trip Planning';
$current = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="LankaGuide 360 — intelligent trip planning, destination discovery and tourism support for Sri Lanka.">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<!-- Bootstrap 5 + Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body>

<nav class="lg-nav navbar navbar-expand-lg fixed-top">
  <div class="container">
    <a class="navbar-brand lg-brand" href="<?= url('index.php') ?>">
      <img src="<?= asset('img/logo.png') ?>" alt="LankaGuide 360" style="height:42px;width:auto;">
      <span class="lg-brand-sub">Sri Lanka, mapped for you</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#lgNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="lgNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
        <li class="nav-item"><a class="nav-link <?= $current==='index.php'?'active':'' ?>" href="<?= url('index.php') ?>">Home</a></li>
        <li class="nav-item"><a class="nav-link <?= $current==='destinations.php'?'active':'' ?>" href="<?= url('destinations.php') ?>">Destinations</a></li>
        <li class="nav-item"><a class="nav-link <?= $current==='trip-planner.php'?'active':'' ?>" href="<?= url('trip-planner.php') ?>">Trip Planner</a></li>
        <li class="nav-item"><a class="nav-link <?= $current==='booking.php'?'active':'' ?>" href="<?= url('booking.php') ?>">Booking</a></li>
        <li class="nav-item"><a class="nav-link <?= $current==='about.php'?'active':'' ?>" href="<?= url('about.php') ?>">About</a></li>
        <?php $lgUser = currentUser(); ?>
        <?php if ($lgUser): ?>
          <li class="nav-item dropdown ms-lg-2">
            <a class="nav-link dropdown-toggle d-inline-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
              <img class="lg-avatar lg-avatar-sm" src="<?= e(avatar($lgUser['photo_url'] ?? null, $lgUser['full_name'])) ?>" alt=""> <?= e(explode(' ', $lgUser['full_name'])[0]) ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= dashboardFor($lgUser['role']) ?>"><i class="bi bi-grid"></i> My dashboard</a></li>
              <?php if ($lgUser['role'] === 'customer'): ?>
                <li><a class="dropdown-item" href="<?= url('account.php') ?>"><i class="bi bi-journal-text"></i> My bookings</a></li>
              <?php endif; ?>
              <li><a class="dropdown-item" href="<?= url('profile.php') ?>"><i class="bi bi-person-gear"></i> My profile</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-left"></i> Sign out</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link <?= $current==='login.php'?'active':'' ?>" href="<?= url('login.php') ?>">Sign in</a></li>
        <?php endif; ?>

      </ul>
    </div>
  </div>
</nav>
<main>
