
<?php
// =====================================================
// HP COMPUTER
// FILE: includes/navbar.php
// CHUC NANG: THANH DIEU HUONG QUAN TRI
// =====================================================

// Lay thong tin tai khoan tu Session
$hoTen = $_SESSION['ho_ten'] ?? 'Chưa đăng nhập';
$tenVaiTro = $_SESSION['ten_vai_tro'] ?? 'Chưa xác định';
$csrfToken = $_SESSION['csrf_token'] ?? '';
?>

<header class="topbar">

    <div class="topbar-left">

        <button
            class="btn-toggle-sidebar"
            id="sidebarToggle"
            type="button"
            aria-label="Đóng hoặc mở menu"
        >
            <i class="bi bi-list"></i>
        </button>

        <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/index.php"
           class="brand">

            <span class="brand-icon">
                <i class="bi bi-pc-display"></i>
            </span>

            <span>HP COMPUTER</span>

        </a>

    </div>

    <div class="topbar-right">

        <div class="topbar-date">
            <i class="bi bi-calendar3"></i>
            <?= date('d/m/Y') ?>
        </div>

        <div class="admin-profile">

            <div class="admin-avatar">
                <i class="bi bi-person-fill"></i>
            </div>

            <div class="admin-info">

                <strong>
                    <?= htmlspecialchars($hoTen, ENT_QUOTES, 'UTF-8') ?>
                </strong>

                <small>
                    <?= htmlspecialchars($tenVaiTro, ENT_QUOTES, 'UTF-8') ?>
                </small>

            </div>

            <form action="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/logout.php"
                  method="POST"
                  class="ms-3">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                >

                <button
                    type="submit"
                    class="btn btn-outline-secondary btn-sm"
                    onclick="return confirm('Bạn có chắc chắn muốn đăng xuất?');"
                >
                    <i class="bi bi-box-arrow-right me-1"></i>
                    Đăng xuất
                </button>

            </form>

        </div>

    </div>

</header>
