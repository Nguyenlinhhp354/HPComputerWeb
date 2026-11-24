<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permission_check.php';
yeu_cau_quyen('orders.view');
require_once __DIR__ . '/helpers.php';

$idInput = $_GET['id'] ?? null;
$orderId = is_string($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
$order = null;
$items = $history = [];
$error = '';
if (!$orderId) {
    http_response_code(400);
    $error = 'Mã đơn hàng không hợp lệ.';
} else {
    try {
        $stmt = $pdo->prepare('SELECT dh.*, kh.ten_khach_hang, kh.email,
            nd.ho_ten AS ten_nguoi_tao
            FROM don_hang dh
            JOIN khach_hang kh ON kh.ma_khach_hang = dh.ma_khach_hang
            LEFT JOIN nguoi_dung nd ON nd.ma_nguoi_dung = dh.ma_nguoi_tao
            WHERE dh.ma_don_hang = :id');
        $stmt->execute(['id' => $orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            http_response_code(404);
            $error = 'Không tìm thấy đơn hàng.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM chi_tiet_don_hang WHERE ma_don_hang = :id ORDER BY ma_chi_tiet_don_hang');
            $stmt->execute(['id' => $orderId]);
            $items = $stmt->fetchAll();
            $stmt = $pdo->prepare('SELECT ls.*, nd.ho_ten AS ten_nguoi_thuc_hien
                FROM lich_su_trang_thai_don_hang ls
                LEFT JOIN nguoi_dung nd ON nd.ma_nguoi_dung = ls.ma_nguoi_thuc_hien
                WHERE ls.ma_don_hang = :id ORDER BY ls.ngay_thay_doi DESC, ls.ma_lich_su_trang_thai DESC');
            $stmt->execute(['id' => $orderId]);
            $history = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log('Order detail: ' . $e->getMessage());
        http_response_code(500);
        $error = 'Không thể tải thông tin đơn hàng. Vui lòng thử lại sau.';
    }
}
$ordersUrl = BASE_URL . '/orders.php';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$notice = $orderId ? ($_SESSION['order_notice'][$orderId] ?? null) : null;
if ($orderId) {
    unset($_SESSION['order_notice'][$orderId]);
}
?>
<div class="page-heading">
    <div><h1>Chi tiết đơn hàng</h1><p>Thông tin mua hàng và lịch sử xử lý.</p></div>
    <a class="btn btn-outline-secondary" href="<?= orderEscape($ordersUrl) ?>">Quay lại danh sách</a>
</div>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= orderEscape($error) ?></div>
<?php else: ?>
    <?php if ($notice): ?>
        <div class="alert alert-<?= orderEscape($notice['type']) ?>" role="status"><?= orderEscape($notice['message']) ?></div>
    <?php endif; ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div><h2 class="h5 mb-1"><?= orderEscape($order['ma_so_don_hang']) ?></h2>
                <span class="text-muted">Ngày đặt: <?= orderDate($order['ngay_dat']) ?></span></div>
            <div class="d-flex flex-wrap gap-2">
                <?= orderStatusBadge($order['trang_thai_don_hang']) ?>
                <?= orderStatusBadge($order['trang_thai_thanh_toan'], true) ?>
            </div>
        </div>
    </div>
    <?php if ($order['trang_thai_don_hang'] === 'PENDING' && co_quyen('orders.approve')): ?>
        <form method="post" action="<?= orderEscape(BASE_URL . '/modules/orders/approve.php') ?>"
              class="mb-4" onsubmit="return confirm('Xác nhận duyệt đơn hàng này?');">
            <input type="hidden" name="id" value="<?= (int)$orderId ?>">
            <input type="hidden" name="csrf_token" value="<?= orderEscape($_SESSION['csrf_token']) ?>">
            <button type="submit" class="btn btn-primary" <?= !$items ? 'disabled' : '' ?>>
                <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Duyệt đơn
            </button>
            <?php if (!$items): ?><span class="text-muted ms-2">Cần có sản phẩm trước khi duyệt.</span><?php endif; ?>
        </form>
    <?php endif; ?>
    <?php $nextStatus = ORDER_NEXT_STATUSES[$order['trang_thai_don_hang']] ?? null; ?>
    <?php if ($nextStatus && co_quyen('orders.update_status')): ?>
        <div class="card border-0 shadow-sm mb-4"><div class="card-body">
            <h2 class="h6">Cập nhật trạng thái</h2>
            <p>Bước tiếp theo: <strong><?= orderEscape(ORDER_STATUSES[$nextStatus]) ?></strong></p>
            <?php if (in_array($nextStatus, ['SHIPPING', 'COMPLETED'], true)): ?>
                <p class="small text-muted">Cần có ghi nhận xuất kho đủ sản phẩm của đơn trước khi xác nhận.</p>
            <?php endif; ?>
            <form method="post" action="<?= orderEscape(BASE_URL . '/modules/orders/update_status.php') ?>"
                  onsubmit="return confirm('Xác nhận chuyển đơn sang bước tiếp theo?');">
                <input type="hidden" name="id" value="<?= (int)$orderId ?>">
                <input type="hidden" name="csrf_token" value="<?= orderEscape($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="status" value="<?= orderEscape($nextStatus) ?>">
                <label for="statusNote" class="form-label">Ghi chú (không bắt buộc)</label>
                <textarea class="form-control mb-3" id="statusNote" name="note" rows="2" maxlength="1000"><?= orderEscape($notice['status_note'] ?? '') ?></textarea>
                <button type="submit" class="btn btn-primary" <?= !$items ? 'disabled' : '' ?>>
                    Chuyển sang <?= orderEscape(ORDER_STATUSES[$nextStatus]) ?>
                </button>
            </form>
        </div></div>
    <?php endif; ?>
    <?php if (in_array($order['trang_thai_don_hang'], ['PENDING', 'CONFIRMED'], true)
        && $order['trang_thai_thanh_toan'] === 'UNPAID' && co_quyen('orders.cancel')): ?>
        <details class="card border-0 shadow-sm mb-4" <?= isset($notice['reason']) ? 'open' : '' ?>>
            <summary class="card-header bg-white text-danger py-3">Hủy đơn hàng</summary>
            <div class="card-body">
                <p class="text-muted">Chỉ hủy khi chưa thu tiền và chưa xuất kho. Hệ thống sẽ kiểm tra trước khi xác nhận.</p>
                <form method="post" action="<?= orderEscape(BASE_URL . '/modules/orders/cancel.php') ?>"
                      onsubmit="return confirm('Xác nhận hủy đơn hàng này?');">
                    <input type="hidden" name="id" value="<?= (int)$orderId ?>">
                    <input type="hidden" name="csrf_token" value="<?= orderEscape($_SESSION['csrf_token']) ?>">
                    <label for="cancelReason" class="form-label">Lý do hủy <span class="text-danger">*</span></label>
                    <textarea class="form-control mb-2" id="cancelReason" name="reason" rows="3"
                              maxlength="1000" required aria-describedby="cancelHelp"><?= orderEscape($notice['reason'] ?? '') ?></textarea>
                    <p id="cancelHelp" class="small text-muted">Tối đa 1.000 ký tự. Lý do sẽ được lưu vào lịch sử đơn hàng.</p>
                    <button type="submit" class="btn btn-outline-danger">Xác nhận hủy đơn</button>
                </form>
            </div>
        </details>
    <?php endif; ?>
    <div class="row g-4 mb-4">
        <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h2 class="h6 mb-3">Thông tin khách hàng</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4">Khách hàng</dt><dd class="col-sm-8 text-break"><?= orderEscape($order['ten_khach_hang']) ?></dd>
                <dt class="col-sm-4">Email</dt><dd class="col-sm-8 text-break"><?= orderEscape($order['email'] ?: '—') ?></dd>
                <dt class="col-sm-4">Người tạo đơn</dt><dd class="col-sm-8 text-break"><?= orderEscape($order['ten_nguoi_tao'] ?: 'Không ghi nhận') ?></dd>
            </dl>
        </div></div></div>
        <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h2 class="h6 mb-3">Thông tin giao hàng</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4">Người nhận</dt><dd class="col-sm-8 text-break"><?= orderEscape($order['ten_nguoi_nhan']) ?></dd>
                <dt class="col-sm-4">Điện thoại</dt><dd class="col-sm-8"><?= orderEscape($order['sdt_nguoi_nhan']) ?></dd>
                <dt class="col-sm-4">Địa chỉ</dt><dd class="col-sm-8 text-break"><?= orderEscape($order['dia_chi_giao_hang']) ?></dd>
            </dl>
        </div></div></div>
    </div>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3"><h2 class="h6 mb-0">Sản phẩm trong đơn</h2></div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>Sản phẩm</th><th class="text-end">Số lượng</th><th class="text-end">Đơn giá</th><th class="text-end">Giảm giá dòng</th><th class="text-end">Thành tiền</th><th>Bảo hành</th></tr></thead>
            <tbody>
            <?php if (!$items): ?><tr><td colspan="6" class="text-center text-muted py-4">Đơn hàng chưa có sản phẩm.</td></tr><?php endif; ?>
            <?php foreach ($items as $item): ?>
                <tr><td class="text-break"><?= orderEscape($item['ten_san_pham']) ?></td>
                    <td class="text-end"><?= (int)$item['so_luong'] ?></td>
                    <td class="text-end text-nowrap"><?= orderMoney($item['don_gia']) ?></td>
                    <td class="text-end text-nowrap"><?= orderMoney($item['tien_giam_gia']) ?></td>
                    <td class="text-end text-nowrap"><?= orderMoney($item['thanh_tien']) ?></td>
                    <td class="text-nowrap"><?= (int)$item['thoi_han_bao_hanh'] ?> tháng</td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <div class="card-body border-top"><div class="row g-4">
            <div class="col-md-7"><h3 class="h6">Ghi chú</h3><p class="text-break mb-0"><?= nl2br(orderEscape($order['ghi_chu'] ?: 'Không có ghi chú.')) ?></p></div>
            <div class="col-md-5"><dl class="row mb-0">
                <dt class="col-6 fw-normal">Tạm tính</dt><dd class="col-6 text-end"><?= orderMoney($order['tam_tinh']) ?></dd>
                <dt class="col-6 fw-normal">Giảm giá đơn</dt><dd class="col-6 text-end"><?= orderMoney($order['tien_giam_gia']) ?></dd>
                <dt class="col-6 fw-normal">Phí vận chuyển</dt><dd class="col-6 text-end"><?= orderMoney($order['phi_van_chuyen']) ?></dd>
                <dt class="col-6 border-top pt-2">Tổng thanh toán</dt><dd class="col-6 text-end border-top pt-2 fw-bold text-primary"><?= orderMoney($order['tong_tien']) ?></dd>
            </dl></div>
        </div></div>
    </div>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3"><h2 class="h6 mb-0">Lịch sử trạng thái</h2></div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>Thời gian</th><th>Người thực hiện</th><th>Trước</th><th>Sau</th><th>Nội dung</th></tr></thead>
            <tbody>
            <?php if (!$history): ?><tr><td colspan="5" class="text-center text-muted py-4">Chưa có lịch sử trạng thái.</td></tr><?php endif; ?>
            <?php foreach ($history as $entry): ?>
                <tr><td class="text-nowrap"><?= orderDate($entry['ngay_thay_doi']) ?></td>
                    <td><?= orderEscape($entry['ten_nguoi_thuc_hien'] ?: 'Không ghi nhận') ?></td>
                    <td><?= $entry['trang_thai_cu'] === null ? '—' : orderStatusBadge($entry['trang_thai_cu']) ?></td>
                    <td><?= orderStatusBadge($entry['trang_thai_moi']) ?></td>
                    <td class="text-break"><?php if ($entry['ly_do_thay_doi']): ?><div><?= nl2br(orderEscape($entry['ly_do_thay_doi'])) ?></div><?php endif; ?><?= nl2br(orderEscape($entry['ghi_chu'] ?: '')) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
<?php endif; ?>
