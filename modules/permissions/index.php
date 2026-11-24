
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/permissions/index.php
// CHUC NANG: GIAO DIEN QUAN LY PHAN QUYEN
// =====================================================

// =====================================================
// 1. KIEM TRA DANG NHAP VA QUYEN TRUY CAP
// =====================================================

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permission_check.php';

// Chi nguoi co quyen moi duoc xem phan quyen
yeu_cau_quyen('permissions.view');

// =====================================================
// 2. HAM HO TRO
// =====================================================

// Ham xu ly du lieu an toan khi hien thi
$escape = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

// =====================================================
// 3. KHOI TAO CSRF TOKEN
// =====================================================

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

// =====================================================
// 4. LAY THONG BAO KET QUA CAP NHAT
// =====================================================

$permissionFlash = $_SESSION['permission_flash'] ?? '';

unset($_SESSION['permission_flash']);

$flashMessages = [
    'success' => [
        'type' => 'success',
        'message' => 'Cập nhật phân quyền thành công.'
    ],

    'error' => [
        'type' => 'danger',
        'message' => 'Không thể cập nhật phân quyền. Vui lòng kiểm tra lại.'
    ],

    'invalid_permissions' => [
        'type' => 'warning',
        'message' => 'Danh sách quyền gửi lên không hợp lệ.'
    ]
];

// =====================================================
// 5. KHOI TAO DU LIEU
// =====================================================

// He thong chi co 5 vai tro
$allowedRoleIds = [1, 3, 4, 5, 6];

$roles = [];
$permissions = [];
$rolePermissions = [];
$permissionGroups = [];

$selectedRoleId = 1;
$selectedRoleName = '';

$loadError = '';

// =====================================================
// 6. LAY DANH SACH VAI TRO
// =====================================================

try {

    $placeholders = implode(
        ',',
        array_fill(0, count($allowedRoleIds), '?')
    );

    $roleSql = "
        SELECT
            ma_vai_tro,
            ten_vai_tro,
            mo_ta
        FROM vai_tro
        WHERE ma_vai_tro IN ($placeholders)
        ORDER BY ma_vai_tro ASC
    ";

    $roleStmt = $pdo->prepare($roleSql);

    $roleStmt->execute($allowedRoleIds);

    $roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log(
        'HP COMPUTER - PERMISSIONS ROLE: ' .
        $e->getMessage()
    );

    $loadError = 'Không thể tải danh sách vai trò.';
}

// =====================================================
// 7. KIEM TRA VAI TRO DUOC CHON
// =====================================================

$requestedRole = $_GET['role'] ?? '1';

if (
    is_string($requestedRole) &&
    ctype_digit($requestedRole) &&
    in_array((int) $requestedRole, $allowedRoleIds, true)
) {
    $selectedRoleId = (int) $requestedRole;
}

// Lay danh sach ma vai tro thuc te
$existingRoleIds = array_map(
    static function ($role) {
        return (int) $role['ma_vai_tro'];
    },
    $roles
);

// Neu vai tro khong ton tai thi chon vai tro dau tien
if (!in_array($selectedRoleId, $existingRoleIds, true)) {

    $selectedRoleId = !empty($existingRoleIds)
        ? $existingRoleIds[0]
        : 0;
}

// =====================================================
// 8. LAY DANH SACH QUYEN HE THONG
// =====================================================

try {

    $permissionSql = "
        SELECT
            ma_quyen,
            ma_chuc_nang,
            ten_quyen,
            phan_he,
            mo_ta
        FROM quyen
        ORDER BY phan_he ASC, ma_quyen ASC
    ";

    $permissionStmt = $pdo->query($permissionSql);

    $permissions = $permissionStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log(
        'HP COMPUTER - PERMISSIONS LIST: ' .
        $e->getMessage()
    );

    $loadError = 'Không thể tải danh sách quyền.';
}

// =====================================================
// 9. LAY QUYEN CUA VAI TRO DUOC CHON
// =====================================================

if ($selectedRoleId > 0) {

    try {

        $rolePermissionSql = "
            SELECT ma_quyen
            FROM vai_tro_quyen
            WHERE ma_vai_tro = :ma_vai_tro
        ";

        $rolePermissionStmt = $pdo->prepare($rolePermissionSql);

        $rolePermissionStmt->execute([
            ':ma_vai_tro' => $selectedRoleId
        ]);

        $rolePermissions = array_map(
            'intval',
            $rolePermissionStmt->fetchAll(PDO::FETCH_COLUMN)
        );

    } catch (PDOException $e) {

        error_log(
            'HP COMPUTER - ROLE PERMISSIONS: ' .
            $e->getMessage()
        );

        $loadError = 'Không thể tải quyền của vai trò.';

        $rolePermissions = [];
    }
}

// =====================================================
// 10. NHOM QUYEN THEO PHAN HE
// =====================================================

foreach ($permissions as $permission) {

    $groupName = trim((string) ($permission['phan_he'] ?? ''));

    if ($groupName === '') {
        $groupName = 'Khác';
    }

    if (!isset($permissionGroups[$groupName])) {
        $permissionGroups[$groupName] = [];
    }

    $permissionGroups[$groupName][] = $permission;
}

// =====================================================
// 11. XAC DINH TEN VAI TRO
// =====================================================

foreach ($roles as $role) {

    if ((int) $role['ma_vai_tro'] === $selectedRoleId) {

        $selectedRoleName = $role['ten_vai_tro'];

        break;
    }
}

// =====================================================
// 12. CAC QUYEN QUAN TRI COT LOI CUA ADMIN
// =====================================================

$corePermissionCodes = [
    'permissions.view',
    'permissions.assign'
];

$corePermissionIds = [];

foreach ($permissions as $permission) {

    if (
        in_array(
            $permission['ma_chuc_nang'],
            $corePermissionCodes,
            true
        )
    ) {
        $corePermissionIds[] = (int) $permission['ma_quyen'];
    }
}

// =====================================================
// 13. THONG KE QUYEN
// =====================================================

$totalPermissions = count($permissions);

$assignedPermissions = count($rolePermissions);

$unassignedPermissions = max(
    0,
    $totalPermissions - $assignedPermissions
);

// =====================================================
// 14. KIEM TRA QUYEN DUOC PHEP CAP NHAT
// =====================================================

$canAssignPermissions = co_quyen('permissions.assign');

// Khong cho hien thi nut luu neu du lieu khong tai duoc
$canSavePermissions = (
    $canAssignPermissions &&
    $loadError === '' &&
    $selectedRoleId > 0 &&
    !empty($permissions)
);

?>

<div class="container-fluid py-3">

    <!-- =================================================
         15. THONG BAO KET QUA
    ================================================== -->

    <?php if (isset($flashMessages[$permissionFlash])): ?>

        <div
            class="alert alert-<?= $escape($flashMessages[$permissionFlash]['type']) ?> alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-info-circle me-2"></i>

            <?= $escape($flashMessages[$permissionFlash]['message']) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Đóng"
            ></button>

        </div>

    <?php endif; ?>


    <!-- =================================================
         16. THONG BAO LOI TAI DU LIEU
    ================================================== -->

    <?php if ($loadError !== ''): ?>

        <div class="alert alert-danger" role="alert">

            <i class="bi bi-exclamation-triangle me-2"></i>

            <?= $escape($loadError) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         17. TIEU DE TRANG
    ================================================== -->

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">

        <div>

            <h4 class="mb-1 fw-bold">
                Phân quyền hệ thống
            </h4>

            <p class="text-muted mb-0">
                Quản lý quyền truy cập của các vai trò trong HPComputer
            </p>

        </div>

        <div class="d-flex flex-wrap gap-2">

            <a href="<?= $escape(BASE_URL) ?>/permissions_users.php"
            class="btn btn-outline-primary">

                <i class="bi bi-people me-1"></i>
                Quản lý vai trò tài khoản

            </a>

            <span class="badge bg-primary fs-6 d-flex align-items-center">
                <i class="bi bi-shield-lock me-1"></i>
                RBAC
            </span>

        </div>

    </div>


    <!-- =================================================
         18. THONG KE
    ================================================== -->

    <div class="row g-3 mb-4">

        <div class="col-md-4 col-sm-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="text-muted small mb-2">
                        Tổng số vai trò
                    </div>

                    <h4 class="fw-bold mb-0">
                        <?= count($roles) ?>
                    </h4>

                </div>

            </div>

        </div>


        <div class="col-md-4 col-sm-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="text-muted small mb-2">
                        Tổng số quyền hệ thống
                    </div>

                    <h4 class="fw-bold mb-0">
                        <?= $totalPermissions ?>
                    </h4>

                </div>

            </div>

        </div>


        <div class="col-md-4 col-sm-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="text-muted small mb-2">
                        Quyền của vai trò đang chọn
                    </div>

                    <h4 class="fw-bold text-primary mb-0">
                        <?= $assignedPermissions ?>
                    </h4>

                </div>

            </div>

        </div>

    </div>


    <!-- =================================================
         19. CHON VAI TRO
    ================================================== -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h6 class="fw-bold mb-0">

                <i class="bi bi-people me-2"></i>

                Chọn vai trò cần phân quyền

            </h6>

        </div>


        <div class="card-body">

            <form
                method="GET"
                action="<?= $escape(BASE_URL) ?>/permissions.php"
            >

                <div class="row g-3 align-items-end">

                    <div class="col-lg-6">

                        <label class="form-label">
                            Vai trò
                        </label>

                        <select
                            name="role"
                            class="form-select"
                            onchange="this.form.submit()"
                            <?= empty($roles) ? 'disabled' : '' ?>
                        >

                            <?php foreach ($roles as $role): ?>

                                <option
                                    value="<?= (int) $role['ma_vai_tro'] ?>"
                                    <?= $selectedRoleId === (int) $role['ma_vai_tro'] ? 'selected' : '' ?>
                                >

                                    <?= $escape($role['ten_vai_tro']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="col-lg-6">

                        <div class="text-muted small">
                            Đang quản lý quyền cho:
                        </div>

                        <div class="fw-bold text-primary">

                            <?= $escape($selectedRoleName ?: 'Chưa có vai trò') ?>

                        </div>

                    </div>

                </div>

            </form>

        </div>

    </div>


    <!-- =================================================
         20. DANH SACH QUYEN
    ================================================== -->

    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">

                <div>

                    <h6 class="fw-bold mb-1">
                        Danh sách quyền
                    </h6>

                    <small class="text-muted">
                        Các quyền được phân nhóm theo chức năng hệ thống
                    </small>

                </div>

                <span class="badge bg-primary">

                    <?= $assignedPermissions ?> / <?= $totalPermissions ?> quyền

                </span>

            </div>

        </div>


        <div class="card-body">

            <?php if ($selectedRoleId <= 0): ?>

                <div class="alert alert-warning mb-0">

                    Không tìm thấy vai trò hợp lệ trong hệ thống.

                </div>


            <?php elseif (empty($permissions)): ?>

                <div class="text-center text-muted py-5">

                    <i class="bi bi-shield-exclamation fs-2"></i>

                    <p class="mb-0 mt-2">
                        Chưa có dữ liệu quyền trong hệ thống.
                    </p>

                </div>


            <?php else: ?>

                <form
                    method="POST"
                    action="<?= $escape(BASE_URL) ?>/modules/permissions/update.php"
                >

                    <!-- CSRF TOKEN -->

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= $escape($csrfToken) ?>"
                    >


                    <!-- MA VAI TRO -->

                    <input
                        type="hidden"
                        name="ma_vai_tro"
                        value="<?= $selectedRoleId ?>"
                    >


                    <!-- =====================================
                         CAC NHOM QUYEN
                    ====================================== -->

                    <?php foreach ($permissionGroups as $groupName => $groupPermissions): ?>

                        <div class="border rounded mb-4">


                            <!-- TEN NHOM -->

                            <div class="bg-light px-3 py-3 border-bottom">

                                <h6 class="fw-bold mb-0">

                                    <i class="bi bi-folder2-open me-2"></i>

                                    <?= $escape($groupName) ?>

                                </h6>

                            </div>


                            <!-- DANH SACH QUYEN TRONG NHOM -->

                            <div class="p-3">

                                <div class="row g-3">

                                    <?php foreach ($groupPermissions as $permission): ?>

                                        <?php

                                        $permissionId = (int) $permission['ma_quyen'];

                                        $permissionCode = $permission['ma_chuc_nang'];

                                        // Kiem tra quyen da duoc cap
                                        $isAssigned = in_array(
                                            $permissionId,
                                            $rolePermissions,
                                            true
                                        );

                                        // Kiem tra quyen quan tri cot loi
                                        $isCorePermission = in_array(
                                            $permissionCode,
                                            $corePermissionCodes,
                                            true
                                        );

                                        // Kiem tra vai tro Admin
                                        $isAdminRole = ($selectedRoleId === 1);

                                        // Chi Admin duoc phep co hai quyen nay
                                        $isAdminOnlyPermission = $isCorePermission;

                                        // Khoa checkbox quyen cot loi
                                        $isDisabled = (
                                            ($isAdminRole && $isCorePermission) ||
                                            (!$isAdminRole && $isAdminOnlyPermission)
                                        );

                                        ?>


                                        <div class="col-lg-6 col-xl-4">

                                            <div class="form-check border rounded p-3 h-100">

                                                <input
                                                    class="form-check-input ms-0 me-2"
                                                    type="checkbox"
                                                    name="permissions[]"
                                                    value="<?= $permissionId ?>"
                                                    id="permission_<?= $permissionId ?>"
                                                    <?= $isAssigned ? 'checked' : '' ?>
                                                    <?= $isDisabled ? 'disabled' : '' ?>
                                                >


                                                <label
                                                    class="form-check-label"
                                                    for="permission_<?= $permissionId ?>"
                                                >

                                                    <span class="fw-semibold">

                                                        <?= $escape($permission['ten_quyen']) ?>

                                                    </span>


                                                    <br>


                                                    <small class="text-muted">

                                                        <?= $escape($permissionCode) ?>

                                                    </small>


                                                    <?php if ($isCorePermission && $isAdminRole): ?>

                                                        <br>

                                                        <span class="badge bg-success mt-2">

                                                            Quyền quản trị bắt buộc

                                                        </span>


                                                    <?php elseif ($isAdminOnlyPermission): ?>

                                                        <br>

                                                        <span class="badge bg-secondary mt-2">

                                                            Chỉ dành cho Admin

                                                        </span>

                                                    <?php endif; ?>


                                                    <?php if (!empty($permission['mo_ta'])): ?>

                                                        <div class="small text-muted mt-1">

                                                            <?= $escape($permission['mo_ta']) ?>

                                                        </div>

                                                    <?php endif; ?>

                                                </label>

                                            </div>

                                        </div>


                                    <?php endforeach; ?>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>


                    <!-- =====================================
                         THONG TIN BAO VE ADMIN
                    ====================================== -->

                    <?php if ($selectedRoleId === 1): ?>

                        <div class="alert alert-info">

                            <i class="bi bi-shield-lock me-2"></i>

                            Hai quyền

                            <strong>permissions.view</strong>

                            và

                            <strong>permissions.assign</strong>

                            luôn được giữ cho Admin và không thể tắt.

                        </div>


                    <?php else: ?>

                        <div class="alert alert-warning">

                            <i class="bi bi-info-circle me-2"></i>

                            Hai quyền quản trị phân quyền chỉ được cấp cho Admin.

                        </div>

                    <?php endif; ?>


                    <!-- =====================================
                         NUT LUU PHAN QUYEN
                    ====================================== -->

                    <?php if ($canAssignPermissions): ?>

                        <div class="d-flex flex-wrap justify-content-end gap-2">

                            <a
                                href="<?= $escape(BASE_URL) ?>/permissions.php?role=<?= $selectedRoleId ?>"
                                class="btn btn-outline-secondary"
                            >

                                <i class="bi bi-arrow-counterclockwise me-1"></i>

                                Làm mới

                            </a>


                            <button
                                type="submit"
                                class="btn btn-primary"
                                <?= !$canSavePermissions ? 'disabled' : '' ?>
                            >

                                <i class="bi bi-save me-1"></i>

                                Lưu phân quyền

                            </button>

                        </div>


                    <?php else: ?>

                        <div class="alert alert-secondary mb-0">

                            <i class="bi bi-lock me-2"></i>

                            Bạn chỉ có quyền xem, không có quyền cập nhật phân quyền.

                        </div>

                    <?php endif; ?>


                </form>

            <?php endif; ?>

        </div>

    </div>

</div>
