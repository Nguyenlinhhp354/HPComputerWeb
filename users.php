<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/permission_check.php';
yeu_cau_quyen('users.view');

// =====================================================
// HP COMPUTER
// FILE: users.php
// CHUC NANG: TRANG QUAN LY TAI KHOAN
// =====================================================

// Kiem tra dang nhap va trang thai tai khoan

// Nap cau hinh ung dung
require_once __DIR__ . '/config/app.php';

// Tieu de trang
$pageTitle = 'Quản lý tài khoản';

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
        // Noi dung quan ly tai khoan
        require_once __DIR__ . '/modules/users/index.php';
        ?>

    </main>

</div>

<?php
// Footer
require_once __DIR__ . '/includes/footer.php';
?>
