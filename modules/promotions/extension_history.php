
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/promotions/extension_history.php
// CHUC NANG: XEM LICH SU GIA HAN KHUYEN MAI
// DOI TUONG: ADMIN
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/permission_check.php';

// -----------------------------------------------------
// 1. KIEM TRA QUYEN
// -----------------------------------------------------

if (lay_vai_tro_hien_tai() !== 1 || !co_quyen('promotions.extend')) {
    http_response_code(403);
    exit('Bạn không có quyền xem lịch sử gia hạn khuyến mãi.');
}

// -----------------------------------------------------
// 2. HAM HO TRO
// -----------------------------------------------------

$escape = static function ($value) {
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
};

$formatDate = static function ($date) {
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime($date);

    return $timestamp === false
        ? '-'
        : date('d/m/Y H:i:s', $timestamp);
};

// -----------------------------------------------------
// 3. LAY MA CHUONG TRINH
// -----------------------------------------------------

$maChuongTrinh = filter_input(
    INPUT_GET,
    'ma_chuong_trinh',
    FILTER_VALIDATE_INT
);

if (!$maChuongTrinh || $maChuongTrinh <= 0) {
    http_response_code(400);
    exit('Mã chương trình khuyến mãi không hợp lệ.');
}

// -----------------------------------------------------
// 4. TRUY VAN DU LIEU
// -----------------------------------------------------

try {

    // Thong tin chuong trinh
    $sqlPromotion = "
        SELECT
            ma_chuong_trinh,
            ten_chuong_trinh,
            ngay_bat_dau,
            ngay_ket_thuc,
            trang_thai
        FROM chuong_trinh_khuyen_mai
        WHERE ma_chuong_trinh = :ma_chuong_trinh
        LIMIT 1
    ";

    $stmtPromotion = $pdo->prepare($sqlPromotion);

    $stmtPromotion->execute([
        ':ma_chuong_trinh' => $maChuongTrinh
    ]);

    $promotion = $stmtPromotion->fetch(PDO::FETCH_ASSOC);

    if (!$promotion) {
        http_response_code(404);
        exit('Không tìm thấy chương trình khuyến mãi.');
    }

    // Danh sach lich su gia han
    $sqlHistory = "
        SELECT
            ls.ma_gia_han,
            ls.ngay_ket_thuc_cu,
            ls.ngay_ket_thuc_moi,
            ls.ly_do_gia_han,
            ls.ma_nguoi_thuc_hien,
            ls.ngay_gia_han,
            nd.ho_ten,
            nd.ten_dang_nhap
        FROM lich_su_gia_han_khuyen_mai ls
        LEFT JOIN nguoi_dung nd
            ON ls.ma_nguoi_thuc_hien = nd.ma_nguoi_dung
        WHERE ls.ma_chuong_trinh = :ma_chuong_trinh
        ORDER BY ls.ngay_gia_han ASC, ls.ma_gia_han ASC
    ";

    $stmtHistory = $pdo->prepare($sqlHistory);

    $stmtHistory->execute([
        ':ma_chuong_trinh' => $maChuongTrinh
    ]);

    $history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        'HP COMPUTER - PROMOTION HISTORY ERROR: '
        . $e->getMessage()
    );

    http_response_code(500);
    exit('Không thể tải lịch sử gia hạn. Vui lòng thử lại.');
}

// -----------------------------------------------------
// 5. THONG TIN TRANG
// -----------------------------------------------------

$totalExtensions = count($history);

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

$status = $promotion['trang_thai'];

$statusLabel = $statusLabels[$status] ?? $status;
$statusClass = $statusClasses[$status] ?? 'bg-secondary';

$backUrl = BASE_URL . '/promotions.php';

$pageTitle = 'Lịch sử gia hạn khuyến mãi';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';

?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="container-fluid py-3">

            <!-- TIEU DE -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">

                <div>

                    <h4 class="fw-bold mb-1">
                        <i class="bi bi-clock-history me-2"></i>
                        Lịch sử gia hạn khuyến mãi
                    </h4>

                    <p class="text-muted mb-0">
                        Theo dõi các lần thay đổi thời gian kết thúc chương trình.
                    </p>

                </div>

                <a
                    href="<?= $escape($backUrl) ?>"
                    class="btn btn-outline-secondary"
                >
                    <i class="bi bi-arrow-left me-1"></i>
                    Quay lại
                </a>

            </div>

            <!-- THONG TIN CHUONG TRINH -->
            <div class="card border-0 shadow-sm mb-4">

                <div class="card-header bg-white py-3">

                    <h5 class="fw-bold mb-0">
                        Thông tin chương trình
                    </h5>

                </div>

                <div class="card-body">

                    <div class="row g-3">

                        <div class="col-md-6">

                            <div class="text-muted small mb-1">
                                Mã chương trình
                            </div>

                            <div class="fw-semibold">
                                KM<?= str_pad(
                                    (string) $promotion['ma_chuong_trinh'],
                                    4,
                                    '0',
                                    STR_PAD_LEFT
                                ) ?>
                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="text-muted small mb-1">
                                Tên chương trình
                            </div>

                            <div class="fw-semibold">
                                <?= $escape($promotion['ten_chuong_trinh']) ?>
                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="text-muted small mb-1">
                                Ngày bắt đầu
                            </div>

                            <div>
                                <?= $escape($formatDate($promotion['ngay_bat_dau'])) ?>
                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="text-muted small mb-1">
                                Ngày kết thúc hiện tại
                            </div>

                            <div class="fw-bold text-success">
                                <?= $escape($formatDate($promotion['ngay_ket_thuc'])) ?>
                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="text-muted small mb-1">
                                Trạng thái
                            </div>

                            <span class="badge <?= $escape($statusClass) ?>">
                                <?= $escape($statusLabel) ?>
                            </span>

                        </div>

                        <div class="col-md-6">

                            <div class="text-muted small mb-1">
                                Tổng số lần gia hạn
                            </div>

                            <span class="fw-bold text-primary fs-5">
                                <?= number_format($totalExtensions) ?> lần
                            </span>

                        </div>

                    </div>

                </div>

            </div>

            <!-- LICH SU GIA HAN -->
            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white py-3">

                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-calendar-check me-2"></i>
                        Chi tiết các lần gia hạn
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

                                    <th style="min-width: 170px;">
                                        Ngày kết thúc cũ
                                    </th>

                                    <th style="min-width: 170px;">
                                        Ngày kết thúc mới
                                    </th>

                                    <th style="min-width: 120px;">
                                        Thời gian kéo dài
                                    </th>

                                    <th style="min-width: 180px;">
                                        Lý do gia hạn
                                    </th>

                                    <th style="min-width: 150px;">
                                        Người thực hiện
                                    </th>

                                    <th style="min-width: 170px;">
                                        Thời điểm gia hạn
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php if (empty($history)): ?>

                                    <tr>

                                        <td colspan="7" class="text-center py-5">

                                            <i class="bi bi-calendar-x fs-1 text-muted"></i>

                                            <p class="text-muted mt-2 mb-0">
                                                Chương trình này chưa có lịch sử gia hạn.
                                            </p>

                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($history as $index => $item): ?>

                                        <?php

                                        $daysExtended = 0;

                                        try {

                                            $oldDate = new DateTime(
                                                $item['ngay_ket_thuc_cu']
                                            );

                                            $newDate = new DateTime(
                                                $item['ngay_ket_thuc_moi']
                                            );

                                            $daysExtended = (int) $oldDate
                                                ->diff($newDate)
                                                ->format('%r%a');

                                        } catch (Throwable $e) {
                                            $daysExtended = 0;
                                        }

                                        $executor = $item['ho_ten']
                                            ?: $item['ten_dang_nhap']
                                            ?: 'Tài khoản #' . $item['ma_nguoi_thuc_hien'];

                                        ?>

                                        <tr>

                                            <td class="text-center">
                                                <?= $index + 1 ?>
                                            </td>

                                            <td>
                                                <?= $escape(
                                                    $formatDate($item['ngay_ket_thuc_cu'])
                                                ) ?>
                                            </td>

                                            <td class="fw-semibold text-success">
                                                <?= $escape(
                                                    $formatDate($item['ngay_ket_thuc_moi'])
                                                ) ?>
                                            </td>

                                            <td>

                                                <?php if ($daysExtended > 0): ?>

                                                    <span class="badge bg-success">
                                                        +<?= number_format($daysExtended) ?> ngày
                                                    </span>

                                                <?php else: ?>

                                                    <span class="text-muted">
                                                        <?= number_format($daysExtended) ?> ngày
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                            <td style="white-space: normal; min-width: 180px;">
                                                <?= nl2br(
                                                    $escape(
                                                        $item['ly_do_gia_han'] ?: 'Không có lý do'
                                                    )
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= $escape($executor) ?>
                                            </td>

                                            <td>
                                                <?= $escape(
                                                    $formatDate($item['ngay_gia_han'])
                                                ) ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

                <?php if (!empty($history)): ?>

                    <div class="card-footer bg-white text-muted small">

                        Hiển thị <?= number_format($totalExtensions) ?>
                        lần gia hạn theo thứ tự thời gian từ cũ đến mới.

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
