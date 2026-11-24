
<?php
// =====================================================
// HP COMPUTER
// FILE: permissions_users.php
// CHUC NANG: TRANG QUAN LY VAI TRO TAI KHOAN
// =====================================================

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/app.php';

$pageTitle = 'Quản lý vai trò tài khoản';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">

        <?php
        require_once __DIR__ . '/modules/permissions/users.php';
        ?>

    </main>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
