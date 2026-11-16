<?php

// =====================================================
// HP COMPUTER
// FILE: includes/permission_check.php
// CHUC NANG: KIEM TRA QUYEN TRUY CAP
// TAT CA VAI TRO DEU PHAN QUYEN TU DATABASE
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Kiem tra nguoi dung co quyen thuc hien chuc nang hay khong.
 *
 * @param string $maChucNang
 * @return bool
 */
function co_quyen($maChucNang)
{
    global $pdo;

    // Kiem tra dang nhap
    if (empty($_SESSION['ma_nguoi_dung'])) {
        return false;
    }

    // Lay ma vai tro
    $maVaiTro = (int) ($_SESSION['ma_vai_tro'] ?? 0);

    if ($maVaiTro <= 0) {
        return false;
    }

    // Luu danh sach quyen trong request hien tai
    static $danhSachQuyen = [];

    // Neu chua truy van quyen cua vai tro nay
    if (!isset($danhSachQuyen[$maVaiTro])) {

        $sql = "
            SELECT q.ma_chuc_nang
            FROM quyen q
            INNER JOIN vai_tro_quyen vq
                ON q.ma_quyen = vq.ma_quyen
            WHERE vq.ma_vai_tro = :ma_vai_tro
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':ma_vai_tro' => $maVaiTro
        ]);

        $danhSachQuyen[$maVaiTro] = $stmt->fetchAll(
            PDO::FETCH_COLUMN
        );
    }

    // Kiem tra quyen
    return in_array(
        $maChucNang,
        $danhSachQuyen[$maVaiTro],
        true
    );
}

/**
 * Bat buoc nguoi dung phai co quyen.
 * Khong co quyen thi tu choi truy cap.
 */
function yeu_cau_quyen($maChucNang)
{
    if (!co_quyen($maChucNang)) {

        http_response_code(403);

        exit('Bạn không có quyền truy cập chức năng này.');
    }
}
