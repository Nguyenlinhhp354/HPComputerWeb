
<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/permission_check.php';

// Phai co quyen phe duyet rieng
yeu_cau_quyen('inventory.stocktake.approve');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Phuong thuc khong hop le.');
}

$token = $_POST['csrf_token'] ?? null;
$action = $_POST['action'] ?? null;

$stocktakeId = filter_var(
    $_POST['ma_phieu_kiem_ke'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (
    !is_string($token)
    || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $token)
) {
    http_response_code(403);
    exit('Phien lam viec khong hop le.');
}

if (
    !is_int($stocktakeId)
    || !in_array($action, ['approve', 'reject'], true)
) {
    http_response_code(400);
    exit('Yeu cau khong hop le.');
}

$userId = (int)$_SESSION['ma_nguoi_dung'];

try {
    $pdo->beginTransaction();

    // 1. Khoa phieu kiem ke
    $stmt = $pdo->prepare("
        SELECT
            ma_phieu_kiem_ke,
            ma_so_phieu,
            ma_nguoi_lap,
            trang_thai
        FROM phieu_kiem_ke
        WHERE ma_phieu_kiem_ke = ?
        FOR UPDATE
    ");

    $stmt->execute([$stocktakeId]);
    $stocktake = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stocktake) {
        throw new RuntimeException(
            'Khong tim thay phieu kiem ke.'
        );
    }

    if ($stocktake['trang_thai'] !== 'PENDING') {
        throw new RuntimeException(
            'Phieu khong con o trang thai cho duyet.'
        );
    }

    // Tach biet nguoi lap va nguoi duyet
    if ((int)$stocktake['ma_nguoi_lap'] === $userId) {
        throw new RuntimeException(
            'Nguoi lap phieu khong duoc tu phe duyet.'
        );
    }

    if ($action === 'approve') {

        // 2. Khoa chi tiet phieu
        $stmt = $pdo->prepare("
            SELECT
                ma_san_pham,
                so_luong_he_thong,
                so_luong_thuc_te
            FROM chi_tiet_phieu_kiem_ke
            WHERE ma_phieu_kiem_ke = ?
            ORDER BY ma_san_pham
            FOR UPDATE
        ");

        $stmt->execute([$stocktakeId]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$details) {
            throw new RuntimeException(
                'Phieu kiem ke khong co san pham.'
            );
        }

        $lockStock = $pdo->prepare("
            SELECT so_luong
            FROM ton_kho
            WHERE ma_san_pham = ?
            FOR UPDATE
        ");

        $updateStock = $pdo->prepare("
            UPDATE ton_kho
            SET so_luong = ?
            WHERE ma_san_pham = ?
              AND so_luong = ?
        ");

        $insertHistory = $pdo->prepare("
            INSERT INTO lich_su_kho (
                ma_san_pham,
                ma_nguoi_dung,
                loai_bien_dong,
                huong_bien_dong,
                so_luong,
                loai_chung_tu,
                ma_chung_tu,
                ghi_chu
            )
            VALUES (
                ?, ?, 'ADJUSTMENT', ?, ?,
                'DIEU_CHINH', ?, ?
            )
        ");

        // 3. Kiem tra va dieu chinh tung san pham
        foreach ($details as $detail) {

            $productId = (int)$detail['ma_san_pham'];
            $expected = (int)$detail['so_luong_he_thong'];
            $actual = (int)$detail['so_luong_thuc_te'];

            $lockStock->execute([$productId]);
            $current = $lockStock->fetchColumn();
            $lockStock->closeCursor();

            if ($current === false) {
                throw new RuntimeException(
                    'Khong tim thay ton kho san pham.'
                );
            }

            // Khong duyet neu ton kho thay doi
            // sau khi phieu duoc lap
            if ((int)$current !== $expected) {
                throw new RuntimeException(
                    'Ton kho da thay doi. Can kiem ke lai.'
                );
            }

            $difference = $actual - $expected;

            // Khong can ghi bien dong neu khong chenh lech
            if ($difference === 0) {
                continue;
            }

            // Cap nhat ton kho
            $updateStock->execute([
                $actual,
                $productId,
                $expected
            ]);

            if ($updateStock->rowCount() !== 1) {
                throw new RuntimeException(
                    'Khong the cap nhat ton kho.'
                );
            }

            $direction = $difference > 0
                ? 'TANG'
                : 'GIAM';

            $quantity = abs($difference);

            $note = 'Kiem ke '
                . $stocktake['ma_so_phieu']
                . ': '
                . $expected
                . ' -> '
                . $actual;

            // Ghi lich su dieu chinh
            $insertHistory->execute([
                $productId,
                $userId,
                $direction,
                $quantity,
                $stocktakeId,
                $note
            ]);
        }

        // 4. Chuyen trang thai APPROVED
        $stmt = $pdo->prepare("
            UPDATE phieu_kiem_ke
            SET
                trang_thai = 'APPROVED',
                ma_nguoi_duyet = ?,
                ngay_duyet = NOW()
            WHERE ma_phieu_kiem_ke = ?
              AND trang_thai = 'PENDING'
        ");

        $stmt->execute([$userId, $stocktakeId]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Khong the phe duyet phieu.'
            );
        }

        $message = 'Da phe duyet va dieu chinh ton kho.';

    } else {

        // Tu choi phieu: khong thay doi ton kho
        $stmt = $pdo->prepare("
            UPDATE phieu_kiem_ke
            SET
                trang_thai = 'REJECTED',
                ma_nguoi_duyet = ?,
                ngay_duyet = NOW()
            WHERE ma_phieu_kiem_ke = ?
              AND trang_thai = 'PENDING'
        ");

        $stmt->execute([$userId, $stocktakeId]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Khong the tu choi phieu.'
            );
        }

        $message = 'Da tu choi phieu kiem ke.';
    }

    $pdo->commit();

    $_SESSION['stocktake_flash'] = [
        'type' => 'success',
        'message' => $message
    ];

} catch (RuntimeException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['stocktake_flash'] = [
        'type' => 'danger',
        'message' => $e->getMessage()
    ];

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    $_SESSION['stocktake_flash'] = [
        'type' => 'danger',
        'message' => 'Xu ly phieu kiem ke that bai.'
    ];
}

header(
    'Location: ' . BASE_URL .
    '/inventory_stocktake_list.php'
);
exit;
