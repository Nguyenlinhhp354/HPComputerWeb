
<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/permission_check.php';

yeu_cau_quyen('inventory.view');


$canSubmit = (
    co_quyen('inventory.create')
    || (int)($_SESSION['ma_vai_tro'] ?? 0) === 1
);

$canApprove = co_quyen('inventory.stocktake.approve');


$userId = (int)$_SESSION['ma_nguoi_dung'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function stocktakeListEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$error = '';

$approvalFeedback = $_SESSION['stocktake_flash'] ?? null;
unset($_SESSION['stocktake_flash']);


// XU LY GUI PHIEU KIEM KE CHO DUYET
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? null;

    $stocktakeId = filter_var(
        $_POST['ma_phieu_kiem_ke'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$canSubmit) {
        http_response_code(403);
        $error = 'Ban khong co quyen gui phieu.';

    } elseif (
        !is_string($token)
        || !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        $error = 'Phien lam viec khong hop le.';

    } elseif (($_POST['action'] ?? '') !== 'submit') {
        $error = 'Thao tac khong hop le.';

    } elseif ($stocktakeId === false) {
        $error = 'Ma phieu khong hop le.';

    } else {
        try {
            $pdo->beginTransaction();

            // Khoa phieu, tranh gui trung
            $stmt = $pdo->prepare("
                SELECT
                    ma_phieu_kiem_ke,
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

            if ((int)$stocktake['ma_nguoi_lap'] !== $userId) {
                throw new RuntimeException(
                    'Ban khong phai nguoi lap phieu.'
                );
            }

            if ($stocktake['trang_thai'] !== 'DRAFT') {
                throw new RuntimeException(
                    'Phieu da duoc gui hoac xu ly.'
                );
            }

            // Kiem tra phieu co san pham
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM chi_tiet_phieu_kiem_ke
                WHERE ma_phieu_kiem_ke = ?
            ");

            $stmt->execute([$stocktakeId]);

            if ((int)$stmt->fetchColumn() < 1) {
                throw new RuntimeException(
                    'Phieu kiem ke chua co san pham.'
                );
            }

            // Chuyen trang thai sang PENDING
            $stmt = $pdo->prepare("
                UPDATE phieu_kiem_ke
                SET trang_thai = 'PENDING'
                WHERE ma_phieu_kiem_ke = ?
                  AND trang_thai = 'DRAFT'
            ");

            $stmt->execute([$stocktakeId]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'Khong the gui phieu kiem ke.'
                );
            }

            // Khong cap nhat ton kho o buoc nay
            $pdo->commit();

            header(
                'Location: ' . BASE_URL .
                '/inventory_stocktake_list.php?submitted=1'
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
            $error = 'Khong the gui phieu kiem ke.';
        }
    }
}

// LAY DANH SACH PHIEU
$stmt = $pdo->query("
    SELECT
        kk.ma_phieu_kiem_ke,
        kk.ma_so_phieu,
        kk.ngay_kiem_ke,
        kk.ma_nguoi_lap,
        kk.trang_thai,
        kk.ly_do,
        sp.ten_san_pham,
        ct.so_luong_he_thong,
        ct.so_luong_thuc_te
    FROM phieu_kiem_ke kk
    JOIN chi_tiet_phieu_kiem_ke ct
        ON kk.ma_phieu_kiem_ke = ct.ma_phieu_kiem_ke
    JOIN san_pham sp
        ON ct.ma_san_pham = sp.ma_san_pham
    ORDER BY kk.ma_phieu_kiem_ke DESC
    LIMIT 100
");

$stocktakes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Danh sach phieu kiem ke';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid py-4">

            <h2 class="fw-bold">
                Danh sách phiếu kiểm kê kho
            </h2>

            <p class="text-muted">
                Quản lý phiếu kiểm kê và gửi phê duyệt
            </p>

<?php if (is_array($approvalFeedback)): ?>
    <div class="alert alert-<?=
        ($approvalFeedback['type'] ?? '') === 'success'
            ? 'success' : 'danger'
    ?>">
        <?= stocktakeListEscape(
            $approvalFeedback['message'] ?? ''
        ) ?>
    </div>
<?php endif; ?>

            <?php if (isset($_GET['submitted'])): ?>
                <div class="alert alert-success">
                    Đã gửi phiếu kiểm kê chờ phê duyệt.
                    Tồn kho chưa thay đổi.
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger">
                    <?= stocktakeListEscape($error) ?>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <a
                    href="<?= stocktakeListEscape(BASE_URL) ?>/inventory_stocktake.php"
                    class="btn btn-primary"
                >
                    Tạo phiếu kiểm kê
                </a>

                <a
                    href="<?= stocktakeListEscape(BASE_URL) ?>/inventory.php"
                    class="btn btn-secondary"
                >
                    Quản lý kho
                </a>
            </div>

            <div class="card p-4">
                <div class="table-responsive">

                    <table class="table table-bordered table-hover">

                        <thead>
                            <tr>
                                <th>Mã phiếu</th>
                                <th>Ngày kiểm kê</th>
                                <th>Sản phẩm</th>
                                <th>Tồn hệ thống</th>
                                <th>Thực tế</th>
                                <th>Chênh lệch</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($stocktakes as $item): ?>

                                <?php
                                    $difference =
                                        (int)$item['so_luong_thuc_te']
                                        - (int)$item['so_luong_he_thong'];
                                ?>

                                <tr>
                                    <td>
                                        <?= stocktakeListEscape($item['ma_so_phieu']) ?>
                                    </td>

                                    <td>
                                        <?= stocktakeListEscape($item['ngay_kiem_ke']) ?>
                                    </td>

                                    <td>
                                        <?= stocktakeListEscape($item['ten_san_pham']) ?>
                                    </td>

                                    <td>
                                        <?= (int)$item['so_luong_he_thong'] ?>
                                    </td>

                                    <td>
                                        <?= (int)$item['so_luong_thuc_te'] ?>
                                    </td>

                                    <td>
                                        <?= $difference > 0 ? '+' : '' ?>
                                        <?= $difference ?>
                                    </td>

                                    <td>
                                        <?= stocktakeListEscape($item['trang_thai']) ?>
                                    </td>

                                    <td>
                                        <?php if (
                                            $item['trang_thai'] === 'DRAFT'
                                            && $canSubmit
                                            && (int)$item['ma_nguoi_lap'] === $userId
                                        ): ?>

                                            <form method="POST"
                                                onsubmit="return confirm('Gửi phiếu kiểm kê này để phê duyệt?');">

                                                <input type="hidden"
                                                    name="csrf_token"
                                                    value="<?= stocktakeListEscape($_SESSION['csrf_token']) ?>">

                                                <input type="hidden"
                                                    name="action"
                                                    value="submit">

                                                <input type="hidden"
                                                    name="ma_phieu_kiem_ke"
                                                    value="<?= (int)$item['ma_phieu_kiem_ke'] ?>">

                                                <button type="submit"
                                                    class="btn btn-success btn-sm">
                                                    Gửi duyệt
                                                </button>
                                            </form>



<?php elseif (
    $item['trang_thai'] === 'PENDING'
    && $canApprove
    && (int)$item['ma_nguoi_lap'] !== $userId
): ?>

<div class="d-flex gap-2 flex-wrap">

    <form method="POST"
          action="<?= stocktakeListEscape(BASE_URL) ?>/inventory_stocktake_approve.php"
          onsubmit="return confirm('Xác nhận phê duyệt và điều chỉnh tồn kho?');">

        <input type="hidden"
               name="csrf_token"
               value="<?= stocktakeListEscape($_SESSION['csrf_token']) ?>">

        <input type="hidden"
               name="ma_phieu_kiem_ke"
               value="<?= (int)$item['ma_phieu_kiem_ke'] ?>">

        <input type="hidden"
               name="action"
               value="approve">

        <button type="submit"
                class="btn btn-success btn-sm">
            Phê duyệt
        </button>
    </form>

    <form method="POST"
          action="<?= stocktakeListEscape(BASE_URL) ?>/inventory_stocktake_approve.php"
          onsubmit="return confirm('Xác nhận từ chối phiếu kiểm kê?');">

        <input type="hidden"
               name="csrf_token"
               value="<?= stocktakeListEscape($_SESSION['csrf_token']) ?>">

        <input type="hidden"
               name="ma_phieu_kiem_ke"
               value="<?= (int)$item['ma_phieu_kiem_ke'] ?>">

        <input type="hidden"
               name="action"
               value="reject">

        <button type="submit"
                class="btn btn-danger btn-sm">
            Từ chối
        </button>
    </form>

</div>
<?php else: ?>
                                            Không có thao tác
                                        <?php endif; ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                            <?php if (!$stocktakes): ?>
                                <tr>
                                    <td colspan="8"
                                        class="text-center">
                                        Chưa có phiếu kiểm kê.
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
