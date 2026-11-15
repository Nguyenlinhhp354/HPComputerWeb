
<?php

// =====================================================
// HP COMPUTER
// FILE: config/database.php
// KET NOI PHP VOI MYSQL BANG PDO
// =====================================================

// Cau hinh database local tren XAMPP
$host = 'localhost';
$dbname = 'ezyro_43436024_hpcomputer';
$username = 'root';
$password = '';

// Cau hinh charset
$charset = 'utf8mb4';

// Tao chuoi ket noi
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

try {

    // Khoi tao ket noi PDO
    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {

    // Ghi loi vao error log, khong hien thi thong tin
    // ket noi va mat khau database cho nguoi dung.
    error_log($e->getMessage());

    die('Khong the ket noi co so du lieu. Vui long kiem tra cau hinh MySQL.');

}
