
<?php
// =====================================================
// HP COMPUTER
// FILE: orders_report.php
// CHUC NANG: BAO CAO THONG KE DON HANG
// =====================================================

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/permission_check.php';

yeu_cau_quyen('reports.view');

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Lỗi: Không thể kết nối cơ sở dữ liệu.');
}

$pageTitle = 'Báo cáo đơn hàng';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <?php require_once __DIR__ . '/modules/reports/orders.php'; ?>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
