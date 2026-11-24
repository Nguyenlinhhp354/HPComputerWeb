<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permission_check.php';
yeu_cau_quyen('orders.view');
yeu_cau_quyen('orders.approve');
require_once __DIR__ . '/approval.php';

// GET chỉ xem dữ liệu; thao tác duyệt phải gửi bằng biểu mẫu POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Vui lòng sử dụng nút Duyệt đơn trong trang chi tiết.');
}

$token = $_POST['csrf_token'] ?? null;
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    exit('Phiên xác nhận không hợp lệ. Vui lòng tải lại trang chi tiết rồi thử lại.');
}

$idInput = $_POST['id'] ?? null;
$orderId = is_string($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
if (!$orderId) {
    http_response_code(400);
    exit('Mã đơn hàng không hợp lệ.');
}

try {
    approveOrder($pdo, $orderId, (int)$_SESSION['ma_nguoi_dung']);
    $notice = ['type' => 'success', 'message' => 'Đã duyệt đơn hàng thành công.'];
} catch (DomainException $e) {
    $notice = ['type' => 'warning', 'message' => $e->getMessage()];
} catch (Throwable $e) {
    error_log('Approve order: ' . $e->getMessage());
    $notice = ['type' => 'danger', 'message' => 'Không thể duyệt đơn hàng. Vui lòng thử lại sau.'];
}

$_SESSION['order_notice'][$orderId] = $notice;
// Chuyển về trang GET để tải lại trình duyệt không gửi lại biểu mẫu.
header('Location: ' . BASE_URL . '/orders.php?id=' . $orderId, true, 303);
exit;
