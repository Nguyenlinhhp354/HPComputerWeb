
<?php
// =====================================================
// HP COMPUTER
// FILE: reports.php
// CHUC NANG: TRANG BAO CAO THONG KE
// =====================================================

// =====================================================
// 1. KIEM TRA DANG NHAP
// =====================================================

require_once __DIR__ . '/includes/auth_check.php';

// =====================================================
// 2. KET NOI HE THONG
// =====================================================

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/permission_check.php';

// =====================================================
// 3. KIEM TRA QUYEN
// =====================================================

yeu_cau_quyen('reports.view');

// =====================================================
// 4. THIET LAP TIEU DE
// =====================================================

$pageTitle = 'Báo cáo thống kê';

// =====================================================
// 5. KIEM TRA DATABASE
// =====================================================

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Lỗi: Không thể kết nối cơ sở dữ liệu.');
}

// =====================================================
// 6. HEADER VA NAVBAR
// =====================================================

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

?>

<!-- =====================================================
     7. KHUNG GIAO DIEN QUAN TRI
====================================================== -->

<div class="admin-layout">

    <?php
    // Sidebar quản trị
    require_once __DIR__ . '/includes/sidebar.php';
    ?>

    <!-- =================================================
         8. NOI DUNG CHINH
    ================================================== -->

    <main class="main-content">

        <?php
        // Nội dung báo cáo thống kê
        $reportView = $_GET['view'] ?? 'index';

        $allowedViews = [
            'index' => __DIR__ . '/modules/reports/index.php',
            'revenue' => __DIR__ . '/modules/reports/revenue.php'
        ];

        if (!isset($allowedViews[$reportView])) {
            http_response_code(404);
            echo '<div class="container-fluid py-4">';
            echo '<div class="alert alert-warning">Không tìm thấy trang báo cáo.</div>';
            echo '</div>';
        } else {
            require $allowedViews[$reportView];
        }
        ?>

    </main>

</div>

<?php

// =====================================================
// 9. FOOTER
// =====================================================

require_once __DIR__ . '/includes/footer.php';

?>
