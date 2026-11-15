
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/users/edit.php
// CHUC NANG: SUA THONG TIN TAI KHOAN
// =====================================================

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/app.php';

// Chi Admin duoc phep sua tai khoan
if ((int) $_SESSION['ma_vai_tro'] !== 1) {
    http_response_code(403);
    exit('Ban khong co quyen truy cap chuc nang nay.');
}

// Tao CSRF token neu chua co
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ham xu ly du lieu dau ra
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
        nd.*,
        vt.ten_vai_tro
    FROM nguoi_dung nd
    INNER JOIN vai_tro vt
        ON nd.ma_vai_tro = vt.ma_vai_tro
    WHERE nd.ma_nguoi_dung = :id
    LIMIT 1
");

$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ' . BASE_URL . '/users.php?error=notfound');
    exit;
}

// Khong cho phep sua tai khoan khach hang tai trang quan tri
if ((int) $user['ma_vai_tro'] === 6) {
    header('Location: ' . BASE_URL . '/users.php?error=invalidrole');
    exit;
}

// Lay danh sach vai tro
$roleStmt = $pdo->query("
    SELECT ma_vai_tro, ten_vai_tro
    FROM vai_tro
    WHERE ma_vai_tro <> 6
    ORDER BY ma_vai_tro ASC
");

$roles = $roleStmt->fetchAll();

// Khoi tao du lieu form
$hoTen = $user['ho_ten'];
$tenDangNhap = $user['ten_dang_nhap'];
$email = $user['email'] ?? '';
$soDienThoai = $user['so_dien_thoai'] ?? '';
$maVaiTro = (int) $user['ma_vai_tro'];
$trangThai = $user['trang_thai'];

$errors = [];

// Kiem tra tai khoan hien tai co phai Admin dang dang nhap khong
$isSelf = ((int) $_SESSION['ma_nguoi_dung'] === (int) $id);

// Xu ly form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $errors[] = 'Phien lam viec khong hop le. Vui long tai lai trang.';
    }

    $hoTen = trim($_POST['ho_ten'] ?? '');
    $tenDangNhap = trim($_POST['ten_dang_nhap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $soDienThoai = trim($_POST['so_dien_thoai'] ?? '');

    // Neu sua chinh tai khoan dang dang nhap,
    // giu nguyen vai tro va trang thai
    if ($isSelf) {
        $maVaiTro = (int) $user['ma_vai_tro'];
        $trangThai = $user['trang_thai'];
    } else {
        $maVaiTro = (int) ($_POST['ma_vai_tro'] ?? 0);
        $trangThai = $_POST['trang_thai'] ?? '';
    }

    // Kiem tra ho ten
    if ($hoTen === '') {
        $errors[] = 'Vui long nhap ho va ten.';
    } elseif (mb_strlen($hoTen, 'UTF-8') > 150) {
        $errors[] = 'Ho va ten khong duoc vuot qua 150 ky tu.';
    }

    // Kiem tra ten dang nhap
    if ($tenDangNhap === '') {
        $errors[] = 'Vui long nhap ten dang nhap.';
    } elseif (
        !preg_match('/^[a-zA-Z0-9_.-]+$/', $tenDangNhap)
    ) {
        $errors[] = 'Ten dang nhap chi duoc chua chu cai khong dau, so, dau cham, gach ngang va gach duoi.';
    } elseif (
        mb_strlen($tenDangNhap, 'UTF-8') < 4 ||
        mb_strlen($tenDangNhap, 'UTF-8') > 100
    ) {
        $errors[] = 'Ten dang nhap phai tu 4 den 100 ky tu.';
    }

    // Kiem tra email
    if ($email !== '') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Dia chi email khong hop le.';
        } elseif (mb_strlen($email, 'UTF-8') > 150) {
            $errors[] = 'Email khong duoc vuot qua 150 ky tu.';
        }
    }

    // Kiem tra so dien thoai
    if (
        mb_strlen($soDienThoai, 'UTF-8') > 20
    ) {
        $errors[] = 'So dien thoai khong duoc vuot qua 20 ky tu.';
    }

    // Kiem tra vai tro
    $validRole = false;

    foreach ($roles as $role) {
        if ((int) $role['ma_vai_tro'] === $maVaiTro) {
            $validRole = true;
            break;
        }
    }

    if (!$validRole) {
        $errors[] = 'Vai tro duoc chon khong hop le.';
    }

    // Kiem tra trang thai
    $validStatuses = ['ACTIVE', 'INACTIVE', 'LOCKED'];

    if (!in_array($trangThai, $validStatuses, true)) {
        $errors[] = 'Trang thai tai khoan khong hop le.';
    }

    // Kiem tra trung ten dang nhap
    if ($tenDangNhap !== '') {
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

        if ($checkUsername->fetch()) {
            $errors[] = 'Ten dang nhap da ton tai.';
        }
    }

    // Kiem tra trung email
    if ($email !== '') {
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

        if ($checkEmail->fetch()) {
            $errors[] = 'Email da duoc su dung boi tai khoan khac.';
        }
    }

    // Cap nhat tai khoan
    if (empty($errors)) {
        try {
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

            header('Location: ' . BASE_URL . '/users.php?updated=1');
            exit;

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Khong the cap nhat tai khoan. Vui long thu lai.';
        }
    }
}

// Ten trang
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

                <a href="<?= $escape(BASE_URL) ?>/users.php"
                   class="btn btn-outline-secondary">
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

                    <form method="POST"
                          action="<?= $escape(BASE_URL) ?>/modules/users/edit.php?id=<?= (int) $id ?>">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= $escape($_SESSION['csrf_token']) ?>">

                        <div class="row">

                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Tên đăng nhập <span class="text-danger">*</span>
                                </label>

                                <input type="text"
                                       name="ten_dang_nhap"
                                       class="form-control"
                                       maxlength="100"
                                       required
                                       value="<?= $escape($tenDangNhap) ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Họ và tên <span class="text-danger">*</span>
                                </label>

                                <input type="text"
                                       name="ho_ten"
                                       class="form-control"
                                       maxlength="150"
                                       required
                                       value="<?= $escape($hoTen) ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>

                                <input type="email"
                                       name="email"
                                       class="form-control"
                                       maxlength="150"
                                       value="<?= $escape($email) ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Số điện thoại</label>

                                <input type="text"
                                       name="so_dien_thoai"
                                       class="form-control"
                                       maxlength="20"
                                       value="<?= $escape($soDienThoai) ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Vai trò</label>

                                <?php if ($isSelf): ?>

                                    <input type="hidden"
                                           name="ma_vai_tro"
                                           value="<?= $maVaiTro ?>">

                                    <input type="text"
                                           class="form-control"
                                           value="<?= $escape($user['ten_vai_tro']) ?>"
                                           disabled>

                                    <small class="text-muted">
                                        Không thể tự thay đổi vai trò của tài khoản đang đăng nhập.
                                    </small>

                                <?php else: ?>

                                    <select name="ma_vai_tro"
                                            class="form-select"
                                            required>

                                        <?php foreach ($roles as $role): ?>
                                            <option
                                                value="<?= (int) $role['ma_vai_tro'] ?>"
                                                <?= $maVaiTro === (int) $role['ma_vai_tro'] ? 'selected' : '' ?>>
                                                <?= $escape($role['ten_vai_tro']) ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>

                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Trạng thái</label>

                                <?php if ($isSelf): ?>

                                    <input type="hidden"
                                           name="trang_thai"
                                           value="<?= $escape($trangThai) ?>">

                                    <input type="text"
                                           class="form-control"
                                           value="Tài khoản đang đăng nhập"
                                           disabled>

                                    <small class="text-muted">
                                        Không thể tự thay đổi trạng thái tài khoản của mình.
                                    </small>

                                <?php else: ?>

                                    <select name="trang_thai"
                                            class="form-select"
                                            required>

                                        <option value="ACTIVE"
                                            <?= $trangThai === 'ACTIVE' ? 'selected' : '' ?>>
                                            Đang hoạt động
                                        </option>

                                        <option value="INACTIVE"
                                            <?= $trangThai === 'INACTIVE' ? 'selected' : '' ?>>
                                            Ngừng hoạt động
                                        </option>

                                        <option value="LOCKED"
                                            <?= $trangThai === 'LOCKED' ? 'selected' : '' ?>>
                                            Đã khóa
                                        </option>

                                    </select>

                                <?php endif; ?>
                            </div>

                        </div>

                        <hr>

                        <div class="d-flex gap-2 justify-content-end">

                            <a href="<?= $escape(BASE_URL) ?>/users.php"
                               class="btn btn-outline-secondary">
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
