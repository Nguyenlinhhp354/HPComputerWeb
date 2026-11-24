<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permission_check.php';
yeu_cau_quyen('orders.view');
yeu_cau_quyen('orders.cancel');
require_once __DIR__ . '/cancellation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Vui lòng sử dụng nút Hủy đơn trong trang chi tiết.');
}

$token = $_POST['csrf_token'] ?? null;
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    exit('Phiên xác nhận không hợp lệ. Vui lòng tải lại trang chi tiết rồi thử lại.');
}

$idInput = $_POST['id'] ?? null;
$orderId = is_string($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
$reason = $_POST['reason'] ?? '';
if (!$orderId || !is_string($reason)) {
    http_response_code(400);
    exit('Mã đơn hàng hoặc lý do hủy không hợp lệ.');
}

try {
    cancelOrder($pdo, $orderId, (int)$_SESSION['ma_nguoi_dung'], $reason);
    $notice = ['type' => 'success', 'message' => 'Đã hủy đơn hàng và lưu lý do.'];
} catch (DomainException $e) {
    $notice = ['type' => 'warning', 'message' => $e->getMessage(), 'reason' => mb_substr($reason, 0, 1000, 'UTF-8')];
} catch (Throwable $e) {
    error_log('Cancel order: ' . $e->getMessage());
    $notice = ['type' => 'danger', 'message' => 'Không thể hủy đơn hàng. Vui lòng thử lại sau.', 'reason' => mb_substr($reason, 0, 1000, 'UTF-8')];
}

$_SESSION['order_notice'][$orderId] = $notice;
header('Location: ' . BASE_URL . '/orders.php?id=' . $orderId, true, 303);
exit;
