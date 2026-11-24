<?php
// =====================================================
// HP COMPUTER
// FILE: includes/auth_check.php
// CHUC NANG: KIEM TRA QUYEN TRUY CAP QUAN TRI
// =====================================================

// Khoi tao Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nap cau hinh va ket noi database
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Kiem tra tai khoan da dang nhap
if (empty($_SESSION['ma_nguoi_dung'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

try {
    // Kiem tra tai khoan con ton tai va dang hoat dong
    $sql = "
        SELECT
            ma_nguoi_dung,
            ma_vai_tro,
            ho_ten,
            trang_thai
        FROM nguoi_dung
        WHERE ma_nguoi_dung = :ma_nguoi_dung
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':ma_nguoi_dung' => $_SESSION['ma_nguoi_dung']
    ]);

    $user = $stmt->fetch();

    // Tai khoan khong ton tai hoac khong hoat dong
    if (!$user || $user['trang_thai'] !== 'ACTIVE') {

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $cookieParams = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $cookieParams['path'],
                $cookieParams['domain'],
                $cookieParams['secure'],
                $cookieParams['httponly']
            );
        }

        session_destroy();

        header('Location: ' . BASE_URL . '/login.php?error=account');
        exit;
    }

    // Cap nhat thong tin tai khoan moi nhat
    $_SESSION['ma_vai_tro'] = $user['ma_vai_tro'];
    $_SESSION['ho_ten'] = $user['ho_ten'];

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);
    exit('Không thể kiểm tra quyền truy cập. Vui lòng thử lại sau.');
}
