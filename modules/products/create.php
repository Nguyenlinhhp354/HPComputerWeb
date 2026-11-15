
<?php

// ==========================================
// 1. KHỞI TẠO
// ==========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

$pageTitle = 'Thêm sản phẩm';

$errors = [];

$formData = [
    'ma_sku' => '',
    'ten_san_pham' => '',
    'ma_danh_muc' => '',
    'ma_thuong_hieu' => '',
    'gia_ban' => '',
    'thoi_han_bao_hanh' => '12',
    'trang_thai' => 'ACTIVE',
    'mo_ta' => ''
];

// Tạo CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==========================================
// 2. LẤY DANH MỤC VÀ THƯƠNG HIỆU
// ==========================================

$stmt = $pdo->query("
    SELECT ma_danh_muc, ten_danh_muc
    FROM danh_muc
    WHERE trang_thai = 'ACTIVE'
    ORDER BY ten_danh_muc ASC
");

$categories = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT ma_thuong_hieu, ten_thuong_hieu
    FROM thuong_hieu
    WHERE trang_thai = 'ACTIVE'
    ORDER BY ten_thuong_hieu ASC
");

$brands = $stmt->fetchAll();

// ==========================================
// 3. XỬ LÝ FORM THÊM SẢN PHẨM
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($formData as $key => $value) {
        $formData[$key] = trim($_POST[$key] ?? $value);
    }

    // Kiểm tra CSRF
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    }

    // Kiểm tra SKU
    if ($formData['ma_sku'] === '') {
        $errors[] = 'Vui lòng nhập mã SKU.';
    } elseif (strlen($formData['ma_sku']) > 100) {
        $errors[] = 'Mã SKU không được vượt quá 100 ký tự.';
    }

    // Kiểm tra tên sản phẩm
    if ($formData['ten_san_pham'] === '') {
        $errors[] = 'Vui lòng nhập tên sản phẩm.';
    } elseif (strlen($formData['ten_san_pham']) > 255) {
        $errors[] = 'Tên sản phẩm không được vượt quá 255 ký tự.';
    }

    // Kiểm tra danh mục
    if (
        $formData['ma_danh_muc'] === '' ||
        !ctype_digit($formData['ma_danh_muc'])
    ) {
        $errors[] = 'Vui lòng chọn danh mục hợp lệ.';
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM danh_muc
            WHERE ma_danh_muc = ?
            AND trang_thai = 'ACTIVE'
        ");

        $stmt->execute([(int)$formData['ma_danh_muc']]);

        if ((int)$stmt->fetchColumn() === 0) {
            $errors[] = 'Danh mục không tồn tại hoặc đã ngừng hoạt động.';
        }
    }

    // Kiểm tra thương hiệu
    if ($formData['ma_thuong_hieu'] !== '') {

        if (!ctype_digit($formData['ma_thuong_hieu'])) {
            $errors[] = 'Thương hiệu không hợp lệ.';
        } else {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM thuong_hieu
                WHERE ma_thuong_hieu = ?
                AND trang_thai = 'ACTIVE'
            ");

            $stmt->execute([(int)$formData['ma_thuong_hieu']]);

            if ((int)$stmt->fetchColumn() === 0) {
                $errors[] = 'Thương hiệu không tồn tại hoặc đã ngừng hoạt động.';
            }
        }
    }

    // Kiểm tra giá bán
    if (
        $formData['gia_ban'] === '' ||
        !is_numeric($formData['gia_ban']) ||
        !is_finite((float)$formData['gia_ban']) ||
        (float)$formData['gia_ban'] < 0 ||
        (float)$formData['gia_ban'] > 9999999999999.99
    ) {
        $errors[] = 'Giá bán phải là số hợp lệ và không được âm.';
    }

    // Kiểm tra bảo hành
    if (
        $formData['thoi_han_bao_hanh'] === '' ||
        !ctype_digit($formData['thoi_han_bao_hanh']) ||
        (int)$formData['thoi_han_bao_hanh'] > 65535
    ) {
        $errors[] = 'Thời hạn bảo hành phải là số tháng không âm hợp lệ.';
    }

    // Kiểm tra trạng thái
    if (!in_array($formData['trang_thai'], ['ACTIVE', 'INACTIVE'], true)) {
        $errors[] = 'Trạng thái sản phẩm không hợp lệ.';
    }

    // ======================================
    // 4. LƯU SẢN PHẨM VÀ TỒN KHO
    // ======================================

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            // Kiểm tra SKU đã tồn tại chưa
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM san_pham
                WHERE ma_sku = ?
            ");

            $stmt->execute([$formData['ma_sku']]);

            if ((int)$stmt->fetchColumn() > 0) {
                throw new RuntimeException('Mã SKU đã tồn tại. Vui lòng nhập mã khác.');
            }

            // Thêm sản phẩm
            $sql = "
                INSERT INTO san_pham (
                    ma_danh_muc,
                    ma_thuong_hieu,
                    ma_sku,
                    ten_san_pham,
                    mo_ta,
                    gia_ban,
                    thoi_han_bao_hanh,
                    trang_thai
                ) VALUES (
                    :ma_danh_muc,
                    :ma_thuong_hieu,
                    :ma_sku,
                    :ten_san_pham,
                    :mo_ta,
                    :gia_ban,
                    :thoi_han_bao_hanh,
                    :trang_thai
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':ma_danh_muc' => (int)$formData['ma_danh_muc'],
                ':ma_thuong_hieu' => $formData['ma_thuong_hieu'] !== ''
                    ? (int)$formData['ma_thuong_hieu']
                    : null,
                ':ma_sku' => $formData['ma_sku'],
                ':ten_san_pham' => $formData['ten_san_pham'],
                ':mo_ta' => $formData['mo_ta'] !== ''
                    ? $formData['mo_ta']
                    : null,
                ':gia_ban' => $formData['gia_ban'],
                ':thoi_han_bao_hanh' => (int)$formData['thoi_han_bao_hanh'],
                ':trang_thai' => $formData['trang_thai']
            ]);

            // Lấy mã sản phẩm vừa tạo
            $productId = (int)$pdo->lastInsertId();

            // Tạo bản ghi tồn kho ban đầu
            $stmt = $pdo->prepare("
                INSERT INTO ton_kho (
                    ma_san_pham,
                    so_luong
                ) VALUES (
                    :ma_san_pham,
                    0
                )
            ");

            $stmt->execute([
                ':ma_san_pham' => $productId
            ]);

            // Hoàn tất transaction
            $pdo->commit();

            // Tạo token mới sau khi thêm thành công
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: ../../products.php?created=1');
            exit;

        } catch (RuntimeException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = $e->getMessage();

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            if ($e->getCode() === '23000') {
                $errors[] = 'Mã SKU đã tồn tại hoặc dữ liệu liên kết không hợp lệ.';
            } else {
                $errors[] = 'Không thể thêm sản phẩm. Vui lòng kiểm tra lại dữ liệu.';
            }
        }
    }
}

// ==========================================
// 5. HÀM HỖ TRỢ
// ==========================================

function createEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';

?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="page-heading">

            <div>
                <h1>Thêm sản phẩm</h1>
                <p>Nhập thông tin sản phẩm mới vào hệ thống.</p>
            </div>

            <a href="../../products.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i>
                Quay lại
            </a>

        </div>

        <?php if (!empty($errors)): ?>

            <div class="alert alert-danger">

                <strong>Không thể thêm sản phẩm:</strong>

                <ul class="mb-0 mt-2">

                    <?php foreach ($errors as $error): ?>

                        <li><?= createEscape($error) ?></li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>

        <div class="content-card">

            <div class="content-card-header">
                <div>
                    <h5>Thông tin sản phẩm</h5>
                    <p>Các trường có dấu * là bắt buộc.</p>
                </div>
            </div>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= createEscape($_SESSION['csrf_token']) ?>"
                >

                <div class="row g-3">

                    <div class="col-md-6">

                        <label class="form-label">
                            Mã SKU *
                        </label>

                        <input
                            type="text"
                            name="ma_sku"
                            class="form-control"
                            maxlength="100"
                            required
                            value="<?= createEscape($formData['ma_sku']) ?>"
                            placeholder="Ví dụ: HP-LAP-007"
                        >

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Tên sản phẩm *
                        </label>

                        <input
                            type="text"
                            name="ten_san_pham"
                            class="form-control"
                            maxlength="255"
                            required
                            value="<?= createEscape($formData['ten_san_pham']) ?>"
                            placeholder="Nhập tên sản phẩm"
                        >

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Danh mục *
                        </label>

                        <select
                            name="ma_danh_muc"
                            class="form-select"
                            required
                        >

                            <option value="">-- Chọn danh mục --</option>

                            <?php foreach ($categories as $category): ?>

                                <option
                                    value="<?= (int)$category['ma_danh_muc'] ?>"
                                    <?= $formData['ma_danh_muc'] == $category['ma_danh_muc'] ? 'selected' : '' ?>
                                >
                                    <?= createEscape($category['ten_danh_muc']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Thương hiệu
                        </label>

                        <select
                            name="ma_thuong_hieu"
                            class="form-select"
                        >

                            <option value="">-- Không chọn thương hiệu --</option>

                            <?php foreach ($brands as $brand): ?>

                                <option
                                    value="<?= (int)$brand['ma_thuong_hieu'] ?>"
                                    <?= $formData['ma_thuong_hieu'] == $brand['ma_thuong_hieu'] ? 'selected' : '' ?>
                                >
                                    <?= createEscape($brand['ten_thuong_hieu']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Giá bán (VNĐ) *
                        </label>

                        <input
                            type="number"
                            name="gia_ban"
                            class="form-control"
                            min="0"
                            max="9999999999999.99"
                            step="0.01"
                            required
                            value="<?= createEscape($formData['gia_ban']) ?>"
                            placeholder="Ví dụ: 15000000"
                        >

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Thời hạn bảo hành (tháng) *
                        </label>

                        <input
                            type="number"
                            name="thoi_han_bao_hanh"
                            class="form-control"
                            min="0"
                            max="65535"
                            step="1"
                            required
                            value="<?= createEscape($formData['thoi_han_bao_hanh']) ?>"
                        >

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">
                            Trạng thái *
                        </label>

                        <select
                            name="trang_thai"
                            class="form-select"
                            required
                        >

                            <option
                                value="ACTIVE"
                                <?= $formData['trang_thai'] === 'ACTIVE' ? 'selected' : '' ?>
                            >
                                Đang kinh doanh
                            </option>

                            <option
                                value="INACTIVE"
                                <?= $formData['trang_thai'] === 'INACTIVE' ? 'selected' : '' ?>
                            >
                                Ngừng kinh doanh
                            </option>

                        </select>

                    </div>

                    <div class="col-12">

                        <label class="form-label">
                            Mô tả sản phẩm
                        </label>

                        <textarea
                            name="mo_ta"
                            class="form-control"
                            rows="4"
                            placeholder="Nhập thông tin mô tả sản phẩm..."
                        ><?= createEscape($formData['mo_ta']) ?></textarea>

                    </div>

                </div>

                <hr class="my-4">

                <div class="d-flex gap-2">

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i>
                        Lưu sản phẩm
                    </button>

                    <a href="../../products.php" class="btn btn-secondary">
                        Hủy
                    </a>

                </div>

            </form>

        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
