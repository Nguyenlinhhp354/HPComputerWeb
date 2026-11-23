
<?php
// =====================================================
// HP COMPUTER
// FILE: login.php
// CHUC NANG: DANG NHAP VA DANG KY TAI KHOAN
// TAM THOI: KHONG SU DUNG CAPTCHA
// KHACH HANG DANG KY MAC DINH CO VAI TRO = 6
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

// =====================================================
// HAM HO TRO
// =====================================================

function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// =====================================================
// NEU DA DANG NHAP THI CHUYEN TRANG
// =====================================================

if (!empty($_SESSION['ma_nguoi_dung'])) {

    if ((int) ($_SESSION['ma_vai_tro'] ?? 0) === 6) {
        header('Location: ' . BASE_URL . '/modules/customers/index.php');
        exit;
    }

    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// =====================================================
// KHOI TAO CSRF TOKEN
// =====================================================

if (empty($_SESSION['login_csrf_token'])) {
    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['login_csrf_token'];

// =====================================================
// KHOI TAO DU LIEU
// =====================================================

$mode = $_GET['mode'] ?? 'login';

if (!in_array($mode, ['login', 'register'], true)) {
    $mode = 'login';
}

$error = '';
$success = '';

$old = [
    'ho_ten' => '',
    'ten_dang_nhap' => '',
    'email' => '',
    'so_dien_thoai' => '',
    'tai_khoan' => '',
];

// =====================================================
// THONG BAO
// =====================================================

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $success = 'Đăng ký tài khoản thành công! Bạn có thể đăng nhập ngay.';
}

if (isset($_GET['error']) && $_GET['error'] === 'account') {
    $error = 'Tài khoản của bạn không thể thực hiện thao tác này.';
}

// =====================================================
// XU LY FORM
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    $mode = ($action === 'register') ? 'register' : 'login';

    // -------------------------------------------------
    // KIEM TRA CSRF
    // -------------------------------------------------

    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    }

    // -------------------------------------------------
    // KIEM TRA ACTION
    // -------------------------------------------------

    elseif (!in_array($action, ['login', 'register'], true)) {
        $error = 'Yêu cầu không hợp lệ.';
    }

    // =================================================
    // DANG KY TAI KHOAN
    // =================================================

    elseif ($action === 'register') {

        $hoTen = trim($_POST['ho_ten'] ?? '');
        $tenDangNhap = trim($_POST['ten_dang_nhap'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $soDienThoai = trim($_POST['so_dien_thoai'] ?? '');

        $matKhau = $_POST['mat_khau'] ?? '';
        $xacNhanMatKhau = $_POST['xac_nhan_mat_khau'] ?? '';

        $old = [
            'ho_ten' => $hoTen,
            'ten_dang_nhap' => $tenDangNhap,
            'email' => $email,
            'so_dien_thoai' => $soDienThoai,
            'tai_khoan' => '',
        ];

        // ---------------------------------------------
        // KIEM TRA DU LIEU
        // ---------------------------------------------

        if (
            $hoTen === '' ||
            $tenDangNhap === '' ||
            $email === '' ||
            $matKhau === '' ||
            $xacNhanMatKhau === ''
        ) {
            $error = 'Vui lòng nhập đầy đủ các thông tin bắt buộc.';
        }

        elseif (mb_strlen($hoTen) > 150) {
            $error = 'Họ tên không được vượt quá 150 ký tự.';
        }

        elseif (
            strlen($tenDangNhap) < 4 ||
            strlen($tenDangNhap) > 100
        ) {
            $error = 'Tên đăng nhập phải có từ 4 đến 100 ký tự.';
        }

        elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $tenDangNhap)) {
            $error = 'Tên đăng nhập chỉ được chứa chữ cái, chữ số, dấu chấm, gạch dưới và gạch ngang.';
        }

        elseif (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Địa chỉ email không hợp lệ.';
        }

        elseif (
            $soDienThoai !== '' &&
            !preg_match('/^(0[0-9]{9}|\+84[0-9]{9})$/', $soDienThoai)
        ) {
            $error = 'Số điện thoại không hợp lệ.';
        }

        elseif (strlen($matKhau) < 8) {
            $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
        }

        elseif ($matKhau !== $xacNhanMatKhau) {
            $error = 'Mật khẩu xác nhận không khớp.';
        }

        // ---------------------------------------------
        // KIEM TRA CO SO DU LIEU
        // ---------------------------------------------

        else {

            try {

                // Kiểm tra tên đăng nhập
                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM nguoi_dung
                    WHERE LOWER(ten_dang_nhap) = LOWER(:ten_dang_nhap)
                ");

                $stmt->execute([
                    ':ten_dang_nhap' => $tenDangNhap
                ]);

                if ((int) $stmt->fetchColumn() > 0) {
                    $error = 'Tên đăng nhập đã được sử dụng.';
                }

                // Kiểm tra email
                if ($error === '') {

                    $stmt = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM nguoi_dung
                        WHERE LOWER(email) = LOWER(:email)
                    ");

                    $stmt->execute([
                        ':email' => $email
                    ]);

                    if ((int) $stmt->fetchColumn() > 0) {
                        $error = 'Email đã được sử dụng.';
                    }
                }

                // Kiểm tra số điện thoại
                if ($error === '' && $soDienThoai !== '') {

                    $stmt = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM nguoi_dung
                        WHERE so_dien_thoai = :so_dien_thoai
                    ");

                    $stmt->execute([
                        ':so_dien_thoai' => $soDienThoai
                    ]);

                    if ((int) $stmt->fetchColumn() > 0) {
                        $error = 'Số điện thoại đã được sử dụng.';
                    }
                }

                // -------------------------------------
                // KIEM TRA VAI TRO KHACH HANG
                // -------------------------------------

                if ($error === '') {

                    $stmt = $pdo->prepare("
                        SELECT ma_vai_tro
                        FROM vai_tro
                        WHERE ma_vai_tro = 6
                        LIMIT 1
                    ");

                    $stmt->execute();

                    $role = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$role) {
                        $error = 'Không tìm thấy vai trò Khách hàng trong hệ thống.';
                    }
                }

                // -------------------------------------
                // THEM TAI KHOAN
                // -------------------------------------

                if ($error === '') {

                    $matKhauHash = password_hash(
                        $matKhau,
                        PASSWORD_DEFAULT
                    );

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
                            :ma_vai_tro,
                            :ten_dang_nhap,
                            :mat_khau,
                            :ho_ten,
                            :email,
                            :so_dien_thoai,
                            'ACTIVE'
                        )
                    ");

                    $stmt->execute([
                        ':ma_vai_tro' => 6,
                        ':ten_dang_nhap' => $tenDangNhap,
                        ':mat_khau' => $matKhauHash,
                        ':ho_ten' => $hoTen,
                        ':email' => $email,
                        ':so_dien_thoai' => ($soDienThoai !== '')
                            ? $soDienThoai
                            : null,
                    ]);

                    // Tạo CSRF token mới
                    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));

                    header(
                        'Location: ' . BASE_URL . '/login.php?registered=1'
                    );

                    exit;
                }

            } catch (PDOException $ex) {

                error_log(
                    'HPComputer - Register error: ' . $ex->getMessage()
                );

                // Lỗi khóa UNIQUE
                if ($ex->getCode() === '23000') {
                    $error = 'Tên đăng nhập, email hoặc số điện thoại đã tồn tại.';
                } else {
                    $error = 'Đăng ký thất bại. Vui lòng kiểm tra lại dữ liệu hoặc thử lại sau.';
                }
            }
        }
    }

    // =================================================
    // DANG NHAP
    // =================================================

    elseif ($action === 'login' && $error === '') {

        $taiKhoan = trim($_POST['tai_khoan'] ?? '');
        $matKhau = $_POST['mat_khau'] ?? '';

        $old['tai_khoan'] = $taiKhoan;

        if ($taiKhoan === '' || $matKhau === '') {

            $error = 'Vui lòng nhập tài khoản và mật khẩu.';

        } else {

            try {

                // Tìm người dùng bằng tên đăng nhập hoặc email
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
                    LEFT JOIN vai_tro vt
                        ON nd.ma_vai_tro = vt.ma_vai_tro
                    WHERE
                        LOWER(nd.ten_dang_nhap) = LOWER(:tai_khoan)
                        OR LOWER(nd.email) = LOWER(:email)
                    LIMIT 1
                ");

                $stmt->execute([
                    ':tai_khoan' => $taiKhoan,
                    ':email' => $taiKhoan,
                ]);

                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {

                    $error = 'Tên đăng nhập hoặc mật khẩu không chính xác.';

                } else {

                    $storedPassword = (string) $user['mat_khau'];

                    // Kiểm tra mật khẩu đã mã hóa
                    $passwordValid = password_verify(
                        $matKhau,
                        $storedPassword
                    );

                    // Hỗ trợ mật khẩu cũ dạng văn bản
                    if (
                        !$passwordValid &&
                        hash_equals($storedPassword, $matKhau)
                    ) {
                        $passwordValid = true;

                        // Tự động nâng cấp sang password_hash
                        $newHash = password_hash(
                            $matKhau,
                            PASSWORD_DEFAULT
                        );

                        $update = $pdo->prepare("
                            UPDATE nguoi_dung
                            SET mat_khau = :mat_khau
                            WHERE ma_nguoi_dung = :ma_nguoi_dung
                        ");

                        $update->execute([
                            ':mat_khau' => $newHash,
                            ':ma_nguoi_dung' => $user['ma_nguoi_dung'],
                        ]);
                    }

                    if (!$passwordValid) {

                        $error = 'Tên đăng nhập hoặc mật khẩu không chính xác.';

                    } elseif (
                        strtoupper(trim($user['trang_thai'] ?? ''))
                        !== 'ACTIVE'
                    ) {

                        if (
                            strtoupper(trim($user['trang_thai'] ?? ''))
                            === 'LOCKED'
                        ) {
                            $error = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.';
                        } else {
                            $error = 'Tài khoản của bạn hiện không hoạt động.';
                        }

                    } else {

                        // ---------------------------------
                        // DANG NHAP THANH CONG
                        // ---------------------------------

                        session_regenerate_id(true);

                        $_SESSION['ma_nguoi_dung'] =
                            $user['ma_nguoi_dung'];

                        $_SESSION['ma_vai_tro'] =
                            $user['ma_vai_tro'];

                        $_SESSION['ten_dang_nhap'] =
                            $user['ten_dang_nhap'];

                        $_SESSION['ho_ten'] =
                            $user['ho_ten'];

                        $_SESSION['email'] =
                            $user['email'];

                        $_SESSION['ten_vai_tro'] =
                            $user['ten_vai_tro'];

                        // Tạo CSRF token mới sau đăng nhập
                        $_SESSION['login_csrf_token'] =
                            bin2hex(random_bytes(32));

                        // Tạo CSRF token dùng cho các chức năng quản trị
                        $_SESSION['csrf_token'] =
                            bin2hex(random_bytes(32));

                        // Chuyển hướng theo vai trò
                        if ((int) $user['ma_vai_tro'] === 6) {

                            header(
                                'Location: ' .
                                BASE_URL .
                                '/modules/customers/index.php'
                            );

                        } else {

                            header(
                                'Location: ' .
                                BASE_URL .
                                '/index.php'
                            );
                        }

                        exit;
                    }
                }

            } catch (PDOException $ex) {

                error_log(
                    'HPComputer - Login error: ' . $ex->getMessage()
                );

                $error = 'Có lỗi xảy ra khi đăng nhập. Vui lòng thử lại sau.';
            }
        }
    }
}

// =====================================================
// LAY BASE URL
// =====================================================

$baseUrl = rtrim(BASE_URL, '/');

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?= $mode === 'register'
            ? 'Đăng ký tài khoản - HP Computer'
            : 'Đăng nhập - HP Computer' ?>
    </title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #eef4ff, #f8faff);
            font-family: Arial, sans-serif;
        }

        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 35px 15px;
        }

        .auth-container {
            width: 100%;
            max-width: 1050px;
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, .10);
        }

        .auth-intro {
            height: 100%;
            min-height: 580px;
            padding: 50px 40px;
            background: linear-gradient(145deg, #0756c9, #00347f);
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .brand-icon {
            width: 75px;
            height: 75px;
            border-radius: 18px;
            background: rgba(255,255,255,.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            margin-bottom: 25px;
        }

        .auth-intro h1 {
            font-weight: 800;
            margin-bottom: 18px;
        }

        .auth-intro p {
            color: rgba(255,255,255,.85);
            line-height: 1.8;
        }

        .auth-feature {
            margin-top: 30px;
        }

        .auth-feature div {
            margin-bottom: 15px;
        }

        .auth-feature i {
            margin-right: 10px;
        }

        .auth-form {
            padding: 45px 45px;
        }

        .auth-form h2 {
            font-weight: 750;
            color: #172b4d;
            margin-bottom: 8px;
        }

        .auth-subtitle {
            color: #758195;
            margin-bottom: 28px;
        }

        .form-label {
            font-weight: 600;
            color: #344054;
        }

        .form-control {
            min-height: 46px;
            border-radius: 9px;
            border-color: #dce2ea;
        }

        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 .2rem rgba(13,110,253,.10);
        }

        .input-group-text {
            background: #f7f9fc;
            border-color: #dce2ea;
            color: #667085;
        }

        .btn-primary {
            min-height: 48px;
            border-radius: 9px;
            font-weight: 700;
            background: #0756c9;
            border-color: #0756c9;
        }

        .btn-primary:hover {
            background: #0647a6;
            border-color: #0647a6;
        }

        .auth-switch {
            text-align: center;
            margin-top: 22px;
            color: #667085;
        }

        .auth-switch a {
            text-decoration: none;
            font-weight: 700;
        }

        .back-home {
            text-decoration: none;
            color: #667085;
            display: inline-block;
            margin-top: 22px;
        }

        .back-home:hover {
            color: #0756c9;
        }

        .alert {
            border-radius: 9px;
        }

        @media (max-width: 767px) {
            .auth-intro {
                min-height: auto;
                padding: 35px 25px;
            }

            .auth-feature {
                display: none;
            }

            .auth-form {
                padding: 35px 25px;
            }
        }
    </style>
</head>

<body>

<div class="auth-wrapper">

    <div class="auth-container">

        <div class="row g-0">

            <!-- ===================================== -->
            <!-- COT GIOI THIEU -->
            <!-- ===================================== -->

            <div class="col-md-5">

                <div class="auth-intro">

                    <div class="brand-icon">
                        <i class="bi bi-pc-display-horizontal"></i>
                    </div>

                    <h1>HP COMPUTER</h1>

                    <p>
                        Hệ thống quản lý và kinh doanh thiết bị máy tính.
                        Mang đến trải nghiệm mua sắm thuận tiện,
                        nhanh chóng và đáng tin cậy.
                    </p>

                    <div class="auth-feature">

                        <div>
                            <i class="bi bi-shield-check"></i>
                            Thông tin tài khoản được bảo vệ
                        </div>

                        <div>
                            <i class="bi bi-cart-check"></i>
                            Quản lý đơn hàng thuận tiện
                        </div>

                        <div>
                            <i class="bi bi-headset"></i>
                            Hỗ trợ khách hàng
                        </div>

                    </div>

                </div>

            </div>

            <!-- ===================================== -->
            <!-- COT FORM -->
            <!-- ===================================== -->

            <div class="col-md-7">

                <div class="auth-form">

                    <?php if ($mode === 'register'): ?>

                        <h2>Tạo tài khoản</h2>

                        <p class="auth-subtitle">
                            Đăng ký tài khoản khách hàng HP Computer
                        </p>

                    <?php else: ?>

                        <h2>Chào mừng trở lại!</h2>

                        <p class="auth-subtitle">
                            Đăng nhập vào tài khoản HP Computer của bạn
                        </p>

                    <?php endif; ?>


                    <!-- THONG BAO -->

                    <?php if ($error !== ''): ?>

                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            <?= e($error) ?>
                        </div>

                    <?php endif; ?>


                    <?php if ($success !== ''): ?>

                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            <?= e($success) ?>
                        </div>

                    <?php endif; ?>


                    <!-- ================================= -->
                    <!-- FORM DANG KY -->
                    <!-- ================================= -->

                    <?php if ($mode === 'register'): ?>

                        <form method="POST"
                              action="<?= e($baseUrl) ?>/login.php?mode=register"
                              autocomplete="on">

                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?= e($csrfToken) ?>">

                            <input type="hidden"
                                   name="action"
                                   value="register">


                            <div class="mb-3">

                                <label class="form-label">
                                    Họ và tên <span class="text-danger">*</span>
                                </label>

                                <input type="text"
                                       name="ho_ten"
                                       class="form-control"
                                       placeholder="Nhập họ và tên"
                                       maxlength="150"
                                       value="<?= e($old['ho_ten']) ?>"
                                       required>

                            </div>


                            <div class="mb-3">

                                <label class="form-label">
                                    Tên đăng nhập <span class="text-danger">*</span>
                                </label>

                                <input type="text"
                                       name="ten_dang_nhap"
                                       class="form-control"
                                       placeholder="Nhập tên đăng nhập"
                                       minlength="4"
                                       maxlength="100"
                                       value="<?= e($old['ten_dang_nhap']) ?>"
                                       required>

                            </div>


                            <div class="mb-3">

                                <label class="form-label">
                                    Email <span class="text-danger">*</span>
                                </label>

                                <input type="email"
                                       name="email"
                                       class="form-control"
                                       placeholder="example@gmail.com"
                                       maxlength="150"
                                       value="<?= e($old['email']) ?>"
                                       required>

                            </div>


                            <div class="mb-3">

                                <label class="form-label">
                                    Số điện thoại
                                </label>

                                <input type="tel"
                                       name="so_dien_thoai"
                                       class="form-control"
                                       placeholder="Nhập số điện thoại"
                                       value="<?= e($old['so_dien_thoai']) ?>">

                            </div>


                            <div class="row">

                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Mật khẩu <span class="text-danger">*</span>
                                    </label>

                                    <input type="password"
                                           name="mat_khau"
                                           class="form-control"
                                           placeholder="Ít nhất 8 ký tự"
                                           minlength="8"
                                           autocomplete="new-password"
                                           required>

                                </div>


                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Xác nhận mật khẩu
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input type="password"
                                           name="xac_nhan_mat_khau"
                                           class="form-control"
                                           placeholder="Nhập lại mật khẩu"
                                           minlength="8"
                                           autocomplete="new-password"
                                           required>

                                </div>

                            </div>


                            <button type="submit"
                                    class="btn btn-primary w-100">

                                <i class="bi bi-person-plus me-2"></i>
                                Đăng ký tài khoản

                            </button>

                        </form>


                        <div class="auth-switch">

                            Đã có tài khoản?

                            <a href="<?= e($baseUrl) ?>/login.php">
                                Đăng nhập ngay
                            </a>

                        </div>


                    <!-- ================================= -->
                    <!-- FORM DANG NHAP -->
                    <!-- ================================= -->

                    <?php else: ?>

                        <form method="POST"
                              action="<?= e($baseUrl) ?>/login.php"
                              autocomplete="on">

                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?= e($csrfToken) ?>">

                            <input type="hidden"
                                   name="action"
                                   value="login">


                            <div class="mb-3">

                                <label class="form-label">
                                    Tên đăng nhập hoặc Email
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">
                                        <i class="bi bi-person"></i>
                                    </span>

                                    <input type="text"
                                           name="tai_khoan"
                                           class="form-control"
                                           placeholder="Nhập tên đăng nhập hoặc email"
                                           value="<?= e($old['tai_khoan']) ?>"
                                           required>

                                </div>

                            </div>


                            <div class="mb-4">

                                <label class="form-label">
                                    Mật khẩu
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>

                                    <input type="password"
                                           name="mat_khau"
                                           class="form-control"
                                           placeholder="Nhập mật khẩu"
                                           autocomplete="current-password"
                                           required>

                                </div>

                            </div>


                            <button type="submit"
                                    class="btn btn-primary w-100">

                                <i class="bi bi-box-arrow-in-right me-2"></i>
                                Đăng nhập

                            </button>

                        </form>


                        <div class="auth-switch">

                            Chưa có tài khoản?

                            <a href="<?= e($baseUrl) ?>/login.php?mode=register">
                                Đăng ký ngay
                            </a>

                        </div>

                    <?php endif; ?>


                    <div class="text-center">

                        <a href="<?= e($baseUrl) ?>/index.php"
                           class="back-home">

                            <i class="bi bi-arrow-left me-1"></i>
                            Quay lại trang chủ

                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
```