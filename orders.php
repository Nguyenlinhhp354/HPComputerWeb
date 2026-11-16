
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/customers/orders.php
// CHUC NANG: LICH SU DON HANG KHACH HANG
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
// 2. KIEM TRA TAI KHOAN
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
// 3. HAM HO TRO
// =====================================================

function customer_orders_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function customer_orders_money($value)
{
    return number_format((float)$value, 0, ',', '.') . 'đ';
}

function customer_orders_status($status)
{
    $statuses = [
        'PENDING' => [
            'label' => 'Chờ xác nhận',
            'class' => 'warning'
        ],
        'CONFIRMED' => [
            'label' => 'Đã xác nhận',
            'class' => 'primary'
        ],
        'PROCESSING' => [
            'label' => 'Đang xử lý',
            'class' => 'info'
        ],
        'SHIPPING' => [
            'label' => 'Đang giao hàng',
            'class' => 'primary'
        ],
        'COMPLETED' => [
            'label' => 'Hoàn thành',
            'class' => 'success'
        ],
        'CANCELLED' => [
            'label' => 'Đã hủy',
            'class' => 'danger'
        ]
    ];

    return $statuses[$status] ?? [
        'label' => 'Không xác định',
        'class' => 'secondary'
    ];
}

function customer_orders_payment_status($status)
{
    $statuses = [
        'UNPAID' => [
            'label' => 'Chưa thanh toán',
            'class' => 'danger'
        ],
        'PARTIAL' => [
            'label' => 'Thanh toán một phần',
            'class' => 'warning'
        ],
        'PAID' => [
            'label' => 'Đã thanh toán',
            'class' => 'success'
        ],
        'REFUNDED' => [
            'label' => 'Đã hoàn tiền',
            'class' => 'secondary'
        ]
    ];

    return $statuses[$status] ?? [
        'label' => 'Không xác định',
        'class' => 'secondary'
    ];
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

if ($customerId !== false) {
    $customerId = (int)$customerId;
} else {
    $customerId = 0;
}

// =====================================================
// 5. BO LOC TRANG THAI
// =====================================================

$allowedStatuses = [
    'PENDING',
    'CONFIRMED',
    'PROCESSING',
    'SHIPPING',
    'COMPLETED',
    'CANCELLED'
];

$statusFilter = strtoupper(trim($_GET['status'] ?? ''));

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

// =====================================================
// 6. PHAN TRANG
// =====================================================

$perPage = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));

$totalOrders = 0;
$totalPages = 1;
$orders = [];

if ($customerId > 0) {

    $countSql = "
        SELECT COUNT(*)
        FROM don_hang
        WHERE ma_khach_hang = :customer_id
    ";

    $countParams = [
        ':customer_id' => $customerId
    ];

    if ($statusFilter !== '') {
        $countSql .= " AND trang_thai_don_hang = :status";
        $countParams[':status'] = $statusFilter;
    }

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);

    $totalOrders = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($totalOrders / $perPage));

    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    $offset = ($currentPage - 1) * $perPage;

    // ---------------------------------------------
    // LAY DANH SACH DON HANG
    // ---------------------------------------------

    $ordersSql = "
        SELECT
            dh.ma_don_hang,
            dh.ma_so_don_hang,
            dh.ngay_dat,
            dh.tam_tinh,
            dh.tien_giam_gia,
            dh.phi_van_chuyen,
            dh.tong_tien,
            dh.trang_thai_don_hang,
            dh.trang_thai_thanh_toan,

            (
                SELECT COUNT(*)
                FROM chi_tiet_don_hang ctdh
                WHERE ctdh.ma_don_hang = dh.ma_don_hang
            ) AS so_loai_san_pham,

            (
                SELECT COALESCE(SUM(ctdh.so_luong), 0)
                FROM chi_tiet_don_hang ctdh
                WHERE ctdh.ma_don_hang = dh.ma_don_hang
            ) AS tong_so_luong

        FROM don_hang dh

        WHERE dh.ma_khach_hang = :customer_id
    ";

    $ordersParams = [
        ':customer_id' => $customerId
    ];

    if ($statusFilter !== '') {
        $ordersSql .= "
            AND dh.trang_thai_don_hang = :status
        ";

        $ordersParams[':status'] = $statusFilter;
    }

    $ordersSql .= "
        ORDER BY dh.ngay_dat DESC, dh.ma_don_hang DESC
        LIMIT :limit OFFSET :offset
    ";

    $ordersStmt = $pdo->prepare($ordersSql);

    foreach ($ordersParams as $key => $value) {
        $ordersStmt->bindValue($key, $value);
    }

    $ordersStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $ordersStmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $ordersStmt->execute();

    $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
}

// =====================================================
// 7. GIAO DIEN
// =====================================================

$pageTitle = 'Đơn hàng của tôi';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

?>

<style>
.customer-orders-page {
    background: #f5f7fa;
    min-height: 600px;
    padding: 35px 0 60px;
}

.orders-title {
    color: #10243e;
    font-size: 27px;
    font-weight: 800;
    margin-bottom: 8px;
}

.orders-description {
    color: #7d8997;
    margin-bottom: 25px;
}

.orders-card {
    background: #fff;
    border: 1px solid #e9edf2;
    border-radius: 12px;
    overflow: hidden;
}

.orders-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
    padding: 20px;
    border-bottom: 1px solid #edf0f3;
}

.orders-filter a {
    display: inline-block;
    text-decoration: none;
    padding: 8px 14px;
    border-radius: 20px;
    background: #f2f5f9;
    color: #526174;
    font-size: 13px;
    font-weight: 600;
}

.orders-filter a:hover,
.orders-filter a.active {
    background: #0878d1;
    color: #fff;
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
}

.orders-table th {
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
    padding: 15px;
    white-space: nowrap;
}

.orders-table td {
    padding: 17px 15px;
    border-top: 1px solid #edf0f3;
    vertical-align: middle;
    font-size: 13px;
}

.orders-code {
    color: #0878d1;
    font-weight: 800;
    text-decoration: none;
}

.orders-code:hover {
    text-decoration: underline;
}

.orders-total {
    color: #dc3545;
    font-weight: 800;
    white-space: nowrap;
}

.orders-detail-btn {
    white-space: nowrap;
    font-size: 12px;
}

.orders-empty {
    text-align: center;
    padding: 65px 20px;
}

.orders-empty i {
    font-size: 65px;
    color: #a8b3c1;
}

.orders-pagination {
    padding: 20px;
    border-top: 1px solid #edf0f3;
}

.orders-pagination .page-link {
    border-radius: 6px;
    margin: 0 3px;
}

.orders-mobile-item {
    padding: 17px;
    border-top: 1px solid #edf0f3;
}

.orders-mobile-item:first-child {
    border-top: none;
}

@media (max-width: 767px) {
    .orders-title {
        font-size: 23px;
    }

    .orders-filter {
        padding: 15px;
    }
}
</style>

<div class="customer-orders-page">

    <div class="container">

        <h2 class="orders-title">
            <i class="bi bi-receipt me-2"></i>
            Đơn hàng của tôi
        </h2>

        <p class="orders-description">
            Theo dõi tình trạng và thông tin các đơn hàng bạn đã đặt tại HP Computer.
        </p>

        <div class="orders-card">

            <!-- BO LOC -->
            <div class="orders-filter">

                <a href="<?= BASE_URL ?>/modules/customers/orders.php"
                   class="<?= $statusFilter === '' ? 'active' : '' ?>">
                    Tất cả
                </a>

                <?php
                $filterLabels = [
                    'PENDING' => 'Chờ xác nhận',
                    'CONFIRMED' => 'Đã xác nhận',
                    'PROCESSING' => 'Đang xử lý',
                    'SHIPPING' => 'Đang giao hàng',
                    'COMPLETED' => 'Hoàn thành',
                    'CANCELLED' => 'Đã hủy'
                ];
                ?>

                <?php foreach ($filterLabels as $status => $label): ?>

                    <a href="<?= BASE_URL ?>/modules/customers/orders.php?status=<?= $status ?>"
                       class="<?= $statusFilter === $status ? 'active' : '' ?>">

                        <?= customer_orders_escape($label) ?>

                    </a>

                <?php endforeach; ?>

            </div>

            <?php if (empty($orders)): ?>

                <div class="orders-empty">

                    <i class="bi bi-receipt"></i>

                    <h5 class="fw-bold mt-3">
                        Bạn chưa có đơn hàng nào
                    </h5>

                    <p class="text-muted">
                        Các đơn hàng bạn đặt sẽ được hiển thị tại đây.
                    </p>

                    <a href="<?= BASE_URL ?>/modules/customers/products.php"
                       class="btn btn-primary mt-2">

                        <i class="bi bi-shop me-1"></i>
                        Mua sắm ngay

                    </a>

                </div>

            <?php else: ?>

                <!-- GIAO DIEN MAY TINH -->
                <div class="table-responsive d-none d-md-block">

                    <table class="orders-table">

                        <thead>
                            <tr>
                                <th>Mã đơn hàng</th>
                                <th>Ngày đặt</th>
                                <th>Sản phẩm</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th>Thanh toán</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($orders as $order): ?>

                                <?php
                                $orderStatus = customer_orders_status(
                                    $order['trang_thai_don_hang']
                                );

                                $paymentStatus = customer_orders_payment_status(
                                    $order['trang_thai_thanh_toan']
                                );
                                ?>

                                <tr>

                                    <td>
                                        <a class="orders-code"
                                           href="<?= BASE_URL ?>/modules/customers/order_detail.php?id=<?= (int)$order['ma_don_hang'] ?>">

                                            <?= customer_orders_escape($order['ma_so_don_hang']) ?>

                                        </a>
                                    </td>

                                    <td>
                                        <?= date('d/m/Y H:i', strtotime($order['ngay_dat'])) ?>
                                    </td>

                                    <td>
                                        <?= (int)$order['tong_so_luong'] ?> sản phẩm
                                    </td>

                                    <td>
                                        <span class="orders-total">
                                            <?= customer_orders_money($order['tong_tien']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge text-bg-<?= $orderStatus['class'] ?>">
                                            <?= customer_orders_escape($orderStatus['label']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge text-bg-<?= $paymentStatus['class'] ?>">
                                            <?= customer_orders_escape($paymentStatus['label']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <a href="<?= BASE_URL ?>/modules/customers/order_detail.php?id=<?= (int)$order['ma_don_hang'] ?>"
                                           class="btn btn-sm btn-outline-primary orders-detail-btn">

                                            Chi tiết

                                        </a>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <!-- GIAO DIEN DIEN THOAI -->
                <div class="d-md-none">

                    <?php foreach ($orders as $order): ?>

                        <?php
                        $orderStatus = customer_orders_status(
                            $order['trang_thai_don_hang']
                        );

                        $paymentStatus = customer_orders_payment_status(
                            $order['trang_thai_thanh_toan']
                        );
                        ?>

                        <div class="orders-mobile-item">

                            <div class="d-flex justify-content-between gap-2">

                                <a class="orders-code"
                                   href="<?= BASE_URL ?>/modules/customers/order_detail.php?id=<?= (int)$order['ma_don_hang'] ?>">

                                    <?= customer_orders_escape($order['ma_so_don_hang']) ?>

                                </a>

                                <span class="orders-total">
                                    <?= customer_orders_money($order['tong_tien']) ?>
                                </span>

                            </div>

                            <div class="small text-muted mt-2">
                                <?= date('d/m/Y H:i', strtotime($order['ngay_dat'])) ?>
                            </div>

                            <div class="small mt-2">
                                <?= (int)$order['tong_so_luong'] ?> sản phẩm
                            </div>

                            <div class="d-flex flex-wrap gap-2 mt-3">

                                <span class="badge text-bg-<?= $orderStatus['class'] ?>">
                                    <?= customer_orders_escape($orderStatus['label']) ?>
                                </span>

                                <span class="badge text-bg-<?= $paymentStatus['class'] ?>">
                                    <?= customer_orders_escape($paymentStatus['label']) ?>
                                </span>

                            </div>

                            <a href="<?= BASE_URL ?>/modules/customers/order_detail.php?id=<?= (int)$order['ma_don_hang'] ?>"
                               class="btn btn-sm btn-outline-primary mt-3">

                                Xem chi tiết đơn hàng

                            </a>

                        </div>

                    <?php endforeach; ?>

                </div>

                <!-- PHAN TRANG -->
                <?php if ($totalPages > 1): ?>

                    <div class="orders-pagination">

                        <nav aria-label="Phân trang đơn hàng">

                            <ul class="pagination justify-content-center mb-0">

                                <?php
                                $baseQuery = [];

                                if ($statusFilter !== '') {
                                    $baseQuery['status'] = $statusFilter;
                                }
                                ?>

                                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">

                                    <?php
                                    $previousQuery = $baseQuery;
                                    $previousQuery['page'] = max(1, $currentPage - 1);
                                    ?>

                                    <a class="page-link"
                                       href="?<?= http_build_query($previousQuery) ?>">

                                        Trước

                                    </a>

                                </li>

                                <?php for (
                                    $page = max(1, $currentPage - 2);
                                    $page <= min($totalPages, $currentPage + 2);
                                    $page++
                                ): ?>

                                    <?php
                                    $pageQuery = $baseQuery;
                                    $pageQuery['page'] = $page;
                                    ?>

                                    <li class="page-item <?= $page === $currentPage ? 'active' : '' ?>">

                                        <a class="page-link"
                                           href="?<?= http_build_query($pageQuery) ?>">

                                            <?= $page ?>

                                        </a>

                                    </li>

                                <?php endfor; ?>

                                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">

                                    <?php
                                    $nextQuery = $baseQuery;
                                    $nextQuery['page'] = min($totalPages, $currentPage + 1);
                                    ?>

                                    <a class="page-link"
                                       href="?<?= http_build_query($nextQuery) ?>">

                                        Sau

                                    </a>

                                </li>

                            </ul>

                        </nav>

                    </div>

                <?php endif; ?>

            <?php endif; ?>

        </div>

        <div class="mt-4">

            <a href="<?= BASE_URL ?>/modules/customers/index.php"
               class="btn btn-outline-secondary">

                <i class="bi bi-arrow-left me-1"></i>
                Về trang chủ

            </a>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
