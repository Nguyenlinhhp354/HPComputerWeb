
<?php
/**
 * DASHBOARD - HP COMPUTER
 * Lấy dữ liệu thống kê trực tiếp từ MySQL
 */

// Kết nối database
require_once __DIR__ . '/../../config/database.php';

// Giá trị mặc định
$tongSanPham = 0;
$tongDonHang = 0;
$tongKhachHang = 0;
$tongDoanhThu = 0;

$donHangGanDay = [];
$dashboardError = false;

try {

    // 1. Tổng số sản phẩm
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM san_pham
    ");
    $tongSanPham = (int) $stmt->fetchColumn();

    // 2. Tổng số đơn hàng
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM don_hang
    ");
    $tongDonHang = (int) $stmt->fetchColumn();

    // 3. Tổng số khách hàng
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM khach_hang
    ");
    $tongKhachHang = (int) $stmt->fetchColumn();

    // 4. Tổng doanh thu từ đơn hàng đã thanh toán
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(tong_tien), 0)
        FROM don_hang
        WHERE trang_thai_thanh_toan = 'PAID'
    ");
    $tongDoanhThu = (float) $stmt->fetchColumn();

    // 5. Lấy 5 đơn hàng gần nhất
    $stmt = $pdo->query("
        SELECT
            ma_so_don_hang,
            ten_nguoi_nhan,
            ngay_dat,
            tong_tien,
            trang_thai_don_hang,
            trang_thai_thanh_toan
        FROM don_hang
        ORDER BY ngay_dat DESC
        LIMIT 5
    ");

    $donHangGanDay = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    // Ghi lỗi vào log, không hiển thị thông tin database
    error_log('Dashboard error: ' . $e->getMessage());

    $dashboardError = true;
}

// Hàm định dạng tiền Việt Nam
function dinhDangTien($tien)
{
    return number_format((float) $tien, 0, ',', '.') . ' ₫';
}

// Chuyển trạng thái đơn hàng sang tiếng Việt
function tenTrangThai($trangThai)
{
    $danhSach = [
        'PENDING'    => 'Chờ xác nhận',
        'CONFIRMED'  => 'Đã xác nhận',
        'PROCESSING' => 'Đang xử lý',
        'SHIPPING'   => 'Đang giao hàng',
        'COMPLETED'  => 'Hoàn thành',
        'CANCELLED'  => 'Đã hủy'
    ];

    return $danhSach[$trangThai] ?? $trangThai;
}
?>

<!-- TIÊU ĐỀ TRANG -->
<div class="page-heading">
    <div>
        <h1>Tổng quan hệ thống</h1>
        <p>Chào mừng bạn đến với hệ thống quản lý HP COMPUTER.</p>
    </div>

    <div class="page-heading-date">
        <i class="bi bi-calendar-event"></i>
        <?= date('d/m/Y') ?>
    </div>
</div>

<?php if ($dashboardError): ?>
    <div class="alert alert-danger">
        Không thể tải dữ liệu Dashboard.
        Vui lòng kiểm tra kết nối cơ sở dữ liệu.
    </div>
<?php endif; ?>

<!-- THỐNG KÊ -->
<div class="row g-3 mb-4">

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon stat-blue">
                <i class="bi bi-box-seam"></i>
            </div>

            <div>
                <p>Tổng sản phẩm</p>
                <h3><?= number_format($tongSanPham) ?></h3>
                <small>Dữ liệu từ MySQL</small>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon stat-green">
                <i class="bi bi-receipt"></i>
            </div>

            <div>
                <p>Tổng đơn hàng</p>
                <h3><?= number_format($tongDonHang) ?></h3>
                <small>Dữ liệu từ MySQL</small>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon stat-orange">
                <i class="bi bi-people"></i>
            </div>

            <div>
                <p>Khách hàng</p>
                <h3><?= number_format($tongKhachHang) ?></h3>
                <small>Dữ liệu từ MySQL</small>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon stat-purple">
                <i class="bi bi-currency-dollar"></i>
            </div>

            <div>
                <p>Doanh thu</p>
                <h3><?= dinhDangTien($tongDoanhThu) ?></h3>
                <small>Đơn hàng đã thanh toán</small>
            </div>
        </div>
    </div>

</div>

<!-- NỘI DUNG DASHBOARD -->
<div class="row g-3">

    <div class="col-12 col-xl-8">
        <div class="content-card">

            <div class="content-card-header">
                <div>
                    <h5>Hoạt động kinh doanh</h5>
                    <p>Tổng quan tình hình hoạt động cửa hàng</p>
                </div>

                <span class="status-label">
                    Dữ liệu MySQL
                </span>
            </div>

            <div class="empty-chart">
                <i class="bi bi-bar-chart-line"></i>

                <h6>Biểu đồ thống kê</h6>

                <p>
                    Chúng ta sẽ bổ sung biểu đồ doanh thu
                    sau khi hoàn thiện dữ liệu mẫu.
                </p>
            </div>

        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="content-card">

            <div class="content-card-header">
                <div>
                    <h5>Thông tin hệ thống</h5>
                    <p>Thông tin cơ bản</p>
                </div>
            </div>

            <div class="system-info">

                <div>
                    <span>Website</span>
                    <strong>HP COMPUTER</strong>
                </div>

                <div>
                    <span>Ngôn ngữ</span>
                    <strong>PHP</strong>
                </div>

                <div>
                    <span>Cơ sở dữ liệu</span>
                    <strong>MySQL</strong>
                </div>

                <div>
                    <span>Giao diện</span>
                    <strong>Bootstrap 5</strong>
                </div>

                <div>
                    <span>Ngày hệ thống</span>
                    <strong><?= date('d/m/Y') ?></strong>
                </div>

            </div>
        </div>
    </div>

</div>

<!-- ĐƠN HÀNG GẦN ĐÂY -->
<div class="content-card mt-3">

    <div class="content-card-header">
        <div>
            <h5>Đơn hàng gần đây</h5>
            <p>5 đơn hàng mới nhất</p>
        </div>
    </div>

    <div class="table-responsive">

        <table class="table dashboard-table align-middle mb-0">

            <thead>
                <tr>
                    <th>Mã đơn hàng</th>
                    <th>Khách hàng</th>
                    <th>Ngày đặt</th>
                    <th>Tổng tiền</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>

            <tbody>

                <?php if (empty($donHangGanDay)): ?>

                    <tr>
                        <td colspan="5"
                            class="text-center py-4 text-muted">

                            <?= $dashboardError
                                ? 'Không thể tải danh sách đơn hàng.'
                                : 'Chưa có đơn hàng để hiển thị.' ?>

                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($donHangGanDay as $donHang): ?>

                        <tr>
                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $donHang['ma_so_don_hang'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $donHang['ten_nguoi_nhan'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= date(
                                    'd/m/Y H:i',
                                    strtotime($donHang['ngay_dat'])
                                ) ?>
                            </td>

                            <td>
                                <?= dinhDangTien($donHang['tong_tien']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    tenTrangThai($donHang['trang_thai_don_hang']),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>
</div>
