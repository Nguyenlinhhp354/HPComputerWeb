
<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/permission_check.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

yeu_cau_quyen('inventory.view');

// Quyen xac nhan phieu xuat.
// Vai tro 1 tam duoc coi la Admin trong database local.
$canConfirm = (
    co_quyen('inventory.confirm')
    || (int)($_SESSION['ma_vai_tro'] ?? 0) === 1
);

// Khoi tao CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function issueListEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$error = '';

// XU LY KHI NHAN NUT XAC NHAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$canConfirm) {
        http_response_code(403);
        exit('Ban khong co quyen xac nhan phieu xuat.');
    }

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        exit('Phien lam viec khong hop le.');
    }

    $issueId = filter_var(
        $_POST['ma_phieu_xuat'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$issueId) {
        $error = 'Ma phieu xuat khong hop le.';
    } else {

        try {
            // BAT DAU GIAO DICH
            $pdo->beginTransaction();

            // 1. Khoa phieu xuat
            $stmt = $pdo->prepare("
                SELECT
                    ma_phieu_xuat,
                    ma_don_hang,
                    loai_xuat,
                    trang_thai
                FROM phieu_xuat
                WHERE ma_phieu_xuat = ?
                FOR UPDATE
            ");

            $stmt->execute([$issueId]);
            $issue = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$issue) {
                throw new RuntimeException(
                    'Khong tim thay phieu xuat.'
                );
            }

            // Chi xac nhan phieu chua xu ly
            if ($issue['trang_thai'] !== 'DRAFT') {
                throw new RuntimeException(
                    'Phieu xuat da duoc xu ly hoac huy.'
                );
            }

            // Chi xu ly xuat kho doc lap.
            // Xuat theo don hang se lam o giai doan rieng.
            if (
                $issue['ma_don_hang'] !== null
                || $issue['loai_xuat'] === 'BAN_HANG'
            ) {
                throw new RuntimeException(
                    'Phieu lien ket don hang can xu ly rieng.'
                );
            }

            // 2. Lay chi tiet phieu
            $stmt = $pdo->prepare("
                SELECT
                    ma_san_pham,
                    SUM(so_luong) AS tong_so_luong
                FROM chi_tiet_phieu_xuat
                WHERE ma_phieu_xuat = ?
                GROUP BY ma_san_pham
                ORDER BY ma_san_pham
            ");

            $stmt->execute([$issueId]);
            $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$details) {
                throw new RuntimeException(
                    'Phieu xuat khong co san pham.'
                );
            }

            // 3. Lenh tru ton an toan
            $stockStmt = $pdo->prepare("
                UPDATE ton_kho
                SET so_luong = so_luong - :qty
                WHERE ma_san_pham = :product_id
                  AND so_luong >= :required_qty
            ");

            // 4. Lenh ghi lich su kho
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
                )
                VALUES (
                    :product_id,
                    :user_id,
                    'EXPORT',
                    'GIAM',
                    :qty,
                    'PHIEU_XUAT',
                    :issue_id,
                    :note
                )
            ");

            foreach ($details as $detail) {

                $productId = (int)$detail['ma_san_pham'];
                $quantity = (int)$detail['tong_so_luong'];

                if ($productId <= 0 || $quantity <= 0) {
                    throw new RuntimeException(
                        'Du lieu san pham xuat khong hop le.'
                    );
                }

                // TRU TON KHO
                $stockStmt->execute([
                    ':qty' => $quantity,
                    ':product_id' => $productId,
                    ':required_qty' => $quantity
                ]);

                // Neu khong tru duoc thi huy transaction
                if ($stockStmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Ton kho khong du de xuat san pham.'
                    );
                }

                // GHI LICH SU XUAT KHO
                $historyStmt->execute([
                    ':product_id' => $productId,
                    ':user_id' =>
                        (int)$_SESSION['ma_nguoi_dung'],
                    ':qty' => $quantity,
                    ':issue_id' => $issueId,
                    ':note' => 'Xac nhan phieu xuat kho'
                ]);
            }

            // 5. Chuyen phieu sang CONFIRMED
            $stmt = $pdo->prepare("
                UPDATE phieu_xuat
                SET trang_thai = 'CONFIRMED'
                WHERE ma_phieu_xuat = ?
                  AND trang_thai = 'DRAFT'
                  AND ma_don_hang IS NULL
            ");

            $stmt->execute([$issueId]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'Khong the xac nhan phieu xuat.'
                );
            }

            // 6. Luu tat ca thay doi
            $pdo->commit();

            header(
                'Location: ' . BASE_URL .
                '/inventory_issue_list.php?confirmed=1'
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

            $error = 'Khong the xac nhan phieu xuat.';
        }
    }
}

// LAY DANH SACH PHIEU XUAT
$stmt = $pdo->query("
    SELECT
        px.ma_phieu_xuat,
        px.ma_so_phieu,
        px.ngay_xuat,
        px.loai_xuat,
        px.trang_thai,
        sp.ten_san_pham,
        ct.so_luong
    FROM phieu_xuat px
    JOIN chi_tiet_phieu_xuat ct
        ON px.ma_phieu_xuat = ct.ma_phieu_xuat
    JOIN san_pham sp
        ON ct.ma_san_pham = sp.ma_san_pham
    ORDER BY px.ma_phieu_xuat DESC
    LIMIT 100
");

$issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Danh sách phiếu xuất kho';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="admin-layout">

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">

<div class="container-fluid py-4">

    <h2 class="fw-bold">
        Danh sách phiếu xuất kho
    </h2>

    <p class="text-muted">
        Quản lý và xác nhận xuất hàng khỏi kho
    </p>

    <?php if (isset($_GET['confirmed'])): ?>

        <div class="alert alert-success">
            Xác nhận phiếu xuất thành công!
            Tồn kho đã được cập nhật.
        </div>

    <?php endif; ?>

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">
            <?= issueListEscape($error) ?>
        </div>

    <?php endif; ?>

    <div class="mb-3">

        <a href="<?= issueListEscape(BASE_URL) ?>/inventory_issues.php"
           class="btn btn-primary">
            Tạo phiếu xuất
        </a>

        <a href="<?= issueListEscape(BASE_URL) ?>/inventory.php"
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
                        <th>Ngày xuất</th>
                        <th>Sản phẩm</th>
                        <th>Số lượng</th>
                        <th>Loại xuất</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($issues as $issue): ?>

                    <tr>

                        <td>
                            <?= issueListEscape(
                                $issue['ma_so_phieu']
                            ) ?>
                        </td>

                        <td>
                            <?= issueListEscape(
                                $issue['ngay_xuat']
                            ) ?>
                        </td>

                        <td>
                            <?= issueListEscape(
                                $issue['ten_san_pham']
                            ) ?>
                        </td>

                        <td>
                            <?= (int)$issue['so_luong'] ?>
                        </td>

                        <td>
                            <?= issueListEscape(
                                $issue['loai_xuat']
                            ) ?>
                        </td>

                        <td>
                            <?= issueListEscape(
                                $issue['trang_thai']
                            ) ?>
                        </td>

                        <td>

                        <?php if (
                            $issue['trang_thai'] === 'DRAFT'
                            && $canConfirm
                            && $issue['loai_xuat'] !== 'BAN_HANG'
                        ): ?>

                            <form method="POST"
                                  onsubmit="return confirm(
                                    'Xác nhận xuất kho?'
                                  );">

                                <input type="hidden"
                                       name="csrf_token"
                                       value="<?= issueListEscape(
                                           $_SESSION['csrf_token']
                                       ) ?>">

                                <input type="hidden"
                                       name="ma_phieu_xuat"
                                       value="<?= (int)$issue[
                                           'ma_phieu_xuat'
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

                <?php if (!$issues): ?>

                    <tr>
                        <td colspan="7" class="text-center">
                            Chưa có phiếu xuất kho.
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
