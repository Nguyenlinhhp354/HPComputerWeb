
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/reports/products.php
// CHUC NANG: BAO CAO THONG KE SAN PHAM
// =====================================================

require_once __DIR__ . '/../../includes/permission_check.php';

yeu_cau_quyen('reports.view');

// -----------------------------------------------------
// 1. KIEM TRA DATABASE
// -----------------------------------------------------

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Lỗi: Không thể kết nối cơ sở dữ liệu.');
}

// -----------------------------------------------------
// 2. HAM HO TRO
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
// 3. BO LOC NGAY
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
// 4. KHOI TAO DU LIEU
// -----------------------------------------------------

$thongKe = [
    'tong_san_pham' => 0,
    'san_pham_dang_kinh_doanh' => 0,
    'san_pham_ngung_kinh_doanh' => 0,
    'san_pham_co_phat_sinh_ban' => 0,
    'tong_so_luong_ban' => 0,
    'tong_gia_tri_ban' => 0
];

$topSanPham = [];
$sanPhamChuaBan = [];

$loiTruyVan = null;

// -----------------------------------------------------
// 5. TRUY VAN DU LIEU
// -----------------------------------------------------

try {

    // -------------------------------------------------
    // 5.1. TONG QUAN SAN PHAM
    // -------------------------------------------------

    $sqlTongSanPham = "
        SELECT
            COUNT(*) AS tong_san_pham,

            SUM(
                CASE
                    WHEN trang_thai = 'ACTIVE'
                    THEN 1 ELSE 0
                END
            ) AS san_pham_dang_kinh_doanh,

            SUM(
                CASE
                    WHEN trang_thai = 'INACTIVE'
                    THEN 1 ELSE 0
                END
            ) AS san_pham_ngung_kinh_doanh

        FROM san_pham
    ";

    $stmt = $pdo->query($sqlTongSanPham);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $thongKe['tong_san_pham'] =
            (int) ($result['tong_san_pham'] ?? 0);

        $thongKe['san_pham_dang_kinh_doanh'] =
            (int) ($result['san_pham_dang_kinh_doanh'] ?? 0);

        $thongKe['san_pham_ngung_kinh_doanh'] =
            (int) ($result['san_pham_ngung_kinh_doanh'] ?? 0);
    }


    // -------------------------------------------------
    // 5.2. THONG KE SAN PHAM DA BAN
    // Chi tinh don COMPLETED
    // -------------------------------------------------

    $sqlThongKeBan = "
        SELECT
            COUNT(DISTINCT ct.ma_san_pham) AS san_pham_co_phat_sinh_ban,
            COALESCE(SUM(ct.so_luong), 0) AS tong_so_luong_ban,
            COALESCE(SUM(ct.thanh_tien), 0) AS tong_gia_tri_ban

        FROM chi_tiet_don_hang ct

        INNER JOIN don_hang dh
            ON dh.ma_don_hang = ct.ma_don_hang

        WHERE dh.trang_thai_don_hang = 'COMPLETED'
          AND dh.ngay_dat >= :tu_ngay
          AND dh.ngay_dat < :den_ngay
    ";

    $stmt = $pdo->prepare($sqlThongKeBan);

    $stmt->execute([
        ':tu_ngay' => $tuNgaySQL,
        ':den_ngay' => $denNgaySQL
    ]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $thongKe['san_pham_co_phat_sinh_ban'] =
            (int) ($result['san_pham_co_phat_sinh_ban'] ?? 0);

        $thongKe['tong_so_luong_ban'] =
            (int) ($result['tong_so_luong_ban'] ?? 0);

        $thongKe['tong_gia_tri_ban'] =
            (float) ($result['tong_gia_tri_ban'] ?? 0);
    }


    // -------------------------------------------------
    // 5.3. TOP 10 SAN PHAM BAN CHAY
    // -------------------------------------------------

    $sqlTopSanPham = "
        SELECT
            sp.ma_san_pham,
            sp.ma_sku,
            sp.ten_san_pham,
            sp.gia_ban,
            sp.trang_thai,

            COUNT(DISTINCT dh.ma_don_hang) AS so_don_hang,

            SUM(ct.so_luong) AS tong_so_luong_ban,

            SUM(ct.thanh_tien) AS tong_gia_tri_ban

        FROM chi_tiet_don_hang ct

        INNER JOIN don_hang dh
            ON dh.ma_don_hang = ct.ma_don_hang

        INNER JOIN san_pham sp
            ON sp.ma_san_pham = ct.ma_san_pham

        WHERE dh.trang_thai_don_hang = 'COMPLETED'
          AND dh.ngay_dat >= :tu_ngay
          AND dh.ngay_dat < :den_ngay

        GROUP BY
            sp.ma_san_pham,
            sp.ma_sku,
            sp.ten_san_pham,
            sp.gia_ban,
            sp.trang_thai

        ORDER BY
            tong_so_luong_ban DESC,
            tong_gia_tri_ban DESC

        LIMIT 10
    ";

    $stmt = $pdo->prepare($sqlTopSanPham);

    $stmt->execute([
        ':tu_ngay' => $tuNgaySQL,
        ':den_ngay' => $denNgaySQL
    ]);

    $topSanPham = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // -------------------------------------------------
    // 5.4. SAN PHAM CHUA PHAT SINH BAN
    // Chi xet san pham ACTIVE
    // -------------------------------------------------

    $sqlSanPhamChuaBan = "
        SELECT
            sp.ma_san_pham,
            sp.ma_sku,
            sp.ten_san_pham,
            sp.gia_ban,
            sp.trang_thai

        FROM san_pham sp

        LEFT JOIN (
            SELECT DISTINCT
                ct.ma_san_pham

            FROM chi_tiet_don_hang ct

            INNER JOIN don_hang dh
                ON dh.ma_don_hang = ct.ma_don_hang

            WHERE dh.trang_thai_don_hang = 'COMPLETED'
              AND dh.ngay_dat >= :tu_ngay
              AND dh.ngay_dat < :den_ngay

        ) AS da_ban
            ON da_ban.ma_san_pham = sp.ma_san_pham

        WHERE sp.trang_thai = 'ACTIVE'
          AND da_ban.ma_san_pham IS NULL

        ORDER BY sp.ten_san_pham ASC

        LIMIT 20
    ";

    $stmt = $pdo->prepare($sqlSanPhamChuaBan);

    $stmt->execute([
        ':tu_ngay' => $tuNgaySQL,
        ':den_ngay' => $denNgaySQL
    ]);

    $sanPhamChuaBan = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {

    error_log(
        'HPComputer Product Reports Error: ' .
        $exception->getMessage()
    );

    $loiTruyVan = 'Không thể tải dữ liệu báo cáo sản phẩm. Vui lòng kiểm tra cơ sở dữ liệu.';
}

?>

<div class="container-fluid py-3">

    <!-- TIEU DE -->

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>
            <h4 class="fw-bold mb-1">
                <i class="bi bi-box-seam me-2"></i>
                Báo cáo thống kê sản phẩm
            </h4>

            <p class="text-muted mb-0">
                Theo dõi tình hình tiêu thụ sản phẩm của HP COMPUTER
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

                    <a href="<?= $e(BASE_URL) ?>/products_report.php"
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

        <!-- TONG SAN PHAM -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>
                            <p class="text-muted mb-2">
                                Tổng sản phẩm
                            </p>

                            <h4 class="fw-bold mb-0">
                                <?= $e($formatNumber($thongKe['tong_san_pham'])) ?>
                            </h4>
                        </div>

                        <i class="bi bi-boxes text-primary fs-2"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- SAN PHAM ACTIVE -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>
                            <p class="text-muted mb-2">
                                Đang kinh doanh
                            </p>

                            <h4 class="fw-bold text-success mb-0">
                                <?= $e($formatNumber($thongKe['san_pham_dang_kinh_doanh'])) ?>
                            </h4>
                        </div>

                        <i class="bi bi-check-circle text-success fs-2"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- SO LUONG BAN -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>
                            <p class="text-muted mb-2">
                                Tổng số lượng bán
                            </p>

                            <h4 class="fw-bold text-primary mb-0">
                                <?= $e($formatNumber($thongKe['tong_so_luong_ban'])) ?>
                            </h4>
                        </div>

                        <i class="bi bi-cart-check text-primary fs-2"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- GIA TRI BAN -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>
                            <p class="text-muted mb-2">
                                Tổng giá trị hàng bán
                            </p>

                            <h4 class="fw-bold text-success mb-0">
                                <?= $e($formatMoney($thongKe['tong_gia_tri_ban'])) ?>
                            </h4>
                        </div>

                        <i class="bi bi-cash-stack text-success fs-2"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- THONG KE BO SUNG -->

    <div class="row g-3 mb-4">

        <div class="col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <p class="text-muted mb-2">
                                Sản phẩm có phát sinh bán
                            </p>

                            <h3 class="fw-bold text-primary mb-0">
                                <?= $e($formatNumber($thongKe['san_pham_co_phat_sinh_ban'])) ?>
                            </h3>
                        </div>

                        <i class="bi bi-bag-check text-primary fs-1"></i>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <p class="text-muted mb-2">
                                Sản phẩm ngừng kinh doanh
                            </p>

                            <h3 class="fw-bold text-secondary mb-0">
                                <?= $e($formatNumber($thongKe['san_pham_ngung_kinh_doanh'])) ?>
                            </h3>
                        </div>

                        <i class="bi bi-pause-circle text-secondary fs-1"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- TOP 10 SAN PHAM BAN CHAY -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h6 class="fw-bold mb-1">
                <i class="bi bi-trophy me-2"></i>
                Top 10 sản phẩm bán chạy
            </h6>

            <small class="text-muted">
                Xếp hạng theo số lượng bán trong các đơn hàng đã hoàn thành
            </small>

        </div>

        <div class="card-body">

            <?php if (empty($topSanPham)): ?>

                <div class="text-center py-4">

                    <i class="bi bi-box-seam text-muted fs-1"></i>

                    <p class="text-muted mt-2 mb-0">
                        Chưa có sản phẩm bán ra trong khoảng thời gian này.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>
                                <th style="width: 75px;">Xếp hạng</th>
                                <th>Mã SKU</th>
                                <th>Tên sản phẩm</th>
                                <th class="text-end">Giá bán hiện tại</th>
                                <th class="text-center">Số đơn</th>
                                <th class="text-center">Số lượng bán</th>
                                <th class="text-end">Giá trị hàng bán</th>
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
                                        <small class="fw-semibold">
                                            <?= $e($sanPham['ma_sku']) ?>
                                        </small>
                                    </td>

                                    <td>

                                        <div class="fw-semibold">
                                            <?= $e($sanPham['ten_san_pham']) ?>
                                        </div>

                                        <small class="text-muted">
                                            Mã SP: <?= $e($sanPham['ma_san_pham']) ?>
                                        </small>

                                    </td>

                                    <td class="text-end">
                                        <?= $e($formatMoney($sanPham['gia_ban'])) ?>
                                    </td>

                                    <td class="text-center">
                                        <?= $e($formatNumber($sanPham['so_don_hang'])) ?>
                                    </td>

                                    <td class="text-center fw-bold text-primary">
                                        <?= $e($formatNumber($sanPham['tong_so_luong_ban'])) ?>
                                    </td>

                                    <td class="text-end fw-semibold text-success">
                                        <?= $e($formatMoney($sanPham['tong_gia_tri_ban'])) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                        <tfoot class="table-light">

                            <tr>
                                <th colspan="5">
                                    Tổng cộng các sản phẩm trong bảng
                                </th>

                                <th class="text-center">
                                    <?= $e($formatNumber(
                                        array_sum(array_column($topSanPham, 'tong_so_luong_ban'))
                                    )) ?>
                                </th>

                                <th class="text-end">
                                    <?= $e($formatMoney(
                                        array_sum(array_column($topSanPham, 'tong_gia_tri_ban'))
                                    )) ?>
                                </th>
                            </tr>

                        </tfoot>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- SAN PHAM CHUA BAN -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h6 class="fw-bold mb-1">
                <i class="bi bi-exclamation-circle me-2"></i>
                Sản phẩm chưa phát sinh bán
            </h6>

            <small class="text-muted">
                Sản phẩm đang kinh doanh nhưng chưa xuất hiện trong đơn hàng hoàn thành của kỳ báo cáo
            </small>

        </div>

        <div class="card-body">

            <?php if (empty($sanPhamChuaBan)): ?>

                <div class="text-center py-4">

                    <i class="bi bi-check-circle text-success fs-1"></i>

                    <p class="text-muted mt-2 mb-0">
                        Không có sản phẩm nào thuộc nhóm này.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>
                                <th>Mã SKU</th>
                                <th>Tên sản phẩm</th>
                                <th class="text-end">Giá bán</th>
                                <th>Trạng thái</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($sanPhamChuaBan as $sanPham): ?>

                                <tr>

                                    <td>
                                        <small class="fw-semibold">
                                            <?= $e($sanPham['ma_sku']) ?>
                                        </small>
                                    </td>

                                    <td>
                                        <div class="fw-semibold">
                                            <?= $e($sanPham['ten_san_pham']) ?>
                                        </div>

                                        <small class="text-muted">
                                            Mã SP: <?= $e($sanPham['ma_san_pham']) ?>
                                        </small>
                                    </td>

                                    <td class="text-end">
                                        <?= $e($formatMoney($sanPham['gia_ban'])) ?>
                                    </td>

                                    <td>
                                        <span class="badge bg-success">
                                            Đang kinh doanh
                                        </span>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <small class="text-muted d-block mt-3">
                    Danh sách hiển thị tối đa 20 sản phẩm.
                </small>

            <?php endif; ?>

        </div>

    </div>


    <!-- GHI CHU -->

    <div class="alert alert-info border-0">

        <h6 class="fw-bold">
            <i class="bi bi-info-circle me-2"></i>
            Lưu ý về báo cáo sản phẩm
        </h6>

        <ul class="mb-0">

            <li>
                Sản phẩm bán chạy được thống kê từ chi tiết các đơn hàng
                có trạng thái <strong>COMPLETED</strong>.
            </li>

            <li>
                Số lượng bán là tổng số lượng sản phẩm trong các đơn hoàn thành
                thuộc khoảng thời gian đã chọn.
            </li>

            <li>
                Giá trị hàng bán được tính từ trường
                <strong>thanh_tien</strong> của bảng chi tiết đơn hàng.
            </li>

            <li>
                Giá bán hiển thị là giá bán hiện tại trong bảng sản phẩm,
                không nhất thiết là giá bán tại thời điểm đặt đơn.
            </li>

            <li>
                Sản phẩm chưa phát sinh bán chỉ xét các sản phẩm đang kinh doanh
                và chưa xuất hiện trong đơn hàng hoàn thành của kỳ báo cáo.
            </li>

            <li>
                Báo cáo này chưa phản ánh tồn kho thực tế.
                Không sử dụng số lượng bán để kết luận số lượng hàng còn trong kho.
            </li>

        </ul>

    </div>

    <?php endif; ?>

</div>
