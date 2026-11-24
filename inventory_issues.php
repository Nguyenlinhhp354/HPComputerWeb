
<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/permission_check.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

yeu_cau_quyen('inventory.view');

// Phan quyen thu nghiem tuong tu phieu nhap.
// Gia dinh vai tro 1 la Admin trong database local.
// Khi trien khai can cap quyen inventory.create ro rang.
$canCreate = (
    (int)($_SESSION['ma_vai_tro'] ?? 0) === 1
    || co_quyen('inventory.create')
);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function issueEscape($value)
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

// Loai xuat cho giai doan thu nghiem.
// BAN_HANG se tich hop rieng voi module don hang.
$issueTypes = [
    'KHAC' => 'Xuất kho khác (kiểm thử)',
    'LAP_RAP' => 'Xuất linh kiện lắp ráp',
    'BAO_HANH' => 'Xuất bảo hành',
    'DIEU_CHUYEN' => 'Xuất điều chuyển'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    $productId = filter_var(
        $_POST['ma_san_pham'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $quantity = filter_var(
        $_POST['so_luong'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $type = $_POST['loai_xuat'] ?? '';
    $note = trim((string)($_POST['ghi_chu'] ?? ''));

    if (!$canCreate) {

        http_response_code(403);
        $error = 'Bạn không có quyền lập phiếu xuất.';

    } elseif (
        !is_string($token)
        || !hash_equals($_SESSION['csrf_token'], $token)
    ) {

        $error = 'Phiên làm việc không hợp lệ.';

    } elseif (
        !$productId
        || !$quantity
        || !is_string($type)
        || !array_key_exists($type, $issueTypes)
        || strlen($note) > 3000
    ) {

        $error = 'Vui lòng kiểm tra thông tin phiếu xuất.';

    } else {

        try {
            $pdo->beginTransaction();

            // Kiem tra san pham hop le
            $check = $pdo->prepare("
                SELECT ma_san_pham
                FROM san_pham
                WHERE ma_san_pham = ?
                  AND trang_thai = 'ACTIVE'
            ");

            $check->execute([$productId]);

            if (!$check->fetchColumn()) {
                throw new RuntimeException(
                    'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh.'
                );
            }

            // Sinh ma phieu xuat duy nhat
            $issueCode = 'PX-'
                . date('YmdHis')
                . '-'
                . strtoupper(bin2hex(random_bytes(3)));

            // Tao phieu xuat DRAFT
            $stmt = $pdo->prepare("
                INSERT INTO phieu_xuat (
                    ma_so_phieu,
                    ma_nguoi_dung,
                    ma_don_hang,
                    loai_xuat,
                    trang_thai,
                    ghi_chu
                )
                VALUES (?, ?, NULL, ?, 'DRAFT', ?)
            ");

            $stmt->execute([
                $issueCode,
                (int)$_SESSION['ma_nguoi_dung'],
                $type,
                $note !== '' ? $note : null
            ]);

            $issueId = (int)$pdo->lastInsertId();

            // Luu chi tiet phieu xuat
            $stmt = $pdo->prepare("
                INSERT INTO chi_tiet_phieu_xuat (
                    ma_phieu_xuat,
                    ma_san_pham,
                    so_luong
                )
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $issueId,
                $productId,
                $quantity
            ]);

            $pdo->commit();

            // Phieu nhap DRAFT khong duoc tru ton kho
            header(
                'Location: ' . BASE_URL .
                '/inventory_issues.php?created=1'
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

            $error = 'Không thể lưu phiếu xuất kho.';
        }
    }
}

$pageTitle = 'Tạo phiếu xuất kho';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="container-fluid py-4">

            <h2 class="fw-bold">Tạo phiếu xuất kho</h2>

            <p class="text-muted">
                Lập chứng từ xuất sản phẩm khỏi kho
            </p>

            <?php if (isset($_GET['created'])): ?>
                <div class="alert alert-success">
                    Đã lưu phiếu xuất nháp thành công.
                    Tồn kho chưa thay đổi.
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger">
                    <?= issueEscape($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!$canCreate): ?>

                <div class="alert alert-warning">
                    Tài khoản chưa có quyền lập phiếu xuất.
                </div>

            <?php else: ?>

                <div class="card p-4">

                    <form method="POST">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= issueEscape(
                                   $_SESSION['csrf_token']
                               ) ?>">

                        <div class="mb-3">

                            <label class="form-label">
                                Loại xuất kho *
                            </label>

                            <select name="loai_xuat"
                                    class="form-select"
                                    required>

                                <?php foreach ($issueTypes as $key => $label): ?>
                                    <option value="<?= issueEscape($key) ?>">
                                        <?= issueEscape($label) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Sản phẩm *
                            </label>

                            <select name="ma_san_pham"
                                    class="form-select"
                                    required>

                                <option value="">
                                    -- Chọn sản phẩm --
                                </option>

                                <?php foreach ($products as $p): ?>

                                    <option value="<?= (int)$p['ma_san_pham'] ?>">
                                        <?= issueEscape(
                                            $p['ma_sku'] . ' - ' .
                                            $p['ten_san_pham'] .
                                            ' (Tồn: ' .
                                            $p['so_luong_ton'] . ')'
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Số lượng xuất *
                            </label>

                            <input type="number"
                                   name="so_luong"
                                   class="form-control"
                                   min="1"
                                   step="1"
                                   required>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Ghi chú
                            </label>

                            <textarea name="ghi_chu"
                                      class="form-control"
                                      maxlength="3000"
                                      rows="3"></textarea>

                        </div>

                        <div class="alert alert-info">
                            Lưu phiếu nháp chưa trừ tồn kho.
                            Số lượng sẽ được kiểm tra lại
                            khi xác nhận xuất.
                        </div>

                        <button type="submit"
                                class="btn btn-primary"
                                <?= !$products ? 'disabled' : '' ?>>

                            <i class="bi bi-save me-1"></i>
                            Lưu phiếu xuất nháp

                        </button>

                        <a href="<?= issueEscape(BASE_URL) ?>/inventory.php"
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
