
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/reports/index.php
// CHUC NANG: TONG QUAN BAO CAO THONG KE
// =====================================================

// -----------------------------------------------------
// 1. KIEM TRA QUYEN
// -----------------------------------------------------

require_once __DIR__ . '/../../includes/permission_check.php';

yeu_cau_quyen('reports.view');

// -----------------------------------------------------
// 2. KIEM TRA DATABASE
// -----------------------------------------------------

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Lỗi: Không thể kết nối cơ sở dữ liệu.');
}

// -----------------------------------------------------
// 3. HAM HO TRO
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
// 4. LAY VA KIEM TRA BO LOC NGAY
// -----------------------------------------------------

$today = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');

$tuNgay = $_GET['tu_ngay'] ?? $firstDayOfMonth;
$denNgay = $_GET['den_ngay'] ?? $today;

$dateValidator = static function ($date) {
    if (!is_string($date)) {
        return false;
    }

    $parsed = DateTime::createFromFormat('!Y-m-d', $date);

    return $parsed && $parsed->format('Y-m-d') === $date;
};

if (!$dateValidator($tuNgay)) {
    $tuNgay = $firstDayOfMonth;
}

if (!$dateValidator($denNgay)) {
    $denNgay = $today;
}

if ($tuNgay > $denNgay) {
    [$tuNgay, $denNgay] = [$denNgay, $tuNgay];
}

$tuNgaySQL = $tuNgay . ' 00:00:00';
$denNgaySQL = date('Y-m-d', strtotime($denNgay . ' +1 day')) . ' 00:00:00';

// -----------------------------------------------------
// 5. KHOI TAO DU LIEU
// -----------------------------------------------------

$tongDoanhThu = 0;
$tongGiaoDichThanhCong = 0;
$tongDonHang = 0;
$tongDonHoanThanh = 0;
$tongDonDaHuy = 0;

$doanhThuTheoNgay = [];
$topSanPham = [];
$donHangGanDay = [];

$loiTruyVan = null;

// -----------------------------------------------------
// 6. TRUY VAN DU LIEU BAO CAO
// -----------------------------------------------------

try {

    // -------------------------------------------------
    // 6.1. DOANH THU THUC THU
    // Chi tinh giao dich SUCCESS
    // -------------------------------------------------

    $sqlDoanhThu = "
        SELECT
            COALESCE(SUM(so_tien), 0) AS tong_doanh_thu,
            COUNT(*) AS tong_giao_dich
        FROM thanh_toan
        WHERE trang_thai = 'SUCCESS'
          AND ngay_thanh_toan IS NOT NULL
          AND ngay_thanh_toan >= :tu_ngay
          AND ngay_thanh_toan < :den_ngay
    ";

    $stmt = $pdo->prepare($sqlDoanhThu);
    $stmt->execute([
        ':tu_ngay' => $tuNgaySQL,
        ':den_ngay' => $denNgaySQL
    ]);

    $doanhThu = $stmt->fetch(PDO::FETCH_ASSOC);

    $tongDoanhThu = (float) ($doanhThu['tong_doanh_thu'] ?? 0);
    $tongGiaoDichThanhCong = (int) ($doanhThu['tong_giao_dich'] ?? 0);


    // -------------------------------------------------
    // 6.2. THONG KE DON HANG
    // Tinh theo ngay dat don
    // -------------------------------------------------

    $sqlDonHang = "
        SELECT
            COUNT(*) AS tong_don,
            SUM(
                CASE
                    WHEN trang_thai_don_hang = 'COMPLETED'
                    THEN 1 ELSE 0
                END
            ) AS don_hoan_thanh,
            SUM(
                CASE
                    WHEN trang_thai_don_hang = 'CANCELLED'
                    THEN 1 ELSE 0
                END
            ) AS don_da_huy
        FROM don_hang
        WHERE ngay_dat >= :tu_ngay
          AND ngay_dat < :den_ngay
    ";

    $stmt = $pdo->prepare($sqlDonHang);
    $stmt->execute([
        ':tu_ngay' => $tuNgaySQL,
        ':den_ngay' => $denNgaySQL
    ]);

    $thongKeDonHang = $stmt->fetch(PDO::FETCH_ASSOC);

    $tongDonHang = (int) ($thongKeDonHang['tong_don'] ?? 0);
    $tongDonHoanThanh = (int) ($thongKeDonHang['don_hoan_thanh'] ?? 0);
    $tongDonDaHuy = (int) ($thongKeDonHang['don_da_huy'] ?? 0);


    // -------------------------------------------------
    // 6.3. DOANH THU THEO NGAY
    // Chi tinh giao dich SUCCESS
    // -------------------------------------------------

    $sqlDoanhThuTheoNgay = "
        SELECT
            DATE(ngay_thanh_toan) AS ngay,
            SUM(so_tien) AS doanh_thu,
            COUNT(*) AS so_giao_dich
        FROM thanh_toan
        WHERE trang_thai = 'SUCCESS'
          AND ngay_thanh_toan IS NOT NULL
          AND ngay_thanh_toan >= :tu_ngay
          AND ngay_thanh_toan < :den_ngay
        GROUP BY DATE(ngay_thanh_toan)
        ORDER BY ngay DESC
    ";

    $stmt = $pdo->prepare($sqlDoanhThuTheoNgay);
    $stmt->execute([
        ':tu_ngay' => $tuNgaySQL,
        ':den_ngay' => $denNgaySQL
    ]);

    $doanhThuTheoNgay = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // -------------------------------------------------
    // 6.4. TOP 5 SAN PHAM TRONG DON HOAN THANH
    // Thong ke theo ngay dat don
    // -------------------------------------------------

    $sqlTopSanPham = "
        SELECT
            ct.ma_san_pham,
            ct.ten_san_pham,
            SUM(ct.so_luong) AS tong_so_luong,
            SUM(ct.thanh_tien) AS tong_gia_tri
        FROM chi_tiet_don_hang ct
        INNER JOIN don_hang dh
            ON dh.ma_don_hang = ct.ma_don_hang
        WHERE dh.trang_thai_don_hang = 'COMPLETED'
          AND dh.ngay_dat >= :tu_ngay
          AND dh.ngay_dat < :den_ngay
        GROUP BY
            ct.ma_san_pham,
            ct.ten_san_pham
        ORDER BY tong_so_luong DESC
        LIMIT 5
    ";

    $stmt = $pdo->prepare($sqlTopSanPham);
    $stmt->execute([
        ':tu_ngay' => $tuNgaySQL,
        ':den_ngay' => $denNgaySQL
    ]);

    $topSanPham = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // -------------------------------------------------
    // 6.5. DON HANG GAN DAY
    // -------------------------------------------------

    $sqlDonHangGanDay = "
        SELECT
            ma_don_hang,
            ma_so_don_hang,
            ten_nguoi_nhan,
            tong_tien,
            trang_thai_don_hang,
            trang_thai_thanh_toan,
            ngay_dat
        FROM don_hang
        WHERE ngay_dat >= :tu_ngay
          AND ngay_dat < :den_ngay
        ORDER BY ngay_dat DESC
        LIMIT 10
    ";

    $stmt = $pdo->prepare($sqlDonHangGanDay);
    $stmt->execute([
        ':tu_ngay' => $tuNgaySQL,
        ':den_ngay' => $denNgaySQL
    ]);

    $donHangGanDay = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {

    error_log('HPComputer Reports Error: ' . $exception->getMessage());

    $loiTruyVan = 'Không thể tải dữ liệu báo cáo. Vui lòng kiểm tra cơ sở dữ liệu.';
}

// -----------------------------------------------------
// 7. HAM HIEN THI TRANG THAI
// -----------------------------------------------------

$trangThaiDonHang = [
    'PENDING' => [
        'label' => 'Chờ xác nhận',
        'class' => 'bg-warning text-dark'
    ],
    'CONFIRMED' => [
        'label' => 'Đã xác nhận',
        'class' => 'bg-info text-dark'
    ],
    'PROCESSING' => [
        'label' => 'Đang chuẩn bị',
        'class' => 'bg-primary'
    ],
    'SHIPPING' => [
        'label' => 'Đang giao',
        'class' => 'bg-primary'
    ],
    'COMPLETED' => [
        'label' => 'Hoàn thành',
        'class' => 'bg-success'
    ],
    'CANCELLED' => [
        'label' => 'Đã hủy',
        'class' => 'bg-danger'
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

?>

<!-- =================================================
     GIAO DIEN BAO CAO THONG KE
================================================== -->

<div class="container-fluid py-3">

    <!-- TIEU DE -->

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>
            <h4 class="fw-bold mb-1">
                <i class="bi bi-bar-chart-line me-2"></i>
                Báo cáo thống kê
            </h4>

            <p class="text-muted mb-0">
                Tổng quan hoạt động kinh doanh của HP COMPUTER
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">

            <a href="<?= $e(BASE_URL) ?>/revenue.php"
                class="btn btn-success">
                <i class="bi bi-cash-stack me-1"></i>
                Chi tiết doanh thu
            </a>

            <a href="<?= $e(BASE_URL) ?>/orders_report.php"
                class="btn btn-primary">
                <i class="bi bi-receipt me-1"></i>
                Thống kê đơn hàng
            </a>

            <a href="<?= $e(BASE_URL) ?>/products_report.php"
                class="btn btn-info">
                <i class="bi bi-box-seam me-1"></i>
                Thống kê sản phẩm
            </a>

            <a href="<?= $e(BASE_URL) ?>/promotions_report.php"
                class="btn btn-warning">
                <i class="bi bi-tags me-1"></i>
                Thống kê khuyến mãi
            </a>
        </div>

    </div>


    <!-- BO LOC NGAY -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form method="GET"
                  action=""
                  class="row g-3 align-items-end">

                <div class="col-md-4 col-lg-3">

                    <label for="tu_ngay" class="form-label fw-semibold">
                        Từ ngày
                    </label>

                    <input type="date"
                           name="tu_ngay"
                           id="tu_ngay"
                           class="form-control"
                           value="<?= $e($tuNgay) ?>"
                           required>

                </div>

                <div class="col-md-4 col-lg-3">

                    <label for="den_ngay" class="form-label fw-semibold">
                        Đến ngày
                    </label>

                    <input type="date"
                           name="den_ngay"
                           id="den_ngay"
                           class="form-control"
                           value="<?= $e($denNgay) ?>"
                           required>

                </div>

                <div class="col-md-4 col-lg-3">

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i>
                        Lọc báo cáo
                    </button>

                </div>

                <div class="col-md-12 col-lg-3">

                    <a href="<?= $e(BASE_URL) ?>/reports.php"
                       class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>
                        Đặt lại bộ lọc
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

        <!-- DOANH THU -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <p class="text-muted mb-2">
                                Doanh thu thực thu
                            </p>

                            <h4 class="fw-bold text-success mb-1">
                                <?= $e($formatMoney($tongDoanhThu)) ?>
                            </h4>

                            <small class="text-muted">
                                Giao dịch thành công
                            </small>
                        </div>

                        <div class="rounded-circle bg-success bg-opacity-10 p-3">
                            <i class="bi bi-cash-stack text-success fs-4"></i>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- SO GIAO DICH -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <p class="text-muted mb-2">
                                Giao dịch thành công
                            </p>

                            <h4 class="fw-bold text-primary mb-1">
                                <?= $e($formatNumber($tongGiaoDichThanhCong)) ?>
                            </h4>

                            <small class="text-muted">
                                Số giao dịch thanh toán
                            </small>
                        </div>

                        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                            <i class="bi bi-receipt text-primary fs-4"></i>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- TONG DON HANG -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <p class="text-muted mb-2">
                                Tổng đơn hàng
                            </p>

                            <h4 class="fw-bold text-dark mb-1">
                                <?= $e($formatNumber($tongDonHang)) ?>
                            </h4>

                            <small class="text-muted">
                                Đơn được tạo trong kỳ
                            </small>
                        </div>

                        <div class="rounded-circle bg-info bg-opacity-10 p-3">
                            <i class="bi bi-cart-check text-info fs-4"></i>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- DON HOAN THANH -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <p class="text-muted mb-2">
                                Đơn hoàn thành
                            </p>

                            <h4 class="fw-bold text-success mb-1">
                                <?= $e($formatNumber($tongDonHoanThanh)) ?>
                            </h4>

                            <small class="text-muted">
                                Đơn có trạng thái COMPLETED
                            </small>
                        </div>

                        <div class="rounded-circle bg-success bg-opacity-10 p-3">
                            <i class="bi bi-check-circle text-success fs-4"></i>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- THONG KE DON HANG -->

    <div class="row g-3 mb-4">

        <div class="col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <h6 class="text-muted mb-2">
                                Đơn hàng đã hủy
                            </h6>

                            <h3 class="fw-bold text-danger mb-0">
                                <?= $e($formatNumber($tongDonDaHuy)) ?>
                            </h3>
                        </div>

                        <i class="bi bi-x-circle text-danger fs-1"></i>

                    </div>

                </div>

            </div>

        </div>

        <div class="col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <h6 class="text-muted mb-2">
                        Tỷ lệ đơn hoàn thành
                    </h6>

                    <?php
                    $tyLeHoanThanh = $tongDonHang > 0
                        ? ($tongDonHoanThanh / $tongDonHang) * 100
                        : 0;
                    ?>

                    <h3 class="fw-bold text-primary mb-2">
                        <?= number_format($tyLeHoanThanh, 1, ',', '.') ?>%
                    </h3>

                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-primary"
                             role="progressbar"
                             style="width: <?= min(100, max(0, $tyLeHoanThanh)) ?>%;"
                             aria-valuenow="<?= $e($tyLeHoanThanh) ?>"
                             aria-valuemin="0"
                             aria-valuemax="100">
                        </div>
                    </div>

                    <small class="text-muted mt-2 d-block">
                        Tính trên tổng số đơn hàng được tạo trong kỳ
                    </small>

                </div>

            </div>

        </div>

    </div>


    <!-- DOANH THU THEO NGAY -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h6 class="fw-bold mb-0">
                <i class="bi bi-calendar3 me-2"></i>
                Doanh thu theo ngày
            </h6>

        </div>

        <div class="card-body">

            <?php if (empty($doanhThuTheoNgay)): ?>

                <div class="text-center py-4">

                    <i class="bi bi-bar-chart text-muted fs-1"></i>

                    <p class="text-muted mt-2 mb-0">
                        Chưa có giao dịch thành công trong khoảng thời gian này.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">
                            <tr>
                                <th>Ngày</th>
                                <th class="text-center">Số giao dịch</th>
                                <th class="text-end">Doanh thu</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($doanhThuTheoNgay as $item): ?>

                                <tr>

                                    <td>
                                        <?= $e(date('d/m/Y', strtotime($item['ngay']))) ?>
                                    </td>

                                    <td class="text-center">
                                        <?= $e($formatNumber($item['so_giao_dich'])) ?>
                                    </td>

                                    <td class="text-end fw-semibold text-success">
                                        <?= $e($formatMoney($item['doanh_thu'])) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                        <tfoot class="table-light">

                            <tr>
                                <th colspan="2">
                                    Tổng doanh thu
                                </th>

                                <th class="text-end text-success">
                                    <?= $e($formatMoney($tongDoanhThu)) ?>
                                </th>
                            </tr>

                        </tfoot>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- TOP SAN PHAM -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h6 class="fw-bold mb-0">
                <i class="bi bi-trophy me-2"></i>
                Top 5 sản phẩm bán ra
            </h6>

            <small class="text-muted">
                Thống kê số lượng sản phẩm trong các đơn hàng đã hoàn thành
            </small>

        </div>

        <div class="card-body">

            <?php if (empty($topSanPham)): ?>

                <div class="text-center py-4">

                    <i class="bi bi-box-seam text-muted fs-1"></i>

                    <p class="text-muted mt-2 mb-0">
                        Chưa có dữ liệu sản phẩm trong kỳ báo cáo.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">
                            <tr>
                                <th style="width: 70px;">Xếp hạng</th>
                                <th>Sản phẩm</th>
                                <th class="text-center">Số lượng bán</th>
                                <th class="text-end">Giá trị đơn hàng</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($topSanPham as $index => $sanPham): ?>

                                <tr>

                                    <td>
                                        <?php if ($index === 0): ?>
                                            <span class="badge bg-warning text-dark">
                                                #1
                                            </span>
                                        <?php elseif ($index === 1): ?>
                                            <span class="badge bg-secondary">
                                                #2
                                            </span>
                                        <?php elseif ($index === 2): ?>
                                            <span class="badge bg-secondary">
                                                #3
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                #<?= $index + 1 ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="fw-semibold">
                                            <?= $e($sanPham['ten_san_pham']) ?>
                                        </div>

                                        <small class="text-muted">
                                            Mã SP: <?= $e($sanPham['ma_san_pham']) ?>
                                        </small>
                                    </td>

                                    <td class="text-center fw-semibold">
                                        <?= $e($formatNumber($sanPham['tong_so_luong'])) ?>
                                    </td>

                                    <td class="text-end">
                                        <?= $e($formatMoney($sanPham['tong_gia_tri'])) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- DON HANG GAN DAY -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h6 class="fw-bold mb-0">
                <i class="bi bi-clock-history me-2"></i>
                Đơn hàng gần đây
            </h6>

        </div>

        <div class="card-body">

            <?php if (empty($donHangGanDay)): ?>

                <div class="text-center py-4">

                    <i class="bi bi-inbox text-muted fs-1"></i>

                    <p class="text-muted mt-2 mb-0">
                        Chưa có đơn hàng trong khoảng thời gian này.
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

                            <?php foreach ($donHangGanDay as $donHang): ?>

                                <?php
                                $statusDon = $trangThaiDonHang[$donHang['trang_thai_don_hang']]
                                    ?? [
                                        'label' => $donHang['trang_thai_don_hang'],
                                        'class' => 'bg-secondary'
                                    ];

                                $statusThanhToan = $trangThaiThanhToan[$donHang['trang_thai_thanh_toan']]
                                    ?? [
                                        'label' => $donHang['trang_thai_thanh_toan'],
                                        'class' => 'bg-secondary'
                                    ];
                                ?>

                                <tr>

                                    <td class="fw-semibold">
                                        <?= $e($donHang['ma_so_don_hang']) ?>
                                    </td>

                                    <td>
                                        <?= $e($donHang['ten_nguoi_nhan']) ?>
                                    </td>

                                    <td>
                                        <?= $e(date(
                                            'd/m/Y H:i',
                                            strtotime($donHang['ngay_dat'])
                                        )) ?>
                                    </td>

                                    <td class="text-end fw-semibold">
                                        <?= $e($formatMoney($donHang['tong_tien'])) ?>
                                    </td>

                                    <td>
                                        <span class="badge <?= $e($statusDon['class']) ?>">
                                            <?= $e($statusDon['label']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge <?= $e($statusThanhToan['class']) ?>">
                                            <?= $e($statusThanhToan['label']) ?>
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


    <!-- GHI CHU -->

    <div class="alert alert-info border-0 mb-4">

        <h6 class="fw-bold">
            <i class="bi bi-info-circle me-2"></i>
            Lưu ý về số liệu báo cáo
        </h6>

        <ul class="mb-0">

            <li>
                Doanh thu thực thu chỉ tính các giao dịch có trạng thái
                <strong>SUCCESS</strong> và có thời điểm thanh toán hợp lệ.
            </li>

            <li>
                Đơn hàng hoàn thành được thống kê riêng theo trạng thái
                <strong>COMPLETED</strong>, không đồng nghĩa với đã thanh toán.
            </li>

            <li>
                Top sản phẩm được tính từ các đơn hàng đã hoàn thành,
                không phải doanh thu thực thu của từng sản phẩm.
            </li>

            <li>
                Báo cáo hiện chưa trừ các khoản hoàn tiền thực tế.
                Cần bổ sung dữ liệu hoàn tiền trước khi tính doanh thu ròng.
            </li>

            <li>
                Các chỉ số đơn hàng được lọc theo ngày đặt đơn;
                doanh thu được lọc theo ngày thanh toán thành công.
            </li>

        </ul>

    </div>

    <?php endif; ?>

</div>
