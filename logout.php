
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

// Chi cho phep dang xuat bang POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Kiem tra CSRF token
$token = $_POST['csrf_token'] ?? '';

if (
    !is_string($token) ||
    empty($_SESSION['csrf_token']) ||
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
        time() - 42000,
        $cookieParams['path'],
        $cookieParams['domain'],
        $cookieParams['secure'],
        $cookieParams['httponly']
    );
}

// Huy Session
session_destroy();

// Chuyen ve trang dang nhap
header('Location: ' . BASE_URL . '/login.php');
exit;
