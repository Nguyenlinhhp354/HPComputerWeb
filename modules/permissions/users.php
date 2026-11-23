
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/permissions/users.php
// CHUC NANG: QUAN LY VAI TRO TAI KHOAN
// =====================================================

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permission_check.php';

yeu_cau_quyen('permissions.view');

$escape = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

// =====================================================
// 1. CSRF TOKEN
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
// 2. THONG BAO
// =====================================================

$flashMessages = [
    'success' => [
        'type' => 'success',
        'message' => 'Cập nhật vai trò tài khoản thành công.'
    ],
    'error' => [
        'type' => 'danger',
        'message' => 'Không thể cập nhật vai trò. Vui lòng thử lại.'
    ],
    'invalid' => [
        'type' => 'warning',
        'message' => 'Thông tin tài khoản hoặc vai trò không hợp lệ.'
    ],
    'self_change' => [
        'type' => 'warning',
        'message' => 'Bạn không thể tự thay đổi vai trò của tài khoản đang đăng nhập.'
    ],
    'last_admin' => [
        'type' => 'danger',
        'message' => 'Không thể hạ vai trò Admin cuối cùng đang hoạt động.'
    ],
    'not_found' => [
        'type' => 'warning',
        'message' => 'Không tìm thấy tài khoản cần cập nhật.'
    ]
];

$flash = $_SESSION['role_flash'] ?? '';
unset($_SESSION['role_flash']);

// =====================================================
// 3. DIEU KIEN TIM KIEM
// =====================================================

$keyword = trim($_GET['keyword'] ?? '');
$roleFilter = $_GET['role'] ?? '';

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = ($page !== false && $page !== null && $page > 0) ? $page : 1;

$limit = 10;

$allowedRoleIds = [1, 3, 4, 5, 6];

if (
    $roleFilter !== '' &&
    (
        !is_string($roleFilter) ||
        !ctype_digit($roleFilter) ||
        !in_array((int) $roleFilter, $allowedRoleIds, true)
    )
) {
    $roleFilter = '';
}

// =====================================================
// 4. LAY DANH SACH VAI TRO
// =====================================================

$roles = [];
$users = [];
$totalRecords = 0;
$totalPages = 1;
$activeAdminCount = 0;
$loadError = '';

try {
    $placeholders = implode(',', array_fill(0, count($allowedRoleIds), '?'));

    $roleSql = "
        SELECT ma_vai_tro, ten_vai_tro
        FROM vai_tro
        WHERE ma_vai_tro IN ($placeholders)
        ORDER BY ma_vai_tro ASC
    ";

    $roleStmt = $pdo->prepare($roleSql);
    $roleStmt->execute($allowedRoleIds);
    $roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $loadError = 'Không thể tải danh sách vai trò.';
}

// =====================================================
// 5. DEM ADMIN DANG HOAT DONG
// =====================================================

try {
    $adminCountSql = "
        SELECT COUNT(*)
        FROM nguoi_dung
        WHERE ma_vai_tro = 1
          AND trang_thai = 'ACTIVE'
    ";

    $activeAdminCount = (int) $pdo->query($adminCountSql)->fetchColumn();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $loadError = 'Không thể tải dữ liệu Admin.';
}

// =====================================================
// 6. XAY DUNG DIEU KIEN TIM KIEM
// =====================================================

$where = [];
$params = [];

if ($keyword !== '') {
    $where[] = "(
        nd.ten_dang_nhap LIKE :keyword1
        OR nd.ho_ten LIKE :keyword2
        OR nd.email LIKE :keyword3
    )";

    $searchValue = '%' . $keyword . '%';

    $params[':keyword1'] = $searchValue;
    $params[':keyword2'] = $searchValue;
    $params[':keyword3'] = $searchValue;
}

if ($roleFilter !== '') {
    $where[] = 'nd.ma_vai_tro = :ma_vai_tro';
    $params[':ma_vai_tro'] = (int) $roleFilter;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// =====================================================
// 7. DEM TAI KHOAN
// =====================================================

try {
    $countSql = "
        SELECT COUNT(*)
        FROM nguoi_dung nd
        INNER JOIN vai_tro vt
            ON nd.ma_vai_tro = vt.ma_vai_tro
        $whereSql
    ";

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);

    $totalRecords = (int) $countStmt->fetchColumn();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $loadError = 'Không thể tải số lượng tài khoản.';
}

// =====================================================
// 8. PHAN TRANG
// =====================================================

$totalPages = max(1, (int) ceil($totalRecords / $limit));
$page = min($page, $totalPages);

$offset = ($page - 1) * $limit;

// =====================================================
// 9. LAY DANH SACH TAI KHOAN
// =====================================================

try {
    $userSql = "
        SELECT
            nd.ma_nguoi_dung,
            nd.ma_vai_tro,
            nd.ten_dang_nhap,
            nd.ho_ten,
            nd.email,
            nd.trang_thai,
            nd.ngay_tao,
            vt.ten_vai_tro
        FROM nguoi_dung nd
        INNER JOIN vai_tro vt
            ON nd.ma_vai_tro = vt.ma_vai_tro
        $whereSql
        ORDER BY nd.ma_nguoi_dung DESC
        LIMIT $limit OFFSET $offset
    ";

    $userStmt = $pdo->prepare($userSql);
    $userStmt->execute($params);

    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $loadError = 'Không thể tải danh sách tài khoản.';
}

// =====================================================
// 10. DUONG DAN PHAN TRANG
// =====================================================

$queryParams = $_GET;
unset($queryParams['page']);

$paginationUrl = '?' . http_build_query($queryParams);

$currentUserId = (int) ($_SESSION['ma_nguoi_dung'] ?? 0);
$canAssign = co_quyen('permissions.assign');

?>

<div class="container-fluid py-3">

    <!-- THONG BAO -->
    <?php if (isset($flashMessages[$flash])): ?>

        <div class="alert alert-<?= $escape($flashMessages[$flash]['type']) ?> alert-dismissible fade show">
            <i class="bi bi-info-circle me-2"></i>
            <?= $escape($flashMessages[$flash]['message']) ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

    <?php endif; ?>

    <?php if ($loadError !== ''): ?>

        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= $escape($loadError) ?>
        </div>

    <?php endif; ?>

    <!-- TIEU DE -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">

        <div>
            <h4 class="fw-bold mb-1">Quản lý vai trò tài khoản</h4>
            <p class="text-muted mb-0">
                Gán vai trò và quản lý quyền truy cập của người dùng
            </p>
        </div>

        <a href="<?= $escape(BASE_URL) ?>/permissions.php"
           class="btn btn-outline-primary">

            <i class="bi bi-shield-lock me-1"></i>
            Quản lý quyền chức năng

        </a>

    </div>

    <!-- THONG KE -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">

            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">Tổng tài khoản</div>
                    <h4 class="fw-bold mb-0"><?= $totalRecords ?></h4>
                </div>
            </div>

        </div>

        <div class="col-md-4">

            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">Admin đang hoạt động</div>
                    <h4 class="fw-bold text-success mb-0"><?= $activeAdminCount ?></h4>
                </div>
            </div>

        </div>

        <div class="col-md-4">

            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">Vai trò hệ thống</div>
                    <h4 class="fw-bold mb-0"><?= count($roles) ?></h4>
                </div>
            </div>

        </div>

    </div>

    <!-- TIM KIEM -->
    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form method="GET">

                <div class="row g-3 align-items-end">

                    <div class="col-lg-6">

                        <label class="form-label">Tìm kiếm tài khoản</label>

                        <input
                            type="text"
                            name="keyword"
                            class="form-control"
                            placeholder="Tên đăng nhập, họ tên hoặc email"
                            value="<?= $escape($keyword) ?>"
                        >

                    </div>

                    <div class="col-lg-4">

                        <label class="form-label">Vai trò</label>

                        <select name="role" class="form-select">

                            <option value="">Tất cả vai trò</option>

                            <?php foreach ($roles as $role): ?>

                                <option
                                    value="<?= (int) $role['ma_vai_tro'] ?>"
                                    <?= (string) $roleFilter === (string) $role['ma_vai_tro'] ? 'selected' : '' ?>
                                >
                                    <?= $escape($role['ten_vai_tro']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-lg-2">

                        <div class="d-flex gap-2">

                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-search me-1"></i>
                                Lọc
                            </button>

                            <a href="<?= $escape(BASE_URL) ?>/permissions_users.php"
                               class="btn btn-outline-secondary"
                               title="Xóa bộ lọc">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>

                        </div>

                    </div>

                </div>

            </form>

        </div>

    </div>

    <!-- DANH SACH -->
    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3">

            <div class="d-flex justify-content-between align-items-center">

                <h6 class="fw-bold mb-0">Danh sách tài khoản</h6>

                <span class="text-muted small">
                    Tổng <?= $totalRecords ?> tài khoản
                </span>

            </div>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>
                            <th class="ps-3">Mã</th>
                            <th>Tài khoản</th>
                            <th>Họ và tên</th>
                            <th>Email</th>
                            <th>Vai trò hiện tại</th>
                            <th>Trạng thái</th>
                            <th class="text-center">Cập nhật vai trò</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($users)): ?>

                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="bi bi-person-x fs-2 text-muted"></i>
                                    <p class="text-muted mt-2 mb-0">
                                        Không tìm thấy tài khoản phù hợp
                                    </p>
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($users as $user): ?>

                                <?php
                                $userId = (int) $user['ma_nguoi_dung'];
                                $isSelf = ($userId === $currentUserId);
                                ?>

                                <tr>

                                    <td class="ps-3"><?= $userId ?></td>

                                    <td>
                                        <strong><?= $escape($user['ten_dang_nhap']) ?></strong>
                                        <?php if ($isSelf): ?>
                                            <span class="badge bg-info ms-1">Bạn</span>
                                        <?php endif; ?>
                                    </td>

                                    <td><?= $escape($user['ho_ten']) ?></td>

                                    <td><?= $escape($user['email'] ?: '—') ?></td>

                                    <td>
                                        <span class="badge bg-primary">
                                            <?= $escape($user['ten_vai_tro']) ?>
                                        </span>
                                    </td>

                                    <td>

                                        <?php if ($user['trang_thai'] === 'ACTIVE'): ?>
                                            <span class="badge bg-success">Hoạt động</span>
                                        <?php elseif ($user['trang_thai'] === 'INACTIVE'): ?>
                                            <span class="badge bg-secondary">Ngừng hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Đã khóa</span>
                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?php if (!$canAssign): ?>

                                            <span class="text-muted small">
                                                Chỉ có quyền xem
                                            </span>

                                        <?php elseif ($isSelf): ?>

                                            <span class="text-muted small">
                                                Không thể tự đổi vai trò
                                            </span>

                                        <?php else: ?>

                                            <form
                                                method="POST"
                                                action="<?= $escape(BASE_URL) ?>/modules/permissions/update_role.php"
                                                class="d-flex gap-2 justify-content-center"
                                            >

                                                <input type="hidden" name="csrf_token"
                                                       value="<?= $escape($csrfToken) ?>">

                                                <input type="hidden" name="ma_nguoi_dung"
                                                       value="<?= $userId ?>">

                                                <select name="ma_vai_tro"
                                                        class="form-select form-select-sm"
                                                        style="max-width: 180px"
                                                        required>

                                                    <?php foreach ($roles as $role): ?>

                                                        <option
                                                            value="<?= (int) $role['ma_vai_tro'] ?>"
                                                            <?= (int) $user['ma_vai_tro'] === (int) $role['ma_vai_tro'] ? 'selected' : '' ?>
                                                        >
                                                            <?= $escape($role['ten_vai_tro']) ?>
                                                        </option>

                                                    <?php endforeach; ?>

                                                </select>

                                                <button type="submit"
                                                        class="btn btn-sm btn-primary"
                                                        title="Cập nhật vai trò">

                                                    <i class="bi bi-save"></i>

                                                </button>

                                            </form>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

        <!-- PHAN TRANG -->
        <?php if ($totalPages > 1): ?>

            <div class="card-footer bg-white">

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

                    <small class="text-muted">
                        Trang <?= $page ?> / <?= $totalPages ?>
                    </small>

                    <nav aria-label="Phân trang">

                        <ul class="pagination pagination-sm mb-0">

                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link"
                                   href="<?= $escape($paginationUrl . '&page=' . max(1, $page - 1)) ?>">
                                    Trước
                                </a>
                            </li>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>

                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link"
                                       href="<?= $escape($paginationUrl . '&page=' . $i) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>

                            <?php endfor; ?>

                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link"
                                   href="<?= $escape($paginationUrl . '&page=' . min($totalPages, $page + 1)) ?>">
                                    Sau
                                </a>
                            </li>

                        </ul>

                    </nav>

                </div>

            </div>

        <?php endif; ?>

    </div>

    <!-- LUU Y -->
    <div class="alert alert-info mt-4">

        <i class="bi bi-shield-lock me-2"></i>

        Không thể tự thay đổi vai trò của tài khoản đang đăng nhập.
        Hệ thống cũng bảo vệ Admin cuối cùng đang hoạt động để tránh mất quyền quản trị.

    </div>

</div>
