
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/permissions/update_role.php
// CHUC NANG: XU LY THAY DOI VAI TRO TAI KHOAN
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/permission_check.php';

// =====================================================
// 1. HAM CHUYEN HUONG
// =====================================================

$baseUrl = rtrim(BASE_URL, '/');

$redirect = static function ($status) use ($baseUrl) {
    $_SESSION['role_flash'] = $status;

    header('Location: ' . $baseUrl . '/permissions_users.php');
    exit;
};

// =====================================================
// 2. KIEM TRA QUYEN
// =====================================================

yeu_cau_quyen('permissions.assign');

// =====================================================
// 3. KIEM TRA PHUONG THUC
// =====================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Phương thức truy cập không hợp lệ.');
}

// =====================================================
// 4. KIEM TRA CSRF
// =====================================================

$sessionToken = $_SESSION['csrf_token'] ?? '';
$postToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !is_string($postToken) ||
    $postToken === '' ||
    !hash_equals($sessionToken, $postToken)
) {
    http_response_code(403);
    exit('Yêu cầu không hợp lệ. Vui lòng tải lại trang.');
}

// =====================================================
// 5. KIEM TRA DU LIEU DAU VAO
// =====================================================

$userIdInput = $_POST['ma_nguoi_dung'] ?? '';
$roleIdInput = $_POST['ma_vai_tro'] ?? '';

if (
    !is_string($userIdInput) ||
    !ctype_digit($userIdInput) ||
    (int) $userIdInput <= 0 ||
    !is_string($roleIdInput) ||
    !ctype_digit($roleIdInput)
) {
    $redirect('invalid');
}

$userId = (int) $userIdInput;
$newRoleId = (int) $roleIdInput;

$allowedRoleIds = [1, 3, 4, 5, 6];

if (!in_array($newRoleId, $allowedRoleIds, true)) {
    $redirect('invalid');
}

// =====================================================
// 6. KHONG CHO TU THAY DOI VAI TRO
// =====================================================

$currentUserId = (int) ($_SESSION['ma_nguoi_dung'] ?? 0);

if ($userId === $currentUserId) {
    $redirect('self_change');
}

// =====================================================
// 7. CAP NHAT VAI TRO BANG TRANSACTION
// =====================================================

try {

    $pdo->beginTransaction();

    // Khoa tai khoan dang duoc cap nhat
    $userSql = "
        SELECT
            ma_nguoi_dung,
            ma_vai_tro,
            trang_thai
        FROM nguoi_dung
        WHERE ma_nguoi_dung = :ma_nguoi_dung
        FOR UPDATE
    ";

    $userStmt = $pdo->prepare($userSql);

    $userStmt->execute([
        ':ma_nguoi_dung' => $userId
    ]);

    $targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        $pdo->rollBack();
        $redirect('not_found');
    }

    $oldRoleId = (int) $targetUser['ma_vai_tro'];

    // Kiem tra vai tro moi co ton tai
    $roleSql = "
        SELECT ma_vai_tro
        FROM vai_tro
        WHERE ma_vai_tro = :ma_vai_tro
        FOR UPDATE
    ";

    $roleStmt = $pdo->prepare($roleSql);

    $roleStmt->execute([
        ':ma_vai_tro' => $newRoleId
    ]);

    if (!$roleStmt->fetchColumn()) {
        throw new RuntimeException('Vai trò không tồn tại.');
    }

    // Khong can cap nhat neu vai tro khong thay doi
    if ($oldRoleId === $newRoleId) {
        $pdo->commit();
        $redirect('success');
    }

    // =================================================
    // 8. BAO VE ADMIN CUOI CUNG
    // =================================================

    if (
        $oldRoleId === 1 &&
        $newRoleId !== 1 &&
        $targetUser['trang_thai'] === 'ACTIVE'
    ) {

        // Khoa cac tai khoan Admin dang hoat dong
        $adminLockSql = "
            SELECT ma_nguoi_dung
            FROM nguoi_dung
            WHERE ma_vai_tro = 1
              AND trang_thai = 'ACTIVE'
            FOR UPDATE
        ";

        $adminLockStmt = $pdo->query($adminLockSql);

        $activeAdmins = $adminLockStmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($activeAdmins) <= 1) {
            $pdo->rollBack();
            $redirect('last_admin');
        }
    }

    // =================================================
    // 9. CAP NHAT VAI TRO
    // =================================================

    $updateSql = "
        UPDATE nguoi_dung
        SET
            ma_vai_tro = :ma_vai_tro,
            ngay_cap_nhat = CURRENT_TIMESTAMP
        WHERE ma_nguoi_dung = :ma_nguoi_dung
    ";

    $updateStmt = $pdo->prepare($updateSql);

    $updateStmt->execute([
        ':ma_vai_tro' => $newRoleId,
        ':ma_nguoi_dung' => $userId
    ]);

    $pdo->commit();

    $redirect('success');

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'HP COMPUTER - UPDATE USER ROLE: ' .
        $e->getMessage()
    );

    $redirect('error');
}
