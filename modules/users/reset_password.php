
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/users/reset_password.php
// CHUC NANG: DAT LAI MAT KHAU TAI KHOAN
// =====================================================

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/app.php';

// Chi Admin duoc phep dat lai mat khau
if ((int) $_SESSION['ma_vai_tro'] !== 1) {
    http_response_code(403);
    exit('Ban khong co quyen thuc hien chuc nang nay.');
}

// Khoi tao CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$escape = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

// Lay ma tai khoan
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header('Location: ' . BASE_URL . '/users.php');
    exit;
}

// Lay thong tin tai khoan
$stmt = $pdo->prepare("
    SELECT
        ma_nguoi_dung,
        ten_dang_nhap,
        ho_ten,
        ma_vai_tro,
        trang_thai
    FROM nguoi_dung
    WHERE ma_nguoi_dung = :id
    LIMIT 1
");

$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ' . BASE_URL . '/users.php?error=notfound');
    exit;
}

// Khong dat lai mat khau tai khoan khach hang tai trang quan tri
if ((int) $user['ma_vai_tro'] === 6) {
    header('Location: ' . BASE_URL . '/users.php?error=invalidrole');
    exit;
}

$errors = [];

// Xu ly form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    // Kiem tra CSRF
    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $errors[] = 'Phien lam viec khong hop le. Vui long tai lai trang.';
    }

    $matKhauMoi = $_POST['mat_khau_moi'] ?? '';
    $xacNhanMatKhau = $_POST['xac_nhan_mat_khau'] ?? '';

    if (!is_string($matKhauMoi) || !is_string($xacNhanMatKhau)) {
        $errors[] = 'Du lieu mat khau khong hop le.';
    } else {

        // Kiem tra do dai mat khau
        if (strlen($matKhauMoi) < 8) {
            $errors[] = 'Mat khau moi phai co it nhat 8 ky tu.';
        } elseif (strlen($matKhauMoi) > 255) {
            $errors[] = 'Mat khau moi qua dai.';
        }

        // Kiem tra xac nhan mat khau
        if ($matKhauMoi !== $xacNhanMatKhau) {
            $errors[] = 'Xac nhan mat khau khong khop.';
        }
    }

    // Cap nhat mat khau
    if (empty($errors)) {
        try {
            // Luu mat khau dang van ban ro cho demo localhost
            $update = $pdo->prepare("
                UPDATE nguoi_dung
                SET mat_khau = :mat_khau
                WHERE ma_nguoi_dung = :id
            ");

            $update->execute([
                ':mat_khau' => $matKhauMoi,
                ':id' => $id
            ]);

            header('Location: ' . BASE_URL . '/users.php?password_reset=1');
            exit;

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Khong the dat lai mat khau. Vui long thu lai.';
        }
    }
}

// Hien thi giao dien
$pageTitle = 'Đặt lại mật khẩu';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="container-fluid py-4">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Đặt lại mật khẩu</h4>
                    <p class="text-muted mb-0">
                        Cấp mật khẩu mới cho tài khoản nhân viên.
                    </p>
                </div>

                <a href="<?= $escape(BASE_URL) ?>/users.php"
                   class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Quay lại
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>Không thể đặt lại mật khẩu:</strong>

                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?= $escape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Thông tin tài khoản</h5>
                </div>

                <div class="card-body">

                    <div class="row mb-3">

                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">
                                Tên đăng nhập
                            </label>

                            <input type="text"
                                   class="form-control"
                                   value="<?= $escape($user['ten_dang_nhap']) ?>"
                                   disabled>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">
                                Họ và tên
                            </label>

                            <input type="text"
                                   class="form-control"
                                   value="<?= $escape($user['ho_ten']) ?>"
                                   disabled>
                        </div>

                    </div>

                    <hr>

                    <form method="POST"
                          action="<?= $escape(BASE_URL) ?>/modules/users/reset_password.php?id=<?= (int) $id ?>">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= $escape($_SESSION['csrf_token']) ?>">

                        <div class="mb-3">

                            <label class="form-label">
                                Mật khẩu mới
                                <span class="text-danger">*</span>
                            </label>

                            <input type="password"
                                   name="mat_khau_moi"
                                   class="form-control"
                                   minlength="8"
                                   maxlength="255"
                                   autocomplete="new-password"
                                   required>

                            <div class="form-text">
                                Mật khẩu phải có ít nhất 8 ký tự.
                            </div>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Xác nhận mật khẩu mới
                                <span class="text-danger">*</span>
                            </label>

                            <input type="password"
                                   name="xac_nhan_mat_khau"
                                   class="form-control"
                                   minlength="8"
                                   maxlength="255"
                                   autocomplete="new-password"
                                   required>

                        </div>

                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Mật khẩu hiện tại sẽ được thay thế sau khi lưu.
                        </div>

                        <div class="d-flex justify-content-end gap-2">

                            <a href="<?= $escape(BASE_URL) ?>/users.php"
                               class="btn btn-outline-secondary">
                                Hủy
                            </a>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-key me-1"></i>
                                Xác nhận đổi mật khẩu
                            </button>

                        </div>

                    </form>

                </div>
            </div>

        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
