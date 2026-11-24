<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permission_check.php';
yeu_cau_quyen('promotions.view');


// =====================================================
// HP COMPUTER
// FILE: modules/promotions/index.php
// CHUC NANG: DANH SACH CHUONG TRINH KHUYEN MAI
// BO SUNG: GIA HAN VA LICH SU GIA HAN (ADMIN)
// =====================================================

// -----------------------------------------------------
// 1. KIEM TRA KET NOI DATABASE
// -----------------------------------------------------

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo '<div class="alert alert-danger m-3">';
    echo 'Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra config/database.php.';
    echo '</div>';
    return;
}

// -----------------------------------------------------
// 2. KIEM TRA QUYEN GIA HAN KHUYEN MAI
// -----------------------------------------------------

require_once __DIR__ . '/../../includes/permission_check.php';

$isAdmin = (
    lay_vai_tro_hien_tai() === 1
    && co_quyen('promotions.extend')
);

// -----------------------------------------------------
// 3. KHOI TAO DU LIEU
// -----------------------------------------------------

$keyword = trim($_GET['keyword'] ?? '');
$status = trim($_GET['status'] ?? '');

$currentPage = max(1, (int) ($_GET['page'] ?? 1));

$limit = 10;
$offset = 0;

$errorMessage = '';
$promotions = [];
$totalRecords = 0;
$totalPages = 0;

// -----------------------------------------------------
// 4. DANH SACH TRANG THAI
// -----------------------------------------------------

$statusLabels = [
    'DRAFT'     => 'Bản nháp',
    'ACTIVE'    => 'Đang hoạt động',
    'INACTIVE'  => 'Ngừng hoạt động',
    'EXPIRED'   => 'Đã hết hạn',
    'CANCELLED' => 'Đã hủy'
];

$statusClasses = [
    'DRAFT'     => 'bg-secondary',
    'ACTIVE'    => 'bg-success',
    'INACTIVE'  => 'bg-warning text-dark',
    'EXPIRED'   => 'bg-dark',
    'CANCELLED' => 'bg-danger'
];

// -----------------------------------------------------
// 5. HAM HIEN THI DU LIEU AN TOAN
// -----------------------------------------------------

$escape = static function ($value) {
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
};

// -----------------------------------------------------
// 6. HAM DINH DANG NGAY GIO
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
// 7. HAM DINH DANG MUC GIAM GIA
// -----------------------------------------------------

$formatDiscount = static function ($promotion) {

    $value = (float) $promotion['gia_tri_giam'];

    if ($promotion['loai_giam_gia'] === 'PERCENT') {

        $result = rtrim(
            rtrim(number_format($value, 2, '.', ''), '0'),
            '.'
        );

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

// -----------------------------------------------------
// 8. KIEM TRA BO LOC
// -----------------------------------------------------

if (!array_key_exists($status, $statusLabels)) {
    $status = '';
}

// -----------------------------------------------------
// 9. TRUY VAN DATABASE
// -----------------------------------------------------

try {

    $conditions = [];
    $params = [];

    // Tim kiem ten chuong trinh
    if ($keyword !== '') {

        $conditions[] = "
            (
                ten_chuong_trinh LIKE :keyword
                OR mo_ta LIKE :keyword
            )
        ";

        $params[':keyword'] = '%' . $keyword . '%';
    }

    // Loc trang thai
    if ($status !== '') {

        $conditions[] = "trang_thai = :status";

        $params[':status'] = $status;
    }

    // Tao dieu kien WHERE
    $whereSQL = '';

    if (!empty($conditions)) {
        $whereSQL = ' WHERE ' . implode(' AND ', $conditions);
    }

    // Dem tong so ban ghi
    $sqlCount = "
        SELECT COUNT(*)
        FROM chuong_trinh_khuyen_mai
        $whereSQL
    ";

    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);

    $totalRecords = (int) $stmtCount->fetchColumn();

    // Tinh phan trang
    $totalPages = (int) ceil($totalRecords / $limit);

    if ($totalPages > 0 && $currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    $offset = ($currentPage - 1) * $limit;

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
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        'HP COMPUTER - PROMOTIONS ERROR: ' . $e->getMessage()
    );

    $errorMessage = 'Không thể tải danh sách chương trình khuyến mãi. Vui lòng kiểm tra cấu hình database và cấu trúc bảng.';
}

// -----------------------------------------------------
// 10. DUONG DAN CAC CHUC NANG
// -----------------------------------------------------

$createUrl = BASE_URL . '/modules/promotions/create.php';
$editUrl = BASE_URL . '/modules/promotions/edit.php';
$selectProductsUrl = BASE_URL . '/modules/promotions/select_products.php';
$extendUrl = BASE_URL . '/modules/promotions/extend.php';
$historyUrl = BASE_URL . '/modules/promotions/extension_history.php';
$listUrl = BASE_URL . '/promotions.php';

?>

<!-- =====================================================
     NOI DUNG QUAN LY CHUONG TRINH KHUYEN MAI
====================================================== -->

<div class="container-fluid py-3">

    <!-- TIEU DE TRANG -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">

        <div>

            <h4 class="fw-bold mb-1">
                Quản lý chương trình khuyến mãi
            </h4>

            <p class="text-muted mb-0">
                Quản lý các chương trình ưu đãi của HP Computer.
            </p>

        </div>

        <a
            href="<?= $escape($createUrl) ?>"
            class="btn btn-primary"
        >
            <i class="bi bi-plus-lg me-1"></i>
            Thêm chương trình
        </a>

    </div>

    <!-- THONG BAO THANH CONG -->
    <?php if (isset($_GET['created'])): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <i class="bi bi-check-circle me-2"></i>
            Thêm chương trình khuyến mãi thành công.

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <i class="bi bi-check-circle me-2"></i>
            Cập nhật chương trình khuyến mãi thành công.

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>

    <?php if (isset($_GET['extended'])): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <i class="bi bi-check-circle me-2"></i>
            Gia hạn chương trình khuyến mãi thành công.
            Lịch sử gia hạn đã được lưu lại.

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>

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
                        Tổng chương trình
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
                        Tổng số trang
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

            <form
                method="GET"
                action="<?= $escape($listUrl) ?>"
            >

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
                            placeholder="Nhập tên chương trình..."
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

                            <option value="">
                                Tất cả trạng thái
                            </option>

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

                            <button
                                type="submit"
                                class="btn btn-primary flex-fill"
                            >
                                <i class="bi bi-search me-1"></i>
                                Tìm kiếm
                            </button>

                            <a
                                href="<?= $escape($listUrl) ?>"
                                class="btn btn-outline-secondary"
                                title="Làm mới"
                            >
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>

                        </div>

                    </div>

                </div>

            </form>

        </div>

    </div>

    <!-- BANG DANH SACH -->
    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3">

            <h5 class="fw-bold mb-0">
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
                                Ngày bắt đầu
                            </th>

                            <th style="min-width: 170px;">
                                Ngày kết thúc
                            </th>

                            <th class="text-center" style="min-width: 130px;">
                                Trạng thái
                            </th>

                            <th class="text-center" style="min-width: 190px;">
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
                                        Chưa có chương trình khuyến mãi nào.
                                    </p>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($promotions as $index => $promotion): ?>

                                <?php

                                $promotionId = (int) $promotion['ma_chuong_trinh'];

                                $promotionStatus = $promotion['trang_thai'];

                                $statusLabel = $statusLabels[$promotionStatus]
                                    ?? $promotionStatus;

                                $statusClass = $statusClasses[$promotionStatus]
                                    ?? 'bg-secondary';

                                // Duong dan chinh sua
                                $currentEditUrl = $editUrl
                                    . '?ma_chuong_trinh=' . $promotionId;

                                // Duong dan quan ly san pham
                                $currentProductsUrl = $selectProductsUrl
                                    . '?ma_chuong_trinh=' . $promotionId;

                                // Duong dan gia han
                                $currentExtendUrl = $extendUrl
                                    . '?ma_chuong_trinh=' . $promotionId;

                                // Duong dan lich su gia han
                                $currentHistoryUrl = $historyUrl
                                    . '?ma_chuong_trinh=' . $promotionId;

                                ?>

                                <tr>

                                    <td class="text-center">
                                        <?= $offset + $index + 1 ?>
                                    </td>

                                    <td>

                                        <div class="fw-semibold">
                                            <?= $escape($promotion['ten_chuong_trinh']) ?>
                                        </div>

                                        <small class="text-muted">
                                            Mã: KM<?= str_pad(
                                                (string) $promotionId,
                                                4,
                                                '0',
                                                STR_PAD_LEFT
                                            ) ?>
                                        </small>

                                    </td>

                                    <td class="fw-semibold">
                                        <?= $escape($formatDiscount($promotion)) ?>
                                    </td>

                                    <td>
                                        <?= $escape($formatDate($promotion['ngay_bat_dau'])) ?>
                                    </td>

                                    <td>
                                        <?= $escape($formatDate($promotion['ngay_ket_thuc'])) ?>
                                    </td>

                                    <td class="text-center">

                                        <span class="badge <?= $escape($statusClass) ?>">
                                            <?= $escape($statusLabel) ?>
                                        </span>

                                    </td>

                                    <!-- CAC NUT THAO TAC -->
                                    <td class="text-center">

                                        <div class="d-flex justify-content-center gap-1">

                                            <!-- CHINH SUA -->
                                            <a
                                                href="<?= $escape($currentEditUrl) ?>"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Chỉnh sửa chương trình"
                                            >
                                                <i class="bi bi-pencil-square"></i>
                                            </a>

                                            <!-- CHON SAN PHAM -->
                                            <a
                                                href="<?= $escape($currentProductsUrl) ?>"
                                                class="btn btn-sm btn-outline-success"
                                                title="Quản lý sản phẩm khuyến mãi"
                                            >
                                                <i class="bi bi-box-seam"></i>
                                            </a>

                                            <?php if ($isAdmin): ?>

                                                <!-- GIA HAN KHUYEN MAI -->
                                                <?php if ($promotionStatus === 'ACTIVE'): ?>

                                                    <a
                                                        href="<?= $escape($currentExtendUrl) ?>"
                                                        class="btn btn-sm btn-outline-warning"
                                                        title="Gia hạn chương trình"
                                                    >
                                                        <i class="bi bi-calendar-plus"></i>
                                                    </a>

                                                <?php endif; ?>

                                                <!-- LICH SU GIA HAN -->
                                                <a
                                                    href="<?= $escape($currentHistoryUrl) ?>"
                                                    class="btn btn-sm btn-outline-info"
                                                    title="Xem lịch sử gia hạn"
                                                >
                                                    <i class="bi bi-clock-history"></i>
                                                </a>

                                            <?php endif; ?>

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

            <?php

            $queryParams = [];

            if ($keyword !== '') {
                $queryParams['keyword'] = $keyword;
            }

            if ($status !== '') {
                $queryParams['status'] = $status;
            }

            ?>

            <div class="card-footer bg-white">

                <nav aria-label="Phân trang">

                    <ul class="pagination justify-content-end mb-0">

                        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">

                            <a
                                class="page-link"
                                href="<?= $escape($listUrl) ?>?<?= $escape(
                                    http_build_query(
                                        array_merge(
                                            $queryParams,
                                            ['page' => max(1, $currentPage - 1)]
                                        )
                                    )
                                ) ?>"
                            >
                                Trước
                            </a>

                        </li>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                            <li class="page-item <?= $currentPage === $i ? 'active' : '' ?>">

                                <a
                                    class="page-link"
                                    href="<?= $escape($listUrl) ?>?<?= $escape(
                                        http_build_query(
                                            array_merge(
                                                $queryParams,
                                                ['page' => $i]
                                            )
                                        )
                                    ) ?>"
                                >
                                    <?= $i ?>
                                </a>

                            </li>

                        <?php endfor; ?>

                        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">

                            <a
                                class="page-link"
                                href="<?= $escape($listUrl) ?>?<?= $escape(
                                    http_build_query(
                                        array_merge(
                                            $queryParams,
                                            ['page' => min($totalPages, $currentPage + 1)]
                                        )
                                    )
                                ) ?>"
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
