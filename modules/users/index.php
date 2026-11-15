
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/users/index.php
// CHUC NANG: QUAN LY TAI KHOAN
// =====================================================

// Kiem tra dang nhap va quyen truy cap
require_once __DIR__ . '/../../includes/auth_check.php';

// Ham xu ly du lieu an toan khi hien thi
$escape = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

// =====================================================
// 1. KHOI TAO CAC DIEU KIEN TIM KIEM
// =====================================================

$keyword = trim($_GET['keyword'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = ($page !== false && $page !== null && $page > 0) ? $page : 1;

$limit = 10;

// Kiem tra trang thai hop le
$allowedStatuses = ['ACTIVE', 'INACTIVE', 'LOCKED'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

// Kiem tra ma vai tro
if ($roleFilter !== '' && (!ctype_digit((string) $roleFilter) || (int) $roleFilter <= 0)) {
    $roleFilter = '';
}

// =====================================================
// 2. LAY DANH SACH VAI TRO
// =====================================================

try {
    $roleSql = "
        SELECT ma_vai_tro, ten_vai_tro
        FROM vai_tro
        ORDER BY ma_vai_tro ASC
    ";

    $roleStmt = $pdo->query($roleSql);
    $roles = $roleStmt->fetchAll();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $roles = [];
}

// =====================================================
// 3. XAY DUNG DIEU KIEN TRUY VAN
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
    $where[] = "nd.ma_vai_tro = :ma_vai_tro";
    $params[':ma_vai_tro'] = (int) $roleFilter;
}

if ($statusFilter !== '') {
    $where[] = "nd.trang_thai = :trang_thai";
    $params[':trang_thai'] = $statusFilter;
}

$whereSql = '';

if (!empty($where)) {
    $whereSql = ' WHERE ' . implode(' AND ', $where);
}

// =====================================================
// 4. LAY THONG KE TAI KHOAN
// =====================================================

try {
    $statsSql = "
        SELECT
            COUNT(*) AS tong_tai_khoan,
            SUM(CASE WHEN trang_thai = 'ACTIVE' THEN 1 ELSE 0 END) AS dang_hoat_dong,
            SUM(CASE WHEN trang_thai = 'INACTIVE' THEN 1 ELSE 0 END) AS ngung_hoat_dong,
            SUM(CASE WHEN trang_thai = 'LOCKED' THEN 1 ELSE 0 END) AS bi_khoa
        FROM nguoi_dung
    ";

    $statsStmt = $pdo->query($statsSql);
    $stats = $statsStmt->fetch();

} catch (PDOException $e) {
    error_log($e->getMessage());

    $stats = [
        'tong_tai_khoan' => 0,
        'dang_hoat_dong' => 0,
        'ngung_hoat_dong' => 0,
        'bi_khoa' => 0
    ];
}

// =====================================================
// 5. DEM SO TAI KHOAN THEO DIEU KIEN
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
    $totalRecords = 0;
}

// =====================================================
// 6. TINH TOAN PHAN TRANG
// =====================================================

$totalPages = max(1, (int) ceil($totalRecords / $limit));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $limit;

// =====================================================
// 7. LAY DANH SACH TAI KHOAN
// =====================================================

$users = [];

try {
    $userSql = "
        SELECT
            nd.ma_nguoi_dung,
            nd.ma_vai_tro,
            nd.ten_dang_nhap,
            nd.ho_ten,
            nd.email,
            nd.so_dien_thoai,
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

    $users = $userStmt->fetchAll();

} catch (PDOException $e) {
    error_log($e->getMessage());
}

// =====================================================
// 8. TAO DUONG DAN PHAN TRANG
// =====================================================

$queryParams = $_GET;
unset($queryParams['page']);

$paginationUrl = '?' . http_build_query($queryParams);

?>

<div class="container-fluid py-3">

    <!-- TIEU DE TRANG -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">

        <div>
            <h4 class="mb-1 fw-bold">Quản lý tài khoản</h4>

            <p class="text-muted mb-0">
                Quản lý tài khoản người dùng và phân quyền hệ thống
            </p>
        </div>

        <a href="<?= $escape(BASE_URL) ?>/modules/users/create.php"
           class="btn btn-primary">

            <i class="bi bi-person-plus me-1"></i>
            Tạo tài khoản

        </a>

    </div>

    <!-- THONG KE TAI KHOAN -->
    <div class="row g-3 mb-4">

        <div class="col-md-3 col-sm-6">

            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">

                    <div class="text-muted small mb-2">
                        Tổng tài khoản
                    </div>

                    <h4 class="mb-0 fw-bold">
                        <?= (int) $stats['tong_tai_khoan'] ?>
                    </h4>

                </div>
            </div>

        </div>

        <div class="col-md-3 col-sm-6">

            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">

                    <div class="text-muted small mb-2">
                        Đang hoạt động
                    </div>

                    <h4 class="mb-0 fw-bold text-success">
                        <?= (int) $stats['dang_hoat_dong'] ?>
                    </h4>

                </div>
            </div>

        </div>

        <div class="col-md-3 col-sm-6">

            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">

                    <div class="text-muted small mb-2">
                        Ngừng hoạt động
                    </div>

                    <h4 class="mb-0 fw-bold">
                        <?= (int) $stats['ngung_hoat_dong'] ?>
                    </h4>

                </div>
            </div>

        </div>

        <div class="col-md-3 col-sm-6">

            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">

                    <div class="text-muted small mb-2">
                        Bị khóa
                    </div>

                    <h4 class="mb-0 fw-bold text-danger">
                        <?= (int) $stats['bi_khoa'] ?>
                    </h4>

                </div>
            </div>

        </div>

    </div>

    <!-- TIM KIEM VA LOC -->
    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form method="GET" action="">

                <div class="row g-3 align-items-end">

                    <div class="col-lg-4 col-md-6">

                        <label class="form-label">
                            Tìm kiếm tài khoản
                        </label>

                        <input
                            type="text"
                            name="keyword"
                            class="form-control"
                            placeholder="Tên đăng nhập, họ tên hoặc email"
                            value="<?= $escape($keyword) ?>"
                        >

                    </div>

                    <div class="col-lg-3 col-md-6">

                        <label class="form-label">
                            Vai trò
                        </label>

                        <select name="role" class="form-select">

                            <option value="">
                                Tất cả vai trò
                            </option>

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

                    <div class="col-lg-3 col-md-6">

                        <label class="form-label">
                            Trạng thái
                        </label>

                        <select name="status" class="form-select">

                            <option value="">Tất cả trạng thái</option>

                            <option value="ACTIVE"
                                <?= $statusFilter === 'ACTIVE' ? 'selected' : '' ?>>
                                Đang hoạt động
                            </option>

                            <option value="INACTIVE"
                                <?= $statusFilter === 'INACTIVE' ? 'selected' : '' ?>>
                                Ngừng hoạt động
                            </option>

                            <option value="LOCKED"
                                <?= $statusFilter === 'LOCKED' ? 'selected' : '' ?>>
                                Đã khóa
                            </option>

                        </select>

                    </div>

                    <div class="col-lg-2 col-md-6">

                        <div class="d-flex gap-2">

                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-search me-1"></i>
                                Lọc
                            </button>

                            <a href="<?= $escape(BASE_URL) ?>/users.php"
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

    <!-- DANH SACH TAI KHOAN -->
    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3">

            <div class="d-flex justify-content-between align-items-center">

                <h6 class="mb-0 fw-bold">
                    Danh sách tài khoản
                </h6>

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
                            <th>Vai trò</th>
                            <th>Email</th>
                            <th>Số điện thoại</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th class="text-center">Thao tác</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($users)): ?>

                            <tr>
                                <td colspan="9" class="text-center py-5">

                                    <i class="bi bi-person-x fs-2 text-muted"></i>

                                    <p class="text-muted mb-0 mt-2">
                                        Không tìm thấy tài khoản phù hợp
                                    </p>

                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($users as $user): ?>

                                <tr>

                                    <td class="ps-3">
                                        <?= (int) $user['ma_nguoi_dung'] ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= $escape($user['ten_dang_nhap']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= $escape($user['ho_ten']) ?>
                                    </td>

                                    <td>
                                        <?= $escape($user['ten_vai_tro']) ?>
                                    </td>

                                    <td>
                                        <?= $escape($user['email'] ?: '—') ?>
                                    </td>

                                    <td>
                                        <?= $escape($user['so_dien_thoai'] ?: '—') ?>
                                    </td>

                                    <td>

                                        <?php if ($user['trang_thai'] === 'ACTIVE'): ?>

                                            <span class="badge bg-success">
                                                Đang hoạt động
                                            </span>

                                        <?php elseif ($user['trang_thai'] === 'INACTIVE'): ?>

                                            <span class="badge bg-secondary">
                                                Ngừng hoạt động
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-danger">
                                                Đã khóa
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?= $escape(date('d/m/Y', strtotime($user['ngay_tao']))) ?>
                                    </td>

                                    <td>

                                        <div class="d-flex justify-content-center gap-1">

                                            <a
                                                href="<?= $escape(BASE_URL) ?>/modules/users/edit.php?id=<?= (int) $user['ma_nguoi_dung'] ?>"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Cập nhật tài khoản"
                                            >
                                                <i class="bi bi-pencil-square"></i>
                                            </a>

                                            <a
                                                href="<?= $escape(BASE_URL) ?>/modules/users/reset_password.php?id=<?= (int) $user['ma_nguoi_dung'] ?>"
                                                class="btn btn-sm btn-outline-secondary"
                                                title="Reset mật khẩu"
                                            >
                                                <i class="bi bi-key"></i>
                                            </a>

                                        </div>

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

                    <nav aria-label="Phân trang tài khoản">

                        <ul class="pagination pagination-sm mb-0">

                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">

                                <a class="page-link"
                                   href="<?= $escape($paginationUrl . '&page=' . ($page - 1)) ?>">

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
                                   href="<?= $escape($paginationUrl . '&page=' . ($page + 1)) ?>">

                                    Sau

                                </a>

                            </li>

                        </ul>

                    </nav>

                </div>

            </div>

        <?php endif; ?>

    </div>

</div>
