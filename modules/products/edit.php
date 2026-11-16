<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permission_check.php';
yeu_cau_quyen('products.edit');

// =====================================================
// MODULE: CẬP NHẬT SẢN PHẨM
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

// Hàm xử lý dữ liệu hiển thị
function editProductEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// =====================================================
// 1. KIỂM TRA MÃ SẢN PHẨM
// =====================================================

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header('Location: ' . BASE_URL . '/products.php');
    exit;
}

// =====================================================
// 2. LẤY THÔNG TIN SẢN PHẨM
// =====================================================

$stmt = $pdo->prepare("
    SELECT *
    FROM san_pham
    WHERE ma_san_pham = :id
    LIMIT 1
");

$stmt->execute([':id' => $id]);

$product = $stmt->fetch();

if (!$product) {
    header('Location: ' . BASE_URL . '/products.php');
    exit;
}

// =====================================================
// 3. LẤY DANH SÁCH DANH MỤC VÀ THƯƠNG HIỆU
// =====================================================

$categories = $pdo->query("
    SELECT ma_danh_muc, ten_danh_muc
    FROM danh_muc
    ORDER BY ten_danh_muc ASC
")->fetchAll();

$brands = $pdo->query("
    SELECT ma_thuong_hieu, ten_thuong_hieu
    FROM thuong_hieu
    ORDER BY ten_thuong_hieu ASC
")->fetchAll();

// =====================================================
// 4. KHỞI TẠO CSRF TOKEN
// =====================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];

// =====================================================
// 5. XỬ LÝ CẬP NHẬT
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    }

    $sku = trim($_POST['ma_sku'] ?? '');
    $name = trim($_POST['ten_san_pham'] ?? '');
    $categoryId = (int)($_POST['ma_danh_muc'] ?? 0);

    $brandId = trim((string)($_POST['ma_thuong_hieu'] ?? ''));

    $priceInput = trim((string)($_POST['gia_ban'] ?? ''));
    $warrantyInput = trim((string)($_POST['thoi_han_bao_hanh'] ?? ''));

    $description = trim($_POST['mo_ta'] ?? '');
    $status = $_POST['trang_thai'] ?? '';

    // Kiểm tra SKU
    if ($sku === '') {
        $errors[] = 'Vui lòng nhập mã SKU.';
    } elseif (mb_strlen($sku) > 100) {
        $errors[] = 'Mã SKU không được vượt quá 100 ký tự.';
    }

    // Kiểm tra tên sản phẩm
    if ($name === '') {
        $errors[] = 'Vui lòng nhập tên sản phẩm.';
    } elseif (mb_strlen($name) > 255) {
        $errors[] = 'Tên sản phẩm không được vượt quá 255 ký tự.';
    }

    // Kiểm tra danh mục
    if ($categoryId <= 0) {
        $errors[] = 'Vui lòng chọn danh mục sản phẩm.';
    }

    // Kiểm tra thương hiệu
    if ($brandId !== '' && !ctype_digit($brandId)) {
        $errors[] = 'Thương hiệu không hợp lệ.';
    }

    // Kiểm tra giá bán
    if (
        $priceInput === '' ||
        !is_numeric($priceInput) ||
        (float)$priceInput < 0 ||
        (float)$priceInput > 9999999999999.99
    ) {
        $errors[] = 'Giá bán phải là số hợp lệ và không được âm.';
    }

    // Kiểm tra thời hạn bảo hành
    if (
        $warrantyInput === '' ||
        !ctype_digit($warrantyInput) ||
        (int)$warrantyInput > 2147483647
    ) {
        $errors[] = 'Thời hạn bảo hành phải là số tháng hợp lệ.';
    }

    // Kiểm tra trạng thái
    if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
        $errors[] = 'Trạng thái sản phẩm không hợp lệ.';
    }

    // Kiểm tra danh mục tồn tại
    if ($categoryId > 0) {
        $checkCategory = $pdo->prepare("
            SELECT COUNT(*)
            FROM danh_muc
            WHERE ma_danh_muc = :id
        ");

        $checkCategory->execute([':id' => $categoryId]);

        if ((int)$checkCategory->fetchColumn() === 0) {
            $errors[] = 'Danh mục được chọn không tồn tại.';
        }
    }

    // Kiểm tra thương hiệu tồn tại
    if ($brandId !== '' && ctype_digit($brandId)) {
        $checkBrand = $pdo->prepare("
            SELECT COUNT(*)
            FROM thuong_hieu
            WHERE ma_thuong_hieu = :id
        ");

        $checkBrand->execute([':id' => (int)$brandId]);

        if ((int)$checkBrand->fetchColumn() === 0) {
            $errors[] = 'Thương hiệu được chọn không tồn tại.';
        }
    }

    // Kiểm tra SKU trùng với sản phẩm khác
    if ($sku !== '') {
        $checkSku = $pdo->prepare("
            SELECT COUNT(*)
            FROM san_pham
            WHERE ma_sku = :sku
            AND ma_san_pham != :id
        ");

        $checkSku->execute([
            ':sku' => $sku,
            ':id' => $id
        ]);

        if ((int)$checkSku->fetchColumn() > 0) {
            $errors[] = 'Mã SKU đã tồn tại. Vui lòng nhập mã khác.';
        }
    }

    // =================================================
    // 6. GHI DỮ LIỆU VÀO DATABASE
    // =================================================

    if (empty($errors)) {

        try {

            $sql = "
                UPDATE san_pham
                SET
                    ma_sku = :sku,
                    ten_san_pham = :name,
                    ma_danh_muc = :category,
                    ma_thuong_hieu = :brand,
                    gia_ban = :price,
                    thoi_han_bao_hanh = :warranty,
                    mo_ta = :description,
                    trang_thai = :status,
                    ngay_cap_nhat = NOW()
                WHERE ma_san_pham = :id
            ";

            $stmtUpdate = $pdo->prepare($sql);

            $stmtUpdate->execute([
                ':sku' => $sku,
                ':name' => $name,
                ':category' => $categoryId,
                ':brand' => $brandId === '' ? null : (int)$brandId,
                ':price' => (float)$priceInput,
                ':warranty' => (int)$warrantyInput,
                ':description' => $description !== '' ? $description : null,
                ':status' => $status,
                ':id' => $id
            ]);

            header(
                'Location: ' . BASE_URL . '/products.php?updated=1'
            );
            exit;

        } catch (PDOException $e) {

            error_log($e->getMessage());

            if ($e->getCode() === '23000') {
                $errors[] = 'Mã SKU đã tồn tại hoặc dữ liệu vi phạm ràng buộc.';
            } else {
                $errors[] = 'Không thể cập nhật sản phẩm. Vui lòng thử lại.';
            }
        }
    }

    // Giữ lại dữ liệu đã nhập nếu có lỗi
    $product['ma_sku'] = $sku;
    $product['ten_san_pham'] = $name;
    $product['ma_danh_muc'] = $categoryId;
    $product['ma_thuong_hieu'] = $brandId;
    $product['gia_ban'] = $priceInput;
    $product['thoi_han_bao_hanh'] = $warrantyInput;
    $product['mo_ta'] = $description;
    $product['trang_thai'] = $status;
}

// =====================================================
// 7. GIAO DIỆN
// =====================================================

$pageTitle = 'Cập nhật sản phẩm';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="container-fluid px-0">

            <!-- TIÊU ĐỀ -->
            <div class="page-heading mb-4">

                <div>
                    <h4 class="mb-1">Cập nhật sản phẩm</h4>

                    <p class="text-muted mb-0">
                        Chỉnh sửa thông tin sản phẩm trong hệ thống.
                    </p>
                </div>

            </div>

            <!-- THÔNG BÁO LỖI -->
            <?php if (!empty($errors)): ?>

                <div class="alert alert-danger">

                    <strong>
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Có lỗi xảy ra:
                    </strong>

                    <ul class="mb-0 mt-2">

                        <?php foreach ($errors as $error): ?>

                            <li><?= editProductEscape($error) ?></li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>

            <!-- FORM CẬP NHẬT -->
            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white py-3">

                    <h6 class="mb-0 fw-bold">
                        <i class="bi bi-pencil-square"></i>
                        Thông tin sản phẩm
                    </h6>

                </div>

                <div class="card-body">

                    <form method="POST">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= editProductEscape($_SESSION['csrf_token']) ?>">

                        <div class="row g-3">

                            <!-- SKU -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Mã SKU <span class="text-danger">*</span>
                                </label>

                                <input type="text"
                                       name="ma_sku"
                                       class="form-control"
                                       maxlength="100"
                                       required
                                       value="<?= editProductEscape($product['ma_sku']) ?>">

                            </div>

                            <!-- TÊN SẢN PHẨM -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Tên sản phẩm <span class="text-danger">*</span>
                                </label>

                                <input type="text"
                                       name="ten_san_pham"
                                       class="form-control"
                                       maxlength="255"
                                       required
                                       value="<?= editProductEscape($product['ten_san_pham']) ?>">

                            </div>

                            <!-- DANH MỤC -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Danh mục <span class="text-danger">*</span>
                                </label>

                                <select name="ma_danh_muc"
                                        class="form-select"
                                        required>

                                    <option value="">
                                        -- Chọn danh mục --
                                    </option>

                                    <?php foreach ($categories as $item): ?>

                                        <option
                                            value="<?= (int)$item['ma_danh_muc'] ?>"
                                            <?= (string)$product['ma_danh_muc'] === (string)$item['ma_danh_muc'] ? 'selected' : '' ?>>

                                            <?= editProductEscape($item['ten_danh_muc']) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <!-- THƯƠNG HIỆU -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Thương hiệu
                                </label>

                                <select name="ma_thuong_hieu"
                                        class="form-select">

                                    <option value="">
                                        -- Không chọn thương hiệu --
                                    </option>

                                    <?php foreach ($brands as $item): ?>

                                        <option
                                            value="<?= (int)$item['ma_thuong_hieu'] ?>"
                                            <?= (string)$product['ma_thuong_hieu'] === (string)$item['ma_thuong_hieu'] ? 'selected' : '' ?>>

                                            <?= editProductEscape($item['ten_thuong_hieu']) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <!-- GIÁ BÁN -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Giá bán (VNĐ) <span class="text-danger">*</span>
                                </label>

                                <input type="number"
                                       name="gia_ban"
                                       class="form-control"
                                       min="0"
                                       step="0.01"
                                       required
                                       value="<?= editProductEscape($product['gia_ban']) ?>">

                            </div>

                            <!-- BẢO HÀNH -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Thời hạn bảo hành (tháng)
                                </label>

                                <input type="number"
                                       name="thoi_han_bao_hanh"
                                       class="form-control"
                                       min="0"
                                       step="1"
                                       required
                                       value="<?= editProductEscape($product['thoi_han_bao_hanh']) ?>">

                            </div>

                            <!-- TRẠNG THÁI -->
                            <div class="col-md-6">

                                <label class="form-label">
                                    Trạng thái kinh doanh
                                </label>

                                <select name="trang_thai"
                                        class="form-select"
                                        required>

                                    <option value="ACTIVE"
                                        <?= $product['trang_thai'] === 'ACTIVE' ? 'selected' : '' ?>>

                                        Đang kinh doanh

                                    </option>

                                    <option value="INACTIVE"
                                        <?= $product['trang_thai'] === 'INACTIVE' ? 'selected' : '' ?>>

                                        Ngừng kinh doanh

                                    </option>

                                </select>

                            </div>

                            <!-- MÔ TẢ -->
                            <div class="col-12">

                                <label class="form-label">
                                    Mô tả sản phẩm
                                </label>

                                <textarea name="mo_ta"
                                          class="form-control"
                                          rows="5"><?= editProductEscape($product['mo_ta']) ?></textarea>

                            </div>

                        </div>

                        <!-- NÚT CHỨC NĂNG -->
                        <div class="d-flex gap-2 mt-4">

                            <button type="submit"
                                    class="btn btn-primary">

                                <i class="bi bi-save"></i>
                                Lưu thay đổi

                            </button>

                            <a href="<?= BASE_URL ?>/products.php"
                               class="btn btn-outline-secondary">

                                <i class="bi bi-arrow-left"></i>
                                Quay lại

                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
