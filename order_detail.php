
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/customers/order_detail.php
// CHUC NANG: CHI TIET DON HANG KHACH HANG
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

// =====================================================
// 1. KIEM TRA DANG NHAP
// =====================================================

if (
    empty($_SESSION['ma_nguoi_dung']) ||
    (int)($_SESSION['ma_vai_tro'] ?? 0) !== 6
) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$userId = (int)$_SESSION['ma_nguoi_dung'];

// =====================================================
// 2. HAM HO TRO
// =====================================================

function customer_order_detail_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function customer_order_detail_money($value)
{
    return number_format((float)$value, 0, ',', '.') . 'đ';
}

function customer_order_detail_status($status)
{
    $statuses = [
        'PENDING' => ['label' => 'Chờ xác nhận', 'class' => 'warning'],
        'CONFIRMED' => ['label' => 'Đã xác nhận', 'class' => 'primary'],
        'PROCESSING' => ['label' => 'Đang xử lý', 'class' => 'info'],
        'SHIPPING' => ['label' => 'Đang giao hàng', 'class' => 'primary'],
        'COMPLETED' => ['label' => 'Hoàn thành', 'class' => 'success'],
        'CANCELLED' => ['label' => 'Đã hủy', 'class' => 'danger']
    ];

    return $statuses[$status] ?? [
        'label' => 'Không xác định',
        'class' => 'secondary'
    ];
}

function customer_order_detail_payment_status($status)
{
    $statuses = [
        'UNPAID' => ['label' => 'Chưa thanh toán', 'class' => 'danger'],
        'PARTIAL' => ['label' => 'Thanh toán một phần', 'class' => 'warning'],
        'PAID' => ['label' => 'Đã thanh toán', 'class' => 'success'],
        'REFUNDED' => ['label' => 'Đã hoàn tiền', 'class' => 'secondary']
    ];

    return $statuses[$status] ?? [
        'label' => 'Không xác định',
        'class' => 'secondary'
    ];
}

function customer_order_detail_payment_method($method)
{
    $methods = [
        'CASH' => 'Thanh toán tiền mặt tại cửa hàng',
        'BANK_TRANSFER' => 'Chuyển khoản ngân hàng',
        'COD' => 'Thanh toán khi nhận hàng (COD)'
    ];

    return $methods[$method] ?? 'Không xác định';
}

// =====================================================
// 3. KIEM TRA TAI KHOAN
// =====================================================

$userStmt = $pdo->prepare("
    SELECT ma_nguoi_dung
    FROM nguoi_dung
    WHERE ma_nguoi_dung = :user_id
      AND ma_vai_tro = 6
      AND trang_thai = 'ACTIVE'
    LIMIT 1
");

$userStmt->execute([
    ':user_id' => $userId
]);

if (!$userStmt->fetchColumn()) {
    unset(
        $_SESSION['ma_nguoi_dung'],
        $_SESSION['ma_vai_tro'],
        $_SESSION['ten_dang_nhap'],
        $_SESSION['ho_ten'],
        $_SESSION['email'],
        $_SESSION['ten_vai_tro']
    );

    header('Location: ' . BASE_URL . '/login.php?error=account');
    exit;
}

// =====================================================
// 4. LAY MA KHACH HANG
// =====================================================

$customerStmt = $pdo->prepare("
    SELECT ma_khach_hang
    FROM khach_hang
    WHERE ma_nguoi_dung = :user_id
    LIMIT 1
");

$customerStmt->execute([
    ':user_id' => $userId
]);

$customerId = $customerStmt->fetchColumn();

if ($customerId === false) {
    http_response_code(404);
    $customerId = 0;
} else {
    $customerId = (int)$customerId;
}

// =====================================================
// 5. LAY MA DON HANG
// =====================================================

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId || $orderId <= 0 || $customerId <= 0) {
    http_response_code(404);
    $orderId = 0;
}

// =====================================================
// 6. LAY THONG TIN DON HANG
// =====================================================

$order = null;
$orderItems = [];
$orderPayments = [];
$orderHistory = [];

if ($orderId > 0) {

    $orderStmt = $pdo->prepare("
        SELECT
            dh.ma_don_hang,
            dh.ma_so_don_hang,
            dh.ngay_dat,
            dh.ten_nguoi_nhan,
            dh.sdt_nguoi_nhan,
            dh.dia_chi_giao_hang,
            dh.tam_tinh,
            dh.tien_giam_gia,
            dh.phi_van_chuyen,
            dh.tong_tien,
            dh.trang_thai_don_hang,
            dh.trang_thai_thanh_toan,
            dh.ghi_chu,
            dh.ngay_cap_nhat
        FROM don_hang dh
        WHERE dh.ma_don_hang = :order_id
          AND dh.ma_khach_hang = :customer_id
        LIMIT 1
    ");

    $orderStmt->execute([
        ':order_id' => $orderId,
        ':customer_id' => $customerId
    ]);

    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    // Chi lay du lieu neu don hang thuoc khach hang hien tai
    if ($order) {

        // ---------------------------------------------
        // CHI TIET SAN PHAM
        // ---------------------------------------------

        $itemStmt = $pdo->prepare("
            SELECT
                ma_chi_tiet_don_hang,
                ma_san_pham,
                ten_san_pham,
                so_luong,
                don_gia,
                tien_giam_gia,
                thanh_tien,
                thoi_han_bao_hanh
            FROM chi_tiet_don_hang
            WHERE ma_don_hang = :order_id
            ORDER BY ma_chi_tiet_don_hang ASC
        ");

        $itemStmt->execute([
            ':order_id' => $orderId
        ]);

        $orderItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        // ---------------------------------------------
        // THONG TIN THANH TOAN
        // ---------------------------------------------

        $paymentStmt = $pdo->prepare("
            SELECT
                ma_thanh_toan,
                phuong_thuc,
                so_tien,
                ma_giao_dich,
                ngay_thanh_toan,
                trang_thai,
                ghi_chu
            FROM thanh_toan
            WHERE ma_don_hang = :order_id
            ORDER BY ma_thanh_toan DESC
        ");

        $paymentStmt->execute([
            ':order_id' => $orderId
        ]);

        $orderPayments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

        // ---------------------------------------------
        // LICH SU TRANG THAI
        // ---------------------------------------------

        $historyStmt = $pdo->prepare("
            SELECT
                trang_thai_cu,
                trang_thai_moi,
                ly_do_thay_doi,
                ngay_thay_doi,
                ghi_chu
            FROM lich_su_trang_thai_don_hang
            WHERE ma_don_hang = :order_id
            ORDER BY ngay_thay_doi ASC, ma_lich_su_trang_thai ASC
        ");

        $historyStmt->execute([
            ':order_id' => $orderId
        ]);

        $orderHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// =====================================================
// 7. GIAO DIEN
// =====================================================

$pageTitle = $order
    ? 'Chi tiết đơn hàng ' . $order['ma_so_don_hang']
    : 'Không tìm thấy đơn hàng';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

?>

<style>
.customer-order-detail-page {
    background: #f5f7fa;
    min-height: 600px;
    padding: 35px 0 60px;
}

.order-detail-title {
    font-size: 27px;
    font-weight: 800;
    color: #10243e;
}

.order-detail-card {
    background: #fff;
    border: 1px solid #e9edf2;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
}

.order-detail-card-title {
    color: #10243e;
    font-size: 18px;
    font-weight: 800;
    margin-bottom: 20px;
}

.order-detail-code {
    color: #0878d1;
    font-weight: 800;
    font-size: 18px;
}

.order-detail-meta {
    color: #7d8997;
    font-size: 13px;
}

.order-detail-product {
    display: flex;
    align-items: center;
    gap: 14px;
}

.order-detail-product-icon {
    width: 65px;
    height: 65px;
    border-radius: 9px;
    background: #f2f5f9;
    display: flex;
    justify-content: center;
    align-items: center;
    color: #8192a7;
    font-size: 30px;
    flex-shrink: 0;
}

.order-detail-product-name {
    font-size: 14px;
    font-weight: 700;
    color: #26374b;
}

.order-detail-product-meta {
    font-size: 12px;
    color: #8994a2;
    margin-top: 4px;
}

.order-detail-price {
    color: #dc3545;
    font-weight: 800;
    white-space: nowrap;
}

.order-detail-summary-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 13px;
    font-size: 14px;
}

.order-detail-total {
    border-top: 1px solid #e9edf2;
    padding-top: 17px;
    margin-top: 17px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 800;
}

.order-detail-total strong {
    color: #dc3545;
    font-size: 23px;
}

.order-info-item {
    margin-bottom: 15px;
}

.order-info-label {
    color: #8994a2;
    font-size: 12px;
    margin-bottom: 5px;
}

.order-info-value {
    color: #26374b;
    font-weight: 600;
    font-size: 14px;
    overflow-wrap: anywhere;
}

.order-history {
    position: relative;
    padding-left: 28px;
}

.order-history-item {
    position: relative;
    padding-bottom: 24px;
    border-left: 2px solid #e5eaf0;
    padding-left: 22px;
}

.order-history-item:last-child {
    border-left-color: transparent;
    padding-bottom: 0;
}

.order-history-dot {
    position: absolute;
    width: 13px;
    height: 13px;
    border-radius: 50%;
    background: #0878d1;
    left: -7.5px;
    top: 3px;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #cfe5f8;
}

.order-history-title {
    font-weight: 700;
    font-size: 14px;
    color: #26374b;
}

.order-history-date {
    color: #8994a2;
    font-size: 12px;
    margin-top: 5px;
}

.order-history-note {
    color: #6c7888;
    font-size: 13px;
    margin-top: 6px;
}

.order-not-found {
    background: #fff;
    border-radius: 12px;
    padding: 65px 20px;
    text-align: center;
}

@media (max-width: 767px) {
    .order-detail-card {
        padding: 17px;
    }

    .order-detail-title {
        font-size: 23px;
    }
}
</style>

<div class="customer-order-detail-page">

    <div class="container">

        <?php if (!$order): ?>

            <div class="order-not-found">

                <i class="bi bi-receipt display-2 text-secondary"></i>

                <h4 class="fw-bold mt-3">
                    Không tìm thấy đơn hàng
                </h4>

                <p class="text-muted">
                    Đơn hàng không tồn tại hoặc không thuộc tài khoản của bạn.
                </p>

                <a href="<?= BASE_URL ?>/modules/customers/orders.php"
                   class="btn btn-primary">

                    <i class="bi bi-arrow-left me-1"></i>
                    Quay lại danh sách đơn hàng

                </a>

            </div>

        <?php else: ?>

            <?php
            $orderStatus = customer_order_detail_status(
                $order['trang_thai_don_hang']
            );

            $paymentStatus = customer_order_detail_payment_status(
                $order['trang_thai_thanh_toan']
            );
            ?>

            <!-- TIEU DE -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

                <div>

                    <h2 class="order-detail-title mb-2">
                        Chi tiết đơn hàng
                    </h2>

                    <div class="order-detail-meta">
                        Mã đơn hàng:
                        <span class="order-detail-code">
                            <?= customer_order_detail_escape($order['ma_so_don_hang']) ?>
                        </span>
                    </div>

                </div>

                <a href="<?= BASE_URL ?>/modules/customers/orders.php"
                   class="btn btn-outline-secondary">

                    <i class="bi bi-arrow-left me-1"></i>
                    Danh sách đơn hàng

                </a>

            </div>

            <!-- TRANG THAI -->
            <div class="order-detail-card">

                <div class="row g-3 align-items-center">

                    <div class="col-md-4">

                        <div class="order-info-label">
                            Ngày đặt hàng
                        </div>

                        <div class="order-info-value">
                            <?= date('d/m/Y H:i:s', strtotime($order['ngay_dat'])) ?>
                        </div>

                    </div>

                    <div class="col-md-4">

                        <div class="order-info-label">
                            Trạng thái đơn hàng
                        </div>

                        <span class="badge text-bg-<?= $orderStatus['class'] ?> fs-6">
                            <?= customer_order_detail_escape($orderStatus['label']) ?>
                        </span>

                    </div>

                    <div class="col-md-4">

                        <div class="order-info-label">
                            Trạng thái thanh toán
                        </div>

                        <span class="badge text-bg-<?= $paymentStatus['class'] ?> fs-6">
                            <?= customer_order_detail_escape($paymentStatus['label']) ?>
                        </span>

                    </div>

                </div>

            </div>

            <div class="row g-4">

                <!-- COT TRAI -->
                <div class="col-lg-8">

                    <!-- SAN PHAM -->
                    <div class="order-detail-card">

                        <h4 class="order-detail-card-title">
                            <i class="bi bi-bag-check me-2"></i>
                            Sản phẩm trong đơn hàng
                        </h4>

                        <?php if (empty($orderItems)): ?>

                            <p class="text-muted">
                                Không có thông tin sản phẩm.
                            </p>

                        <?php else: ?>

                            <?php foreach ($orderItems as $item): ?>

                                <div class="border-top py-3">

                                    <div class="row align-items-center g-3">

                                        <div class="col-md-6">

                                            <div class="order-detail-product">

                                                <div class="order-detail-product-icon">
                                                    <i class="bi bi-laptop"></i>
                                                </div>

                                                <div>

                                                    <div class="order-detail-product-name">
                                                        <?= customer_order_detail_escape($item['ten_san_pham']) ?>
                                                    </div>

                                                    <div class="order-detail-product-meta">
                                                        Mã sản phẩm:
                                                        <?= (int)$item['ma_san_pham'] ?>
                                                    </div>

                                                    <div class="order-detail-product-meta">
                                                        Bảo hành:
                                                        <?= (int)$item['thoi_han_bao_hanh'] ?> tháng
                                                    </div>

                                                </div>

                                            </div>

                                        </div>

                                        <div class="col-4 col-md-2">

                                            <div class="order-detail-product-meta">
                                                Số lượng
                                            </div>

                                            <strong>
                                                <?= (int)$item['so_luong'] ?>
                                            </strong>

                                        </div>

                                        <div class="col-8 col-md-4 text-md-end">

                                            <div class="order-detail-product-meta">
                                                Đơn giá
                                            </div>

                                            <div>
                                                <?= customer_order_detail_money($item['don_gia']) ?>
                                            </div>

                                            <?php if ((float)$item['tien_giam_gia'] > 0): ?>

                                                <div class="small text-success">
                                                    Giảm:
                                                    -<?= customer_order_detail_money($item['tien_giam_gia']) ?>
                                                </div>

                                            <?php endif; ?>

                                            <div class="order-detail-price mt-1">
                                                <?= customer_order_detail_money($item['thanh_tien']) ?>
                                            </div>

                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>

                    <!-- LICH SU -->
                    <div class="order-detail-card">

                        <h4 class="order-detail-card-title">
                            <i class="bi bi-clock-history me-2"></i>
                            Lịch sử đơn hàng
                        </h4>

                        <?php if (empty($orderHistory)): ?>

                            <p class="text-muted">
                                Chưa có lịch sử thay đổi trạng thái.
                            </p>

                        <?php else: ?>

                            <div class="order-history">

                                <?php foreach ($orderHistory as $history): ?>

                                    <?php
                                    $historyStatus = customer_order_detail_status(
                                        $history['trang_thai_moi']
                                    );
                                    ?>

                                    <div class="order-history-item">

                                        <span class="order-history-dot"></span>

                                        <div class="order-history-title">

                                            <?= customer_order_detail_escape($historyStatus['label']) ?>

                                        </div>

                                        <div class="order-history-date">

                                            <?= date('d/m/Y H:i:s', strtotime($history['ngay_thay_doi'])) ?>

                                        </div>

                                        <?php if (!empty($history['ly_do_thay_doi'])): ?>

                                            <div class="order-history-note">
                                                <?= customer_order_detail_escape($history['ly_do_thay_doi']) ?>
                                            </div>

                                        <?php endif; ?>

                                        <?php if (!empty($history['ghi_chu'])): ?>

                                            <div class="order-history-note">
                                                <?= customer_order_detail_escape($history['ghi_chu']) ?>
                                            </div>

                                        <?php endif; ?>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

                <!-- COT PHAI -->
                <div class="col-lg-4">

                    <!-- THONG TIN NHAN HANG -->
                    <div class="order-detail-card">

                        <h4 class="order-detail-card-title">
                            <i class="bi bi-truck me-2"></i>
                            Thông tin giao hàng
                        </h4>

                        <div class="order-info-item">

                            <div class="order-info-label">
                                Người nhận
                            </div>

                            <div class="order-info-value">
                                <?= customer_order_detail_escape($order['ten_nguoi_nhan']) ?>
                            </div>

                        </div>

                        <div class="order-info-item">

                            <div class="order-info-label">
                                Số điện thoại
                            </div>

                            <div class="order-info-value">
                                <?= customer_order_detail_escape($order['sdt_nguoi_nhan']) ?>
                            </div>

                        </div>

                        <div class="order-info-item mb-0">

                            <div class="order-info-label">
                                Địa chỉ giao hàng
                            </div>

                            <div class="order-info-value">
                                <?= nl2br(customer_order_detail_escape($order['dia_chi_giao_hang'])) ?>
                            </div>

                        </div>

                    </div>

                    <!-- THANH TOAN -->
                    <div class="order-detail-card">

                        <h4 class="order-detail-card-title">
                            <i class="bi bi-wallet2 me-2"></i>
                            Thanh toán
                        </h4>

                        <?php if (empty($orderPayments)): ?>

                            <p class="text-muted">
                                Chưa có thông tin thanh toán.
                            </p>

                        <?php else: ?>

                            <?php foreach ($orderPayments as $payment): ?>

                                <div class="border-bottom pb-3 mb-3">

                                    <div class="order-info-label">
                                        Phương thức
                                    </div>

                                    <div class="order-info-value">
                                        <?= customer_order_detail_escape(
                                            customer_order_detail_payment_method($payment['phuong_thuc'])
                                        ) ?>
                                    </div>

                                    <div class="order-info-label mt-3">
                                        Số tiền
                                    </div>

                                    <div class="order-info-value">
                                        <?= customer_order_detail_money($payment['so_tien']) ?>
                                    </div>

                                    <div class="order-info-label mt-3">
                                        Trạng thái giao dịch
                                    </div>

                                    <div class="order-info-value">

                                        <?php
                                        $paymentLabels = [
                                            'PENDING' => 'Đang chờ xử lý',
                                            'SUCCESS' => 'Thành công',
                                            'FAILED' => 'Thất bại',
                                            'CANCELLED' => 'Đã hủy',
                                            'REFUNDED' => 'Đã hoàn tiền'
                                        ];
                                        ?>

                                        <?= customer_order_detail_escape(
                                            $paymentLabels[$payment['trang_thai']] ?? 'Không xác định'
                                        ) ?>

                                    </div>

                                    <?php if (!empty($payment['ngay_thanh_toan'])): ?>

                                        <div class="order-info-label mt-3">
                                            Ngày thanh toán
                                        </div>

                                        <div class="order-info-value">
                                            <?= date('d/m/Y H:i:s', strtotime($payment['ngay_thanh_toan'])) ?>
                                        </div>

                                    <?php endif; ?>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>

                    <!-- TONG TIEN -->
                    <div class="order-detail-card">

                        <h4 class="order-detail-card-title">
                            <i class="bi bi-calculator me-2"></i>
                            Tổng thanh toán
                        </h4>

                        <div class="order-detail-summary-row">

                            <span>Tạm tính</span>

                            <strong>
                                <?= customer_order_detail_money($order['tam_tinh']) ?>
                            </strong>

                        </div>

                        <div class="order-detail-summary-row">

                            <span>Giảm giá</span>

                            <strong class="text-success">
                                -<?= customer_order_detail_money($order['tien_giam_gia']) ?>
                            </strong>

                        </div>

                        <div class="order-detail-summary-row">

                            <span>Phí vận chuyển</span>

                            <strong>
                                <?= customer_order_detail_money($order['phi_van_chuyen']) ?>
                            </strong>

                        </div>

                        <div class="order-detail-total">

                            <span>Tổng tiền</span>

                            <strong>
                                <?= customer_order_detail_money($order['tong_tien']) ?>
                            </strong>

                        </div>

                    </div>

                    <!-- GHI CHU -->
                    <?php if (!empty($order['ghi_chu'])): ?>

                        <div class="order-detail-card">

                            <h4 class="order-detail-card-title">
                                <i class="bi bi-chat-left-text me-2"></i>
                                Ghi chú đơn hàng
                            </h4>

                            <p class="mb-0 text-muted">
                                <?= nl2br(customer_order_detail_escape($order['ghi_chu'])) ?>
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
