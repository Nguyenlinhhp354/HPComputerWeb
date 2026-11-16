<?php
// Chỉ chạy CLI và chỉ ghi vào bảng TEMPORARY, không thay đổi dữ liệu thật.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/orders/cancellation.php';

function checkCancellation(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// Bảng tạm cùng tên che bảng thật trong riêng kết nối này.
foreach (['don_hang', 'thanh_toan', 'lich_su_kho', 'lich_su_trang_thai_don_hang'] as $table) {
    $pdo->exec("CREATE TEMPORARY TABLE `cancel_test_$table` LIKE `$table`");
    $pdo->exec("CREATE TEMPORARY TABLE `$table` LIKE `cancel_test_$table`");
}
$pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");

function makeCancelOrder(PDO $pdo, string $status = 'PENDING', string $payment = 'UNPAID'): int
{
    static $number = 0;
    $number++;
    $stmt = $pdo->prepare('INSERT INTO don_hang
        (ma_khach_hang, ma_so_don_hang, ten_nguoi_nhan, sdt_nguoi_nhan,
         dia_chi_giao_hang, trang_thai_don_hang, trang_thai_thanh_toan)
        VALUES (1, :code, :name, :phone, :address, :status, :payment)');
    $stmt->execute(['code' => 'CANCEL-TEST-' . $number, 'name' => 'Test',
        'phone' => '0', 'address' => 'Test', 'status' => $status, 'payment' => $payment]);
    return (int)$pdo->lastInsertId();
}

function cancellationSnapshot(PDO $pdo): array
{
    $snapshot = [];
    foreach (['don_hang', 'thanh_toan', 'lich_su_kho', 'lich_su_trang_thai_don_hang'] as $table) {
        $snapshot[$table] = $pdo->query("SELECT * FROM `$table` ORDER BY 1")->fetchAll();
    }
    return $snapshot;
}

function rejectCancellation(PDO $pdo, int $id, string $label, string $reason = 'Customer request'): void
{
    $before = cancellationSnapshot($pdo);
    $rejected = false;
    try {
        cancelOrder($pdo, $id, 7, $reason);
    } catch (DomainException $e) {
        $rejected = true;
    }
    checkCancellation($rejected, 'Expected rejection: ' . $label);
    checkCancellation(!$pdo->inTransaction(), 'Transaction left open: ' . $label);
    checkCancellation(cancellationSnapshot($pdo) === $before, 'Rejected request changed data: ' . $label);
    echo 'PASS: reject ' . $label . "\n";
}

foreach (['PENDING', 'CONFIRMED'] as $status) {
    $id = makeCancelOrder($pdo, $status);
    cancelOrder($pdo, $id, 7, '  Khách đổi ý, xin hủy đơn.  ');
    checkCancellation($pdo->query("SELECT trang_thai_don_hang FROM don_hang WHERE ma_don_hang=$id")->fetchColumn() === 'CANCELLED', 'Cancellation did not update status');
    $history = $pdo->query("SELECT * FROM lich_su_trang_thai_don_hang WHERE ma_don_hang=$id")->fetchAll();
    checkCancellation(count($history) === 1 && $history[0]['trang_thai_cu'] === $status
        && $history[0]['trang_thai_moi'] === 'CANCELLED'
        && (int)$history[0]['ma_nguoi_thuc_hien'] === 7
        && $history[0]['ly_do_thay_doi'] === 'Khách đổi ý, xin hủy đơn.', 'Wrong actor, reason or history');
    echo "PASS: cancel $status with actor and trimmed reason\n";
    rejectCancellation($pdo, $id, 'repeat cancellation');
}
foreach (['PROCESSING', 'SHIPPING', 'COMPLETED', 'CANCELLED'] as $status) {
    rejectCancellation($pdo, makeCancelOrder($pdo, $status), $status . ' order');
}
foreach (['PAID', 'PARTIAL', 'REFUNDED'] as $payment) {
    rejectCancellation($pdo, makeCancelOrder($pdo, 'CONFIRMED', $payment), $payment . ' payment state');
}

foreach (['PENDING', 'SUCCESS', 'REFUNDED', 'FAILED', 'CANCELLED'] as $payment) {
    $id = makeCancelOrder($pdo);
    $stmt = $pdo->prepare("INSERT INTO thanh_toan (ma_don_hang, phuong_thuc, so_tien, trang_thai)
        VALUES (:id, 'CASH', 100, :status)");
    $stmt->execute(['id' => $id, 'status' => $payment]);
    if (in_array($payment, ['PENDING', 'SUCCESS', 'REFUNDED'], true)) {
        rejectCancellation($pdo, $id, $payment . ' transaction while order UNPAID');
    } else {
        cancelOrder($pdo, $id, 7, 'Customer request');
        checkCancellation($pdo->query("SELECT trang_thai_don_hang FROM don_hang WHERE ma_don_hang=$id")->fetchColumn() === 'CANCELLED', 'Failed transaction should not block cancellation');
        echo "PASS: allow $payment transaction\n";
    }
}

$id = makeCancelOrder($pdo);
$stmt = $pdo->prepare("INSERT INTO lich_su_kho
    (ma_san_pham, ma_nguoi_dung, loai_bien_dong, huong_bien_dong, so_luong, loai_chung_tu, ma_chung_tu)
    VALUES (1, 7, 'EXPORT', 'GIAM', 1, 'DON_HANG', :id)");
$stmt->execute(['id' => $id]);
rejectCancellation($pdo, $id, 'recorded stock export');
$unrelatedId = makeCancelOrder($pdo);
$stmt = $pdo->prepare("INSERT INTO lich_su_kho
    (ma_san_pham, ma_nguoi_dung, loai_bien_dong, huong_bien_dong, so_luong, loai_chung_tu, ma_chung_tu)
    VALUES (1, 7, 'IMPORT', 'TANG', 1, 'PHIEU_NHAP', :id)");
$stmt->execute(['id' => $unrelatedId]);
cancelOrder($pdo, $unrelatedId, 7, 'Customer request');
checkCancellation($pdo->query("SELECT trang_thai_don_hang FROM don_hang WHERE ma_don_hang=$unrelatedId")->fetchColumn() === 'CANCELLED', 'Unrelated stock movement blocked cancellation');
echo "PASS: unrelated order export and import do not block cancellation\n";

rejectCancellation($pdo, 999999, 'missing order');
$id = makeCancelOrder($pdo);
foreach (['' => 'empty reason', " \t\r\n " => 'whitespace reason', str_repeat('á', 1001) => 'oversized Unicode reason'] as $reason => $label) {
    rejectCancellation($pdo, $id, $label, $reason);
}
cancelOrder($pdo, $id, 7, str_repeat('á', 1000));
checkCancellation((int)$pdo->query("SELECT CHAR_LENGTH(ly_do_thay_doi) FROM lich_su_trang_thai_don_hang WHERE ma_don_hang=$id")->fetchColumn() === 1000, 'Unicode reason boundary failed');
echo "PASS: 1000-character Unicode reason accepted\n";

// Làm ghi lịch sử thất bại để xác minh UPDATE đơn cũng được rollback.
$id = makeCancelOrder($pdo);
$pdo->exec('DELETE FROM lich_su_trang_thai_don_hang');
$pdo->exec('ALTER TABLE lich_su_trang_thai_don_hang MODIFY ly_do_thay_doi varchar(1)');
$before = cancellationSnapshot($pdo);
$failed = false;
try {
    cancelOrder($pdo, $id, 7, 'Customer request');
} catch (PDOException $e) {
    $failed = true;
}
checkCancellation($failed && !$pdo->inTransaction(), 'Expected history insert failure and closed transaction');
checkCancellation(cancellationSnapshot($pdo) === $before, 'History failure did not roll back all changes');
echo "PASS: rollback when history insert fails\n";
