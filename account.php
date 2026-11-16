
<?php
// =====================================================
// HP COMPUTER - CUSTOMER ACCOUNT
// FILE: modules/customers/account.php
// CHUC NANG: QUAN LY TAI KHOAN KHACH HANG
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

// =====================================================
// 1. KIEM TRA DANG NHAP
// =====================================================

if (
    empty($_SESSION['ma_nguoi_dung']) ||
    (int)($_SESSION['ma_vai_tro'] ?? 0) !== 6
) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/login.php');
    exit;
}

$maNguoiDung = (int)$_SESSION['ma_nguoi_dung'];

// Kiểm tra trạng thái tài khoản
$stmt = $pdo->prepare("
    SELECT trang_thai
    FROM nguoi_dung
    WHERE ma_nguoi_dung = :ma_nguoi_dung
    LIMIT 1
");
$stmt->execute(['ma_nguoi_dung' => $maNguoiDung]);
$accountStatus = $stmt->fetchColumn();

if ($accountStatus !== 'ACTIVE') {
    session_unset();
    session_destroy();

    header('Location: ' . rtrim(BASE_URL, '/') . '/login.php');
    exit;
}

// =====================================================
// 2. CSRF TOKEN
// =====================================================

if (empty($_SESSION['customer_account_csrf'])) {
    $_SESSION['customer_account_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['customer_account_csrf'];

$errors = [];
$successMessage = '';

if (isset($_GET['updated'])) {
    $successMessage = 'Cập nhật thông tin tài khoản thành công!';
}

if (isset($_GET['password_changed'])) {
    $successMessage = 'Đổi mật khẩu thành công!';
}

// =====================================================
// 3. XU LY FORM
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        empty($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } else {

        $action = $_POST['action'] ?? '';

        // -------------------------------------------------
        // 3.1. CAP NHAT THONG TIN CA NHAN
        // -------------------------------------------------

        if ($action === 'update_profile') {

            $hoTen = trim($_POST['ho_ten'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $soDienThoai = trim($_POST['so_dien_thoai'] ?? '');
            $diaChi = trim($_POST['dia_chi'] ?? '');

            if ($hoTen === '') {
                $errors[] = 'Vui lòng nhập họ tên.';
            } elseif (strlen($hoTen) > 450) {
                $errors[] = 'Họ tên không được vượt quá 150 ký tự.';
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Địa chỉ email không hợp lệ.';
            }

            if (strlen($email) > 150) {
                $errors[] = 'Email không được vượt quá 150 ký tự.';
            }

            if ($soDienThoai === '') {
                $errors[] = 'Vui lòng nhập số điện thoại.';
            } elseif (strlen($soDienThoai) > 20) {
                $errors[] = 'Số điện thoại không được vượt quá 20 ký tự.';
            }

            if (strlen($diaChi) > 255) {
                $errors[] = 'Địa chỉ không được vượt quá 255 ký tự.';
            }

            if (empty($errors)) {
                try {
                    $pdo->beginTransaction();

                    // Cập nhật bảng nguoi_dung
                    $stmt = $pdo->prepare("
                        UPDATE nguoi_dung
                        SET
                            ho_ten = :ho_ten,
                            email = :email,
                            so_dien_thoai = :so_dien_thoai
                        WHERE ma_nguoi_dung = :ma_nguoi_dung
                    ");

                    $stmt->execute([
                        'ho_ten' => $hoTen,
                        'email' => $email !== '' ? $email : null,
                        'so_dien_thoai' => $soDienThoai,
                        'ma_nguoi_dung' => $maNguoiDung
                    ]);

                    // Đồng bộ bảng khach_hang
                    $stmt = $pdo->prepare("
                        INSERT INTO khach_hang (
                            ma_nguoi_dung,
                            ten_khach_hang,
                            so_dien_thoai,
                            email,
                            dia_chi
                        )
                        VALUES (
                            :ma_nguoi_dung,
                            :ten_khach_hang,
                            :so_dien_thoai,
                            :email,
                            :dia_chi
                        )
                        ON DUPLICATE KEY UPDATE
                            ten_khach_hang = VALUES(ten_khach_hang),
                            so_dien_thoai = VALUES(so_dien_thoai),
                            email = VALUES(email),
                            dia_chi = VALUES(dia_chi)
                    ");

                    $stmt->execute([
                        'ma_nguoi_dung' => $maNguoiDung,
                        'ten_khach_hang' => $hoTen,
                        'so_dien_thoai' => $soDienThoai,
                        'email' => $email !== '' ? $email : null,
                        'dia_chi' => $diaChi !== '' ? $diaChi : null
                    ]);

                    $pdo->commit();

                    // Cập nhật session
                    $_SESSION['ho_ten'] = $hoTen;
                    $_SESSION['email'] = $email;

                    header('Location: account.php?updated=1');
                    exit;

                } catch (PDOException $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    if ($e->getCode() === '23000') {
                        $errors[] = 'Email này đã được sử dụng bởi tài khoản khác.';
                    } else {
                        $errors[] = 'Không thể cập nhật thông tin. Vui lòng thử lại.';
                    }
                }
            }
        }

        // -------------------------------------------------
        // 3.2. DOI MAT KHAU
        // -------------------------------------------------

        elseif ($action === 'change_password') {

            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($currentPassword === '') {
                $errors[] = 'Vui lòng nhập mật khẩu hiện tại.';
            }

            if (strlen($newPassword) < 8) {
                $errors[] = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = 'Mật khẩu xác nhận không khớp.';
            }

            if (
                $currentPassword !== '' &&
                $newPassword !== '' &&
                $currentPassword === $newPassword
            ) {
                $errors[] = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
            }

            if (empty($errors)) {

                $stmt = $pdo->prepare("
                    SELECT mat_khau
                    FROM nguoi_dung
                    WHERE ma_nguoi_dung = :ma_nguoi_dung
                    LIMIT 1
                ");

                $stmt->execute([
                    'ma_nguoi_dung' => $maNguoiDung
                ]);

                $passwordHash = $stmt->fetchColumn();

                if (
                    !$passwordHash ||
                    !password_verify($currentPassword, $passwordHash)
                ) {
                    $errors[] = 'Mật khẩu hiện tại không chính xác.';
                } else {

                    $newPasswordHash = password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $pdo->prepare("
                        UPDATE nguoi_dung
                        SET mat_khau = :mat_khau
                        WHERE ma_nguoi_dung = :ma_nguoi_dung
                    ");

                    $stmt->execute([
                        'mat_khau' => $newPasswordHash,
                        'ma_nguoi_dung' => $maNguoiDung
                    ]);

                    header('Location: account.php?password_changed=1');
                    exit;
                }
            }
        } else {
            $errors[] = 'Yêu cầu không hợp lệ.';
        }
    }
}

// =====================================================
// 4. LAY THONG TIN TAI KHOAN
// =====================================================

$stmt = $pdo->prepare("
    SELECT
        nd.ma_nguoi_dung,
        nd.ten_dang_nhap,
        nd.ho_ten,
        nd.email,
        nd.so_dien_thoai,
        nd.ngay_tao,
        kh.ten_khach_hang,
        kh.so_dien_thoai AS sdt_khach_hang,
        kh.email AS email_khach_hang,
        kh.dia_chi
    FROM nguoi_dung nd
    LEFT JOIN khach_hang kh
        ON kh.ma_nguoi_dung = nd.ma_nguoi_dung
    WHERE nd.ma_nguoi_dung = :ma_nguoi_dung
    LIMIT 1
");

$stmt->execute([
    'ma_nguoi_dung' => $maNguoiDung
]);

$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    session_unset();
    session_destroy();

    header('Location: ' . rtrim(BASE_URL, '/') . '/login.php');
    exit;
}

// Lấy dữ liệu đang hiển thị
$hoTenHienTai = $_POST['ho_ten']
    ?? $account['ho_ten']
    ?? '';

$emailHienTai = $_POST['email']
    ?? $account['email']
    ?? '';

$soDienThoaiHienTai = $_POST['so_dien_thoai']
    ?? $account['so_dien_thoai']
    ?? $account['sdt_khach_hang']
    ?? '';

$diaChiHienTai = $_POST['dia_chi']
    ?? $account['dia_chi']
    ?? '';

$customerBase = rtrim(BASE_URL, '/');

// =====================================================
// 5. HIEN THI GIAO DIEN
// =====================================================

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="container py-5">

    <div class="mb-4">
        <h2 class="fw-bold">
            <i class="bi bi-person-circle me-2"></i>
            Tài khoản của tôi
        </h2>

        <p class="text-muted mb-0">
            Quản lý thông tin cá nhân và bảo mật tài khoản HP Computer.
        </p>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Không thể thực hiện yêu cầu:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- THONG TIN TAI KHOAN -->
        <div class="col-lg-7">

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-person-lines-fill me-2"></i>
                        Thông tin cá nhân
                    </h5>
                </div>

                <div class="card-body p-4">

                    <form method="POST" action="account.php">

                        <input type="hidden" name="action" value="update_profile">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Tên đăng nhập
                            </label>

                            <input type="text"
                                   class="form-control"
                                   value="<?= htmlspecialchars($account['ten_dang_nhap'], ENT_QUOTES, 'UTF-8') ?>"
                                   readonly>

                            <small class="text-muted">
                                Tên đăng nhập không thể thay đổi.
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Họ và tên <span class="text-danger">*</span>
                            </label>

                            <input type="text"
                                   name="ho_ten"
                                   class="form-control"
                                   maxlength="150"
                                   required
                                   value="<?= htmlspecialchars($hoTenHienTai, ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Email
                            </label>

                            <input type="email"
                                   name="email"
                                   class="form-control"
                                   maxlength="150"
                                   value="<?= htmlspecialchars($emailHienTai, ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="Nhập địa chỉ email">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Số điện thoại <span class="text-danger">*</span>
                            </label>

                            <input type="text"
                                   name="so_dien_thoai"
                                   class="form-control"
                                   maxlength="20"
                                   required
                                   value="<?= htmlspecialchars($soDienThoaiHienTai, ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                Địa chỉ nhận hàng mặc định
                            </label>

                            <textarea name="dia_chi"
                                      class="form-control"
                                      rows="3"
                                      maxlength="255"
                                      placeholder="Nhập địa chỉ nhận hàng"><?= htmlspecialchars($diaChiHienTai, ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>
                            Lưu thay đổi
                        </button>

                    </form>

                </div>
            </div>

        </div>

        <!-- DOI MAT KHAU -->
        <div class="col-lg-5">

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-shield-lock me-2"></i>
                        Đổi mật khẩu
                    </h5>
                </div>

                <div class="card-body p-4">

                    <form method="POST" action="account.php">

                        <input type="hidden" name="action" value="change_password">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Mật khẩu hiện tại
                            </label>

                            <input type="password"
                                   name="current_password"
                                   class="form-control"
                                   autocomplete="current-password"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Mật khẩu mới
                            </label>

                            <input type="password"
                                   name="new_password"
                                   class="form-control"
                                   minlength="8"
                                   autocomplete="new-password"
                                   required>

                            <small class="text-muted">
                                Mật khẩu phải có ít nhất 8 ký tự.
                            </small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                Xác nhận mật khẩu mới
                            </label>

                            <input type="password"
                                   name="confirm_password"
                                   class="form-control"
                                   minlength="8"
                                   autocomplete="new-password"
                                   required>
                        </div>

                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-key me-1"></i>
                            Đổi mật khẩu
                        </button>

                    </form>

                </div>
            </div>

            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body p-4">

                    <h6 class="fw-bold mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        Thông tin tài khoản
                    </h6>

                    <p class="mb-2">
                        <strong>Mã người dùng:</strong>
                        <?= (int)$account['ma_nguoi_dung'] ?>
                    </p>

                    <p class="mb-0">
                        <strong>Ngày đăng ký:</strong>
                        <?= !empty($account['ngay_tao'])
                            ? date('d/m/Y', strtotime($account['ngay_tao']))
                            : 'Chưa xác định' ?>
                    </p>

                </div>
            </div>

        </div>

    </div>

    <div class="mt-4">
        <a href="<?= $customerBase ?>/modules/customers/index.php"
           class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>
            Quay lại mua sắm
        </a>
    </div>

</main>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
