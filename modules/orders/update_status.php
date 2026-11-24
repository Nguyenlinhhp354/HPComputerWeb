<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permission_check.php';
yeu_cau_quyen('orders.view');
yeu_cau_quyen('orders.update_status');
require_once __DIR__ . '/status_update.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Vui lòng sử dụng biểu mẫu cập nhật trạng thái trong trang chi tiết.');
}
$token = $_POST['csrf_token'] ?? null;
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    exit('Phiên xác nhận không hợp lệ. Vui lòng tải lại trang chi tiết rồi thử lại.');
}

$idInput = $_POST['id'] ?? null;
$orderId = is_string($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
$newStatus = $_POST['status'] ?? null;
$note = $_POST['note'] ?? '';
if (!$orderId || !is_string($newStatus) || !is_string($note)) {
    http_response_code(400);
    exit('Thông tin cập nhật trạng thái không hợp lệ.');
}

try {
    updateOrderStatus($pdo, $orderId, (int)$_SESSION['ma_nguoi_dung'], $newStatus, $note);
    $notice = ['type' => 'success', 'message' => 'Đã chuyển đơn sang trạng thái: ' . ORDER_STATUSES[$newStatus] . '.'];
} catch (DomainException $e) {
    $notice = ['type' => 'warning', 'message' => $e->getMessage(), 'status_note' => mb_substr($note, 0, 1000, 'UTF-8')];
} catch (Throwable $e) {
    error_log('Update order status: ' . $e->getMessage());
    $notice = ['type' => 'danger', 'message' => 'Không thể cập nhật trạng thái. Vui lòng thử lại sau.', 'status_note' => mb_substr($note, 0, 1000, 'UTF-8')];
}
$_SESSION['order_notice'][$orderId] = $notice;
header('Location: ' . BASE_URL . '/orders.php?id=' . $orderId, true, 303);
exit;
