<?php
// Chỉ kiểm tra trên bảng TEMPORARY: không sửa đơn thật hoặc tồn kho.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/orders/approval.php';

function checkApproval(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach (['don_hang', 'chi_tiet_don_hang', 'lich_su_trang_thai_don_hang'] as $table) {
    // Tên trung gian tránh lỗi trùng tên nguồn/đích trên MariaDB.
    $pdo->exec("CREATE TEMPORARY TABLE `approval_test_$table` LIKE `$table`");
    $pdo->exec("CREATE TEMPORARY TABLE `$table` LIKE `approval_test_$table`");
}
$pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
$pdo->exec("INSERT INTO don_hang (ma_don_hang, ma_khach_hang, ma_so_don_hang,
    ten_nguoi_nhan, sdt_nguoi_nhan, dia_chi_giao_hang, trang_thai_don_hang)
    VALUES (1, 1, 'TEST-1', 'Test', '0', 'Test', 'PENDING'),
           (2, 1, 'TEST-2', 'Test', '0', 'Test', 'PENDING'),
           (3, 1, 'TEST-3', 'Test', '0', 'Test', 'CANCELLED'),
           (4, 1, 'TEST-4', 'Test', '0', 'Test', 'PENDING')");
$pdo->exec("INSERT INTO chi_tiet_don_hang
    (ma_don_hang, ma_san_pham, ten_san_pham, so_luong, don_gia, thanh_tien)
    VALUES (1, 1, 'Test', 1, 100, 100), (3, 1, 'Test', 1, 100, 100),
           (4, 1, 'Test', 1, 100, 100)");

approveOrder($pdo, 1, 1);
checkApproval($pdo->query('SELECT trang_thai_don_hang FROM don_hang WHERE ma_don_hang=1')->fetchColumn() === 'CONFIRMED', 'Approval did not update status');
$history = $pdo->query('SELECT * FROM lich_su_trang_thai_don_hang WHERE ma_don_hang=1')->fetchAll();
checkApproval(count($history) === 1 && $history[0]['trang_thai_cu'] === 'PENDING'
    && $history[0]['trang_thai_moi'] === 'CONFIRMED' && (int)$history[0]['ma_nguoi_thuc_hien'] === 1,
    'Approval history mismatch');
echo "PASS: approve and record actor/history\n";

foreach ([1 => 'repeat approval', 2 => 'empty order', 3 => 'cancelled order', 999 => 'missing order'] as $id => $label) {
    $rejected = false;
    try {
        approveOrder($pdo, $id, 1);
    } catch (DomainException $e) {
        $rejected = true;
    }
    checkApproval($rejected, 'Should reject ' . $label);
    checkApproval(!$pdo->inTransaction(), 'Transaction left open');
    echo 'PASS: reject ' . $label . "\n";
}
checkApproval((int)$pdo->query('SELECT COUNT(*) FROM lich_su_trang_thai_don_hang')->fetchColumn() === 1, 'Duplicate history created');

// Cố ý làm ghi lịch sử thất bại để kiểm tra trạng thái cũng được hoàn tác.
$pdo->exec('DELETE FROM lich_su_trang_thai_don_hang');
$pdo->exec('ALTER TABLE lich_su_trang_thai_don_hang MODIFY ghi_chu varchar(1)');
$failed = false;
try {
    approveOrder($pdo, 4, 1);
} catch (PDOException $e) {
    $failed = true;
}
checkApproval($failed, 'Expected history insert failure');
checkApproval($pdo->query('SELECT trang_thai_don_hang FROM don_hang WHERE ma_don_hang=4')->fetchColumn() === 'PENDING', 'Status was not rolled back');
checkApproval((int)$pdo->query('SELECT COUNT(*) FROM lich_su_trang_thai_don_hang')->fetchColumn() === 0, 'History was not rolled back');
echo "PASS: rollback when history cannot be saved\n";
