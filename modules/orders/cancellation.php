<?php
/** Hủy đơn chưa thu tiền/chưa xuất kho và ghi lịch sử trong một transaction. */
function cancelOrder(PDO $pdo, int $orderId, int $staffId, string $reason): void
{
    $reason = trim($reason);
    if ($reason === '' || mb_strlen($reason, 'UTF-8') > 1000) {
        throw new DomainException('Vui lòng nhập lý do hủy từ 1 đến 1.000 ký tự.');
    }

    $pdo->beginTransaction();
    try {
        // Các chức năng thanh toán/xuất kho sau này cũng cần khóa đơn trước khi ghi.
        $stmt = $pdo->prepare('SELECT trang_thai_don_hang, trang_thai_thanh_toan
            FROM don_hang WHERE ma_don_hang = :id FOR UPDATE');
        $stmt->execute(['id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new DomainException('Không tìm thấy đơn hàng.');
        }
        if (!in_array($order['trang_thai_don_hang'], ['PENDING', 'CONFIRMED'], true)) {
            throw new DomainException('Chỉ được hủy đơn chờ duyệt hoặc đã duyệt.');
        }
        if ($order['trang_thai_thanh_toan'] !== 'UNPAID') {
            throw new DomainException('Đơn đã có thanh toán hoặc hoàn tiền cần được xử lý riêng trước khi hủy.');
        }

        // Kiểm tra cả giao dịch thực tế, phòng trường hợp trạng thái đơn chưa đồng bộ.
        $stmt = $pdo->prepare("SELECT ma_thanh_toan FROM thanh_toan
            WHERE ma_don_hang = :id AND trang_thai IN ('PENDING', 'SUCCESS', 'REFUNDED') FOR UPDATE");
        $stmt->execute(['id' => $orderId]);
        if ($stmt->fetchColumn()) {
            throw new DomainException('Đơn có giao dịch đang chờ, đã thu tiền hoặc hoàn tiền. Cần xử lý giao dịch trước khi hủy.');
        }

        $stmt = $pdo->prepare("SELECT ma_lich_su_kho FROM lich_su_kho
            WHERE loai_chung_tu = 'DON_HANG' AND ma_chung_tu = :id
              AND huong_bien_dong = 'GIAM' FOR UPDATE");
        $stmt->execute(['id' => $orderId]);
        if ($stmt->fetchColumn()) {
            throw new DomainException('Đơn đã ghi nhận xuất kho. Cần xử lý hoàn kho riêng trước khi hủy.');
        }

        $stmt = $pdo->prepare("UPDATE don_hang SET trang_thai_don_hang = 'CANCELLED' WHERE ma_don_hang = :id");
        $stmt->execute(['id' => $orderId]);
        $stmt = $pdo->prepare("INSERT INTO lich_su_trang_thai_don_hang
            (ma_don_hang, ma_nguoi_thuc_hien, trang_thai_cu, trang_thai_moi, ly_do_thay_doi)
            VALUES (:id, :staff, :old_status, 'CANCELLED', :reason)");
        $stmt->execute([
            'id' => $orderId, 'staff' => $staffId,
            'old_status' => $order['trang_thai_don_hang'], 'reason' => $reason,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
