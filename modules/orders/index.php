<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permission_check.php';
yeu_cau_quyen('orders.view');
require_once __DIR__ . '/helpers.php';

$keyword = is_string($_GET['keyword'] ?? null) ? trim($_GET['keyword']) : '';
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$payment = is_string($_GET['payment'] ?? null) ? $_GET['payment'] : '';
$status = array_key_exists($status, ORDER_STATUSES) ? $status : '';
$payment = array_key_exists($payment, PAYMENT_STATUSES) ? $payment : '';
$requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $requestedPage ?: 1;
$limit = 10;
$total = 0;
$totalPages = 1;
$offset = 0;
$orders = [];
$loadError = false;

$where = [];
$params = [];
if ($keyword !== '') {
    $where[] = '(dh.ma_so_don_hang LIKE :code OR kh.ten_khach_hang LIKE :customer
        OR dh.ten_nguoi_nhan LIKE :recipient OR dh.sdt_nguoi_nhan LIKE :phone)';
    foreach (['code', 'customer', 'recipient', 'phone'] as $field) {
        $params[':' . $field] = '%' . $keyword . '%';
    }
}
if ($status !== '') {
    $where[] = 'dh.trang_thai_don_hang = :status';
    $params[':status'] = $status;
}
if ($payment !== '') {
    $where[] = 'dh.trang_thai_thanh_toan = :payment';
    $params[':payment'] = $payment;
}
$fromSql = ' FROM don_hang dh LEFT JOIN khach_hang kh ON kh.ma_khach_hang = dh.ma_khach_hang';
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

try {
    $countStmt = $pdo->prepare('SELECT COUNT(*)' . $fromSql . $whereSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $limit));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $limit;

    $stmt = $pdo->prepare('SELECT dh.ma_don_hang, dh.ma_so_don_hang,
        dh.ten_nguoi_nhan, dh.sdt_nguoi_nhan, dh.ngay_dat, dh.tong_tien,
        dh.trang_thai_don_hang, dh.trang_thai_thanh_toan, kh.ten_khach_hang'
        . $fromSql . $whereSql . ' ORDER BY dh.ngay_dat DESC, dh.ma_don_hang DESC LIMIT :limit OFFSET :offset');
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Orders list: ' . $e->getMessage());
    $loadError = true;
    http_response_code(500);
}

$listUrl = BASE_URL . '/orders.php';
$filterParams = array_filter([
    'keyword' => $keyword, 'status' => $status, 'payment' => $payment
], static function ($value) { return $value !== ''; });
$pageUrl = static function ($targetPage) use ($listUrl, $filterParams) {
    return $listUrl . '?' . http_build_query(array_merge($filterParams, ['page' => $targetPage]));
};
?>

<div class="page-heading">
    <div>
        <h1>Quản lý đơn hàng</h1>
        <p>Tra cứu đơn hàng và theo dõi thông tin bán hàng.</p>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="<?= orderEscape($listUrl) ?>">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label for="orderKeyword" class="form-label">Tìm kiếm</label>
                    <input type="search" class="form-control" id="orderKeyword" name="keyword"
                           placeholder="Mã đơn, tên khách hoặc số điện thoại"
                           value="<?= orderEscape($keyword) ?>">
                </div>
                <div class="col-lg-3 col-md-6">
                    <label for="orderStatus" class="form-label">Trạng thái đơn</label>
                    <select class="form-select" id="orderStatus" name="status">
                        <option value="">Tất cả trạng thái</option>
                        <?php foreach (ORDER_STATUSES as $value => $label): ?>
                            <option value="<?= orderEscape($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= orderEscape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label for="paymentStatus" class="form-label">Thanh toán</label>
                    <select class="form-select" id="paymentStatus" name="payment">
                        <option value="">Tất cả trạng thái</option>
                        <?php foreach (PAYMENT_STATUSES as $value => $label): ?>
                            <option value="<?= orderEscape($value) ?>" <?= $payment === $value ? 'selected' : '' ?>><?= orderEscape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button class="btn btn-primary flex-fill" type="submit">Tìm kiếm</button>
                    <a class="btn btn-outline-secondary" href="<?= orderEscape($listUrl) ?>" aria-label="Xóa bộ lọc" title="Xóa bộ lọc"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($loadError): ?>
    <div class="alert alert-danger" role="alert">Không thể tải danh sách đơn hàng. Vui lòng thử lại sau.</div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between gap-2">
            <h2 class="h5 mb-0">Danh sách đơn hàng</h2>
            <span class="text-muted"><?= $total ?> đơn hàng</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Mã đơn</th>
                        <th scope="col">Khách hàng / Người nhận</th>
                        <th scope="col">Ngày đặt</th>
                        <th scope="col" class="text-end">Tổng tiền</th>
                        <th scope="col">Trạng thái đơn</th>
                        <th scope="col">Thanh toán</th>
                        <th scope="col">Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$orders): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">
                            <?= $filterParams ? 'Không tìm thấy đơn hàng phù hợp với bộ lọc.' : 'Chưa có đơn hàng để hiển thị.' ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <?php $detailUrl = $listUrl . '?' . http_build_query(['id' => (int) $order['ma_don_hang']]); ?>
                            <tr>
                                <td class="text-nowrap"><a class="fw-semibold text-decoration-none" href="<?= orderEscape($detailUrl) ?>"><?= orderEscape($order['ma_so_don_hang']) ?></a></td>
                                <td>
                                    <div class="fw-semibold"><?= orderEscape($order['ten_khach_hang'] ?? $order['ten_nguoi_nhan']) ?></div>
                                    <small class="text-muted"><?= orderEscape($order['ten_nguoi_nhan']) ?> · <?= orderEscape($order['sdt_nguoi_nhan']) ?></small>
                                </td>
                                <td class="text-nowrap"><?= orderEscape(orderDate($order['ngay_dat'])) ?></td>
                                <td class="text-end text-nowrap fw-semibold"><?= orderEscape(orderMoney($order['tong_tien'])) ?></td>
                                <td><?= orderStatusBadge($order['trang_thai_don_hang']) ?></td>
                                <td><?= orderStatusBadge($order['trang_thai_thanh_toan'], true) ?></td>
                                <td><a class="btn btn-sm btn-outline-primary text-nowrap" href="<?= orderEscape($detailUrl) ?>" aria-label="<?= orderEscape('Xem đơn ' . $order['ma_so_don_hang']) ?>">Xem</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total > 0): ?>
            <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-3">
                <small class="text-muted">Hiển thị <?= $offset + 1 ?>–<?= min($offset + $limit, $total) ?> / <?= $total ?> đơn</small>
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Phân trang đơn hàng">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page === 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= orderEscape($pageUrl(max(1, $page - 1))) ?>" <?= $page === 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Trước</a>
                            </li>
                            <?php for ($number = max(1, $page - 2); $number <= min($totalPages, $page + 2); $number++): ?>
                                <li class="page-item <?= $number === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= orderEscape($pageUrl($number)) ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page === $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= orderEscape($pageUrl(min($totalPages, $page + 1))) ?>" <?= $page === $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Sau</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
