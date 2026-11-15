
<?php

// =====================================================
// HP COMPUTER
// FILE: promotions.php
// CHUC NANG: QUAN LY CHUONG TRINH KHUYEN MAI
// =====================================================

// Kiem tra dang nhap
require_once __DIR__ . '/includes/auth_check.php';

// Nap cau hinh ung dung va database
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

// Tieu de trang
$pageTitle = 'Quản lý chương trình khuyến mãi';

// Header va Navbar
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">

        <?php
        // Noi dung quan ly khuyen mai
        require __DIR__ . '/modules/promotions/index.php';
        ?>

    </main>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
