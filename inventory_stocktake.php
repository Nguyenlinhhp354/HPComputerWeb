
<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/permission_check.php';

yeu_cau_quyen('inventory.view');

// Admin ma 1 tam duoc su dung khi kiem thu local.
// Khi trien khai can cap quyen kiem ke rieng.
$canCreate = co_quyen('inventory.create')
    || (int)($_SESSION['ma_vai_tro'] ?? 0) === 1;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function stocktakeEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$error = '';

// Lay danh sach san pham
$stmt = $pdo->query("
    SELECT
        sp.ma_san_pham,
        sp.ma_sku,
        sp.ten_san_pham,
        COALESCE(tk.so_luong, 0) AS so_luong_ton
    FROM san_pham sp
    LEFT JOIN ton_kho tk
        ON sp.ma_san_pham = tk.ma_san_pham
    WHERE sp.trang_thai = 'ACTIVE'
    ORDER BY sp.ten_san_pham
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// XU LY TAO PHIEU KIEM KE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? null;

    $productId = filter_var(
        $_POST['ma_san_pham'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $actualQty = filter_var(
        $_POST['so_luong_thuc_te'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );

    $reasonInput = $_POST['ly_do'] ?? '';
    $reason = is_string($reasonInput)
        ? trim($reasonInput)
        : '';

    if (!$canCreate) {
        http_response_code(403);
        $error = 'Bạn không có quyền lập phiếu kiểm kê.';

    } elseif (
        !is_string($token)
        || !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = 'Phiên làm việc không hợp lệ.';

    } elseif (
        !is_int($productId)
        || !is_int($actualQty)
        || $actualQty > 4294967295
        || strlen($reason) > 2000
    ) {
        $error = 'Thông tin kiểm kê không hợp lệ.';

    } else {

        try {
            $pdo->beginTransaction();

            // Lay ton kho thuc te tai thoi diem luu phieu
            $check = $pdo->prepare("
                SELECT tk.so_luong
                FROM ton_kho tk
                JOIN san_pham sp
                    ON tk.ma_san_pham = sp.ma_san_pham
                WHERE sp.ma_san_pham = ?
                  AND sp.trang_thai = 'ACTIVE'
                FOR UPDATE
            ");

            $check->execute([$productId]);
            $systemQty = $check->fetchColumn();

            if ($systemQty === false) {
                throw new RuntimeException(
                    'Sản phẩm chưa có bản ghi tồn kho hợp lệ.'
                );
            }

            // Sinh ma phieu kiem ke
            $code = 'KK-'
                . date('YmdHis')
                . '-'
                . strtoupper(bin2hex(random_bytes(3)));

            // Tao phieu nhap trang thai DRAFT
            $stmt = $pdo->prepare("
                INSERT INTO phieu_kiem_ke (
                    ma_so_phieu,
                    ma_nguoi_lap,
                    trang_thai,
                    ly_do
                )
                VALUES (?, ?, 'DRAFT', ?)
            ");

            $stmt->execute([
                $code,
                (int)$_SESSION['ma_nguoi_dung'],
                $reason !== '' ? $reason : null
            ]);

            $stocktakeId = (int)$pdo->lastInsertId();

            // Luu chi tiet kiem ke
            $stmt = $pdo->prepare("
                INSERT INTO chi_tiet_phieu_kiem_ke (
                    ma_phieu_kiem_ke,
                    ma_san_pham,
                    so_luong_he_thong,
                    so_luong_thuc_te
                )
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $stocktakeId,
                $productId,
                (int)$systemQty,
                $actualQty
            ]);

            // Chi luu phieu, KHONG sua ton kho
            $pdo->commit();

            header(
                'Location: ' . BASE_URL .
                '/inventory_stocktake.php?created=1'
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

            $error = 'Không thể lưu phiếu kiểm kê.';
        }
    }
}

$pageTitle = 'Tạo phiếu kiểm kê';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="container-fluid py-4">

            <h2 class="fw-bold">
                Tạo phiếu kiểm kê kho
            </h2>

            <p class="text-muted">
                Đối chiếu số lượng thực tế với tồn kho hệ thống
            </p>

            <?php if (isset($_GET['created'])): ?>
                <div class="alert alert-success">
                    Đã lưu phiếu kiểm kê nháp thành công.
                    Tồn kho chưa thay đổi.
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger">
                    <?= stocktakeEscape($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($canCreate): ?>

                <div class="card p-4">

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= stocktakeEscape(
                                $_SESSION['csrf_token']
                            ) ?>"
                        >

                        <div class="mb-3">
                            <label class="form-label">
                                Sản phẩm kiểm kê *
                            </label>

                            <select
                                name="ma_san_pham"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    -- Chọn sản phẩm --
                                </option>

                                <?php foreach ($products as $p): ?>

                                    <option
                                        value="<?= (int)$p['ma_san_pham'] ?>"
                                    >
                                        <?= stocktakeEscape(
                                            $p['ma_sku'] . ' - ' .
                                            $p['ten_san_pham'] .
                                            ' (Tồn hệ thống: ' .
                                            $p['so_luong_ton'] . ')'
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>
                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Số lượng kiểm kê thực tế *
                            </label>

                            <input
                                type="number"
                                name="so_luong_thuc_te"
                                class="form-control"
                                min="0"
                                step="1"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Lý do / Ghi chú
                            </label>

                            <textarea
                                name="ly_do"
                                class="form-control"
                                maxlength="2000"
                                rows="3"
                                placeholder="Nhập lý do chênh lệch..."
                            ></textarea>

                        </div>

                        <div class="alert alert-info">
                            Tạo phiếu kiểm kê chưa làm thay đổi
                            số lượng tồn kho.

                            Chênh lệch phải được kiểm tra và
                            phê duyệt trước khi điều chỉnh.
                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary"
                            <?= !$products ? 'disabled' : '' ?>
                        >
                            <i class="bi bi-save me-1"></i>
                            Lưu phiếu kiểm kê
                        </button>

                        <a
                            href="<?= stocktakeEscape(BASE_URL) ?>/inventory.php"
                            class="btn btn-secondary"
                        >
                            Quay lại kho
                        </a>

                    </form>

                </div>

            <?php else: ?>

                <div class="alert alert-warning">
                    Tài khoản chưa có quyền lập phiếu kiểm kê.
                </div>

            <?php endif; ?>

        </div>

    </main>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
