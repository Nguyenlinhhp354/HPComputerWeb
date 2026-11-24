
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/users/edit.php
// CHUC NANG: SUA THONG TIN TAI KHOAN
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

if (lay_vai_tro_hien_tai() !== 1 || !co_quyen('users.edit')) {
    http_response_code(403);
    exit('Bạn không có quyền sửa tài khoản.');
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

$allowedRoleIds = [1, 3, 4, 5];
$validStatuses = ['ACTIVE', 'INACTIVE', 'LOCKED'];

// -----------------------------------------------------
// 4. LAY MA TAI KHOAN
// -----------------------------------------------------

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header('Location: ' . BASE_URL . '/users.php?error=invalidid');
    exit;
}

// -----------------------------------------------------
// 5. LAY THONG TIN TAI KHOAN
// -----------------------------------------------------

try {
    $stmt = $pdo->prepare("
        SELECT
            nd.ma_nguoi_dung,
            nd.ma_vai_tro,
            nd.ten_dang_nhap,
            nd.mat_khau,
            nd.ho_ten,
            nd.email,
            nd.so_dien_thoai,
            nd.trang_thai,
            vt.ten_vai_tro
        FROM nguoi_dung nd
        INNER JOIN vai_tro vt
            ON nd.ma_vai_tro = vt.ma_vai_tro
        WHERE nd.ma_nguoi_dung = :id
        LIMIT 1
    ");

    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: ' . BASE_URL . '/users.php?error=notfound');
        exit;
    }

    // Không sửa tài khoản khách hàng tại trang quản trị nhân viên.
    if ((int) $user['ma_vai_tro'] === 6) {
        header('Location: ' . BASE_URL . '/users.php?error=invalidrole');
        exit;
    }

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
    error_log('HP COMPUTER - EDIT USER LOAD ERROR: ' . $e->getMessage());

    http_response_code(500);
    exit('Không thể tải thông tin tài khoản.');
}

// -----------------------------------------------------
// 6. KHOI TAO DU LIEU FORM
// -----------------------------------------------------

$hoTen = $user['ho_ten'];
$tenDangNhap = $user['ten_dang_nhap'];
$email = $user['email'] ?? '';
$soDienThoai = $user['so_dien_thoai'] ?? '';
$maVaiTro = (int) $user['ma_vai_tro'];
$trangThai = $user['trang_thai'];

$errors = [];

$isSelf = (
    (int) $_SESSION['ma_nguoi_dung'] === (int) $id
);

$canAssignRole = co_quyen('permissions.assign');

// -----------------------------------------------------
// 7. XU LY FORM
// -----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    }

    $hoTen = trim((string) ($_POST['ho_ten'] ?? ''));
    $tenDangNhap = trim((string) ($_POST['ten_dang_nhap'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $soDienThoai = trim((string) ($_POST['so_dien_thoai'] ?? ''));

    // Không cho tự đổi vai trò và trạng thái.
    if ($isSelf) {
        $maVaiTro = (int) $user['ma_vai_tro'];
        $trangThai = $user['trang_thai'];
    } else {
        $roleInput = $_POST['ma_vai_tro'] ?? '';
        $statusInput = $_POST['trang_thai'] ?? '';

        $maVaiTro = (
            is_scalar($roleInput) && ctype_digit((string) $roleInput)
        ) ? (int) $roleInput : 0;

        $trangThai = is_string($statusInput) ? $statusInput : '';
    }

    // -------------------------------------------------
    // 7.1. HO TEN
    // -------------------------------------------------

    if ($hoTen === '') {
        $errors[] = 'Vui lòng nhập họ và tên.';
    } elseif ($getLength($hoTen) > 150) {
        $errors[] = 'Họ và tên không được vượt quá 150 ký tự.';
    }

    // -------------------------------------------------
    // 7.2. TEN DANG NHAP
    // -------------------------------------------------

    $usernameLength = $getLength($tenDangNhap);

    if ($tenDangNhap === '') {
        $errors[] = 'Vui lòng nhập tên đăng nhập.';
    } elseif ($usernameLength < 4 || $usernameLength > 100) {
        $errors[] = 'Tên đăng nhập phải từ 4 đến 100 ký tự.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $tenDangNhap)) {
        $errors[] = 'Tên đăng nhập chỉ được chứa chữ cái không dấu, chữ số, dấu chấm, gạch dưới và gạch ngang.';
    }

    // -------------------------------------------------
    // 7.3. EMAIL
    // -------------------------------------------------

    if ($email !== '') {
        if ($getLength($email) > 150) {
            $errors[] = 'Email không được vượt quá 150 ký tự.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Địa chỉ email không hợp lệ.';
        }
    }

    // -------------------------------------------------
    // 7.4. SO DIEN THOAI
    // -------------------------------------------------

    if ($getLength($soDienThoai) > 20) {
        $errors[] = 'Số điện thoại không được vượt quá 20 ký tự.';
    }

    // -------------------------------------------------
    // 7.5. VAI TRO
    // -------------------------------------------------

    $validRole = false;

    foreach ($roles as $role) {
        if ((int) $role['ma_vai_tro'] === $maVaiTro) {
            $validRole = true;
            break;
        }
    }

    if (!$validRole) {
        $errors[] = 'Vai trò được chọn không hợp lệ.';
    }

    // Nếu đổi vai trò thì phải có quyền permissions.assign.
    if (
        !$isSelf &&
        $maVaiTro !== (int) $user['ma_vai_tro'] &&
        !$canAssignRole
    ) {
        $errors[] = 'Bạn không có quyền thay đổi vai trò tài khoản.';
    }

    // -------------------------------------------------
    // 7.6. TRANG THAI
    // -------------------------------------------------

    if (!in_array($trangThai, $validStatuses, true)) {
        $errors[] = 'Trạng thái tài khoản không hợp lệ.';
    }

    // -------------------------------------------------
    // 7.7. KIEM TRA TRUNG TEN DANG NHAP
    // -------------------------------------------------

    if ($tenDangNhap !== '') {
        try {
            $checkUsername = $pdo->prepare("
                SELECT ma_nguoi_dung
                FROM nguoi_dung
                WHERE ten_dang_nhap = :ten_dang_nhap
                  AND ma_nguoi_dung <> :id
                LIMIT 1
            ");

            $checkUsername->execute([
                ':ten_dang_nhap' => $tenDangNhap,
                ':id' => $id
            ]);

            if ($checkUsername->fetchColumn()) {
                $errors[] = 'Tên đăng nhập đã tồn tại.';
            }

        } catch (Throwable $e) {
            error_log('HP COMPUTER - EDIT USER USERNAME CHECK ERROR: ' . $e->getMessage());
            $errors[] = 'Không thể kiểm tra tên đăng nhập.';
        }
    }

    // -------------------------------------------------
    // 7.8. KIEM TRA TRUNG EMAIL
    // -------------------------------------------------

    if ($email !== '') {
        try {
            $checkEmail = $pdo->prepare("
                SELECT ma_nguoi_dung
                FROM nguoi_dung
                WHERE email = :email
                  AND ma_nguoi_dung <> :id
                LIMIT 1
            ");

            $checkEmail->execute([
                ':email' => $email,
                ':id' => $id
            ]);

            if ($checkEmail->fetchColumn()) {
                $errors[] = 'Email đã được sử dụng bởi tài khoản khác.';
            }

        } catch (Throwable $e) {
            error_log('HP COMPUTER - EDIT USER EMAIL CHECK ERROR: ' . $e->getMessage());
            $errors[] = 'Không thể kiểm tra email.';
        }
    }

    // -------------------------------------------------
    // 7.9. CAP NHAT TAI KHOAN
    // -------------------------------------------------

    if (empty($errors)) {

        try {
            $pdo->beginTransaction();

            // Khóa dòng tài khoản đang sửa để hạn chế cập nhật đồng thời.
            $lockStmt = $pdo->prepare("
                SELECT ma_nguoi_dung, ma_vai_tro, trang_thai
                FROM nguoi_dung
                WHERE ma_nguoi_dung = :id
                FOR UPDATE
            ");

            $lockStmt->execute([':id' => $id]);
            $currentUser = $lockStmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentUser) {
                throw new RuntimeException('Tài khoản không còn tồn tại.');
            }

            if ((int) $currentUser['ma_vai_tro'] === 6) {
                throw new RuntimeException('Không thể sửa tài khoản khách hàng tại trang này.');
            }

            // Tài khoản đang đăng nhập không được đổi vai trò/trạng thái.
            if ($isSelf) {
                $maVaiTro = (int) $currentUser['ma_vai_tro'];
                $trangThai = $currentUser['trang_thai'];
            }

            // Kiểm tra lại quyền gán vai trò.
            if (
                $maVaiTro !== (int) $currentUser['ma_vai_tro'] &&
                !$canAssignRole
            ) {
                throw new RuntimeException('Bạn không có quyền thay đổi vai trò tài khoản.');
            }

            // Bảo vệ Admin cuối cùng đang hoạt động.
            $isRemovingActiveAdmin = (
                (int) $currentUser['ma_vai_tro'] === 1 &&
                $currentUser['trang_thai'] === 'ACTIVE' &&
                (
                    $maVaiTro !== 1 ||
                    $trangThai !== 'ACTIVE'
                )
            );

            if ($isRemovingActiveAdmin) {
                $countStmt = $pdo->query("
                    SELECT COUNT(*)
                    FROM nguoi_dung
                    WHERE ma_vai_tro = 1
                      AND trang_thai = 'ACTIVE'
                ");

                $activeAdminCount = (int) $countStmt->fetchColumn();

                if ($activeAdminCount <= 1) {
                    throw new RuntimeException(
                        'Không thể thay đổi vai trò hoặc trạng thái của Admin cuối cùng đang hoạt động.'
                    );
                }
            }

            $update = $pdo->prepare("
                UPDATE nguoi_dung
                SET
                    ten_dang_nhap = :ten_dang_nhap,
                    ho_ten = :ho_ten,
                    email = :email,
                    so_dien_thoai = :so_dien_thoai,
                    ma_vai_tro = :ma_vai_tro,
                    trang_thai = :trang_thai
                WHERE ma_nguoi_dung = :id
            ");

            $update->execute([
                ':ten_dang_nhap' => $tenDangNhap,
                ':ho_ten' => $hoTen,
                ':email' => $email !== '' ? $email : null,
                ':so_dien_thoai' => $soDienThoai !== '' ? $soDienThoai : null,
                ':ma_vai_tro' => $maVaiTro,
                ':trang_thai' => $trangThai,
                ':id' => $id
            ]);

            $pdo->commit();

            header('Location: ' . BASE_URL . '/users.php?updated=1');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('HP COMPUTER - EDIT USER UPDATE ERROR: ' . $e->getMessage());

            if ($e instanceof RuntimeException) {
                $errors[] = $e->getMessage();
            } elseif ($e instanceof PDOException && $e->getCode() === '23000') {
                $errors[] = 'Tên đăng nhập hoặc email đã tồn tại.';
            } else {
                $errors[] = 'Không thể cập nhật tài khoản. Vui lòng thử lại.';
            }
        }
    }
}

// -----------------------------------------------------
// 8. GIAO DIEN
// -----------------------------------------------------

$pageTitle = 'Sửa thông tin tài khoản';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="container-fluid py-4">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Sửa thông tin tài khoản</h4>
                    <p class="text-muted mb-0">
                        Cập nhật thông tin tài khoản trong hệ thống.
                    </p>
                </div>

                <a
                    href="<?= $escape(BASE_URL) ?>/users.php"
                    class="btn btn-outline-secondary"
                >
                    <i class="bi bi-arrow-left me-1"></i>
                    Quay lại
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>Không thể cập nhật tài khoản:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?= $escape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        Thông tin tài khoản #<?= (int) $id ?>
                    </h5>
                </div>

                <div class="card-body">

                    <form
                        method="POST"
                        action="<?= $escape(BASE_URL) ?>/modules/users/edit.php?id=<?= (int) $id ?>"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= $escape($_SESSION['csrf_token']) ?>"
                        >

                        <div class="row">

                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Tên đăng nhập <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="ten_dang_nhap"
                                    class="form-control"
                                    minlength="4"
                                    maxlength="100"
                                    required
                                    value="<?= $escape($tenDangNhap) ?>"
                                >
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Họ và tên <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="ho_ten"
                                    class="form-control"
                                    maxlength="150"
                                    required
                                    value="<?= $escape($hoTen) ?>"
                                >
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>

                                <input
                                    type="email"
                                    name="email"
                                    class="form-control"
                                    maxlength="150"
                                    value="<?= $escape($email) ?>"
                                >
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Số điện thoại</label>

                                <input
                                    type="text"
                                    name="so_dien_thoai"
                                    class="form-control"
                                    maxlength="20"
                                    value="<?= $escape($soDienThoai) ?>"
                                >
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Vai trò</label>

                                <?php if ($isSelf): ?>

                                    <input
                                        type="text"
                                        class="form-control"
                                        value="<?= $escape($user['ten_vai_tro']) ?>"
                                        disabled
                                    >

                                    <small class="text-muted">
                                        Không thể tự thay đổi vai trò của tài khoản đang đăng nhập.
                                    </small>

                                <?php else: ?>

                                    <select
                                        name="ma_vai_tro"
                                        class="form-select"
                                        required
                                        <?= !$canAssignRole ? 'disabled' : '' ?>
                                    >

                                        <?php foreach ($roles as $role): ?>
                                            <option
                                                value="<?= (int) $role['ma_vai_tro'] ?>"
                                                <?= $maVaiTro === (int) $role['ma_vai_tro'] ? 'selected' : '' ?>
                                            >
                                                <?= $escape($role['ten_vai_tro']) ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>

                                    <?php if (!$canAssignRole): ?>
                                        <input
                                            type="hidden"
                                            name="ma_vai_tro"
                                            value="<?= (int) $user['ma_vai_tro'] ?>"
                                        >
                                        <small class="text-muted">
                                            Bạn không có quyền thay đổi vai trò.
                                        </small>
                                    <?php endif; ?>

                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Trạng thái</label>

                                <?php if ($isSelf): ?>

                                    <input
                                        type="text"
                                        class="form-control"
                                        value="Tài khoản đang đăng nhập"
                                        disabled
                                    >

                                    <small class="text-muted">
                                        Không thể tự thay đổi trạng thái tài khoản của mình.
                                    </small>

                                <?php else: ?>

                                    <select
                                        name="trang_thai"
                                        class="form-select"
                                        required
                                    >
                                        <option
                                            value="ACTIVE"
                                            <?= $trangThai === 'ACTIVE' ? 'selected' : '' ?>
                                        >
                                            Đang hoạt động
                                        </option>

                                        <option
                                            value="INACTIVE"
                                            <?= $trangThai === 'INACTIVE' ? 'selected' : '' ?>
                                        >
                                            Ngừng hoạt động
                                        </option>

                                        <option
                                            value="LOCKED"
                                            <?= $trangThai === 'LOCKED' ? 'selected' : '' ?>
                                        >
                                            Đã khóa
                                        </option>
                                    </select>

                                <?php endif; ?>
                            </div>

                        </div>

                        <hr>

                        <div class="d-flex gap-2 justify-content-end">

                            <a
                                href="<?= $escape(BASE_URL) ?>/users.php"
                                class="btn btn-outline-secondary"
                            >
                                Hủy
                            </a>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i>
                                Lưu thay đổi
                            </button>

                        </div>

                    </form>

                </div>
            </div>

        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
