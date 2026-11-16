
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/promotions/select_products.php
// CHUC NANG: CHON SAN PHAM AP DUNG KHUYEN MAI
// =====================================================

// -----------------------------------------------------
// 1. KIEM TRA DANG NHAP VA KET NOI
// -----------------------------------------------------

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Chọn sản phẩm khuyến mãi';

$errors = [];
$successMessage = '';

$escape = static function ($value) {
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
};

// -----------------------------------------------------
// 2. KHOI TAO CSRF
// -----------------------------------------------------

if (empty($_SESSION['csrf_select_products'])) {
    $_SESSION['csrf_select_products'] = bin2hex(random_bytes(32));
}

// -----------------------------------------------------
// 3. LAY MA CHUONG TRINH
// -----------------------------------------------------

$maChuongTrinh = filter_input(
    INPUT_GET,
    'ma_chuong_trinh',
    FILTER_VALIDATE_INT
);

// Khi gui form POST, lay ma chuong trinh tu POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maChuongTrinh = filter_input(
        INPUT_POST,
        'ma_chuong_trinh',
        FILTER_VALIDATE_INT
    );
}

if (!$maChuongTrinh || $maChuongTrinh <= 0) {
    die('Mã chương trình khuyến mãi không hợp lệ.');
}

// -----------------------------------------------------
// 4. LAY THONG TIN CHUONG TRINH
// -----------------------------------------------------

try {

    $stmt = $pdo->prepare("
        SELECT
            ma_chuong_trinh,
            ten_chuong_trinh,
            loai_giam_gia,
            gia_tri_giam,
            gia_tri_giam_toi_da,
            ngay_bat_dau,
            ngay_ket_thuc,
            trang_thai
        FROM chuong_trinh_khuyen_mai
        WHERE ma_chuong_trinh = :ma_chuong_trinh
        LIMIT 1
    ");

    $stmt->execute([
        ':ma_chuong_trinh' => $maChuongTrinh
    ]);

    $promotion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promotion) {
        die('Không tìm thấy chương trình khuyến mãi.');
    }

} catch (PDOException $e) {

    error_log('HP COMPUTER - SELECT PROMOTION ERROR: ' . $e->getMessage());

    die('Không thể tải thông tin chương trình khuyến mãi.');
}

// -----------------------------------------------------
// 5. XU LY LUU DANH SACH SAN PHAM
// -----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['csrf_select_products'], $csrfToken)
    ) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    }

    $selectedProducts = $_POST['san_pham'] ?? [];

    if (!is_array($selectedProducts)) {
        $selectedProducts = [];
    }

    // Chuyen danh sach ma san pham ve so nguyen
    $selectedIds = [];

    foreach ($selectedProducts as $productId) {

        if (
            filter_var($productId, FILTER_VALIDATE_INT) !== false &&
            (int) $productId > 0
        ) {
            $selectedIds[] = (int) $productId;
        }
    }

    // Loai bo ma trung lap
    $selectedIds = array_values(array_unique($selectedIds));

    if (empty($selectedIds)) {
        $errors[] = 'Vui lòng chọn ít nhất một sản phẩm áp dụng chương trình khuyến mãi.';
    }

    // Gioi han so luong du lieu gui len
    if (count($selectedIds) > 5000) {
        $errors[] = 'Số lượng sản phẩm được chọn vượt quá giới hạn.';
    }

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            // Kiem tra cac san pham co ton tai hay khong
            $placeholders = implode(
                ',',
                array_fill(0, count($selectedIds), '?')
            );

            $checkSql = "
                SELECT ma_san_pham
                FROM san_pham
                WHERE ma_san_pham IN ($placeholders)
            ";

            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute($selectedIds);

            $validIds = array_map(
                'intval',
                $checkStmt->fetchAll(PDO::FETCH_COLUMN)
            );

            sort($validIds);

            $compareIds = $selectedIds;
            sort($compareIds);

            if ($validIds !== $compareIds) {
                throw new RuntimeException(
                    'Một hoặc nhiều sản phẩm không tồn tại trong hệ thống.'
                );
            }

            // Xoa danh sach cu cua chuong trinh
            $deleteStmt = $pdo->prepare("
                DELETE FROM san_pham_khuyen_mai
                WHERE ma_chuong_trinh = :ma_chuong_trinh
            ");

            $deleteStmt->execute([
                ':ma_chuong_trinh' => $maChuongTrinh
            ]);

            // Them lai danh sach san pham moi
            $insertStmt = $pdo->prepare("
                INSERT INTO san_pham_khuyen_mai (
                    ma_chuong_trinh,
                    ma_san_pham
                ) VALUES (
                    :ma_chuong_trinh,
                    :ma_san_pham
                )
            ");

            foreach ($selectedIds as $productId) {

                $insertStmt->execute([
                    ':ma_chuong_trinh' => $maChuongTrinh,
                    ':ma_san_pham' => $productId
                ]);
            }

            $pdo->commit();

            // Tao CSRF token moi
            $_SESSION['csrf_select_products'] = bin2hex(random_bytes(32));

            // Chuyen ve trang danh sach
            header('Location: ' . BASE_URL . '/promotions.php?products_saved=1');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'HP COMPUTER - SAVE PROMOTION PRODUCTS ERROR: ' .
                $e->getMessage()
            );

            $errors[] = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Không thể lưu danh sách sản phẩm. Vui lòng thử lại.';
        }
    }
}

// -----------------------------------------------------
// 6. LAY DANH SACH SAN PHAM DA CHON
// -----------------------------------------------------

try {

    $selectedStmt = $pdo->prepare("
        SELECT ma_san_pham
        FROM san_pham_khuyen_mai
        WHERE ma_chuong_trinh = :ma_chuong_trinh
    ");

    $selectedStmt->execute([
        ':ma_chuong_trinh' => $maChuongTrinh
    ]);

    $selectedIds = array_map(
        'intval',
        $selectedStmt->fetchAll(PDO::FETCH_COLUMN)
    );

} catch (PDOException $e) {

    error_log('HP COMPUTER - SELECTED PRODUCTS ERROR: ' . $e->getMessage());

    die('Không thể tải danh sách sản phẩm đã chọn.');
}

// Neu POST bi loi, hien lai cac lua chon vua gui
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    !empty($errors) &&
    isset($selectedProducts)
) {
    $selectedIds = $selectedIds ?? [];

    $selectedIds = array_values(array_unique(array_filter(
        array_map('intval', $selectedProducts),
        static function ($id) {
            return $id > 0;
        }
    )));
}

// -----------------------------------------------------
// 7. LAY DANH SACH SAN PHAM
// -----------------------------------------------------

try {

    $productStmt = $pdo->query("
        SELECT
            ma_san_pham,
            ten_san_pham,
            ma_sku,
            gia_ban,
            trang_thai
        FROM san_pham
        ORDER BY ma_san_pham DESC
    ");

    $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log('HP COMPUTER - PRODUCT LIST ERROR: ' . $e->getMessage());

    die('Không thể tải danh sách sản phẩm.');
}

// -----------------------------------------------------
// 8. HAM DINH DANG
// -----------------------------------------------------

$formatMoney = static function ($amount) {
    return number_format((float) $amount, 0, ',', '.') . ' đ';
};

$discountRate = (float) $promotion['gia_tri_giam'];

$formatDiscount = static function ($promotion) use ($formatMoney) {

    if ($promotion['loai_giam_gia'] === 'PERCENT') {
        return rtrim(
            rtrim(number_format((float) $promotion['gia_tri_giam'], 2, ',', '.'), '0'),
            ','
        ) . '%';
    }

    return $formatMoney($promotion['gia_tri_giam']);
};

$calculateDiscountPrice = static function ($price) use ($promotion) {

    $price = (float) $price;

    if ($promotion['loai_giam_gia'] === 'PERCENT') {

        $discount = $price * (float) $promotion['gia_tri_giam'] / 100;

        if ($promotion['gia_tri_giam_toi_da'] !== null) {
            $discount = min(
                $discount,
                (float) $promotion['gia_tri_giam_toi_da']
            );
        }

    } else {

        $discount = (float) $promotion['gia_tri_giam'];
    }

    $discount = min($discount, $price);

    return max(0, $price - $discount);
};

$selectedCount = count($selectedIds);

// -----------------------------------------------------
// 9. HIEN THI GIAO DIEN
// -----------------------------------------------------

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';

?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="container-fluid py-4">

            <!-- TIEU DE -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">

                <div>
                    <h3 class="fw-bold mb-1">
                        Chọn sản phẩm áp dụng khuyến mãi
                    </h3>

                    <p class="text-muted mb-0">
                        Lựa chọn các sản phẩm được áp dụng chương trình.
                    </p>
                </div>

                <a
                    href="<?= $escape(BASE_URL) ?>/promotions.php"
                    class="btn btn-outline-secondary"
                >
                    <i class="bi bi-arrow-left me-1"></i>
                    Quay lại
                </a>

            </div>

            <!-- THONG TIN CHUONG TRINH -->
            <div class="card border-0 shadow-sm mb-4">

                <div class="card-body">

                    <div class="row align-items-center g-3">

                        <div class="col-lg-7">

                            <div class="text-muted small mb-1">
                                Chương trình khuyến mãi
                            </div>

                            <h5 class="fw-bold mb-2">
                                <?= $escape($promotion['ten_chuong_trinh']) ?>
                            </h5>

                            <div class="d-flex flex-wrap gap-3 text-muted small">

                                <span>
                                    <i class="bi bi-calendar-event me-1"></i>
                                    <?= $escape(date('d/m/Y H:i', strtotime($promotion['ngay_bat_dau']))) ?>
                                </span>

                                <span>
                                    <i class="bi bi-calendar-check me-1"></i>
                                    <?= $escape(date('d/m/Y H:i', strtotime($promotion['ngay_ket_thuc']))) ?>
                                </span>

                            </div>

                        </div>

                        <div class="col-lg-5">

                            <div class="row g-2">

                                <div class="col-6">

                                    <div class="bg-light rounded p-3">

                                        <div class="text-muted small">
                                            Mức giảm
                                        </div>

                                        <div class="fs-4 fw-bold text-success">
                                            <?= $escape($formatDiscount($promotion)) ?>
                                        </div>

                                    </div>

                                </div>

                                <div class="col-6">

                                    <div class="bg-light rounded p-3">

                                        <div class="text-muted small">
                                            Sản phẩm đã chọn
                                        </div>

                                        <div class="fs-4 fw-bold text-primary" id="selectedCount">
                                            <?= $selectedCount ?>
                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <?php if (
                        $promotion['loai_giam_gia'] === 'PERCENT' &&
                        $promotion['gia_tri_giam_toi_da'] !== null
                    ): ?>

                        <div class="text-muted small mt-3">
                            Mức giảm tối đa:
                            <strong>
                                <?= $escape($formatMoney($promotion['gia_tri_giam_toi_da'])) ?>
                            </strong>
                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <!-- THONG BAO LOI -->
            <?php if (!empty($errors)): ?>

                <div class="alert alert-danger">

                    <div class="fw-bold mb-2">
                        Có lỗi xảy ra:
                    </div>

                    <ul class="mb-0">

                        <?php foreach ($errors as $error): ?>

                            <li><?= $escape($error) ?></li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>

            <!-- DANH SACH SAN PHAM -->
            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white py-3">

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">

                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-box-seam me-2"></i>
                            Danh sách sản phẩm
                        </h5>

                        <span class="text-muted small">
                            Tổng số: <?= count($products) ?> sản phẩm
                        </span>

                    </div>

                </div>

                <div class="card-body">

                    <!-- TIM KIEM -->
                    <div class="row g-2 mb-3">

                        <div class="col-md-8">

                            <div class="input-group">

                                <span class="input-group-text bg-white">
                                    <i class="bi bi-search"></i>
                                </span>

                                <input
                                    type="text"
                                    id="productSearch"
                                    class="form-control"
                                    placeholder="Tìm theo tên sản phẩm hoặc mã SKU..."
                                >

                            </div>

                        </div>

                        <div class="col-md-4">

                            <select id="selectionFilter" class="form-select">

                                <option value="all">
                                    Tất cả sản phẩm
                                </option>

                                <option value="selected">
                                    Sản phẩm đã chọn
                                </option>

                                <option value="unselected">
                                    Sản phẩm chưa chọn
                                </option>

                            </select>

                        </div>

                    </div>

                    <form method="POST" action="">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= $escape($_SESSION['csrf_select_products']) ?>"
                        >

                        <input
                            type="hidden"
                            name="ma_chuong_trinh"
                            value="<?= (int) $maChuongTrinh ?>"
                        >

                        <!-- CHON TAT CA -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">

                            <div class="form-check">

                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    id="selectAll"
                                >

                                <label class="form-check-label fw-semibold" for="selectAll">
                                    Chọn tất cả sản phẩm đang hiển thị
                                </label>

                            </div>

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary"
                                id="clearSelection"
                            >
                                Bỏ chọn tất cả
                            </button>

                        </div>

                        <!-- BANG SAN PHAM -->
                        <div class="table-responsive">

                            <table class="table table-hover align-middle">

                                <thead class="table-light">

                                    <tr>
                                        <th width="55">Chọn</th>
                                        <th width="75">Mã SP</th>
                                        <th>Tên sản phẩm</th>
                                        <th width="150">Mã SKU</th>
                                        <th width="140" class="text-end">Giá bán</th>
                                        <th width="140" class="text-end">Giá sau giảm</th>
                                    </tr>

                                </thead>

                                <tbody id="productTableBody">

                                    <?php if (empty($products)): ?>

                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">
                                                Chưa có sản phẩm nào trong hệ thống.
                                            </td>
                                        </tr>

                                    <?php else: ?>

                                        <?php foreach ($products as $product): ?>

                                            <?php
                                            $productId = (int) $product['ma_san_pham'];

                                            $isSelected = in_array(
                                                $productId,
                                                $selectedIds,
                                                true
                                            );

                                            $price = (float) $product['gia_ban'];

                                            $discountPrice = $calculateDiscountPrice($price);
                                            ?>

                                            <tr
                                                class="product-row"
                                                data-name="<?= $escape(mb_strtolower($product['ten_san_pham'], 'UTF-8')) ?>"
                                                data-sku="<?= $escape(mb_strtolower($product['ma_sku'], 'UTF-8')) ?>"
                                                data-selected="<?= $isSelected ? '1' : '0' ?>"
                                            >

                                                <td>

                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input product-checkbox"
                                                        name="san_pham[]"
                                                        value="<?= $productId ?>"
                                                        <?= $isSelected ? 'checked' : '' ?>
                                                    >

                                                </td>

                                                <td>
                                                    <?= $productId ?>
                                                </td>

                                                <td>

                                                    <div class="fw-semibold">
                                                        <?= $escape($product['ten_san_pham']) ?>
                                                    </div>

                                                    <div class="small text-muted">
                                                        <?= $escape($product['trang_thai']) ?>
                                                    </div>

                                                </td>

                                                <td>
                                                    <?= $escape($product['ma_sku']) ?>
                                                </td>

                                                <td class="text-end">
                                                    <?= $escape($formatMoney($price)) ?>
                                                </td>

                                                <td class="text-end">

                                                    <span class="fw-semibold text-success">
                                                        <?= $escape($formatMoney($discountPrice)) ?>
                                                    </span>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                    <tr id="noSearchResult" style="display:none;">

                                        <td colspan="6" class="text-center text-muted py-4">
                                            Không tìm thấy sản phẩm phù hợp.
                                        </td>

                                    </tr>

                                </tbody>

                            </table>

                        </div>

                        <hr class="my-4">

                        <!-- CAC NUT -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

                            <div class="text-muted small">
                                Đã chọn
                                <strong id="selectedCountBottom">
                                    <?= $selectedCount ?>
                                </strong>
                                sản phẩm
                            </div>

                            <div class="d-flex gap-2">

                                <a
                                    href="<?= $escape(BASE_URL) ?>/promotions.php"
                                    class="btn btn-light border"
                                >
                                    Hủy
                                </a>

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >
                                    <i class="bi bi-save me-1"></i>
                                    Lưu sản phẩm áp dụng
                                </button>

                            </div>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const searchInput = document.getElementById('productSearch');
    const selectionFilter = document.getElementById('selectionFilter');
    const selectAll = document.getElementById('selectAll');
    const clearSelection = document.getElementById('clearSelection');

    const rows = Array.from(
        document.querySelectorAll('.product-row')
    );

    const noResult = document.getElementById('noSearchResult');

    const selectedCount = document.getElementById('selectedCount');
    const selectedCountBottom = document.getElementById('selectedCountBottom');

    function updateSelectedCount() {

        const count = document.querySelectorAll(
            '.product-checkbox:checked'
        ).length;

        selectedCount.textContent = count;
        selectedCountBottom.textContent = count;

        rows.forEach(function (row) {

            const checkbox = row.querySelector('.product-checkbox');

            row.dataset.selected = checkbox.checked ? '1' : '0';

        });

        updateSelectAllState();
    }

    function updateSelectAllState() {

        const visibleRows = rows.filter(function (row) {
            return row.style.display !== 'none';
        });

        const visibleCheckboxes = visibleRows.map(function (row) {
            return row.querySelector('.product-checkbox');
        });

        const checkedCount = visibleCheckboxes.filter(function (checkbox) {
            return checkbox.checked;
        }).length;

        selectAll.checked =
            visibleCheckboxes.length > 0 &&
            checkedCount === visibleCheckboxes.length;

        selectAll.indeterminate =
            checkedCount > 0 &&
            checkedCount < visibleCheckboxes.length;
    }

    function filterProducts() {

        const keyword = searchInput.value
            .trim()
            .toLocaleLowerCase('vi');

        const filter = selectionFilter.value;

        let visibleCount = 0;

        rows.forEach(function (row) {

            const name = row.dataset.name;
            const sku = row.dataset.sku;

            const checkbox = row.querySelector('.product-checkbox');

            const matchKeyword =
                name.includes(keyword) ||
                sku.includes(keyword);

            let matchFilter = true;

            if (filter === 'selected') {
                matchFilter = checkbox.checked;
            }

            if (filter === 'unselected') {
                matchFilter = !checkbox.checked;
            }

            const visible = matchKeyword && matchFilter;

            row.style.display = visible ? '' : 'none';

            if (visible) {
                visibleCount++;
            }

        });

        noResult.style.display = visibleCount === 0 ? '' : 'none';

        updateSelectAllState();
    }

    // Cap nhat so luong khi tich chon
    document.querySelectorAll('.product-checkbox').forEach(function (checkbox) {

        checkbox.addEventListener('change', function () {
            updateSelectedCount();
            filterProducts();
        });

    });

    // Chon tat ca san pham dang hien thi
    selectAll.addEventListener('change', function () {

        rows.forEach(function (row) {

            if (row.style.display !== 'none') {

                row.querySelector('.product-checkbox').checked =
                    selectAll.checked;

            }

        });

        updateSelectedCount();
        filterProducts();

    });

    // Bo chon tat ca
    clearSelection.addEventListener('click', function () {

        document.querySelectorAll('.product-checkbox').forEach(function (checkbox) {
            checkbox.checked = false;
        });

        updateSelectedCount();
        filterProducts();

    });

    // Tim kiem
    searchInput.addEventListener('input', filterProducts);

    // Loc san pham
    selectionFilter.addEventListener('change', filterProducts);

    updateSelectedCount();

});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
