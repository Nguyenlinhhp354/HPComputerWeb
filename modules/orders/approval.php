<?php
/** Duyệt và ghi lịch sử cùng lúc; hàm này không thay đổi tồn kho. */
function approveOrder(PDO $pdo, int $orderId, int $staffId): void
{
    $pdo->beginTransaction();
    try {
        // Khóa đơn để hai yêu cầu duyệt đồng thời không cùng ghi lịch sử.
        $stmt = $pdo->prepare('SELECT trang_thai_don_hang FROM don_hang WHERE ma_don_hang = :id FOR UPDATE');
        $stmt->execute(['id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new DomainException('Không tìm thấy đơn hàng.');
        }
        if ($order['trang_thai_don_hang'] !== 'PENDING') {
            throw new DomainException('Chỉ có thể duyệt đơn đang chờ duyệt. Đơn này có thể đã được xử lý trước đó.');
        }

        $stmt = $pdo->prepare('SELECT ma_chi_tiet_don_hang FROM chi_tiet_don_hang WHERE ma_don_hang = :id FOR UPDATE');
        $stmt->execute(['id' => $orderId]);
        if (!$stmt->fetchColumn()) {
            throw new DomainException('Không thể duyệt đơn chưa có sản phẩm.');
        }

        $stmt = $pdo->prepare("UPDATE don_hang SET trang_thai_don_hang = 'CONFIRMED' WHERE ma_don_hang = :id");
        $stmt->execute(['id' => $orderId]);

        $stmt = $pdo->prepare("INSERT INTO lich_su_trang_thai_don_hang
            (ma_don_hang, ma_nguoi_thuc_hien, trang_thai_cu, trang_thai_moi, ghi_chu)
            VALUES (:id, :staff, 'PENDING', 'CONFIRMED', :note)");
        $stmt->execute(['id' => $orderId, 'staff' => $staffId, 'note' => 'Nhân viên xác nhận duyệt đơn hàng.']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
