
<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/permission_check.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

yeu_cau_quyen('inventory.view');

// Admin duoc xac nhan trong giai doan thu nghiem.
// Nhan vien khac phai co quyen inventory.confirm.
$canConfirm = (
    (int)($_SESSION['ma_vai_tro'] ?? 0) === 1
    || co_quyen('inventory.confirm')
);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function receiptListEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$error = '';

// Xu ly xac nhan phieu nhap
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$canConfirm) {
        http_response_code(403);
        exit('Ban khong co quyen xac nhan phieu nhap.');
    }

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        exit('Phien lam viec khong hop le.');
    }

    $receiptId = filter_var(
        $_POST['ma_phieu_nhap'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$receiptId) {
        $error = 'Ma phieu nhap khong hop le.';
    } else {

        try {
            $pdo->beginTransaction();

            // 1. Khoa phieu de tranh xac nhan trung
            $stmt = $pdo->prepare("
                SELECT ma_phieu_nhap, trang_thai
                FROM phieu_nhap
                WHERE ma_phieu_nhap = ?
                FOR UPDATE
            ");

            $stmt->execute([$receiptId]);
            $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$receipt) {
                throw new RuntimeException(
                    'Khong tim thay phieu nhap.'
                );
            }

            if ($receipt['trang_thai'] !== 'DRAFT') {
                throw new RuntimeException(
                    'Phieu nhap da duoc xu ly hoac huy.'
                );
            }

            // 2. Lay chi tiet phieu nhap
            $stmt = $pdo->prepare("
                SELECT ma_san_pham,
                       SUM(so_luong) AS tong_so_luong
                FROM chi_tiet_phieu_nhap
                WHERE ma_phieu_nhap = ?
                GROUP BY ma_san_pham
            ");

            $stmt->execute([$receiptId]);
            $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$details) {
                throw new RuntimeException(
                    'Phieu nhap khong co san pham.'
                );
            }

            // 3. Cau lenh cong ton kho
            $stockStmt = $pdo->prepare("
                INSERT INTO ton_kho
                    (ma_san_pham, so_luong)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                    so_luong = so_luong + VALUES(so_luong)
            ");

            // 4. Cau lenh ghi lich su kho
            $historyStmt = $pdo->prepare("
                INSERT INTO lich_su_kho (
                    ma_san_pham,
                    ma_nguoi_dung,
                    loai_bien_dong,
                    huong_bien_dong,
                    so_luong,
                    loai_chung_tu,
                    ma_chung_tu,
                    ghi_chu
                ) VALUES (
                    ?, ?, 'IMPORT', 'TANG',
                    ?, 'PHIEU_NHAP', ?, ?
                )
            ");

            foreach ($details as $detail) {

                $productId = (int)$detail['ma_san_pham'];
                $quantity = (int)$detail['tong_so_luong'];

                if ($quantity <= 0) {
                    throw new RuntimeException(
                        'So luong nhap khong hop le.'
                    );
                }

                // Cong so luong ton
                $stockStmt->execute([
                    $productId,
                    $quantity
                ]);

                // Luu lich su bien dong
                $historyStmt->execute([
                    $productId,
                    (int)$_SESSION['ma_nguoi_dung'],
                    $quantity,
                    $receiptId,
                    'Xac nhan nhap kho'
                ]);
            }

            // 5. Cap nhat trang thai phieu
            $stmt = $pdo->prepare("
                UPDATE phieu_nhap
                SET trang_thai = 'CONFIRMED'
                WHERE ma_phieu_nhap = ?
                  AND trang_thai = 'DRAFT'
            ");

            $stmt->execute([$receiptId]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'Khong the cap nhat trang thai phieu.'
                );
            }

            // 6. Hoan tat tat ca thay doi
            $pdo->commit();

            header(
                'Location: ' . BASE_URL .
                '/inventory_receipt_list.php?confirmed=1'
            );
            exit;

        } catch (RuntimeException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $error = 'Khong the xac nhan phieu nhap.';
        }
    }
}

// Lay danh sach phieu nhap
$stmt = $pdo->query("
    SELECT
        pn.ma_phieu_nhap,
        pn.ma_so_phieu,
        pn.ngay_nhap,
        pn.tong_tien,
        pn.trang_thai,
        ncc.ten_nha_cung_cap
    FROM phieu_nhap pn
    INNER JOIN nha_cung_cap ncc
        ON pn.ma_nha_cung_cap = ncc.ma_nha_cung_cap
    ORDER BY pn.ma_phieu_nhap DESC
    LIMIT 50
");

$receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Danh sách phiếu nhập kho';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="admin-layout">

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">

<div class="container-fluid py-4">

    <h2 class="fw-bold">Danh sách phiếu nhập kho</h2>

    <p class="text-muted">
        Quản lý và xác nhận phiếu nhập hàng
    </p>

    <?php if (isset($_GET['confirmed'])): ?>
        <div class="alert alert-success">
            Xác nhận phiếu nhập thành công!
            Số lượng tồn kho đã được cập nhật.
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= receiptListEscape($error) ?>
        </div>
    <?php endif; ?>

    <div class="mb-3">

        <a href="<?= receiptListEscape(BASE_URL) ?>/inventory_receipts.php"
           class="btn btn-primary">
            Tạo phiếu nhập
        </a>

        <a href="<?= receiptListEscape(BASE_URL) ?>/inventory.php"
           class="btn btn-secondary">
            Quản lý kho
        </a>

    </div>

    <div class="card p-4">

        <div class="table-responsive">

            <table class="table table-bordered table-hover">

                <thead>
                    <tr>
                        <th>Mã phiếu</th>
                        <th>Ngày nhập</th>
                        <th>Nhà cung cấp</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($receipts as $receipt): ?>

                    <tr>
                        <td>
                            <?= receiptListEscape(
                                $receipt['ma_so_phieu']
                            ) ?>
                        </td>

                        <td>
                            <?= receiptListEscape(
                                $receipt['ngay_nhap']
                            ) ?>
                        </td>

                        <td>
                            <?= receiptListEscape(
                                $receipt['ten_nha_cung_cap']
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                (float)$receipt['tong_tien'],
                                0,
                                ',',
                                '.'
                            ) ?> VNĐ
                        </td>

                        <td>
                            <?= receiptListEscape(
                                $receipt['trang_thai']
                            ) ?>
                        </td>

                        <td>

                        <?php if (
                            $receipt['trang_thai'] === 'DRAFT'
                            && $canConfirm
                        ): ?>

                            <form method="POST"
                                  onsubmit="return confirm(
                                    'Xác nhận nhập kho?'
                                  );">

                                <input type="hidden"
                                       name="csrf_token"
                                       value="<?= receiptListEscape(
                                           $_SESSION['csrf_token']
                                       ) ?>">

                                <input type="hidden"
                                       name="ma_phieu_nhap"
                                       value="<?= (int)$receipt[
                                           'ma_phieu_nhap'
                                       ] ?>">

                                <button type="submit"
                                        class="btn btn-success btn-sm">
                                    Xác nhận
                                </button>

                            </form>

                        <?php else: ?>

                            <span class="text-muted">
                                Không có thao tác
                            </span>

                        <?php endif; ?>

                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if (!$receipts): ?>
                    <tr>
                        <td colspan="6" class="text-center">
                            Chưa có phiếu nhập kho.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>
            </table>

        </div>

    </div>

</div>

</main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
