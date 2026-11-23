
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/reports/revenue.php
// CHUC NANG: BAO CAO DOANH THU CHI TIET
// =====================================================

require_once __DIR__ . '/../../includes/permission_check.php';

yeu_cau_quyen('reports.view');

// =====================================================
// 1. HAM HO TRO
// =====================================================

$e = static function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$formatMoney = static function ($value) {
    return number_format((float)$value, 0, ',', '.') . ' đ';
};

$formatDate = static function ($value, $withTime = false) {
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return '-';
    }

    return date($withTime ? 'd/m/Y H:i:s' : 'd/m/Y', $timestamp);
};

// =====================================================
// 2. BO LOC THOI GIAN
// Mac dinh: dau thang den hien tai
// =====================================================

$today = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');

$isValidDate = static function ($date) {
    if (!is_string($date)) {
        return false;
    }

    $dateObject = DateTime::createFromFormat('!Y-m-d', $date);

    return $dateObject !== false
        && $dateObject->format('Y-m-d') === $date;
};

$tuNgay = $_GET['tu_ngay'] ?? $firstDayOfMonth;
$denNgay = $_GET['den_ngay'] ?? $today;

if (!$isValidDate($tuNgay)) {
    $tuNgay = $firstDayOfMonth;
}

if (!$isValidDate($denNgay)) {
    $denNgay = $today;
}

if ($tuNgay > $denNgay) {
    $tuNgay = $firstDayOfMonth;
    $denNgay = $today;
}

$tuNgaySql = $tuNgay . ' 00:00:00';
$denNgaySql = date(
    'Y-m-d 00:00:00',
    strtotime($denNgay . ' +1 day')
);

// =====================================================
// 3. TONG QUAN DOANH THU
// =====================================================

$sqlTongQuan = "
    SELECT
        COALESCE(SUM(so_tien), 0) AS tong_doanh_thu,
        COUNT(*) AS tong_giao_dich,
        COALESCE(AVG(so_tien), 0) AS gia_tri_trung_binh
    FROM thanh_toan
    WHERE trang_thai = 'SUCCESS'
      AND ngay_thanh_toan IS NOT NULL
      AND ngay_thanh_toan >= :tu_ngay
      AND ngay_thanh_toan < :den_ngay
";

$stmt = $pdo->prepare($sqlTongQuan);

$stmt->execute([
    ':tu_ngay' => $tuNgaySql,
    ':den_ngay' => $denNgaySql
]);

$tongQuan = $stmt->fetch(PDO::FETCH_ASSOC);

$tongDoanhThu = (float)($tongQuan['tong_doanh_thu'] ?? 0);
$tongGiaoDich = (int)($tongQuan['tong_giao_dich'] ?? 0);
$giaTriTrungBinh = (float)($tongQuan['gia_tri_trung_binh'] ?? 0);

// =====================================================
// 4. DOANH THU THEO NGAY
// =====================================================

$sqlTheoNgay = "
    SELECT
        DATE(ngay_thanh_toan) AS ngay,
        COUNT(*) AS so_giao_dich,
        SUM(so_tien) AS doanh_thu
    FROM thanh_toan
    WHERE trang_thai = 'SUCCESS'
      AND ngay_thanh_toan IS NOT NULL
      AND ngay_thanh_toan >= :tu_ngay
      AND ngay_thanh_toan < :den_ngay
    GROUP BY DATE(ngay_thanh_toan)
    ORDER BY ngay DESC
";

$stmt = $pdo->prepare($sqlTheoNgay);

$stmt->execute([
    ':tu_ngay' => $tuNgaySql,
    ':den_ngay' => $denNgaySql
]);

$doanhThuTheoNgay = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// 5. DOANH THU THEO PHUONG THUC THANH TOAN
// =====================================================

$sqlTheoPhuongThuc = "
    SELECT
        phuong_thuc,
        COUNT(*) AS so_giao_dich,
        SUM(so_tien) AS doanh_thu
    FROM thanh_toan
    WHERE trang_thai = 'SUCCESS'
      AND ngay_thanh_toan IS NOT NULL
      AND ngay_thanh_toan >= :tu_ngay
      AND ngay_thanh_toan < :den_ngay
    GROUP BY phuong_thuc
    ORDER BY doanh_thu DESC
";

$stmt = $pdo->prepare($sqlTheoPhuongThuc);

$stmt->execute([
    ':tu_ngay' => $tuNgaySql,
    ':den_ngay' => $denNgaySql
]);

$doanhThuTheoPhuongThuc = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// 6. DANH SACH GIAO DICH THANH CONG
// =====================================================

$sqlGiaoDich = "
    SELECT
        tt.ma_thanh_toan,
        tt.ma_don_hang,
        tt.phuong_thuc,
        tt.so_tien,
        tt.ma_giao_dich,
        tt.ngay_thanh_toan,
        dh.ma_so_don_hang,
        dh.ten_nguoi_nhan
    FROM thanh_toan tt
    INNER JOIN don_hang dh
        ON dh.ma_don_hang = tt.ma_don_hang
    WHERE tt.trang_thai = 'SUCCESS'
      AND tt.ngay_thanh_toan IS NOT NULL
      AND tt.ngay_thanh_toan >= :tu_ngay
      AND tt.ngay_thanh_toan < :den_ngay
    ORDER BY tt.ngay_thanh_toan DESC, tt.ma_thanh_toan DESC
";

$stmt = $pdo->prepare($sqlGiaoDich);

$stmt->execute([
    ':tu_ngay' => $tuNgaySql,
    ':den_ngay' => $denNgaySql
]);

$giaoDich = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// 7. TEN PHUONG THUC THANH TOAN
// =====================================================

$tenPhuongThuc = [
    'CASH' => 'Tiền mặt',
    'BANK_TRANSFER' => 'Chuyển khoản',
    'COD' => 'Thanh toán khi nhận hàng'
];

?>

<div class="container-fluid py-3">

    <!-- =================================================
         8. TIEU DE
    ================================================== -->

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">

        <div>
            <h4 class="fw-bold mb-1">
                <i class="bi bi-cash-stack me-2"></i>
                Báo cáo doanh thu
            </h4>

            <p class="text-muted mb-0">
                Theo dõi doanh thu và các giao dịch thanh toán thành công
            </p>
        </div>

        <a href="<?= $e(BASE_URL) ?>/reports.php"
           class="btn btn-outline-secondary mt-2 mt-md-0">

            <i class="bi bi-arrow-left me-1"></i>
            Quay lại tổng quan

        </a>

    </div>


    <!-- =================================================
         9. BO LOC
    ================================================== -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form method="GET" action="<?= $e(BASE_URL) ?>/revenue.php">

                <div class="row g-3 align-items-end">

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            Từ ngày
                        </label>

                        <input
                            type="date"
                            name="tu_ngay"
                            class="form-control"
                            value="<?= $e($tuNgay) ?>"
                            max="<?= $e($today) ?>"
                            required
                        >

                    </div>

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            Đến ngày
                        </label>

                        <input
                            type="date"
                            name="den_ngay"
                            class="form-control"
                            value="<?= $e($denNgay) ?>"
                            max="<?= $e($today) ?>"
                            required
                        >

                    </div>

                    <div class="col-md-4">

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel me-1"></i>
                            Lọc doanh thu
                        </button>

                        <a href="<?= $e(BASE_URL) ?>/revenue.php"
                           class="btn btn-outline-secondary">

                            <i class="bi bi-arrow-counterclockwise me-1"></i>
                            Đặt lại

                        </a>

                    </div>

                </div>

            </form>

        </div>

    </div>


    <!-- =================================================
         10. CAC THE TONG QUAN
    ================================================== -->

    <div class="row g-3 mb-4">

        <div class="col-xl-4 col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-2">
                                Tổng doanh thu
                            </p>

                            <h4 class="fw-bold text-success mb-0">
                                <?= $formatMoney($tongDoanhThu) ?>
                            </h4>

                        </div>

                        <div class="fs-2 text-success">
                            <i class="bi bi-cash-stack"></i>
                        </div>

                    </div>

                    <small class="text-muted">
                        Tổng tiền các giao dịch SUCCESS
                    </small>

                </div>

            </div>

        </div>


        <div class="col-xl-4 col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-2">
                                Số giao dịch thành công
                            </p>

                            <h4 class="fw-bold text-primary mb-0">
                                <?= number_format($tongGiaoDich) ?>
                            </h4>

                        </div>

                        <div class="fs-2 text-primary">
                            <i class="bi bi-receipt"></i>
                        </div>

                    </div>

                    <small class="text-muted">
                        Số bản ghi thanh toán thành công
                    </small>

                </div>

            </div>

        </div>


        <div class="col-xl-4 col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="text-muted mb-2">
                                Giá trị giao dịch trung bình
                            </p>

                            <h4 class="fw-bold mb-0">
                                <?= $formatMoney($giaTriTrungBinh) ?>
                            </h4>

                        </div>

                        <div class="fs-2 text-warning">
                            <i class="bi bi-calculator"></i>
                        </div>

                    </div>

                    <small class="text-muted">
                        Tổng doanh thu / số giao dịch
                    </small>

                </div>

            </div>

        </div>

    </div>


    <!-- =================================================
         11. DOANH THU THEO PHUONG THUC
    ================================================== -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h6 class="fw-bold mb-0">
                <i class="bi bi-wallet2 me-2"></i>
                Doanh thu theo phương thức thanh toán
            </h6>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>
                            <th>Phương thức thanh toán</th>
                            <th class="text-center">Số giao dịch</th>
                            <th class="text-end">Doanh thu</th>
                            <th class="text-end">Tỷ trọng</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($doanhThuTheoPhuongThuc)): ?>

                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    Chưa có dữ liệu thanh toán trong kỳ.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($doanhThuTheoPhuongThuc as $item): ?>

                                <?php
                                $phuongThuc = $item['phuong_thuc'];
                                $doanhThu = (float)$item['doanh_thu'];

                                $tyTrong = $tongDoanhThu > 0
                                    ? ($doanhThu / $tongDoanhThu) * 100
                                    : 0;
                                ?>

                                <tr>

                                    <td class="fw-semibold">
                                        <?= $e(
                                            $tenPhuongThuc[$phuongThuc]
                                            ?? $phuongThuc
                                        ) ?>
                                    </td>

                                    <td class="text-center">
                                        <?= number_format((int)$item['so_giao_dich']) ?>
                                    </td>

                                    <td class="text-end fw-semibold">
                                        <?= $formatMoney($doanhThu) ?>
                                    </td>

                                    <td class="text-end">
                                        <?= number_format($tyTrong, 1) ?>%
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- =================================================
         12. DOANH THU THEO NGAY
    ================================================== -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h6 class="fw-bold mb-0">
                <i class="bi bi-calendar3 me-2"></i>
                Doanh thu theo ngày
            </h6>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>
                            <th>Ngày thanh toán</th>
                            <th class="text-center">Số giao dịch</th>
                            <th class="text-end">Doanh thu</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($doanhThuTheoNgay)): ?>

                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    Chưa có giao dịch thành công trong kỳ.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($doanhThuTheoNgay as $item): ?>

                                <tr>

                                    <td>
                                        <?= $e($formatDate($item['ngay'])) ?>
                                    </td>

                                    <td class="text-center">
                                        <?= number_format((int)$item['so_giao_dich']) ?>
                                    </td>

                                    <td class="text-end fw-semibold text-success">
                                        <?= $formatMoney($item['doanh_thu']) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- =================================================
         13. CHI TIET GIAO DICH
    ================================================== -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h6 class="fw-bold mb-0">
                <i class="bi bi-list-ul me-2"></i>
                Chi tiết giao dịch thành công
            </h6>

            <small class="text-muted">
                Có <?= number_format(count($giaoDich)) ?> giao dịch trong kỳ báo cáo
            </small>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>Mã giao dịch</th>
                            <th>Mã đơn hàng</th>
                            <th>Người nhận</th>
                            <th>Phương thức</th>
                            <th class="text-end">Số tiền</th>
                            <th>Ngày thanh toán</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($giaoDich)): ?>

                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Không có giao dịch thành công trong kỳ báo cáo.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($giaoDich as $item): ?>

                                <?php
                                $phuongThuc = $item['phuong_thuc'];
                                ?>

                                <tr>

                                    <td>
                                        <?= $e(
                                            $item['ma_giao_dich']
                                            ?: ('TT-' . $item['ma_thanh_toan'])
                                        ) ?>
                                    </td>

                                    <td class="fw-semibold">
                                        <?= $e($item['ma_so_don_hang']) ?>
                                    </td>

                                    <td>
                                        <?= $e($item['ten_nguoi_nhan']) ?>
                                    </td>

                                    <td>
                                        <?= $e(
                                            $tenPhuongThuc[$phuongThuc]
                                            ?? $phuongThuc
                                        ) ?>
                                    </td>

                                    <td class="text-end fw-semibold text-success">
                                        <?= $formatMoney($item['so_tien']) ?>
                                    </td>

                                    <td>
                                        <?= $e($formatDate(
                                            $item['ngay_thanh_toan'],
                                            true
                                        )) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- =================================================
         14. GHI CHU
    ================================================== -->

    <div class="alert alert-info border-0">

        <div class="d-flex">

            <i class="bi bi-info-circle-fill me-2 mt-1"></i>

            <div>

                <strong>Nguyên tắc thống kê doanh thu:</strong>

                <ul class="mb-0 mt-1">

                    <li>
                        Chỉ tính giao dịch có trạng thái
                        <code>SUCCESS</code>.
                    </li>

                    <li>
                        Thời gian thống kê dựa trên
                        <code>ngay_thanh_toan</code>, không dựa trên ngày đặt đơn.
                    </li>

                    <li>
                        Mỗi bản ghi thanh toán thành công được tính là
                        một giao dịch.
                    </li>

                    <li>
                        Chưa trừ các khoản hoàn tiền thực tế vì hệ thống
                        chưa có dữ liệu chi tiết xác nhận số tiền đã hoàn.
                    </li>

                </ul>

            </div>

        </div>

    </div>

</div>
