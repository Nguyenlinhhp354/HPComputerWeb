
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/users/create.php
// CHUC NANG: TAO TAI KHOAN NHAN VIEN
// =====================================================

// Kiem tra dang nhap
require_once __DIR__ . '/../../includes/auth_check.php';

// Chi Admin duoc tao tai khoan
if ((int) $_SESSION['ma_vai_tro'] !== 1) {
    http_response_code(403);
    exit('Ban khong co quyen thuc hien chuc nang nay.');
}

// Nap cau hinh ung dung
require_once __DIR__ . '/../../config/app.php';

// Ham hien thi du lieu an toan
$escape = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

// =====================================================
// 1. KHOI TAO DU LIEU
// =====================================================

$errors = [];

$username = '';
$fullName = '';
$email = '';
$phone = '';
$roleId = '';

// =====================================================
// 2. LAY DANH SACH VAI TRO
// =====================================================

try {
    // Khong cho tao tai khoan khach hang tai trang quan tri
    $roleStmt = $pdo->query("
        SELECT ma_vai_tro, ten_vai_tro
        FROM vai_tro
        WHERE ma_vai_tro <> 6
        ORDER BY ma_vai_tro ASC
    ");

    $roles = $roleStmt->fetchAll();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $roles = [];
    $errors[] = 'Không thể tải danh sách vai trò.';
}

// =====================================================
// 3. XU LY KHI GUI FORM
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Lay du lieu tu form
    $username = trim($_POST['ten_dang_nhap'] ?? '');
    $fullName = trim($_POST['ho_ten'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['so_dien_thoai'] ?? '');
    $roleId = trim($_POST['ma_vai_tro'] ?? '');

    $password = $_POST['mat_khau'] ?? '';
    $confirmPassword = $_POST['xac_nhan_mat_khau'] ?? '';

    $csrfToken = $_POST['csrf_token'] ?? '';

    // -------------------------------------------------
    // 3.1. KIEM TRA CSRF
    // -------------------------------------------------

    if (
        !is_string($csrfToken) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    }

    // -------------------------------------------------
    // 3.2. KIEM TRA TEN DANG NHAP
    // -------------------------------------------------

    if ($username === '') {
        $errors[] = 'Vui lòng nhập tên đăng nhập.';
    } elseif (strlen($username) < 4 || strlen($username) > 100) {
        $errors[] = 'Tên đăng nhập phải từ 4 đến 100 ký tự.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        $errors[] = 'Tên đăng nhập chỉ được chứa chữ cái, chữ số, dấu chấm, gạch dưới và gạch ngang.';
    }

    // -------------------------------------------------
    // 3.3. KIEM TRA HO TEN
    // -------------------------------------------------

    if ($fullName === '') {
        $errors[] = 'Vui lòng nhập họ và tên.';
    } elseif (strlen($fullName) > 150) {
        $errors[] = 'Họ và tên không được vượt quá 150 ký tự.';
    }

    // -------------------------------------------------
    // 3.4. KIEM TRA EMAIL
    // -------------------------------------------------

    if ($email !== '') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Địa chỉ email không hợp lệ.';
        } elseif (strlen($email) > 150) {
            $errors[] = 'Email không được vượt quá 150 ký tự.';
        }
    }

    // -------------------------------------------------
    // 3.5. KIEM TRA SO DIEN THOAI
    // -------------------------------------------------

    if ($phone !== '' && strlen($phone) > 20) {
        $errors[] = 'Số điện thoại không được vượt quá 20 ký tự.';
    }

    // -------------------------------------------------
    // 3.6. KIEM TRA MAT KHAU
    // -------------------------------------------------

    if ($password === '') {
        $errors[] = 'Vui lòng nhập mật khẩu.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Mật khẩu phải có ít nhất 8 ký tự.';
    } elseif (strlen($password) > 255) {
        $errors[] = 'Mật khẩu không được vượt quá 255 ký tự.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Mật khẩu xác nhận không khớp.';
    }

    // -------------------------------------------------
    // 3.7. KIEM TRA VAI TRO
    // -------------------------------------------------

    if (
        $roleId === '' ||
        !ctype_digit($roleId) ||
        (int) $roleId <= 0
    ) {
        $errors[] = 'Vui lòng chọn vai trò.';
    } else {
        $validRole = false;

        foreach ($roles as $role) {
            if ((int) $role['ma_vai_tro'] === (int) $roleId) {
                $validRole = true;
                break;
            }
        }

        if (!$validRole) {
            $errors[] = 'Vai trò được chọn không hợp lệ.';
        }
    }

    // -------------------------------------------------
    // 3.8. KIEM TRA DU LIEU TRUNG LAP
    // -------------------------------------------------

    if (empty($errors)) {

        try {
            // Kiem tra ten dang nhap
            $checkUsername = $pdo->prepare("
                SELECT ma_nguoi_dung
                FROM nguoi_dung
                WHERE ten_dang_nhap = :ten_dang_nhap
                LIMIT 1
            ");

            $checkUsername->execute([
                ':ten_dang_nhap' => $username
            ]);

            if ($checkUsername->fetch()) {
                $errors[] = 'Tên đăng nhập đã tồn tại.';
            }

            // Kiem tra email neu co nhap
            if ($email !== '') {

                $checkEmail = $pdo->prepare("
                    SELECT ma_nguoi_dung
                    FROM nguoi_dung
                    WHERE email = :email
                    LIMIT 1
                ");

                $checkEmail->execute([
                    ':email' => $email
                ]);

                if ($checkEmail->fetch()) {
                    $errors[] = 'Email này đã được sử dụng.';
                }
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Không thể kiểm tra thông tin tài khoản.';
        }
    }

    // -------------------------------------------------
    // 3.9. THEM TAI KHOAN VAO DATABASE
    // -------------------------------------------------

    if (empty($errors)) {

        try {
            $insertSql = "
                INSERT INTO nguoi_dung (
                    ma_vai_tro,
                    ten_dang_nhap,
                    mat_khau,
                    ho_ten,
                    email,
                    so_dien_thoai,
                    trang_thai
                ) VALUES (
                    :ma_vai_tro,
                    :ten_dang_nhap,
                    :mat_khau,
                    :ho_ten,
                    :email,
                    :so_dien_thoai,
                    'ACTIVE'
                )
            ";

            $insertStmt = $pdo->prepare($insertSql);

            $insertStmt->execute([
                ':ma_vai_tro' => (int) $roleId,
                ':ten_dang_nhap' => $username,
                ':mat_khau' => $password,
                ':ho_ten' => $fullName,
                ':email' => $email !== '' ? $email : null,
                ':so_dien_thoai' => $phone !== '' ? $phone : null
            ]);

            // Chuyen ve danh sach tai khoan
            header('Location: ' . BASE_URL . '/users.php?created=1');
            exit;

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Không thể tạo tài khoản. Vui lòng kiểm tra lại dữ liệu.';
        }
    }
}

// =====================================================
// 4. HIEN THI GIAO DIEN
// =====================================================

$pageTitle = 'Tạo tài khoản';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="container-fluid py-3">

            <!-- TIEU DE -->
            <div class="mb-4">

                <h4 class="fw-bold mb-1">
                    Tạo tài khoản
                </h4>

                <p class="text-muted mb-0">
                    Tạo tài khoản nhân viên mới cho hệ thống HP Computer
                </p>

            </div>

            <!-- THONG BAO LOI -->
            <?php if (!empty($errors)): ?>

                <div class="alert alert-danger">

                    <strong>Không thể tạo tài khoản:</strong>

                    <ul class="mb-0 mt-2">

                        <?php foreach ($errors as $error): ?>

                            <li><?= $escape($error) ?></li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>

            <!-- FORM TAO TAI KHOAN -->
            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white py-3">

                    <h6 class="fw-bold mb-0">
                        Thông tin tài khoản
                    </h6>

                </div>

                <div class="card-body">

                    <form method="POST" action="" autocomplete="off">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= $escape($_SESSION['csrf_token'] ?? '') ?>"
                        >

                        <div class="row g-3">

                            <!-- TEN DANG NHAP -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Tên đăng nhập <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="ten_dang_nhap"
                                    class="form-control"
                                    maxlength="100"
                                    value="<?= $escape($username) ?>"
                                    placeholder="Nhập tên đăng nhập"
                                    required
                                >

                                <small class="text-muted">
                                    Từ 4 ký tự, không dấu và không chứa khoảng trắng.
                                </small>

                            </div>

                            <!-- HO TEN -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Họ và tên <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="ho_ten"
                                    class="form-control"
                                    maxlength="150"
                                    value="<?= $escape($fullName) ?>"
                                    placeholder="Nhập họ và tên"
                                    required
                                >

                            </div>

                            <!-- EMAIL -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Email
                                </label>

                                <input
                                    type="email"
                                    name="email"
                                    class="form-control"
                                    maxlength="150"
                                    value="<?= $escape($email) ?>"
                                    placeholder="example@gmail.com"
                                >

                            </div>

                            <!-- SO DIEN THOAI -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Số điện thoại
                                </label>

                                <input
                                    type="text"
                                    name="so_dien_thoai"
                                    class="form-control"
                                    maxlength="20"
                                    value="<?= $escape($phone) ?>"
                                    placeholder="Nhập số điện thoại"
                                >

                            </div>

                            <!-- VAI TRO -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Vai trò <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="ma_vai_tro"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        -- Chọn vai trò --
                                    </option>

                                    <?php foreach ($roles as $role): ?>

                                        <option
                                            value="<?= (int) $role['ma_vai_tro'] ?>"
                                            <?= (string) $roleId === (string) $role['ma_vai_tro'] ? 'selected' : '' ?>
                                        >

                                            <?= $escape($role['ten_vai_tro']) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <!-- MAT KHAU -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Mật khẩu <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="password"
                                    name="mat_khau"
                                    class="form-control"
                                    minlength="8"
                                    maxlength="255"
                                    placeholder="Nhập mật khẩu"
                                    required
                                >

                                <small class="text-muted">
                                    Mật khẩu tối thiểu 8 ký tự.
                                </small>

                            </div>

                            <!-- XAC NHAN MAT KHAU -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Xác nhận mật khẩu <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="password"
                                    name="xac_nhan_mat_khau"
                                    class="form-control"
                                    minlength="8"
                                    maxlength="255"
                                    placeholder="Nhập lại mật khẩu"
                                    required
                                >

                            </div>

                        </div>

                        <!-- CANH BAO -->
                        <div class="alert alert-warning mt-4 mb-0">

                            <i class="bi bi-exclamation-triangle me-1"></i>

                            Đây là chế độ demo localhost. Mật khẩu hiện được lưu trực tiếp
                            trong database. Không sử dụng cách này khi triển khai website thực tế.

                        </div>

                        <!-- BUTTON -->
                        <div class="d-flex justify-content-end gap-2 mt-4">

                            <a
                                href="<?= $escape(BASE_URL) ?>/users.php"
                                class="btn btn-outline-secondary"
                            >
                                <i class="bi bi-arrow-left me-1"></i>
                                Quay lại
                            </a>

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                <i class="bi bi-person-plus me-1"></i>
                                Tạo tài khoản
                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
