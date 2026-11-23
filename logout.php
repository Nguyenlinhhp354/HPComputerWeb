
<?php

// =====================================================
// HP COMPUTER
// FILE: logout.php
// CHUC NANG: DANG XUAT TAI KHOAN
// =====================================================

// Khoi tao Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nap cau hinh ung dung
require_once __DIR__ . '/config/app.php';

// URL trang chu mua hang
$customerHomeUrl = rtrim(BASE_URL, '/') . '/modules/customers/index.php';

// Chi cho phep dang xuat bang POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $customerHomeUrl);
    exit;
}

// Kiem tra CSRF token
$token = $_POST['csrf_token'] ?? '';

if (
    !is_string($token) ||
    !is_string($_SESSION['csrf_token'] ?? null) ||
    $_SESSION['csrf_token'] === '' ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {
    http_response_code(403);
    exit('Yeu cau dang xuat khong hop le.');
}

// Xoa toan bo du lieu Session
$_SESSION = [];

// Xoa cookie Session tren trinh duyet
if (ini_get('session.use_cookies')) {

    $cookieParams = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $cookieParams['path'],
            'domain' => $cookieParams['domain'],
            'secure' => $cookieParams['secure'],
            'httponly' => $cookieParams['httponly'],
            'samesite' => $cookieParams['samesite'] ?? 'Lax'
        ]
    );
}

// Huy Session
session_destroy();

// Chuyen ve trang chu mua hang
header('Location: ' . $customerHomeUrl);
exit;
