
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/users/create.php
// CHUC NANG: TAO TAI KHOAN NHAN VIEN
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/permission_check.php';

// -----------------------------------------------------
// 1. KIEM TRA QUYEN
// -----------------------------------------------------

if (lay_vai_tro_hien_tai() !== 1 || !co_quyen('users.create')) {
    http_response_code(403);
    exit('Bạn không có quyền tạo tài khoản.');
}

// -----------------------------------------------------
// 2. KHOI TAO CSRF
// -----------------------------------------------------

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// -----------------------------------------------------
// 3. HAM HO TRO
// -----------------------------------------------------

$escape = static function ($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
};

$getLength = static function ($value) {
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
};

// Chỉ cho phép tạo tài khoản nhân viên thuộc các vai trò này.
// Không có vai trò ID 2 và không tạo tài khoản khách hàng tại đây.
$allowedRoleIds = [1, 3, 4, 5];

// -----------------------------------------------------
// 4. KHOI TAO DU LIEU
// -----------------------------------------------------

$errors = [];

$username = '';
$fullName = '';
$email = '';
$phone = '';
$roleId = '';

$password = '';
$confirmPassword = '';

$roles = [];

// -----------------------------------------------------
// 5. LAY DANH SACH VAI TRO
// -----------------------------------------------------

try {
    $placeholders = implode(',', array_fill(0, count($allowedRoleIds), '?'));

    $roleStmt = $pdo->prepare("
        SELECT ma_vai_tro, ten_vai_tro
        FROM vai_tro
        WHERE ma_vai_tro IN ($placeholders)
        ORDER BY ma_vai_tro ASC
    ");

    $roleStmt->execute($allowedRoleIds);
    $roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('HP COMPUTER - CREATE USER ROLE ERROR: ' . $e->getMessage());
    $errors[] = 'Không thể tải danh sách vai trò.';
}

// -----------------------------------------------------
// 6. XU LY FORM
// -----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim((string) ($_POST['ten_dang_nhap'] ?? ''));
    $fullName = trim((string) ($_POST['ho_ten'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['so_dien_thoai'] ?? ''));
    $roleId = trim((string) ($_POST['ma_vai_tro'] ?? ''));

    $password = (string) ($_POST['mat_khau'] ?? '');
    $confirmPassword = (string) ($_POST['xac_nhan_mat_khau'] ?? '');

    $csrfToken = $_POST['csrf_token'] ?? '';

    // -------------------------------------------------
    // 6.1. CSRF
    // -------------------------------------------------

    if (
        !is_string($csrfToken) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    }

    // -------------------------------------------------
    // 6.2. TEN DANG NHAP
    // -------------------------------------------------

    $usernameLength = $getLength($username);

    if ($username === '') {
        $errors[] = 'Vui lòng nhập tên đăng nhập.';
    } elseif ($usernameLength < 4 || $usernameLength > 100) {
        $errors[] = 'Tên đăng nhập phải từ 4 đến 100 ký tự.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        $errors[] = 'Tên đăng nhập chỉ được chứa chữ cái không dấu, chữ số, dấu chấm, gạch dưới và gạch ngang.';
    }

    // -------------------------------------------------
    // 6.3. HO TEN
    // -------------------------------------------------

    if ($fullName === '') {
        $errors[] = 'Vui lòng nhập họ và tên.';
    } elseif ($getLength($fullName) > 150) {
        $errors[] = 'Họ và tên không được vượt quá 150 ký tự.';
    }

    // -------------------------------------------------
    // 6.4. EMAIL
    // -------------------------------------------------

    if ($email !== '') {
        if ($getLength($email) > 150) {
            $errors[] = 'Email không được vượt quá 150 ký tự.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Địa chỉ email không hợp lệ.';
        }
    }

    // -------------------------------------------------
    // 6.5. SO DIEN THOAI
    // -------------------------------------------------

    if ($getLength($phone) > 20) {
        $errors[] = 'Số điện thoại không được vượt quá 20 ký tự.';
    }

    // -------------------------------------------------
    // 6.6. MAT KHAU
    // -------------------------------------------------

    if ($password === '') {
        $errors[] = 'Vui lòng nhập mật khẩu.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Mật khẩu phải có ít nhất 8 ký tự.';
    } elseif (strlen($password) > 255) {
        $errors[] = 'Mật khẩu không được vượt quá 255 byte.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Mật khẩu xác nhận không khớp.';
    }

    // -------------------------------------------------
    // 6.7. VAI TRO
    // -------------------------------------------------

    if (
        $roleId === '' ||
        !ctype_digit($roleId) ||
        !in_array((int) $roleId, $allowedRoleIds, true)
    ) {
        $errors[] = 'Vai trò được chọn không hợp lệ.';
    } else {
        $validRole = false;

        foreach ($roles as $role) {
            if ((int) $role['ma_vai_tro'] === (int) $roleId) {
                $validRole = true;
                break;
            }
        }

        if (!$validRole) {
            $errors[] = 'Vai trò không tồn tại trong hệ thống.';
        }
    }

    // -------------------------------------------------
    // 6.8. KIEM TRA DU LIEU TRUNG
    // -------------------------------------------------

    if (empty($errors)) {
        try {
            $checkUsername = $pdo->prepare("
                SELECT ma_nguoi_dung
                FROM nguoi_dung
                WHERE ten_dang_nhap = :ten_dang_nhap
                LIMIT 1
            ");

            $checkUsername->execute([
                ':ten_dang_nhap' => $username
            ]);

            if ($checkUsername->fetchColumn()) {
                $errors[] = 'Tên đăng nhập đã tồn tại.';
            }

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

                if ($checkEmail->fetchColumn()) {
                    $errors[] = 'Email này đã được sử dụng.';
                }
            }

        } catch (Throwable $e) {
            error_log('HP COMPUTER - CREATE USER CHECK ERROR: ' . $e->getMessage());
            $errors[] = 'Không thể kiểm tra thông tin tài khoản.';
        }
    }

    // -------------------------------------------------
    // 6.9. THEM TAI KHOAN
    // -------------------------------------------------

    if (empty($errors)) {
        try {
            // Giữ cách lưu mật khẩu hiện tại để tương thích với login.php.
            // Chỉ chuyển sang password_hash khi cập nhật đồng bộ luồng đăng nhập.
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

            header('Location: ' . BASE_URL . '/users.php?created=1');
            exit;

        } catch (PDOException $e) {
            error_log('HP COMPUTER - CREATE USER INSERT ERROR: ' . $e->getMessage());

            if ($e->getCode() === '23000') {
                $errors[] = 'Tên đăng nhập hoặc email đã tồn tại.';
            } else {
                $errors[] = 'Không thể tạo tài khoản. Vui lòng kiểm tra lại dữ liệu.';
            }

        } catch (Throwable $e) {
            error_log('HP COMPUTER - CREATE USER ERROR: ' . $e->getMessage());
            $errors[] = 'Đã xảy ra lỗi khi tạo tài khoản.';
        }
    }
}

// -----------------------------------------------------
// 7. GIAO DIEN
// -----------------------------------------------------

$pageTitle = 'Tạo tài khoản';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="container-fluid py-3">

            <div class="mb-4">
                <h4 class="fw-bold mb-1">Tạo tài khoản</h4>
                <p class="text-muted mb-0">
                    Tạo tài khoản nhân viên mới cho hệ thống HP Computer.
                </p>
            </div>

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

            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0">Thông tin tài khoản</h6>
                </div>

                <div class="card-body">

                    <form method="POST" action="" autocomplete="off">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= $escape($_SESSION['csrf_token']) ?>"
                        >

                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="form-label">
                                    Tên đăng nhập <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="ten_dang_nhap"
                                    class="form-control"
                                    minlength="4"
                                    maxlength="100"
                                    value="<?= $escape($username) ?>"
                                    placeholder="Nhập tên đăng nhập"
                                    required
                                >
                                <small class="text-muted">
                                    Từ 4 ký tự, không dấu và không chứa khoảng trắng.
                                </small>
                            </div>

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

                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input
                                    type="email"
                                    name="email"
                                    class="form-control"
                                    maxlength="150"
                                    value="<?= $escape($email) ?>"
                                    placeholder="example@gmail.com"
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Số điện thoại</label>
                                <input
                                    type="text"
                                    name="so_dien_thoai"
                                    class="form-control"
                                    maxlength="20"
                                    value="<?= $escape($phone) ?>"
                                    placeholder="Nhập số điện thoại"
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">
                                    Vai trò <span class="text-danger">*</span>
                                </label>

                                <select name="ma_vai_tro" class="form-select" required>
                                    <option value="">-- Chọn vai trò --</option>

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

                        <div class="alert alert-info mt-4 mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Tài khoản mới được tạo với trạng thái Đang hoạt động.
                            Khách hàng được đăng ký tại chức năng đăng ký riêng.
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">

                            <a
                                href="<?= $escape(BASE_URL) ?>/users.php"
                                class="btn btn-outline-secondary"
                            >
                                <i class="bi bi-arrow-left me-1"></i>
                                Quay lại
                            </a>

                            <button type="submit" class="btn btn-primary">
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
