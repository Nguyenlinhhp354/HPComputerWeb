
<?php
// =====================================================
// HP COMPUTER
// FILE: includes/sidebar.php
// CHUC NANG: HIEN THI SIDEBAR THEO PHAN QUYEN
// =====================================================

$currentPage = basename($_SERVER['PHP_SELF']);

// Ham kiem tra quyen
require_once __DIR__ . '/permission_check.php';
?>

<aside class="sidebar" id="sidebar">

    <div class="sidebar-heading">
        QUẢN LÝ HỆ THỐNG
    </div>

    <nav class="sidebar-menu">

        <!-- TONG QUAN -->
        <?php if (co_quyen('dashboard.view')): ?>
            <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/index.php"
               class="menu-item <?= $currentPage === 'index.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Tổng quan</span>
            </a>
        <?php endif; ?>


        <!-- DANH MUC QUAN LY -->
        <?php if (
            co_quyen('users.view') ||
            co_quyen('products.view') ||
            co_quyen('categories.view') ||
            co_quyen('brands.view') ||
            co_quyen('suppliers.view') ||
            co_quyen('inventory.view')
        ): ?>
            <div class="sidebar-heading">
                DANH MỤC QUẢN LÝ
            </div>
        <?php endif; ?>


        <!-- QUAN LY TAI KHOAN -->
        <?php if (co_quyen('users.view')): ?>
            <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/users.php"
               class="menu-item <?= $currentPage === 'users.php' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Quản lý tài khoản</span>
            </a>
        <?php endif; ?>


        <!-- QUAN LY SAN PHAM -->
        <?php if (co_quyen('products.view')): ?>
            <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/products.php"
               class="menu-item <?= $currentPage === 'products.php' ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i>
                <span>Quản lý sản phẩm</span>
            </a>
        <?php endif; ?>


        <!-- DANH MUC SAN PHAM -->
        <?php if (co_quyen('categories.view')): ?>
            <a href="#" class="menu-item">
                <i class="bi bi-tags"></i>
                <span>Danh mục sản phẩm</span>
            </a>
        <?php endif; ?>


        <!-- THUONG HIEU -->
        <?php if (co_quyen('brands.view')): ?>
            <a href="#" class="menu-item">
                <i class="bi bi-award"></i>
                <span>Thương hiệu</span>
            </a>
        <?php endif; ?>


        <!-- NHA CUNG CAP -->
        <?php if (co_quyen('suppliers.view')): ?>
            <a href="#" class="menu-item">
                <i class="bi bi-truck"></i>
                <span>Nhà cung cấp</span>
            </a>
        <?php endif; ?>


        <!-- QUAN LY KHO -->
        <?php if (co_quyen('inventory.view')): ?>
            <a href="#" class="menu-item">
                <i class="bi bi-boxes"></i>
                <span>Quản lý kho</span>
            </a>
        <?php endif; ?>


        <!-- KINH DOANH -->
        <?php if (
            co_quyen('customers.view') ||
            co_quyen('orders.view') ||
            co_quyen('promotions.view')
        ): ?>
            <div class="sidebar-heading">
                KINH DOANH
            </div>
        <?php endif; ?>


        <!-- KHACH HANG -->
        <?php if (co_quyen('customers.view')): ?>
            <a href="#" class="menu-item">
                <i class="bi bi-person-lines-fill"></i>
                <span>Khách hàng</span>
            </a>
        <?php endif; ?>


        <!-- DON HANG -->
        <?php if (co_quyen('orders.view')): ?>
            <a href="#" class="menu-item">
                <i class="bi bi-receipt"></i>
                <span>Đơn hàng</span>
            </a>
        <?php endif; ?>


        <!-- THANH TOAN -->
        <?php if (co_quyen('orders.view')): ?>
            <a href="#" class="menu-item">
                <i class="bi bi-credit-card"></i>
                <span>Thanh toán</span>
            </a>
        <?php endif; ?>


        <!-- KHUYEN MAI -->
        <?php if (co_quyen('promotions.view')): ?>
            <a href="#" class="menu-item">
                <i class="bi bi-megaphone"></i>
                <span>Khuyến mãi</span>
            </a>
        <?php endif; ?>


        <!-- HAU MAI VA BAO CAO -->
        <?php if (
            co_quyen('warranty.view') ||
            co_quyen('reports.view')
        ): ?>
            <div class="sidebar-heading">
                HẬU MÃI &amp; BÁO CÁO
            </div>
        <?php endif; ?>


        <!-- BAO HANH -->
        <?php if (co_quyen('warranty.view')): ?>
            <a href="#" class="menu-item">
                <i class="bi bi-shield-check"></i>
                <span>Bảo hành</span>
            </a>
        <?php endif; ?>


        <!-- BAO CAO -->
        <?php if (co_quyen('reports.view')): ?>
            <a href="#" class="menu-item">
                <i class="bi bi-bar-chart"></i>
                <span>Thống kê báo cáo</span>
            </a>
        <?php endif; ?>


        <!-- NHAT KY HOAT DONG -->
        <?php if (co_quyen('dashboard.view')): ?>
            <a href="#" class="menu-item">
                <i class="bi bi-clock-history"></i>
                <span>Nhật ký hoạt động</span>
            </a>
        <?php endif; ?>

    </nav>

    <div class="sidebar-bottom">
        <i class="bi bi-pc-display-horizontal"></i>
        <span>HP COMPUTER MANAGEMENT</span>
    </div>

</aside>
