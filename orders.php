<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/permission_check.php';
yeu_cau_quyen('orders.view');
require_once __DIR__ . '/modules/orders/helpers.php';

// Load data before the header so invalid IDs can return a proper HTTP status.
ob_start();
require __DIR__ . '/modules/orders/' . (array_key_exists('id', $_GET) ? 'detail.php' : 'index.php');
$ordersContent = ob_get_clean();
$pageTitle = array_key_exists('id', $_GET) ? 'Chi tiết đơn hàng' : 'Quản lý đơn hàng';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<div class="admin-layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <?= $ordersContent ?>
    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
