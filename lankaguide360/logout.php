<?php
require_once __DIR__ . '/config/app.php';
logoutUser();
header('Location: ' . url('index.php'));
exit;
