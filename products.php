
<?php

// =====================================================
// HP COMPUTER
// FILE: products.php
// TRANG QUAN LY SAN PHAM
// =====================================================


// 1. Kiem tra dang nhap
require_once __DIR__ . '/includes/auth_check.php';


// 2. Kiem tra quyen truy cap
require_once __DIR__ . '/includes/permission_check.php';

yeu_cau_quyen('products.view');


// 3. Cau hinh ung dung
require_once __DIR__ . '/config/app.php';


// 4. Thiet lap tieu de trang
$pageTitle = 'Quản lý sản phẩm';


// 5. Nap phan dau trang
require_once __DIR__ . '/includes/header.php';


// 6. Nap thanh dieu huong
require_once __DIR__ . '/includes/navbar.php';

?>

<div class="admin-layout">

    <?php
    // Thanh menu ben trai
    require_once __DIR__ . '/includes/sidebar.php';
    ?>

    <main class="main-content">

        <?php
        // Noi dung quan ly san pham
        require_once __DIR__ . '/modules/products/index.php';
        ?>

    </main>

</div>

<?php

// 7. Nap phan cuoi trang
require_once __DIR__ . '/includes/footer.php';

?>
