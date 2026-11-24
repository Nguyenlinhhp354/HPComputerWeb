
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/permissions/update.php
// CHUC NANG: XU LY LUU PHAN QUYEN
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================
// 1. NAP CAC FILE CAN THIET
// =====================================================

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/permission_check.php';

// Chi nguoi co quyen moi duoc luu phan quyen
yeu_cau_quyen('permissions.assign');

// =====================================================
// 2. HAM CHUYEN HUONG
// =====================================================

$baseUrl = rtrim(BASE_URL, '/');

$redirect = static function ($roleId, $status) use ($baseUrl) {
    $_SESSION['permission_flash'] = $status;

    header(
        'Location: ' . $baseUrl .
        '/permissions.php?role=' . (int) $roleId
    );

    exit;
};

// =====================================================
// 3. CHI CHAP NHAN POST
// =====================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Phương thức truy cập không hợp lệ.');
}

// =====================================================
// 4. KIEM TRA CSRF TOKEN
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
    exit('Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.');
}

// =====================================================
// 5. KIEM TRA VAI TRO DUOC CAP NHAT
// =====================================================

// He thong chi co 5 vai tro
$allowedRoleIds = [1, 3, 4, 5, 6];

$roleIdInput = $_POST['ma_vai_tro'] ?? '';

if (
    !is_string($roleIdInput) ||
    !ctype_digit($roleIdInput) ||
    !in_array((int) $roleIdInput, $allowedRoleIds, true)
) {
    http_response_code(400);
    exit('Vai trò không hợp lệ.');
}

$roleId = (int) $roleIdInput;

// =====================================================
// 6. KIEM TRA DANH SACH QUYEN GUI LEN
// =====================================================

$submittedPermissions = $_POST['permissions'] ?? [];

if (!is_array($submittedPermissions)) {
    $redirect($roleId, 'invalid_permissions');
}

$permissionIds = [];

foreach ($submittedPermissions as $permissionId) {

    if (
        !is_string($permissionId) &&
        !is_int($permissionId)
    ) {
        $redirect($roleId, 'invalid_permissions');
    }

    $permissionId = (string) $permissionId;

    if (!ctype_digit($permissionId) || (int) $permissionId <= 0) {
        $redirect($roleId, 'invalid_permissions');
    }

    $permissionIds[] = (int) $permissionId;
}

// Loai bo ID trung lap
$permissionIds = array_values(array_unique($permissionIds));

// =====================================================
// 7. KIEM TRA DU LIEU TRONG CSDL
// =====================================================

try {

    $pdo->beginTransaction();

    // Kiem tra vai tro co ton tai hay khong
    $roleCheckSql = "
        SELECT ma_vai_tro
        FROM vai_tro
        WHERE ma_vai_tro = :ma_vai_tro
        FOR UPDATE
    ";

    $roleCheckStmt = $pdo->prepare($roleCheckSql);

    $roleCheckStmt->execute([
        ':ma_vai_tro' => $roleId
    ]);

    if (!$roleCheckStmt->fetchColumn()) {
        throw new RuntimeException('Vai trò không tồn tại.');
    }

    // Lay toan bo quyen hop le trong he thong
    $allPermissionSql = "
        SELECT
            ma_quyen,
            ma_chuc_nang
        FROM quyen
    ";

    $allPermissionStmt = $pdo->query($allPermissionSql);

    $allPermissions = $allPermissionStmt->fetchAll(PDO::FETCH_ASSOC);

    $validPermissionMap = [];

    $corePermissionIds = [];

    $corePermissionCodes = [
        'permissions.view',
        'permissions.assign'
    ];

    foreach ($allPermissions as $permission) {

        $id = (int) $permission['ma_quyen'];

        $code = $permission['ma_chuc_nang'];

        $validPermissionMap[$id] = $code;

        if (in_array($code, $corePermissionCodes, true)) {
            $corePermissionIds[] = $id;
        }
    }

    // Kiem tra cac quyen gui len co ton tai khong
    foreach ($permissionIds as $permissionId) {

        if (!isset($validPermissionMap[$permissionId])) {
            throw new RuntimeException('Danh sách quyền không hợp lệ.');
        }
    }

    // =================================================
    // 8. BAO VE QUYEN QUAN TRI COT LOI
    // =================================================

    if ($roleId === 1) {

        // Dam bao CSDL co day du hai quyen quan tri
        if (count($corePermissionIds) !== 2) {
            throw new RuntimeException(
                'Không tìm thấy đầy đủ quyền quản trị cốt lõi.'
            );
        }

        // Tu dong giu hai quyen cho Admin
        $permissionIds = array_merge(
            $permissionIds,
            $corePermissionIds
        );

    } else {

        // Khong cho vai tro khac nhan quyen quan tri
        $permissionIds = array_values(
            array_filter(
                $permissionIds,
                static function ($permissionId) use (
                    $validPermissionMap,
                    $corePermissionCodes
                ) {
                    return !in_array(
                        $validPermissionMap[$permissionId],
                        $corePermissionCodes,
                        true
                    );
                }
            )
        );
    }

    // Loai bo ID trung lap sau khi bo sung quyen Admin
    $permissionIds = array_values(array_unique($permissionIds));

    // =================================================
    // 9. CAP NHAT QUYEN
    // =================================================

    // Xoa danh sach quyen cu cua vai tro
    $deleteSql = "
        DELETE FROM vai_tro_quyen
        WHERE ma_vai_tro = :ma_vai_tro
    ";

    $deleteStmt = $pdo->prepare($deleteSql);

    $deleteStmt->execute([
        ':ma_vai_tro' => $roleId
    ]);

    // Them danh sach quyen moi
    if (!empty($permissionIds)) {

        $insertSql = "
            INSERT INTO vai_tro_quyen (
                ma_vai_tro,
                ma_quyen
            )
            VALUES (
                :ma_vai_tro,
                :ma_quyen
            )
        ";

        $insertStmt = $pdo->prepare($insertSql);

        foreach ($permissionIds as $permissionId) {

            $insertStmt->execute([
                ':ma_vai_tro' => $roleId,
                ':ma_quyen' => $permissionId
            ]);
        }
    }

    // =================================================
    // 10. HOAN TAT GIAO DICH
    // =================================================

    $pdo->commit();

    $redirect($roleId, 'success');

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'HP COMPUTER - UPDATE PERMISSIONS: ' .
        $e->getMessage()
    );

    $redirect($roleId, 'error');
}
