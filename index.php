<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/app.php';

$pageTitle = 'Tổng quan';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <?php require_once __DIR__ . '/modules/dashboard/index.php'; ?>

        <?php require_once __DIR__ . '/includes/footer.php'; ?>