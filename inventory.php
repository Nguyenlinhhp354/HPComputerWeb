
<?php
// KIEM TRA DANG NHAP
require_once __DIR__ . '/includes/auth_check.php';

// KIEM TRA PHAN QUYEN QUAN LY KHO
require_once __DIR__ . '/includes/permission_check.php';
yeu_cau_quyen('inventory.view');

// CAU HINH WEBSITE
require_once __DIR__ . '/config/app.php';

$pageTitle = 'Quản lý kho';

// NAP GIAO DIEN CHUNG
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">

        <?php
        require_once __DIR__ . '/modules/inventory/index.php';
        ?>

    </main>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
