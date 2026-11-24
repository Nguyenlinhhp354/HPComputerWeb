<?php
// Kiểm tra CLI trên bảng TEMPORARY, không thay đổi đơn hàng hay tồn kho thật.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/orders/status_update.php';

foreach (['don_hang', 'chi_tiet_don_hang', 'lich_su_kho', 'lich_su_trang_thai_don_hang'] as $table) {
    $pdo->exec("CREATE TEMPORARY TABLE `status_test_$table` LIKE `$table`");
    $pdo->exec("CREATE TEMPORARY TABLE `$table` LIKE `status_test_$table`");
}
$pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");

function checkStatus(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function statusSnapshot(PDO $pdo): array
{
    $snapshot = [];
    foreach (['don_hang', 'chi_tiet_don_hang', 'lich_su_kho', 'lich_su_trang_thai_don_hang'] as $table) {
        $snapshot[$table] = $pdo->query("SELECT * FROM `$table` ORDER BY 1")->fetchAll();
    }
    return $snapshot;
}

function makeStatusOrder(PDO $pdo, string $status = 'CONFIRMED', int $quantity = 2): int
{
    static $number = 0;
    $stmt = $pdo->prepare('INSERT INTO don_hang
        (ma_khach_hang, ma_so_don_hang, ten_nguoi_nhan, sdt_nguoi_nhan,
         dia_chi_giao_hang, trang_thai_don_hang, trang_thai_thanh_toan)
        VALUES (1, :code, :name, :phone, :address, :status, :payment)');
    $stmt->execute(['code' => 'STATUS-TEST-' . ++$number, 'name' => 'Test',
        'phone' => '0', 'address' => 'Test', 'status' => $status, 'payment' => 'UNPAID']);
    $id = (int)$pdo->lastInsertId();
    if ($quantity >= 0) addStatusItem($pdo, $id, 1, $quantity);
    return $id;
}

function addStatusItem(PDO $pdo, int $id, int $product, int $quantity): void
{
    $stmt = $pdo->prepare('INSERT INTO chi_tiet_don_hang
        (ma_don_hang, ma_san_pham, ten_san_pham, so_luong, don_gia, thanh_tien)
        VALUES (:id, :product, :name, :quantity, 100, :total)');
    $stmt->execute(['id' => $id, 'product' => $product, 'name' => 'Test',
        'quantity' => $quantity, 'total' => $quantity * 100]);
}

function addStatusStock(PDO $pdo, int $id, int $quantity, int $product = 1,
    string $type = 'EXPORT', string $direction = 'GIAM', string $document = 'DON_HANG'): void
{
    $stmt = $pdo->prepare('INSERT INTO lich_su_kho
        (ma_san_pham, ma_nguoi_dung, loai_bien_dong, huong_bien_dong, so_luong, loai_chung_tu, ma_chung_tu)
        VALUES (:product, 7, :type, :direction, :quantity, :document, :id)');
    $stmt->execute(compact('product', 'type', 'direction', 'quantity', 'document', 'id'));
}

function rejectStatus(PDO $pdo, int $id, string $target, string $label, string $note = ''): void
{
    $before = statusSnapshot($pdo);
    $rejected = false;
    try {
        updateOrderStatus($pdo, $id, 7, $target, $note);
    } catch (DomainException $e) {
        $rejected = true;
    }
    checkStatus($rejected, 'Expected rejection: ' . $label);
    checkStatus(!$pdo->inTransaction(), 'Transaction left open: ' . $label);
    checkStatus(statusSnapshot($pdo) === $before, 'Rejected request changed data: ' . $label);
    echo "PASS: reject $label\n";
}

function acceptStatus(PDO $pdo, int $id, string $old, string $target, string $note = ''): void
{
    $before = statusSnapshot($pdo);
    updateOrderStatus($pdo, $id, 7, $target, $note);
    $after = statusSnapshot($pdo);
    checkStatus($after['lich_su_kho'] === $before['lich_su_kho'], 'Status update changed stock');
    checkStatus($after['chi_tiet_don_hang'] === $before['chi_tiet_don_hang'], 'Status update changed items');
    $order = $pdo->query("SELECT * FROM don_hang WHERE ma_don_hang=$id")->fetch();
    checkStatus($order['trang_thai_don_hang'] === $target, 'Status not updated');
    checkStatus($order['trang_thai_thanh_toan'] === 'UNPAID', 'Payment state changed');
    checkStatus(count($after['lich_su_trang_thai_don_hang']) === count($before['lich_su_trang_thai_don_hang']) + 1, 'Wrong history count');
    $history = $pdo->query("SELECT * FROM lich_su_trang_thai_don_hang WHERE ma_don_hang=$id ORDER BY ma_lich_su_trang_thai DESC LIMIT 1")->fetch();
    checkStatus($history['trang_thai_cu'] === $old && $history['trang_thai_moi'] === $target
        && (int)$history['ma_nguoi_thuc_hien'] === 7, 'Wrong history or actor');
    if (trim($note) !== '') checkStatus($history['ghi_chu'] === trim($note), 'Note not preserved/trimmed');
    echo "PASS: $old -> $target, history, payment and stock unchanged\n";
}

$id = makeStatusOrder($pdo);
acceptStatus($pdo, $id, 'CONFIRMED', 'PROCESSING', '  Đã đóng gói sản phẩm.  ');
rejectStatus($pdo, $id, 'PROCESSING', 'duplicate processing');
addStatusStock($pdo, $id, 2);
acceptStatus($pdo, $id, 'PROCESSING', 'SHIPPING');
rejectStatus($pdo, $id, 'SHIPPING', 'duplicate shipping');
acceptStatus($pdo, $id, 'SHIPPING', 'COMPLETED');
rejectStatus($pdo, $id, 'COMPLETED', 'duplicate completion');

foreach ([['PENDING', 'PROCESSING'], ['CANCELLED', 'PROCESSING'], ['COMPLETED', 'SHIPPING'],
    ['CONFIRMED', 'SHIPPING'], ['PROCESSING', 'COMPLETED'], ['SHIPPING', 'PROCESSING']] as [$old, $target]) {
    rejectStatus($pdo, makeStatusOrder($pdo, $old), $target, "$old -> $target");
}
rejectStatus($pdo, 99999999, 'PROCESSING', 'missing order');
rejectStatus($pdo, makeStatusOrder($pdo), 'CANCELLED', 'unsupported target');
rejectStatus($pdo, makeStatusOrder($pdo), 'unknown', 'unknown target');
rejectStatus($pdo, makeStatusOrder($pdo, 'CONFIRMED', -1), 'PROCESSING', 'empty items');
rejectStatus($pdo, makeStatusOrder($pdo, 'CONFIRMED', 0), 'PROCESSING', 'zero quantity');
rejectStatus($pdo, makeStatusOrder($pdo), 'PROCESSING', 'oversized Unicode note', str_repeat('á', 1001));
acceptStatus($pdo, makeStatusOrder($pdo), 'CONFIRMED', 'PROCESSING', str_repeat('á', 1000));

$id = makeStatusOrder($pdo, 'PROCESSING');
rejectStatus($pdo, $id, 'SHIPPING', 'missing exports');
addStatusStock($pdo, $id, 1);
rejectStatus($pdo, $id, 'SHIPPING', 'partial exports');
addStatusStock($pdo, $id, 20, 2);
rejectStatus($pdo, $id, 'SHIPPING', 'wrong product exports');
addStatusStock($pdo, $id + 10000, 20);
rejectStatus($pdo, $id, 'SHIPPING', 'unrelated order exports');
addStatusStock($pdo, $id, 20, 1, 'ADJUSTMENT');
rejectStatus($pdo, $id, 'SHIPPING', 'adjustment is not an export');
addStatusStock($pdo, $id, 20, 1, 'EXPORT', 'GIAM', 'PHIEU_NHAP');
rejectStatus($pdo, $id, 'SHIPPING', 'wrong document type');
addStatusStock($pdo, $id, 1);
addStatusStock($pdo, $id, 1, 1, 'RETURN', 'TANG');
rejectStatus($pdo, $id, 'SHIPPING', 'returned stock reduces net export');
addStatusStock($pdo, $id, 1);
acceptStatus($pdo, $id, 'PROCESSING', 'SHIPPING');
addStatusStock($pdo, $id, 1, 1, 'RETURN', 'TANG');
rejectStatus($pdo, $id, 'COMPLETED', 'completion rechecks exports');

$id = makeStatusOrder($pdo, 'PROCESSING');
addStatusItem($pdo, $id, 1, 3);
addStatusItem($pdo, $id, 2, 1);
addStatusStock($pdo, $id, 3);
addStatusStock($pdo, $id, 1, 2);
rejectStatus($pdo, $id, 'SHIPPING', 'duplicate product lines require summed quantity');
addStatusStock($pdo, $id, 2);
acceptStatus($pdo, $id, 'PROCESSING', 'SHIPPING');

// Làm ghi lịch sử thất bại để kiểm tra rollback toàn bộ cập nhật.
$id = makeStatusOrder($pdo);
$pdo->exec('DELETE FROM lich_su_trang_thai_don_hang');
$pdo->exec('ALTER TABLE lich_su_trang_thai_don_hang MODIFY ghi_chu varchar(1)');
$before = statusSnapshot($pdo);
$failed = false;
try {
    updateOrderStatus($pdo, $id, 7, 'PROCESSING', 'History insert must fail');
} catch (PDOException $e) {
    $failed = true;
}
checkStatus($failed && !$pdo->inTransaction(), 'Expected history failure and closed transaction');
checkStatus(statusSnapshot($pdo) === $before, 'History failure did not roll back changes');
echo "PASS: rollback on history insert failure\n";
