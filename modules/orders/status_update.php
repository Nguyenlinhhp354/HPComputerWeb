<?php
require_once __DIR__ . '/helpers.php';

function updateOrderStatus(PDO $pdo, int $orderId, int $staffId, string $newStatus, string $note = ''): void
{
    $note = trim($note);
    if (!in_array($newStatus, ORDER_NEXT_STATUSES, true)) {
        throw new DomainException('Trạng thái cập nhật không hợp lệ.');
    }
    if (mb_strlen($note, 'UTF-8') > 1000) {
        throw new DomainException('Ghi chú không được vượt quá 1.000 ký tự.');
    }

    $pdo->beginTransaction();
    try {
        // Cùng khóa đơn như chức năng duyệt/hủy để tránh cập nhật chồng chéo.
        $stmt = $pdo->prepare('SELECT trang_thai_don_hang FROM don_hang WHERE ma_don_hang = :id FOR UPDATE');
        $stmt->execute(['id' => $orderId]);
        $oldStatus = $stmt->fetchColumn();
        if ($oldStatus === false) {
            throw new DomainException('Không tìm thấy đơn hàng.');
        }
        if ((ORDER_NEXT_STATUSES[$oldStatus] ?? null) !== $newStatus) {
            throw new DomainException('Không thể chuyển sang trạng thái này. Đơn có thể đã được người khác xử lý; vui lòng kiểm tra lại trạng thái hiện tại.');
        }

        $stmt = $pdo->prepare('SELECT ma_san_pham, so_luong FROM chi_tiet_don_hang WHERE ma_don_hang = :id FOR UPDATE');
        $stmt->execute(['id' => $orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$items) {
            throw new DomainException('Không thể cập nhật đơn chưa có sản phẩm.');
        }
        $requiredQuantities = [];
        foreach ($items as $item) {
            if ((int)$item['so_luong'] <= 0) {
                throw new DomainException('Số lượng sản phẩm trong đơn không hợp lệ.');
            }
            $productId = (int)$item['ma_san_pham'];
            $requiredQuantities[$productId] = ($requiredQuantities[$productId] ?? 0) + (int)$item['so_luong'];
        }

        if (in_array($newStatus, ['SHIPPING', 'COMPLETED'], true)) {
            // Chỉ đọc lịch sử kho. Không tự xuất kho và không trừ tồn lần nữa.
            // Phần kho cần ghi đúng DON_HANG + mã đơn, và khóa đơn trước khi ghi.
            $stmt = $pdo->prepare("SELECT ma_san_pham, so_luong, huong_bien_dong FROM lich_su_kho
                WHERE loai_chung_tu = 'DON_HANG' AND ma_chung_tu = :id
                  AND ((loai_bien_dong = 'EXPORT' AND huong_bien_dong = 'GIAM')
                    OR (loai_bien_dong = 'RETURN' AND huong_bien_dong = 'TANG')) FOR UPDATE");
            $stmt->execute(['id' => $orderId]);
            $exportedQuantities = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $movement) {
                $productId = (int)$movement['ma_san_pham'];
                $quantity = (int)$movement['so_luong'];
                // Hàng đã hoàn lại kho không được tính là hàng đang xuất cho đơn.
                $exportedQuantities[$productId] = ($exportedQuantities[$productId] ?? 0)
                    + ($movement['huong_bien_dong'] === 'GIAM' ? $quantity : -$quantity);
            }
            foreach ($requiredQuantities as $productId => $quantity) {
                if (($exportedQuantities[$productId] ?? 0) < $quantity) {
                    throw new DomainException('Đơn chưa được xuất kho đủ sản phẩm. Vui lòng liên hệ nhân viên kho trước khi chuyển sang giao hàng hoặc hoàn thành.');
                }
            }
        }

        $stmt = $pdo->prepare('UPDATE don_hang SET trang_thai_don_hang = :status WHERE ma_don_hang = :id');
        $stmt->execute(['status' => $newStatus, 'id' => $orderId]);
        $stmt = $pdo->prepare('INSERT INTO lich_su_trang_thai_don_hang
            (ma_don_hang, ma_nguoi_thuc_hien, trang_thai_cu, trang_thai_moi, ghi_chu)
            VALUES (:id, :staff, :old_status, :new_status, :note)');
        $stmt->execute([
            'id' => $orderId, 'staff' => $staffId, 'old_status' => $oldStatus,
            'new_status' => $newStatus, 'note' => $note !== '' ? $note : 'Cập nhật trạng thái đơn hàng.',
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
