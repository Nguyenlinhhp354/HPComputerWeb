
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/reports/orders.php
// CHUC NANG: THONG KE DON HANG
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
    'tong_don' => 0,
    'cho_xac_nhan' => 0,
    'da_xac_nhan' => 0,
    'dang_chuan_bi' => 0,
    'dang_giao' => 0,
    'hoan_thanh' => 0,
    'da_huy' => 0,
    'gia_tri_hoan_thanh' => 0
];

$donTheoTrangThai = [];
$donGanDay = [];

$loiTruyVan = null;

// -----------------------------------------------------
// 4. TRUY VAN BAO CAO
// -----------------------------------------------------

try {

    // -------------------------------------------------
    // 4.1. TONG QUAN DON HANG
    // -------------------------------------------------

    $sqlThongKe = "
        SELECT
            COUNT(*) AS tong_don,

            SUM(
                CASE WHEN trang_thai_don_hang = 'PENDING'
                THEN 1 ELSE 0 END
            ) AS cho_xac_nhan,

            SUM(
                CASE WHEN trang_thai_don_hang = 'CONFIRMED'
                THEN 1 ELSE 0 END
            ) AS da_xac_nhan,

            SUM(
                CASE WHEN trang_thai_don_hang = 'PROCESSING'
                THEN 1 ELSE 0 END
            ) AS dang_chuan_bi,

            SUM(
                CASE WHEN trang_thai_don_hang = 'SHIPPING'
                THEN 1 ELSE 0 END
            ) AS dang_giao,

            SUM(
                CASE WHEN trang_thai_don_hang = 'COMPLETED'
                THEN 1 ELSE 0 END
            ) AS hoan_thanh,

            SUM(
                CASE WHEN trang_thai_don_hang = 'CANCELLED'
                THEN 1 ELSE 0 END
            ) AS da_huy,

            COALESCE(
                SUM(
                    CASE
                        WHEN trang_thai_don_hang = 'COMPLETED'
                        THEN tong_tien
                        ELSE 0
                    END
                ),
                0
            ) AS gia_tri_hoan_thanh

        FROM don_hang
        WHERE ngay_dat >= :tu_ngay
          AND ngay_dat < :den_ngay
    ";

    $stmt = $pdo->prepare($sqlThongKe);

    $stmt->execute([
        ':tu_ngay' => $tuNgaySQL,
        ':den_ngay' => $denNgaySQL
    ]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        foreach ($thongKe as $key => $value) {
            $thongKe[$key] = $result[$key] ?? $value;
        }
    }

    // Chuyen cac chi so dem ve integer
    foreach ([
        'tong_don',
        'cho_xac_nhan',
        'da_xac_nhan',
        'dang_chuan_bi',
        'dang_giao',
        'hoan_thanh',
        'da_huy'
    ] as $key) {
        $thongKe[$key] = (int) $thongKe[$key];
    }

    $thongKe['gia_tri_hoan_thanh'] =
        (float) $thongKe['gia_tri_hoan_thanh'];


    // -------------------------------------------------
    // 4.2. THONG KE THEO TRANG THAI
    // -------------------------------------------------

    $sqlTrangThai = "
        SELECT
            trang_thai_don_hang,
            COUNT(*) AS so_luong,
            COALESCE(SUM(tong_tien), 0) AS tong_gia_tri
        FROM don_hang
        WHERE ngay_dat >= :tu_ngay
          AND ngay_dat < :den_ngay
        GROUP BY trang_thai_don_hang
    ";

    $stmt = $pdo->prepare($sqlTrangThai);

    $stmt->execute([
        ':tu_ngay' => $tuNgaySQL,
        ':den_ngay' => $denNgaySQL
    ]);

    $donTheoTrangThai = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // -------------------------------------------------
    // 4.3. DANH SACH DON HANG
    // -------------------------------------------------

    $sqlDonHang = "
        SELECT
            ma_don_hang,
            ma_so_don_hang,
            ten_nguoi_nhan,
            sdt_nguoi_nhan,
            tong_tien,
            trang_thai_don_hang,
            trang_thai_thanh_toan,
            ngay_dat
        FROM don_hang
        WHERE ngay_dat >= :tu_ngay
          AND ngay_dat < :den_ngay
        ORDER BY ngay_dat DESC
        LIMIT 50
    ";

    $stmt = $pdo->prepare($sqlDonHang);

    $stmt->execute([
        ':tu_ngay' => $tuNgaySQL,
        ':den_ngay' => $denNgaySQL
    ]);

    $donGanDay = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {

    error_log(
        'HPComputer Order Reports Error: ' .
        $exception->getMessage()
    );

    $loiTruyVan = 'Không thể tải dữ liệu báo cáo đơn hàng. Vui lòng kiểm tra cơ sở dữ liệu.';
}

// -----------------------------------------------------
// 5. CAU HINH TRANG THAI
// -----------------------------------------------------

$trangThai = [
    'PENDING' => [
        'label' => 'Chờ xác nhận',
        'class' => 'bg-warning text-dark',
        'icon' => 'bi-hourglass-split'
    ],
    'CONFIRMED' => [
        'label' => 'Đã xác nhận',
        'class' => 'bg-info text-dark',
        'icon' => 'bi-check-circle'
    ],
    'PROCESSING' => [
        'label' => 'Đang chuẩn bị',
        'class' => 'bg-primary',
        'icon' => 'bi-box-seam'
    ],
    'SHIPPING' => [
        'label' => 'Đang giao hàng',
        'class' => 'bg-primary',
        'icon' => 'bi-truck'
    ],
    'COMPLETED' => [
        'label' => 'Hoàn thành',
        'class' => 'bg-success',
        'icon' => 'bi-check-circle-fill'
    ],
    'CANCELLED' => [
        'label' => 'Đã hủy',
        'class' => 'bg-danger',
        'icon' => 'bi-x-circle'
    ]
];

$trangThaiThanhToan = [
    'UNPAID' => [
        'label' => 'Chưa thanh toán',
        'class' => 'bg-warning text-dark'
    ],
    'PARTIAL' => [
        'label' => 'Thanh toán một phần',
        'class' => 'bg-info text-dark'
    ],
    'PAID' => [
        'label' => 'Đã thanh toán',
        'class' => 'bg-success'
    ],
    'REFUNDED' => [
        'label' => 'Đã hoàn tiền',
        'class' => 'bg-secondary'
    ]
];

// -----------------------------------------------------
// 6. TINH TY LE
// -----------------------------------------------------

$tyLeHoanThanh = $thongKe['tong_don'] > 0
    ? $thongKe['hoan_thanh'] / $thongKe['tong_don'] * 100
    : 0;

$tyLeDaHuy = $thongKe['tong_don'] > 0
    ? $thongKe['da_huy'] / $thongKe['tong_don'] * 100
    : 0;

?>

<div class="container-fluid py-3">

    <!-- TIEU DE -->

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>
            <h4 class="fw-bold mb-1">
                <i class="bi bi-receipt-cutoff me-2"></i>
                Báo cáo thống kê đơn hàng
            </h4>

            <p class="text-muted mb-0">
                Theo dõi tình hình xử lý và trạng thái đơn hàng
            </p>
        </div>

        <a href="<?= $e(BASE_URL) ?>/reports.php"
           class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>
            Quay lại tổng quan
        </a>

    </div>


    <!-- BO LOC -->

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

                    <a href="<?= $e(BASE_URL) ?>/orders_report.php"
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


    <!-- CAC THE THONG KE -->

    <div class="row g-3 mb-4">

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>
                            <p class="text-muted mb-2">Tổng đơn hàng</p>

                            <h4 class="fw-bold mb-0">
                                <?= $e($formatNumber($thongKe['tong_don'])) ?>
                            </h4>
                        </div>

                        <i class="bi bi-cart-check text-primary fs-2"></i>

                    </div>

                </div>
            </div>

        </div>


        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>
                            <p class="text-muted mb-2">Đơn hoàn thành</p>

                            <h4 class="fw-bold text-success mb-0">
                                <?= $e($formatNumber($thongKe['hoan_thanh'])) ?>
                            </h4>
                        </div>

                        <i class="bi bi-check-circle text-success fs-2"></i>

                    </div>

                </div>
            </div>

        </div>


        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>
                            <p class="text-muted mb-2">Đơn đang xử lý</p>

                            <h4 class="fw-bold text-primary mb-0">
                                <?= $e($formatNumber(
                                    $thongKe['da_xac_nhan'] +
                                    $thongKe['dang_chuan_bi'] +
                                    $thongKe['dang_giao']
                                )) ?>
                            </h4>
                        </div>

                        <i class="bi bi-truck text-primary fs-2"></i>

                    </div>

                </div>
            </div>

        </div>


        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>
                            <p class="text-muted mb-2">Đơn đã hủy</p>

                            <h4 class="fw-bold text-danger mb-0">
                                <?= $e($formatNumber($thongKe['da_huy'])) ?>
                            </h4>
                        </div>

                        <i class="bi bi-x-circle text-danger fs-2"></i>

                    </div>

                </div>
            </div>

        </div>

    </div>


    <!-- GIA TRI DON HOAN THANH -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

                <div>
                    <p class="text-muted mb-2">
                        Tổng giá trị đơn hàng hoàn thành
                    </p>

                    <h3 class="fw-bold text-success mb-0">
                        <?= $e($formatMoney($thongKe['gia_tri_hoan_thanh'])) ?>
                    </h3>

                    <small class="text-muted">
                        Chỉ tính tổng tiền của các đơn COMPLETED
                    </small>
                </div>

                <div class="rounded-circle bg-success bg-opacity-10 p-3">
                    <i class="bi bi-cash-stack text-success fs-2"></i>
                </div>

            </div>

        </div>

    </div>


    <!-- TY LE DON HANG -->

    <div class="row g-3 mb-4">

        <div class="col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <h6 class="fw-bold mb-3">
                        Tỷ lệ đơn hoàn thành
                    </h6>

                    <h3 class="fw-bold text-success">
                        <?= number_format($tyLeHoanThanh, 1, ',', '.') ?>%
                    </h3>

                    <div class="progress" style="height: 12px;">
                        <div class="progress-bar bg-success"
                             role="progressbar"
                             style="width: <?= min(100, max(0, $tyLeHoanThanh)) ?>%;">
                        </div>
                    </div>

                    <small class="text-muted mt-2 d-block">
                        <?= $e($formatNumber($thongKe['hoan_thanh'])) ?>
                        / <?= $e($formatNumber($thongKe['tong_don'])) ?>
                        đơn hàng
                    </small>

                </div>

            </div>

        </div>


        <div class="col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <h6 class="fw-bold mb-3">
                        Tỷ lệ đơn đã hủy
                    </h6>

                    <h3 class="fw-bold text-danger">
                        <?= number_format($tyLeDaHuy, 1, ',', '.') ?>%
                    </h3>

                    <div class="progress" style="height: 12px;">
                        <div class="progress-bar bg-danger"
                             role="progressbar"
                             style="width: <?= min(100, max(0, $tyLeDaHuy)) ?>%;">
                        </div>
                    </div>

                    <small class="text-muted mt-2 d-block">
                        <?= $e($formatNumber($thongKe['da_huy'])) ?>
                        / <?= $e($formatNumber($thongKe['tong_don'])) ?>
                        đơn hàng
                    </small>

                </div>

            </div>

        </div>

    </div>


    <!-- THONG KE THEO TRANG THAI -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h6 class="fw-bold mb-0">
                <i class="bi bi-bar-chart-line me-2"></i>
                Thống kê đơn hàng theo trạng thái
            </h6>

        </div>

        <div class="card-body">

            <?php if (empty($donTheoTrangThai)): ?>

                <div class="text-center py-4">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                    <p class="text-muted mt-2 mb-0">
                        Chưa có dữ liệu đơn hàng trong khoảng thời gian này.
                    </p>
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">
                            <tr>
                                <th>Trạng thái</th>
                                <th class="text-center">Số đơn hàng</th>
                                <th class="text-center">Tỷ lệ</th>
                                <th class="text-end">Tổng giá trị đơn</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($donTheoTrangThai as $item): ?>

                                <?php
                                $status = $trangThai[$item['trang_thai_don_hang']]
                                    ?? [
                                        'label' => $item['trang_thai_don_hang'],
                                        'class' => 'bg-secondary',
                                        'icon' => 'bi-question-circle'
                                    ];

                                $soLuong = (int) $item['so_luong'];

                                $tyLe = $thongKe['tong_don'] > 0
                                    ? $soLuong / $thongKe['tong_don'] * 100
                                    : 0;
                                ?>

                                <tr>

                                    <td>
                                        <span class="badge <?= $e($status['class']) ?>">
                                            <i class="bi <?= $e($status['icon']) ?> me-1"></i>
                                            <?= $e($status['label']) ?>
                                        </span>
                                    </td>

                                    <td class="text-center fw-semibold">
                                        <?= $e($formatNumber($soLuong)) ?>
                                    </td>

                                    <td class="text-center">
                                        <?= number_format($tyLe, 1, ',', '.') ?>%
                                    </td>

                                    <td class="text-end">
                                        <?= $e($formatMoney($item['tong_gia_tri'])) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- DANH SACH DON HANG -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h6 class="fw-bold mb-0">
                <i class="bi bi-list-ul me-2"></i>
                Danh sách đơn hàng
            </h6>

            <small class="text-muted">
                Hiển thị tối đa 50 đơn hàng mới nhất trong khoảng thời gian đã chọn
            </small>

        </div>

        <div class="card-body">

            <?php if (empty($donGanDay)): ?>

                <div class="text-center py-4">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                    <p class="text-muted mt-2 mb-0">
                        Không có đơn hàng trong khoảng thời gian này.
                    </p>
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">
                            <tr>
                                <th>Mã đơn hàng</th>
                                <th>Người nhận</th>
                                <th>Ngày đặt</th>
                                <th class="text-end">Tổng tiền</th>
                                <th>Trạng thái đơn</th>
                                <th>Thanh toán</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($donGanDay as $don): ?>

                                <?php
                                $statusDon = $trangThai[$don['trang_thai_don_hang']]
                                    ?? [
                                        'label' => $don['trang_thai_don_hang'],
                                        'class' => 'bg-secondary'
                                    ];

                                $statusPayment = $trangThaiThanhToan[$don['trang_thai_thanh_toan']]
                                    ?? [
                                        'label' => $don['trang_thai_thanh_toan'],
                                        'class' => 'bg-secondary'
                                    ];
                                ?>

                                <tr>

                                    <td class="fw-semibold">
                                        <?= $e($don['ma_so_don_hang']) ?>
                                    </td>

                                    <td>
                                        <div>
                                            <?= $e($don['ten_nguoi_nhan']) ?>
                                        </div>

                                        <small class="text-muted">
                                            <?= $e($don['sdt_nguoi_nhan']) ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?= $e(date(
                                            'd/m/Y H:i',
                                            strtotime($don['ngay_dat'])
                                        )) ?>
                                    </td>

                                    <td class="text-end fw-semibold">
                                        <?= $e($formatMoney($don['tong_tien'])) ?>
                                    </td>

                                    <td>
                                        <span class="badge <?= $e($statusDon['class']) ?>">
                                            <?= $e($statusDon['label']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge <?= $e($statusPayment['class']) ?>">
                                            <?= $e($statusPayment['label']) ?>
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


    <!-- GHI CHU NGHIEP VU -->

    <div class="alert alert-info border-0">

        <h6 class="fw-bold">
            <i class="bi bi-info-circle me-2"></i>
            Lưu ý về báo cáo đơn hàng
        </h6>

        <ul class="mb-0">

            <li>
                Các chỉ số đơn hàng được lọc theo thời điểm tạo đơn
                trong trường <strong>ngay_dat</strong>.
            </li>

            <li>
                Đơn hoàn thành là đơn có trạng thái
                <strong>COMPLETED</strong>.
            </li>

            <li>
                Tổng giá trị đơn hoàn thành không đồng nghĩa với doanh thu thực thu.
            </li>

            <li>
                Báo cáo doanh thu thực thu được tính riêng từ các giao dịch
                <strong>SUCCESS</strong> trong bảng thanh toán.
            </li>

        </ul>

    </div>

    <?php endif; ?>

</div>
