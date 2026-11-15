
<?php

// =====================================================
// HP COMPUTER
// FILE: login.php
// CHUC NANG: DANG NHAP CHUNG ADMIN, NHAN VIEN, KHACH HANG
// DEMO LOCALHOST: SO SANH MAT KHAU VAN BAN THUAN
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

// Neu da dang nhap thi khong cho quay lai trang login
if (!empty($_SESSION['ma_nguoi_dung'])) {

    if (($_SESSION['ma_vai_tro'] ?? 0) == 6) {
        // Giao dien khach hang se duoc xay dung sau
        header('Location: ' . BASE_URL . '/login.php?customer=1');
        exit;
    }

    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Tao CSRF token
if (empty($_SESSION['login_csrf_token'])) {
    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$notice = '';

// Thong bao danh cho khach hang
if (isset($_GET['customer'])) {
    $notice = 'Khu vuc khach hang dang duoc phat trien. Vui long quay lai sau.';
}

// Xu ly dang nhap
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['login_csrf_token'], $csrfToken)
    ) {

        $error = 'Phien lam viec khong hop le. Vui long tai lai trang.';

    } else {

        $loginName = trim($_POST['ten_dang_nhap'] ?? '');
        $password = $_POST['mat_khau'] ?? '';

        if ($loginName === '' || $password === '') {

            $error = 'Vui long nhap ten dang nhap va mat khau.';

        } else {

            try {

                // Tim tai khoan bang ten dang nhap hoac email
                $stmt = $pdo->prepare("
                    SELECT
                        nd.ma_nguoi_dung,
                        nd.ma_vai_tro,
                        nd.ten_dang_nhap,
                        nd.mat_khau,
                        nd.ho_ten,
                        nd.email,
                        nd.trang_thai,
                        vt.ten_vai_tro
                    FROM nguoi_dung nd
                    INNER JOIN vai_tro vt
                        ON nd.ma_vai_tro = vt.ma_vai_tro
                    WHERE nd.ten_dang_nhap = :ten_dang_nhap
                       OR nd.email = :email
                    LIMIT 1
                ");

                $stmt->execute([
                    ':ten_dang_nhap' => $loginName,
                    ':email' => $loginName
                ]);

                $user = $stmt->fetch();

                if (!$user || $password !== $user['mat_khau']) {

                    $error = 'Ten dang nhap hoac mat khau khong chinh xac.';

                } elseif ($user['trang_thai'] !== 'ACTIVE') {

                    if ($user['trang_thai'] === 'LOCKED') {
                        $error = 'Tai khoan cua ban da bi khoa. Vui long lien he quan tri vien.';
                    } else {
                        $error = 'Tai khoan cua ban dang khong hoat dong.';
                    }

                } else {

                    // Dang nhap thanh cong
                    session_regenerate_id(true);

                    $_SESSION['ma_nguoi_dung'] = (int)$user['ma_nguoi_dung'];
                    $_SESSION['ma_vai_tro'] = (int)$user['ma_vai_tro'];
                    $_SESSION['ten_dang_nhap'] = $user['ten_dang_nhap'];
                    $_SESSION['ho_ten'] = $user['ho_ten'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['ten_vai_tro'] = $user['ten_vai_tro'];

                    // Tao lai CSRF token sau dang nhap
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    // Chuyen huong theo vai tro
                    if ((int)$user['ma_vai_tro'] === 6) {

                        header('Location: ' . BASE_URL . '/login.php?customer=1');
                        exit;

                    } else {

                        header('Location: ' . BASE_URL . '/index.php');
                        exit;
                    }
                }

            } catch (PDOException $e) {

                error_log($e->getMessage());
                $error = 'He thong dang gap loi. Vui long thu lai sau.';
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

    <title>Đăng nhập - HP Computer</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #f3f5f9;
            font-family: Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 950px;
            min-height: 560px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #ffffff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 8px 35px rgba(0, 0, 0, 0.08);
        }

        .login-intro {
            background: #1f2937;
            color: #ffffff;
            padding: 55px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-intro h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .login-intro p {
            color: #d1d5db;
            line-height: 1.7;
        }

        .login-intro .intro-icon {
            font-size: 65px;
            margin-bottom: 25px;
        }

        .login-form-area {
            padding: 55px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-form-area h2 {
            font-size: 27px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-subtitle {
            color: #6c757d;
            margin-bottom: 30px;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-control {
            height: 46px;
            border-radius: 7px;
        }

        .btn-login {
            height: 46px;
            border-radius: 7px;
            font-weight: 600;
            margin-top: 10px;
        }

        .login-footer {
            margin-top: 25px;
            text-align: center;
            color: #6c757d;
            font-size: 13px;
        }

        @media (max-width: 768px) {

            .login-wrapper {
                grid-template-columns: 1fr;
            }

            .login-intro {
                padding: 30px;
                text-align: center;
            }

            .login-intro .intro-icon {
                font-size: 40px;
                margin-bottom: 10px;
            }

            .login-intro h1 {
                font-size: 25px;
            }

            .login-form-area {
                padding: 35px 25px;
            }
        }

    </style>

</head>

<body>

<div class="login-wrapper">

    <!-- KHU VUC GIOI THIEU -->
    <div class="login-intro">

        <div class="intro-icon">
            <i class="bi bi-pc-display-horizontal"></i>
        </div>

        <h1>HP COMPUTER</h1>

        <p>
            Hệ thống quản lý và kinh doanh thiết bị máy tính.
            Vui lòng đăng nhập để tiếp tục sử dụng các chức năng
            của hệ thống.
        </p>

        <div class="mt-3">
            <i class="bi bi-shield-check me-2"></i>
            Hệ thống quản lý tài khoản và phân quyền
        </div>

    </div>

    <!-- KHU VUC DANG NHAP -->
    <div class="login-form-area">

        <h2>Đăng nhập</h2>

        <p class="login-subtitle">
            Chào mừng bạn đến với HP Computer
        </p>

        <?php if ($notice !== ''): ?>

            <div class="alert alert-info">
                <?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <?php if ($error !== ''): ?>

            <div class="alert alert-danger">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['login_csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <div class="mb-3">

                <label class="form-label">
                    Tên đăng nhập hoặc email
                </label>

                <input
                    type="text"
                    name="ten_dang_nhap"
                    class="form-control"
                    placeholder="Nhập tên đăng nhập hoặc email"
                    value="<?= htmlspecialchars($_POST['ten_dang_nhap'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required
                    autofocus
                >

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Mật khẩu
                </label>

                <input
                    type="password"
                    name="mat_khau"
                    class="form-control"
                    placeholder="Nhập mật khẩu"
                    required
                >

            </div>

            <button type="submit" class="btn btn-primary btn-login w-100">
                <i class="bi bi-box-arrow-in-right me-2"></i>
                Đăng nhập
            </button>

        </form>

        <div class="login-footer">
            © <?= date('Y') ?> HP COMPUTER
            <br>
            Hệ thống quản lý bán hàng
        </div>

    </div>

</div>

</body>
</html>
