<?php
/**
 * Shared operational-panel layout (Dispatcher / Manager / Performer).
 * A page must call requireRole(...) BEFORE including this file. Set $pageTitle.
 */
require_once __DIR__ . '/../config/app.php';

$me = currentUser();
if (!$me) {
    header('Location: ' . url('login.php'));
    exit;
}
$role = $me['role'];
$current = basename($_SERVER['SCRIPT_NAME']);
$pageTitle = ($pageTitle ?? ucfirst($role)) . ' — LankaGuide 360';

// Role-specific sidebar links: [href, icon, label, activeScripts[]]
$panelBase = ''; // links are relative within the role folder
$nav = [];
if ($role === 'dispatcher') {
    $nav = [
        ['queue.php', 'bi-inbox', 'Request queue', ['queue.php']],
        ['assign.php', 'bi-diagram-3', 'Assignments', ['assign.php']],
    ];
    $panelHome = url('dispatcher/queue.php');
} elseif ($role === 'manager') {
    $nav = [
        ['approvals.php', 'bi-check2-square', 'Approvals', ['approvals.php']],
        ['pricing.php', 'bi-cash-coin', 'Pricing &amp; review', ['pricing.php']],
    ];
    $panelHome = url('manager/approvals.php');
} else { // guide / driver = performer
    $nav = [
        ['dashboard.php', 'bi-clipboard-check', 'My assignments', ['dashboard.php', 'trip.php']],
    ];
    $panelHome = url('performer/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body class="admin-shell">
<div class="row g-0">
  <div class="col-lg-2 admin-sidebar p-3">
    <div class="lg-brand text-white mb-1"><span class="lg-brand-mark">LG</span>360</div>
    <div class="small opacity-75 mb-4 text-capitalize"><?= e($role) ?> panel</div>
    <?php foreach ($nav as $n): $active = in_array($current, $n[3], true) ? 'active' : ''; ?>
      <a href="<?= e($n[0]) ?>" class="<?= $active ?>"><i class="bi <?= e($n[1]) ?>"></i> <?= $n[2] ?></a>
    <?php endforeach; ?>
    <hr class="border-secondary">
    <a href="<?= url('profile.php') ?>"><i class="bi bi-person-gear"></i> My profile</a>
    <a href="<?= url('index.php') ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i> View site</a>
    <a href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-left"></i> Logout</a>
    <div class="d-flex align-items-center gap-2 mt-4">
      <img class="lg-avatar lg-avatar-sm" src="<?= e(avatar($me['photo_url'] ?? null, $me['full_name'])) ?>" alt="">
      <span class="small opacity-75"><?= e($me['full_name']) ?></span>
    </div>
  </div>
  <div class="col-lg-10 p-4 p-lg-5">
