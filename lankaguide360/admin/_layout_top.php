<?php
require_once __DIR__ . '/../config/app.php';
requireAdmin();
$db = getDbConnection();
$current = basename($_SERVER['SCRIPT_NAME']);
$pageTitle = ($pageTitle ?? 'Dashboard') . ' — LankaGuide 360 Admin';
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
    <div class="lg-brand text-white mb-4"><span class="lg-brand-mark">LG</span>360 <span class="small ms-1 opacity-75">Admin</span></div>
    <a href="dashboard.php" class="<?= $current==='dashboard.php'?'active':'' ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a href="destinations.php" class="<?= in_array($current,['destinations.php','destination-edit.php'])?'active':'' ?>"><i class="bi bi-geo-alt"></i> Destinations</a>
    <a href="locations.php" class="<?= in_array($current,['locations.php','location-edit.php'])?'active':'' ?>"><i class="bi bi-signpost-split"></i> Locations</a>
    <a href="priorities.php" class="<?= in_array($current,['priorities.php','priority-edit.php'])?'active':'' ?>"><i class="bi bi-sliders"></i> Trip priorities</a>
    <div class="small text-uppercase opacity-50 mt-3 mb-1" style="letter-spacing:.08em;font-size:.65rem">Resources</div>
    <a href="guides.php" class="<?= in_array($current,['guides.php','guide-edit.php'])?'active':'' ?>"><i class="bi bi-person-badge"></i> Guides</a>
    <a href="drivers.php" class="<?= in_array($current,['drivers.php','driver-edit.php'])?'active':'' ?>"><i class="bi bi-person-workspace"></i> Drivers</a>
    <a href="vehicles.php" class="<?= in_array($current,['vehicles.php','vehicle-edit.php'])?'active':'' ?>"><i class="bi bi-truck-front"></i> Vehicles</a>
    <a href="hotels.php" class="<?= in_array($current,['hotels.php','hotel-edit.php'])?'active':'' ?>"><i class="bi bi-building"></i> Hotels</a>
    <div class="small text-uppercase opacity-50 mt-3 mb-1" style="letter-spacing:.08em;font-size:.65rem">Operations</div>
    <a href="bookings.php" class="<?= $current==='bookings.php'?'active':'' ?>"><i class="bi bi-journal-check"></i> Bookings</a>
    <a href="users.php" class="<?= in_array($current,['users.php','user-edit.php'])?'active':'' ?>"><i class="bi bi-people"></i> Users</a>
    <a href="reports.php" class="<?= $current==='reports.php'?'active':'' ?>"><i class="bi bi-graph-up"></i> Reports</a>
    <a href="chatbot-logs.php" class="<?= $current==='chatbot-logs.php'?'active':'' ?>"><i class="bi bi-chat-dots"></i> Chatbot logs</a>
    <a href="settings.php" class="<?= $current==='settings.php'?'active':'' ?>"><i class="bi bi-gear"></i> Settings</a>
    <hr class="border-secondary">
    <a href="../profile.php"><i class="bi bi-person-gear"></i> My profile</a>
    <a href="../index.php" target="_blank"><i class="bi bi-box-arrow-up-right"></i> View site</a>
    <a href="logout.php"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
  <div class="col-lg-10 p-4 p-lg-5">
