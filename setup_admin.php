
<?php

// =====================================================
// HP COMPUTER
// FILE: setup_admin.php
// CHUC NANG: TAO TAI KHOAN ADMIN DEMO
// LUU MAT KHAU DUOI DANG VAN BAN THUAN
// CHI SU DUNG TREN LOCALHOST
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

// Tao CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escapeAdminSetup($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$messageType = '';

// Kiem tra vai tro Admin
$stmt = $pdo->prepare("
    SELECT ma_vai_tro
    FROM vai_tro
    WHERE ma_vai_tro = 1
    LIMIT 1
");
$stmt->execute();

$adminRole = $stmt->fetch();

// Kiem tra Admin da ton tai
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM nguoi_dung
    WHERE ma_vai_tro = 1
");
$stmt->execute();

$adminExists = (int)$stmt->fetchColumn() > 0;

// Xu ly tao tai khoan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$adminExists) {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $message = 'Yeu cau khong hop le.';
        $messageType = 'danger';

    } else {

        $username = trim($_POST['ten_dang_nhap'] ?? '');
        $fullname = trim($_POST['ho_ten'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['so_dien_thoai'] ?? '');

        $password = $_POST['mat_khau'] ?? '';
        $confirmPassword = $_POST['xac_nhan_mat_khau'] ?? '';

        if (!$adminRole) {

            $message = 'Khong tim thay vai tro Admin.';
            $messageType = 'danger';

        } elseif (
            $username === '' ||
            $fullname === '' ||
            $password === '' ||
            $confirmPassword === ''
        ) {

            $message = 'Vui long nhap day du thong tin bat buoc.';
            $messageType = 'danger';

        } elseif (strlen($username) < 4 || strlen($username) > 100) {

            $message = 'Ten dang nhap phai tu 4 den 100 ky tu.';
            $messageType = 'danger';

        } elseif (strlen($fullname) > 150) {

            $message = 'Ho ten khong duoc vuot qua 150 ky tu.';
            $messageType = 'danger';

        } elseif (strlen($password) < 8) {

            $message = 'Mat khau phai co it nhat 8 ky tu.';
            $messageType = 'danger';

        } elseif ($password !== $confirmPassword) {

            $message = 'Mat khau xac nhan khong khop.';
            $messageType = 'danger';

        } elseif (
            $email !== '' &&
            (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150)
        ) {

            $message = 'Email khong hop le.';
            $messageType = 'danger';

        } elseif (strlen($phone) > 20) {

            $message = 'So dien thoai khong duoc vuot qua 20 ky tu.';
            $messageType = 'danger';

        } else {

            try {

                // Kiem tra lai Admin
                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM nguoi_dung
                    WHERE ma_vai_tro = 1
                ");
                $stmt->execute();

                if ((int)$stmt->fetchColumn() > 0) {

                    $message = 'Tai khoan Admin da ton tai.';
                    $messageType = 'danger';

                } else {

                    // DEMO: LUU MAT KHAU TRUC TIEP
                    // KHONG SU DUNG KHI TRIEN KHAI THUC TE

                    $stmt = $pdo->prepare("
                        INSERT INTO nguoi_dung (
                            ma_vai_tro,
                            ten_dang_nhap,
                            mat_khau,
                            ho_ten,
                            email,
                            so_dien_thoai,
                            trang_thai
                        ) VALUES (
                            1,
                            :ten_dang_nhap,
                            :mat_khau,
                            :ho_ten,
                            :email,
                            :so_dien_thoai,
                            'ACTIVE'
                        )
                    ");

                    $stmt->execute([
                        ':ten_dang_nhap' => $username,
                        ':mat_khau' => $password,
                        ':ho_ten' => $fullname,
                        ':email' => $email !== '' ? $email : null,
                        ':so_dien_thoai' => $phone !== '' ? $phone : null
                    ]);

                    $message = 'Tao tai khoan Admin demo thanh cong!';
                    $messageType = 'success';

                    $adminExists = true;

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }

            } catch (PDOException $e) {

                error_log($e->getMessage());

                if ($e->getCode() === '23000') {
                    $message = 'Ten dang nhap hoac email da duoc su dung.';
                } else {
                    $message = 'Khong the tao tai khoan. Vui long kiem tra database.';
                }

                $messageType = 'danger';
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tạo Admin - HP Computer</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #f4f6f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: Arial, sans-serif;
        }

        .setup-card {
            width: 100%;
            max-width: 500px;
            background: #fff;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
        }

        .setup-title {
            text-align: center;
            margin-bottom: 25px;
        }

        .setup-title h2 {
            font-weight: 700;
        }

        .setup-title p {
            color: #6c757d;
        }

        .form-label {
            font-weight: 600;
        }

        .btn-create {
            width: 100%;
            padding: 11px;
            font-weight: 600;
        }
    </style>
</head>

<body>

<div class="setup-card">

    <div class="setup-title">
        <h2>HP COMPUTER</h2>
        <p>Thiết lập tài khoản quản trị</p>
    </div>

    <?php if ($adminExists): ?>

        <div class="alert alert-success">
            <strong>Tài khoản Admin đã tồn tại.</strong>
            <p class="mb-0 mt-2">
                Không thể tạo thêm Admin bằng trang thiết lập này.
            </p>
        </div>

        <a href="login.php" class="btn btn-primary w-100">
            Đến trang đăng nhập
        </a>

    <?php else: ?>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= escapeAdminSetup($messageType) ?>">
                <?= escapeAdminSetup($message) ?>
            </div>
        <?php endif; ?>

        <div class="alert alert-warning">
            Chế độ demo: mật khẩu được lưu trực tiếp trong database.
            Không sử dụng cách này khi triển khai website thực tế.
        </div>

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapeAdminSetup($_SESSION['csrf_token']) ?>"
            >

            <div class="mb-3">
                <label class="form-label">Tên đăng nhập *</label>
                <input
                    type="text"
                    name="ten_dang_nhap"
                    class="form-control"
                    minlength="4"
                    maxlength="100"
                    value="<?= escapeAdminSetup($_POST['ten_dang_nhap'] ?? '') ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Họ và tên *</label>
                <input
                    type="text"
                    name="ho_ten"
                    class="form-control"
                    maxlength="150"
                    value="<?= escapeAdminSetup($_POST['ho_ten'] ?? '') ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input
                    type="email"
                    name="email"
                    class="form-control"
                    maxlength="150"
                    value="<?= escapeAdminSetup($_POST['email'] ?? '') ?>"
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Số điện thoại</label>
                <input
                    type="text"
                    name="so_dien_thoai"
                    class="form-control"
                    maxlength="20"
                    value="<?= escapeAdminSetup($_POST['so_dien_thoai'] ?? '') ?>"
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Mật khẩu *</label>
                <input
                    type="password"
                    name="mat_khau"
                    class="form-control"
                    minlength="8"
                    required
                >
            </div>

            <div class="mb-4">
                <label class="form-label">Xác nhận mật khẩu *</label>
                <input
                    type="password"
                    name="xac_nhan_mat_khau"
                    class="form-control"
                    minlength="8"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary btn-create">
                Tạo tài khoản Admin
            </button>

        </form>

    <?php endif; ?>

</div>

</body>
</html>
