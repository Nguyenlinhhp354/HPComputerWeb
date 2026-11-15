
<?php

// =====================================================
// HP COMPUTER
// FILE: modules/promotions/index.php
// CHUC NANG: DANH SACH CHUONG TRINH KHUYEN MAI
// =====================================================

// -----------------------------------------------------
// 1. KHOI TAO DU LIEU
// -----------------------------------------------------
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$keyword = trim($_GET['keyword'] ?? '');
$status = trim($_GET['status'] ?? '');

$allowedStatuses = [
    'DRAFT',
    'ACTIVE',
    'INACTIVE',
    'EXPIRED',
    'CANCELLED'
];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$currentPage = max(1, (int) ($_GET['page'] ?? 1));

$limit = 10;
$offset = ($currentPage - 1) * $limit;

$promotions = [];
$totalRecords = 0;
$totalPages = 0;
$errorMessage = '';

// -----------------------------------------------------
// 2. DANH SACH TRANG THAI
// -----------------------------------------------------

$statusLabels = [
    'DRAFT'     => 'Bản nháp',
    'ACTIVE'    => 'Đang hoạt động',
    'INACTIVE'  => 'Ngừng hoạt động',
    'EXPIRED'   => 'Đã hết hạn',
    'CANCELLED' => 'Đã hủy'
];

// -----------------------------------------------------
// 3. HAM HIEN THI DU LIEU AN TOAN
// -----------------------------------------------------

$escape = static function ($value) {
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
};

// -----------------------------------------------------
// 4. LAY DANH SACH CHUONG TRINH KHUYEN MAI
// -----------------------------------------------------

try {

    $conditions = [];
    $params = [];

    if ($keyword !== '') {
        $conditions[] = "
            (
                ten_chuong_trinh LIKE :keyword
                OR mo_ta LIKE :keyword
            )
        ";

        $params[':keyword'] = '%' . $keyword . '%';
    }

    if ($status !== '') {
        $conditions[] = "trang_thai = :status";
        $params[':status'] = $status;
    }

    $whereSQL = '';

    if (!empty($conditions)) {
        $whereSQL = ' WHERE ' . implode(' AND ', $conditions);
    }

    // Dem tong so chuong trinh
    $sqlCount = "
        SELECT COUNT(*)
        FROM chuong_trinh_khuyen_mai
        $whereSQL
    ";

    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);

    $totalRecords = (int) $stmtCount->fetchColumn();

    $totalPages = (int) ceil($totalRecords / $limit);

    if ($totalPages > 0 && $currentPage > $totalPages) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $limit;
    }

    // Lay danh sach chuong trinh
    $sql = "
        SELECT
            ma_chuong_trinh,
            ten_chuong_trinh,
            mo_ta,
            loai_giam_gia,
            gia_tri_giam,
            gia_tri_giam_toi_da,
            ngay_bat_dau,
            ngay_ket_thuc,
            trang_thai,
            ngay_tao
        FROM chuong_trinh_khuyen_mai
        $whereSQL
        ORDER BY ngay_tao DESC, ma_chuong_trinh DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $errorMessage = 'Không thể tải danh sách chương trình khuyến mãi. Vui lòng thử lại.';

}

// -----------------------------------------------------
// 5. HAM DINH DANG NGAY GIO
// -----------------------------------------------------

$formatDate = static function ($date) {

    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return '-';
    }

    return date('d/m/Y H:i', $timestamp);
};

// -----------------------------------------------------
// 6. HAM DINH DANG GIA TRI GIAM GIA
// -----------------------------------------------------

$formatDiscount = static function ($promotion) {

    $value = (float) $promotion['gia_tri_giam'];

    if ($promotion['loai_giam_gia'] === 'PERCENT') {

        $result = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
        $result .= '%';

        if ($promotion['gia_tri_giam_toi_da'] !== null) {
            $result .= ' (Tối đa ' .
                number_format(
                    (float) $promotion['gia_tri_giam_toi_da'],
                    0,
                    ',',
                    '.'
                ) . 'đ)';
        }

        return $result;
    }

    return number_format($value, 0, ',', '.') . 'đ';
};

?>

<!-- =================================================
     NOI DUNG QUAN LY KHUYEN MAI
================================================== -->

<div class="container-fluid py-3">

    <!-- TIEU DE -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">

        <div>
            <h4 class="mb-1 fw-bold">
                Quản lý chương trình khuyến mãi
            </h4>

            <p class="text-muted mb-0">
                Quản lý thông tin và các chương trình ưu đãi của cửa hàng.
            </p>
        </div>

        <button
            type="button"
            class="btn btn-primary"
            disabled
            title="Chức năng thêm chương trình sẽ được triển khai ở bước tiếp theo"
        >
            <i class="bi bi-plus-lg me-1"></i>
            Thêm chương trình
        </button>

    </div>

    <!-- THONG BAO LOI -->
    <?php if ($errorMessage !== ''): ?>

        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= $escape($errorMessage) ?>
        </div>

    <?php endif; ?>

    <!-- THONG KE -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="text-muted small mb-2">
                        Tổng chương trình tìm thấy
                    </div>

                    <h3 class="fw-bold mb-0">
                        <?= number_format($totalRecords) ?>
                    </h3>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="text-muted small mb-2">
                        Số chương trình đang hiển thị
                    </div>

                    <h3 class="fw-bold mb-0">
                        <?= number_format(count($promotions)) ?>
                    </h3>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="text-muted small mb-2">
                        Số trang
                    </div>

                    <h3 class="fw-bold mb-0">
                        <?= number_format($totalPages) ?>
                    </h3>

                </div>

            </div>

        </div>

    </div>

    <!-- TIM KIEM VA LOC -->
    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form method="GET" action="<?= $escape(BASE_URL) ?>/promotions.php">

                <div class="row g-3 align-items-end">

                    <div class="col-md-6">

                        <label for="keyword" class="form-label">
                            Tìm kiếm chương trình
                        </label>

                        <input
                            type="text"
                            name="keyword"
                            id="keyword"
                            class="form-control"
                            placeholder="Nhập tên hoặc mô tả chương trình..."
                            value="<?= $escape($keyword) ?>"
                        >

                    </div>

                    <div class="col-md-3">

                        <label for="status" class="form-label">
                            Trạng thái
                        </label>

                        <select
                            name="status"
                            id="status"
                            class="form-select"
                        >

                            <option value="">Tất cả trạng thái</option>

                            <?php foreach ($statusLabels as $value => $label): ?>

                                <option
                                    value="<?= $escape($value) ?>"
                                    <?= $status === $value ? 'selected' : '' ?>
                                >
                                    <?= $escape($label) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-md-3">

                        <div class="d-flex gap-2">

                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="bi bi-search me-1"></i>
                                Tìm kiếm
                            </button>

                            <a
                                href="<?= $escape(BASE_URL) ?>/promotions.php"
                                class="btn btn-outline-secondary"
                                title="Làm mới bộ lọc"
                            >
                                <i class="bi bi-arrow-clockwise"></i>
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

            <h5 class="mb-0 fw-bold">
                <i class="bi bi-tags me-2"></i>
                Danh sách chương trình khuyến mãi
            </h5>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th class="text-center" style="width: 70px;">
                                STT
                            </th>

                            <th style="min-width: 220px;">
                                Tên chương trình
                            </th>

                            <th style="min-width: 160px;">
                                Mức giảm giá
                            </th>

                            <th style="min-width: 170px;">
                                Thời gian bắt đầu
                            </th>

                            <th style="min-width: 170px;">
                                Thời gian kết thúc
                            </th>

                            <th class="text-center" style="min-width: 150px;">
                                Trạng thái
                            </th>

                            <th class="text-center" style="width: 100px;">
                                Thao tác
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($promotions)): ?>

                            <tr>

                                <td colspan="7" class="text-center py-5">

                                    <i class="bi bi-inbox fs-1 text-muted"></i>

                                    <p class="text-muted mt-2 mb-0">
                                        Không tìm thấy chương trình khuyến mãi nào.
                                    </p>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($promotions as $index => $promotion): ?>

                                <?php

                                $badgeClasses = [
                                    'DRAFT'     => 'bg-secondary',
                                    'ACTIVE'    => 'bg-success',
                                    'INACTIVE'  => 'bg-warning text-dark',
                                    'EXPIRED'   => 'bg-dark',
                                    'CANCELLED' => 'bg-danger'
                                ];

                                $badgeClass = $badgeClasses[$promotion['trang_thai']]
                                    ?? 'bg-secondary';

                                $statusLabel = $statusLabels[$promotion['trang_thai']]
                                    ?? $promotion['trang_thai'];

                                $stt = $offset + $index + 1;

                                ?>

                                <tr>

                                    <td class="text-center">
                                        <?= $stt ?>
                                    </td>

                                    <td>

                                        <div class="fw-semibold">
                                            <?= $escape($promotion['ten_chuong_trinh']) ?>
                                        </div>

                                        <small class="text-muted">
                                            Mã: KM<?= str_pad(
                                                (string) $promotion['ma_chuong_trinh'],
                                                4,
                                                '0',
                                                STR_PAD_LEFT
                                            ) ?>
                                        </small>

                                    </td>

                                    <td>
                                        <span class="fw-semibold">
                                            <?= $escape($formatDiscount($promotion)) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= $escape($formatDate($promotion['ngay_bat_dau'])) ?>
                                    </td>

                                    <td>
                                        <?= $escape($formatDate($promotion['ngay_ket_thuc'])) ?>
                                    </td>

                                    <td class="text-center">

                                        <span class="badge <?= $escape($badgeClass) ?>">
                                            <?= $escape($statusLabel) ?>
                                        </span>

                                    </td>

                                    <td class="text-center">

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            disabled
                                            title="Chức năng xem chi tiết sẽ được triển khai sau"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>

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

                <?php
                $queryParams = [];

                if ($keyword !== '') {
                    $queryParams['keyword'] = $keyword;
                }

                if ($status !== '') {
                    $queryParams['status'] = $status;
                }
                ?>

                <nav aria-label="Phân trang khuyến mãi">

                    <ul class="pagination justify-content-end mb-0">

                        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">

                            <a
                                class="page-link"
                                href="<?= $escape(BASE_URL) ?>/promotions.php?<?= $escape(http_build_query(array_merge($queryParams, ['page' => max(1, $currentPage - 1)]))) ?>"
                            >
                                Trước
                            </a>

                        </li>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                            <li class="page-item <?= $currentPage === $i ? 'active' : '' ?>">

                                <a
                                    class="page-link"
                                    href="<?= $escape(BASE_URL) ?>/promotions.php?<?= $escape(http_build_query(array_merge($queryParams, ['page' => $i]))) ?>"
                                >
                                    <?= $i ?>
                                </a>

                            </li>

                        <?php endfor; ?>

                        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">

                            <a
                                class="page-link"
                                href="<?= $escape(BASE_URL) ?>/promotions.php?<?= $escape(http_build_query(array_merge($queryParams, ['page' => min($totalPages, $currentPage + 1)]))) ?>"
                            >
                                Sau
                            </a>

                        </li>

                    </ul>

                </nav>

            </div>

        <?php endif; ?>

    </div>

</div>
