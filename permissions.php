<?php
// =====================================================
// HP COMPUTER
// FILE: permissions.php
// CHUC NANG: TRANG QUAN LY PHAN QUYEN
// =====================================================

// Kiem tra dang nhap va trang thai tai khoan
require_once __DIR__ . '/includes/auth_check.php';

// Nap cau hinh ung dung
require_once __DIR__ . '/config/app.php';

// Tieu de trang
$pageTitle = 'Phân quyền hệ thống';

// Header va Navbar
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- BO CUC TRANG QUAN TRI -->
<div class="admin-layout">

    <!-- SIDEBAR -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <!-- NOI DUNG CHINH -->
    <main class="main-content">

        <?php
        // Noi dung quan ly phan quyen
        require_once __DIR__ . '/modules/permissions/index.php';
        ?>

    </main>

</div>

<?php
// Footer
require_once __DIR__ . '/includes/footer.php';
?>