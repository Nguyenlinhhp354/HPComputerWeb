
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/reports/promotions.php
// CHUC NANG: BAO CAO THONG KE KHUYEN MAI
// =====================================================

require_once __DIR__ . '/../../includes/permission_check.php';

yeu_cau_quyen('reports.view');

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Lỗi: Không thể kết nối cơ sở dữ liệu.');
}

// -----------------------------------------------------
// 1. HAM HO TRO
// -----------------------------------------------------

$e = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$formatMoney = static function ($value) {
    return number_format((float) $value, 0, ',', '.') . ' ₫';
};

$formatNumber = static function ($value) {
    return number_format((int) $value, 0, ',', '.');
};

$formatDate = static function ($value) {
    if (empty($value)) {
        return '—';
    }

    return date('d/m/Y', strtotime($value));
};

// -----------------------------------------------------
// 2. BO LOC NGAY
// -----------------------------------------------------

$today = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');

$tuNgay = $_GET['tu_ngay'] ?? $firstDayOfMonth;
$denNgay = $_GET['den_ngay'] ?? $today;

$isValidDate = static function ($date) {
    if (!is_string($date)) {
        return false;
    }

    $parsed = DateTime::createFromFormat('!Y-m-d', $date);

    return $parsed && $parsed->format('Y-m-d') === $date;
};

if (!$isValidDate($tuNgay)) {
    $tuNgay = $firstDayOfMonth;
}

if (!$isValidDate($denNgay)) {
    $denNgay = $today;
}

if ($tuNgay > $denNgay) {
    [$tuNgay, $denNgay] = [$denNgay, $tuNgay];
}

$tuNgaySQL = $tuNgay . ' 00:00:00';

$denNgaySQL = date(
    'Y-m-d',
    strtotime($denNgay . ' +1 day')
) . ' 00:00:00';

// -----------------------------------------------------
// 3. KHOI TAO DU LIEU
// -----------------------------------------------------

$thongKe = [
    'tong_chuong_trinh' => 0,
    'dang_hoat_dong' => 0,
    'ban_nhap' => 0,
    'ngung_hoat_dong' => 0,
    'het_han' => 0,
    'da_huy' => 0,
    'tong_luot_ap_dung' => 0,
    'tong_don_hang_ap_dung' => 0,
    'tong_tien_giam' => 0
];

$topKhuyenMai = [];
$danhSachKhuyenMai = [];

$loiTruyVan = null;

// -----------------------------------------------------
// 4. TRUY VAN DU LIEU
// -----------------------------------------------------

try {

    // -------------------------------------------------
    // 4.1. THONG KE CHUONG TRINH KHUYEN MAI
    // -------------------------------------------------

    $sqlThongKe = "
        SELECT
            COUNT(*) AS tong_chuong_trinh,

            SUM(
                CASE
                    WHEN trang_thai = 'ACTIVE'
                    THEN 1 ELSE 0
                END
            ) AS dang_hoat_dong,

            SUM(
                CASE
                    WHEN trang_thai = 'DRAFT'
                    THEN 1 ELSE 0
                END
            ) AS ban_nhap,

            SUM(
                CASE
                    WHEN trang_thai = 'INACTIVE'
                    THEN 1 ELSE 0
                END
            ) AS ngung_hoat_dong,

            SUM(
                CASE
                    WHEN trang_thai = 'EXPIRED'
                    THEN 1 ELSE 0
                END
            ) AS het_han,

            SUM(
                CASE
                    WHEN trang_thai = 'CANCELLED'
                    THEN 1 ELSE 0
                END
            ) AS da_huy

        FROM chuong_trinh_khuyen_mai
    ";

    $stmt = $pdo->query($sqlThongKe);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        foreach ($thongKe as $key => $value) {
            if (array_key_exists($key, $result)) {
                $thongKe[$key] = (int) ($result[$key] ?? 0);
            }
        }
    }


    // -------------------------------------------------
    // 4.2. THONG KE LUOT AP DUNG KHUYEN MAI
    // Theo ngay_ap_dung
    // -------------------------------------------------

    $sqlApDung = "
        SELECT
            COUNT(*) AS tong_luot_ap_dung,
            COUNT(DISTINCT ma_don_hang) AS tong_don_hang_ap_dung,
            COALESCE(SUM(so_tien_giam), 0) AS tong_tien_giam

        FROM khuyen_mai_don_hang

        WHERE ngay_ap_dung >= :tu_ngay
          AND ngay_ap_dung < :den_ngay
    ";

    $stmt = $pdo->prepare($sqlApDung);

    $stmt->execute([
        ':tu_ngay' => $tuNgaySQL,
        ':den_ngay' => $denNgaySQL
    ]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $thongKe['tong_luot_ap_dung'] =
            (int) ($result['tong_luot_ap_dung'] ?? 0);

        $thongKe['tong_don_hang_ap_dung'] =
            (int) ($result['tong_don_hang_ap_dung'] ?? 0);

        $thongKe['tong_tien_giam'] =
            (float) ($result['tong_tien_giam'] ?? 0);
    }


    // -------------------------------------------------
    // 4.3. TOP 10 CHUONG TRINH KHUYEN MAI
    // -------------------------------------------------

    $sqlTopKhuyenMai = "
        SELECT
            ctkm.ma_chuong_trinh,
            ctkm.ten_chuong_trinh,
            ctkm.loai_giam_gia,
            ctkm.gia_tri_giam,
            ctkm.trang_thai,

            COUNT(*) AS tong_luot_ap_dung,

            COUNT(DISTINCT km.ma_don_hang)
                AS tong_don_hang_ap_dung,

            COALESCE(SUM(km.so_tien_giam), 0)
                AS tong_tien_giam

        FROM khuyen_mai_don_hang km

        INNER JOIN chuong_trinh_khuyen_mai ctkm
            ON ctkm.ma_chuong_trinh = km.ma_chuong_trinh

        WHERE km.ngay_ap_dung >= :tu_ngay
          AND km.ngay_ap_dung < :den_ngay

        GROUP BY
            ctkm.ma_chuong_trinh,
            ctkm.ten_chuong_trinh,
            ctkm.loai_giam_gia,
            ctkm.gia_tri_giam,
            ctkm.trang_thai

        ORDER BY
            tong_luot_ap_dung DESC,
            tong_tien_giam DESC

        LIMIT 10
    ";

    $stmt = $pdo->prepare($sqlTopKhuyenMai);

    $stmt->execute([
        ':tu_ngay' => $tuNgaySQL,
        ':den_ngay' => $denNgaySQL
    ]);

    $topKhuyenMai = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // -------------------------------------------------
    // 4.4. DANH SACH CHUONG TRINH KHUYEN MAI
    // -------------------------------------------------

    $sqlDanhSach = "
        SELECT
            ctkm.ma_chuong_trinh,
            ctkm.ten_chuong_trinh,
            ctkm.loai_giam_gia,
            ctkm.gia_tri_giam,
            ctkm.ngay_bat_dau,
            ctkm.ngay_ket_thuc,
            ctkm.trang_thai,

            COALESCE(thong_ke.tong_luot_ap_dung, 0)
                AS tong_luot_ap_dung,

            COALESCE(thong_ke.tong_don_hang_ap_dung, 0)
                AS tong_don_hang_ap_dung,

            COALESCE(thong_ke.tong_tien_giam, 0)
                AS tong_tien_giam

        FROM chuong_trinh_khuyen_mai ctkm

        LEFT JOIN (
            SELECT
                ma_chuong_trinh,
                COUNT(*) AS tong_luot_ap_dung,
                COUNT(DISTINCT ma_don_hang) AS tong_don_hang_ap_dung,
                SUM(so_tien_giam) AS tong_tien_giam

            FROM khuyen_mai_don_hang

            WHERE ngay_ap_dung >= :tu_ngay
              AND ngay_ap_dung < :den_ngay

            GROUP BY ma_chuong_trinh

        ) AS thong_ke
            ON thong_ke.ma_chuong_trinh = ctkm.ma_chuong_trinh

        ORDER BY
            tong_luot_ap_dung DESC,
            ctkm.ngay_tao DESC

        LIMIT 20
    ";

    $stmt = $pdo->prepare($sqlDanhSach);

    $stmt->execute([
        ':tu_ngay' => $tuNgaySQL,
        ':den_ngay' => $denNgaySQL
    ]);

    $danhSachKhuyenMai = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {

    error_log(
        'HPComputer Promotion Reports Error: ' .
        $exception->getMessage()
    );

    $loiTruyVan = 'Không thể tải dữ liệu báo cáo khuyến mãi. Vui lòng kiểm tra cơ sở dữ liệu.';
}

// -----------------------------------------------------
// 5. CAU HINH TRANG THAI
// -----------------------------------------------------

$trangThaiKhuyenMai = [
    'DRAFT' => [
        'label' => 'Bản nháp',
        'class' => 'bg-secondary'
    ],
    'ACTIVE' => [
        'label' => 'Đang hoạt động',
        'class' => 'bg-success'
    ],
    'INACTIVE' => [
        'label' => 'Ngừng hoạt động',
        'class' => 'bg-warning text-dark'
    ],
    'EXPIRED' => [
        'label' => 'Hết hạn',
        'class' => 'bg-dark'
    ],
    'CANCELLED' => [
        'label' => 'Đã hủy',
        'class' => 'bg-danger'
    ]
];

?>

<div class="container-fluid py-3">

    <!-- TIEU DE -->

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>
            <h4 class="fw-bold mb-1">
                <i class="bi bi-tags me-2"></i>
                Báo cáo thống kê khuyến mãi
            </h4>

            <p class="text-muted mb-0">
                Theo dõi hiệu quả sử dụng các chương trình khuyến mãi
            </p>
        </div>

        <a href="<?= $e(BASE_URL) ?>/reports.php"
           class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>
            Quay lại tổng quan
        </a>

    </div>


    <!-- BO LOC NGAY -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form method="GET" action="" class="row g-3 align-items-end">

                <div class="col-md-4">

                    <label for="tu_ngay" class="form-label fw-semibold">
                        Từ ngày
                    </label>

                    <input type="date"
                           class="form-control"
                           id="tu_ngay"
                           name="tu_ngay"
                           value="<?= $e($tuNgay) ?>"
                           required>

                </div>

                <div class="col-md-4">

                    <label for="den_ngay" class="form-label fw-semibold">
                        Đến ngày
                    </label>

                    <input type="date"
                           class="form-control"
                           id="den_ngay"
                           name="den_ngay"
                           value="<?= $e($denNgay) ?>"
                           required>

                </div>

                <div class="col-md-4 d-flex gap-2">

                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-funnel me-1"></i>
                        Lọc dữ liệu
                    </button>

                    <a href="<?= $e(BASE_URL) ?>/promotions_report.php"
                       class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>

                </div>

            </form>

        </div>

    </div>


    <?php if ($loiTruyVan !== null): ?>

        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= $e($loiTruyVan) ?>
        </div>

    <?php else: ?>


    <!-- CAC THE TONG QUAN -->

    <div class="row g-3 mb-4">

        <!-- TONG CHUONG TRINH -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>
                            <p class="text-muted mb-2">
                                Tổng chương trình
                            </p>

                            <h4 class="fw-bold mb-0">
                                <?= $e($formatNumber($thongKe['tong_chuong_trinh'])) ?>
                            </h4>
                        </div>

                        <i class="bi bi-tags text-primary fs-2"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- DANG HOAT DONG -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>
                            <p class="text-muted mb-2">
                                Đang hoạt động
                            </p>

                            <h4 class="fw-bold text-success mb-0">
                                <?= $e($formatNumber($thongKe['dang_hoat_dong'])) ?>
                            </h4>
                        </div>

                        <i class="bi bi-check-circle text-success fs-2"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- DON HANG AP DUNG -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>
                            <p class="text-muted mb-2">
                                Đơn hàng áp dụng
                            </p>

                            <h4 class="fw-bold text-primary mb-0">
                                <?= $e($formatNumber($thongKe['tong_don_hang_ap_dung'])) ?>
                            </h4>
                        </div>

                        <i class="bi bi-cart-check text-primary fs-2"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- TONG TIEN GIAM -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>
                            <p class="text-muted mb-2">
                                Tổng tiền giảm
                            </p>

                            <h4 class="fw-bold text-danger mb-0">
                                <?= $e($formatMoney($thongKe['tong_tien_giam'])) ?>
                            </h4>
                        </div>

                        <i class="bi bi-cash-coin text-danger fs-2"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- THONG KE TRANG THAI -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h6 class="fw-bold mb-0">
                <i class="bi bi-pie-chart me-2"></i>
                Thống kê trạng thái chương trình
            </h6>

            <small class="text-muted">
                Thống kê trạng thái hiện tại của toàn bộ chương trình khuyến mãi
            </small>

        </div>

        <div class="card-body">

            <div class="row g-3">

                <div class="col-6 col-md-4 col-xl">

                    <div class="border rounded p-3 h-100">
                        <small class="text-muted">Bản nháp</small>
                        <h4 class="fw-bold mb-0">
                            <?= $e($formatNumber($thongKe['ban_nhap'])) ?>
                        </h4>
                    </div>

                </div>

                <div class="col-6 col-md-4 col-xl">

                    <div class="border rounded p-3 h-100">
                        <small class="text-muted">Đang hoạt động</small>
                        <h4 class="fw-bold text-success mb-0">
                            <?= $e($formatNumber($thongKe['dang_hoat_dong'])) ?>
                        </h4>
                    </div>

                </div>

                <div class="col-6 col-md-4 col-xl">

                    <div class="border rounded p-3 h-100">
                        <small class="text-muted">Ngừng hoạt động</small>
                        <h4 class="fw-bold text-warning mb-0">
                            <?= $e($formatNumber($thongKe['ngung_hoat_dong'])) ?>
                        </h4>
                    </div>

                </div>

                <div class="col-6 col-md-4 col-xl">

                    <div class="border rounded p-3 h-100">
                        <small class="text-muted">Hết hạn</small>
                        <h4 class="fw-bold text-secondary mb-0">
                            <?= $e($formatNumber($thongKe['het_han'])) ?>
                        </h4>
                    </div>

                </div>

                <div class="col-6 col-md-4 col-xl">

                    <div class="border rounded p-3 h-100">
                        <small class="text-muted">Đã hủy</small>
                        <h4 class="fw-bold text-danger mb-0">
                            <?= $e($formatNumber($thongKe['da_huy'])) ?>
                        </h4>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- TOP CHUONG TRINH KHUYEN MAI -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h6 class="fw-bold mb-1">
                <i class="bi bi-trophy me-2"></i>
                Top 10 chương trình khuyến mãi được sử dụng nhiều nhất
            </h6>

            <small class="text-muted">
                Xếp hạng theo số lượt áp dụng trong khoảng thời gian đã chọn
            </small>

        </div>

        <div class="card-body">

            <?php if (empty($topKhuyenMai)): ?>

                <div class="text-center py-4">

                    <i class="bi bi-tags text-muted fs-1"></i>

                    <p class="text-muted mt-2 mb-0">
                        Chưa có lượt áp dụng khuyến mãi trong khoảng thời gian này.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>
                                <th style="width: 75px;">Xếp hạng</th>
                                <th>Chương trình</th>
                                <th>Loại giảm giá</th>
                                <th class="text-center">Lượt áp dụng</th>
                                <th class="text-center">Số đơn hàng</th>
                                <th class="text-end">Tổng tiền giảm</th>
                                <th>Trạng thái</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($topKhuyenMai as $index => $item): ?>

                                <?php
                                $status = $trangThaiKhuyenMai[$item['trang_thai']]
                                    ?? [
                                        'label' => $item['trang_thai'],
                                        'class' => 'bg-secondary'
                                    ];

                                if ($item['loai_giam_gia'] === 'PERCENT') {
                                    $loaiGiamGia = 'Giảm theo phần trăm';
                                    $giaTriGiam = number_format(
                                        (float) $item['gia_tri_giam'],
                                        0,
                                        ',',
                                        '.'
                                    ) . '%';
                                } else {
                                    $loaiGiamGia = 'Giảm số tiền cố định';
                                    $giaTriGiam = $formatMoney($item['gia_tri_giam']);
                                }
                                ?>

                                <tr>

                                    <td>

                                        <?php if ($index === 0): ?>
                                            <span class="badge bg-warning text-dark">#1</span>
                                        <?php elseif ($index === 1): ?>
                                            <span class="badge bg-secondary">#2</span>
                                        <?php elseif ($index === 2): ?>
                                            <span class="badge bg-secondary">#3</span>
                                        <?php else: ?>
                                            <span class="text-muted">#<?= $index + 1 ?></span>
                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <div class="fw-semibold">
                                            <?= $e($item['ten_chuong_trinh']) ?>
                                        </div>

                                        <small class="text-muted">
                                            Mã chương trình: <?= $e($item['ma_chuong_trinh']) ?>
                                        </small>

                                    </td>

                                    <td>

                                        <div><?= $e($loaiGiamGia) ?></div>

                                        <small class="text-muted">
                                            Giá trị cấu hình: <?= $e($giaTriGiam) ?>
                                        </small>

                                    </td>

                                    <td class="text-center fw-semibold">
                                        <?= $e($formatNumber($item['tong_luot_ap_dung'])) ?>
                                    </td>

                                    <td class="text-center">
                                        <?= $e($formatNumber($item['tong_don_hang_ap_dung'])) ?>
                                    </td>

                                    <td class="text-end fw-semibold text-danger">
                                        <?= $e($formatMoney($item['tong_tien_giam'])) ?>
                                    </td>

                                    <td>
                                        <span class="badge <?= $e($status['class']) ?>">
                                            <?= $e($status['label']) ?>
                                        </span>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- DANH SACH CHUONG TRINH -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h6 class="fw-bold mb-1">
                <i class="bi bi-list-ul me-2"></i>
                Danh sách chương trình khuyến mãi
            </h6>

            <small class="text-muted">
                Hiển thị tối đa 20 chương trình và số liệu áp dụng trong kỳ
            </small>

        </div>

        <div class="card-body">

            <?php if (empty($danhSachKhuyenMai)): ?>

                <div class="text-center py-4">

                    <i class="bi bi-inbox text-muted fs-1"></i>

                    <p class="text-muted mt-2 mb-0">
                        Chưa có chương trình khuyến mãi nào.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>
                                <th>Chương trình</th>
                                <th>Thời gian</th>
                                <th>Loại giảm giá</th>
                                <th>Trạng thái</th>
                                <th class="text-center">Lượt áp dụng</th>
                                <th class="text-end">Tiền giảm</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($danhSachKhuyenMai as $item): ?>

                                <?php
                                $status = $trangThaiKhuyenMai[$item['trang_thai']]
                                    ?? [
                                        'label' => $item['trang_thai'],
                                        'class' => 'bg-secondary'
                                    ];

                                if ($item['loai_giam_gia'] === 'PERCENT') {
                                    $giaTri = number_format(
                                        (float) $item['gia_tri_giam'],
                                        0,
                                        ',',
                                        '.'
                                    ) . '%';
                                } else {
                                    $giaTri = $formatMoney($item['gia_tri_giam']);
                                }
                                ?>

                                <tr>

                                    <td>

                                        <div class="fw-semibold">
                                            <?= $e($item['ten_chuong_trinh']) ?>
                                        </div>

                                        <small class="text-muted">
                                            Mã: <?= $e($item['ma_chuong_trinh']) ?>
                                        </small>

                                    </td>

                                    <td>

                                        <small>
                                            <?= $e($formatDate($item['ngay_bat_dau'])) ?>
                                            –
                                            <?= $e($formatDate($item['ngay_ket_thuc'])) ?>
                                        </small>

                                    </td>

                                    <td>
                                        <?= $e($giaTri) ?>
                                    </td>

                                    <td>
                                        <span class="badge <?= $e($status['class']) ?>">
                                            <?= $e($status['label']) ?>
                                        </span>
                                    </td>

                                    <td class="text-center fw-semibold">
                                        <?= $e($formatNumber($item['tong_luot_ap_dung'])) ?>
                                    </td>

                                    <td class="text-end text-danger fw-semibold">
                                        <?= $e($formatMoney($item['tong_tien_giam'])) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- GHI CHU -->

    <div class="alert alert-info border-0">

        <h6 class="fw-bold">
            <i class="bi bi-info-circle me-2"></i>
            Lưu ý về báo cáo khuyến mãi
        </h6>

        <ul class="mb-0">

            <li>
                Tổng số chương trình và trạng thái chương trình được thống kê
                trên toàn bộ bảng <strong>chuong_trinh_khuyen_mai</strong>,
                không phụ thuộc bộ lọc ngày.
            </li>

            <li>
                Lượt áp dụng và số đơn hàng áp dụng được lọc theo
                <strong>ngay_ap_dung</strong> trong bảng
                <strong>khuyen_mai_don_hang</strong>.
            </li>

            <li>
                Tổng tiền giảm được tính từ tổng trường
                <strong>so_tien_giam</strong> của các lượt áp dụng.
            </li>

            <li>
                Một đơn hàng có thể sử dụng nhiều chương trình khuyến mãi;
                vì vậy lượt áp dụng và số đơn hàng là hai chỉ số khác nhau.
            </li>

            <li>
                Trạng thái chương trình là trạng thái hiện tại trong database,
                không phải trạng thái lịch sử tại thời điểm đơn hàng áp dụng.
            </li>

        </ul>

    </div>

    <?php endif; ?>

</div>
