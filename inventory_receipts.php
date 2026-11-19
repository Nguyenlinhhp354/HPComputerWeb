
<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/permission_check.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

yeu_cau_quyen('inventory.view');

// Admin duoc tao phieu trong buoc thu nghiem.
// Nhan vien kho can duoc cap quyen inventory.create.
$canCreate = (
    (int)($_SESSION['ma_vai_tro'] ?? 0) === 1
    || co_quyen('inventory.create')
);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function receiptEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$error = '';

// Lay nha cung cap dang hoat dong
$suppliers = $pdo->query("
    SELECT ma_nha_cung_cap, ten_nha_cung_cap
    FROM nha_cung_cap
    WHERE trang_thai = 'ACTIVE'
    ORDER BY ten_nha_cung_cap
")->fetchAll(PDO::FETCH_ASSOC);

// Lay san pham dang kinh doanh
$products = $pdo->query("
    SELECT ma_san_pham, ma_sku, ten_san_pham
    FROM san_pham
    WHERE trang_thai = 'ACTIVE'
    ORDER BY ten_san_pham
")->fetchAll(PDO::FETCH_ASSOC);

// Xu ly luu phieu nhap
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    $supplierId = filter_var(
        $_POST['ma_nha_cung_cap'] ?? null,
        FILTER_VALIDATE_INT
    );

    $productId = filter_var(
        $_POST['ma_san_pham'] ?? null,
        FILTER_VALIDATE_INT
    );

    $quantity = filter_var(
        $_POST['so_luong'] ?? null,
        FILTER_VALIDATE_INT
    );

    $price = trim((string)($_POST['don_gia_nhap'] ?? ''));
    $note = trim((string)($_POST['ghi_chu'] ?? ''));

    if (!$canCreate) {
        http_response_code(403);
        $error = 'Bạn không có quyền tạo phiếu nhập.';

    } elseif (
        !is_string($token)
        || !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = 'Phiên làm việc không hợp lệ.';

    } elseif (
        !$supplierId || !$productId ||
        !$quantity || $quantity <= 0 ||
        !preg_match('/^\d{1,12}(\.\d{1,2})?$/', $price) ||
        (float)$price <= 0 ||
        strlen($note) > 5000
    ) {
        $error = 'Vui lòng kiểm tra thông tin phiếu nhập.';

    } else {

        $total = number_format(
            $quantity * (float)$price,
            2,
            '.',
            ''
        );

        if ((float)$total >= 10000000000000) {
            $error = 'Tổng tiền vượt giới hạn cho phép.';

        } else {

            try {
                $pdo->beginTransaction();

                // Kiem tra nha cung cap
                $check = $pdo->prepare("
                    SELECT 1 FROM nha_cung_cap
                    WHERE ma_nha_cung_cap = ?
                    AND trang_thai = 'ACTIVE'
                ");
                $check->execute([$supplierId]);

                if (!$check->fetchColumn()) {
                    throw new RuntimeException(
                        'Nhà cung cấp không hợp lệ.'
                    );
                }

                // Kiem tra san pham
                $check = $pdo->prepare("
                    SELECT 1 FROM san_pham
                    WHERE ma_san_pham = ?
                    AND trang_thai = 'ACTIVE'
                ");
                $check->execute([$productId]);

                if (!$check->fetchColumn()) {
                    throw new RuntimeException(
                        'Sản phẩm không hợp lệ.'
                    );
                }

                // Sinh ma phieu duy nhat
                $receiptCode = 'PN-' .
                    date('YmdHis') . '-' .
                    strtoupper(bin2hex(random_bytes(3)));

                // Tao phieu nhap DRAFT
                $stmt = $pdo->prepare("
                    INSERT INTO phieu_nhap (
                        ma_nha_cung_cap,
                        ma_nguoi_dung,
                        ma_so_phieu,
                        tong_tien,
                        trang_thai,
                        ghi_chu
                    ) VALUES (
                        ?, ?, ?, ?, 'DRAFT', ?
                    )
                ");

                $stmt->execute([
                    $supplierId,
                    (int)$_SESSION['ma_nguoi_dung'],
                    $receiptCode,
                    $total,
                    $note !== '' ? $note : null
                ]);

                $receiptId = (int)$pdo->lastInsertId();

                // Them chi tiet phieu nhap
                $stmt = $pdo->prepare("
                    INSERT INTO chi_tiet_phieu_nhap (
                        ma_phieu_nhap,
                        ma_san_pham,
                        so_luong,
                        don_gia_nhap,
                        thanh_tien
                    ) VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $receiptId,
                    $productId,
                    $quantity,
                    $price,
                    $total
                ]);

                $pdo->commit();

                // Khong cong ton kho khi phieu la DRAFT
                header(
                    'Location: ' . BASE_URL .
                    '/inventory_receipts.php?created=1'
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
                $error = 'Không lưu được phiếu nhập.';
            }
        }
    }
}

$pageTitle = 'Tạo phiếu nhập kho';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="admin-layout">

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">

    <div class="container-fluid py-4">

        <h2 class="fw-bold">Tạo phiếu nhập kho</h2>

        <p class="text-muted">
            Nhập sản phẩm từ nhà cung cấp
        </p>

        <?php if (isset($_GET['created'])): ?>
            <div class="alert alert-success">
                Đã tạo phiếu nhập nháp thành công.
                Tồn kho chưa thay đổi.
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger">
                <?= receiptEscape($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!$canCreate): ?>
            <div class="alert alert-warning">
                Tài khoản chưa có quyền lập phiếu nhập.
            </div>
        <?php else: ?>

        <div class="card p-4">

            <form method="POST">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= receiptEscape(
                           $_SESSION['csrf_token']
                       ) ?>">

                <div class="mb-3">
                    <label class="form-label">
                        Nhà cung cấp *
                    </label>

                    <select name="ma_nha_cung_cap"
                            class="form-select" required>

                        <option value="">
                            -- Chọn nhà cung cấp --
                        </option>

                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= (int)$s['ma_nha_cung_cap'] ?>">
                                <?= receiptEscape(
                                    $s['ten_nha_cung_cap']
                                ) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Sản phẩm *
                    </label>

                    <select name="ma_san_pham"
                            class="form-select" required>

                        <option value="">
                            -- Chọn sản phẩm --
                        </option>

                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int)$p['ma_san_pham'] ?>">
                                <?= receiptEscape(
                                    $p['ma_sku'] . ' - ' .
                                    $p['ten_san_pham']
                                ) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label class="form-label">
                            Số lượng nhập *
                        </label>

                        <input type="number"
                               name="so_luong"
                               min="1"
                               step="1"
                               class="form-control"
                               required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">
                            Đơn giá nhập *
                        </label>

                        <input type="number"
                               name="don_gia_nhap"
                               min="0.01"
                               step="0.01"
                               class="form-control"
                               required>
                    </div>

                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Ghi chú
                    </label>

                    <textarea name="ghi_chu"
                              maxlength="5000"
                              class="form-control"></textarea>
                </div>

                <button type="submit"
                        class="btn btn-primary"
                        <?= !$suppliers || !$products
                            ? 'disabled' : '' ?>>
                    <i class="bi bi-save me-1"></i>
                    Lưu phiếu nháp
                </button>

                <a href="<?= receiptEscape(BASE_URL) ?>/inventory.php"
                   class="btn btn-secondary">
                    Quay lại kho
                </a>

            </form>

        </div>

        <?php endif; ?>

    </div>

</main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
