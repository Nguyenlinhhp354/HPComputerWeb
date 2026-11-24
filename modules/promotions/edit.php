<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permission_check.php';
yeu_cau_quyen('promotions.edit');

// =====================================================
// HP COMPUTER
// FILE: modules/promotions/edit.php
// CHUC NANG: CHINH SUA CHUONG TRINH KHUYEN MAI
// - Moi san pham co muc giam gia rieng
// - Tim kiem san pham
// - Loc theo danh muc, thuong hieu
// - Loc san pham da chon / chua chon
// - Chon tat ca san pham dang hien thi
// =====================================================

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('Không thể kết nối cơ sở dữ liệu.');
}

$pageTitle = 'Chỉnh sửa chương trình khuyến mãi';

$escape = static function ($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
};

$errors = [];

// =====================================================
// 1. LAY MA CHUONG TRINH
// =====================================================

$maChuongTrinh = filter_var(
    $_POST['ma_chuong_trinh'] ?? $_GET['ma_chuong_trinh'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$maChuongTrinh || $maChuongTrinh <= 0) {
    http_response_code(400);
    exit('Mã chương trình khuyến mãi không hợp lệ.');
}

// =====================================================
// 2. CSRF TOKEN
// =====================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =====================================================
// 3. HAM DINH DANG NGAY
// =====================================================

$formatDate = static function ($value) {
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('Y-m-d\TH:i', $timestamp)
        : '';
};

// =====================================================
// 4. LAY THONG TIN CHUONG TRINH
// =====================================================

try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM chuong_trinh_khuyen_mai
        WHERE ma_chuong_trinh = ?
        LIMIT 1
    ");

    $stmt->execute([$maChuongTrinh]);
    $promotion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promotion) {
        http_response_code(404);
        exit('Không tìm thấy chương trình khuyến mãi.');
    }

} catch (PDOException $e) {
    error_log('PROMOTION LOAD: ' . $e->getMessage());
    exit('Lỗi khi tải chương trình khuyến mãi.');
}

// =====================================================
// 5. LAY SAN PHAM + DANH MUC + THUONG HIEU
// =====================================================

try {
    $stmt = $pdo->query("
        SELECT
            sp.ma_san_pham,
            sp.ma_sku,
            sp.ten_san_pham,
            sp.gia_ban,
            sp.ma_danh_muc,
            dm.ten_danh_muc,
            sp.ma_thuong_hieu,
            th.ten_thuong_hieu

        FROM san_pham sp

        LEFT JOIN danh_muc dm
            ON sp.ma_danh_muc = dm.ma_danh_muc

        LEFT JOIN thuong_hieu th
            ON sp.ma_thuong_hieu = th.ma_thuong_hieu

        ORDER BY sp.ten_san_pham ASC
    ");

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $productMap = [];
    $categoryOptions = [];
    $brandOptions = [];

    foreach ($products as $product) {

        $productId = (int)$product['ma_san_pham'];

        $productMap[$productId] = $product;

        if (!empty($product['ma_danh_muc'])) {
            $categoryId = (int)$product['ma_danh_muc'];

            $categoryOptions[$categoryId] = $product['ten_danh_muc'];
        }

        if (!empty($product['ma_thuong_hieu'])) {
            $brandId = (int)$product['ma_thuong_hieu'];

            $brandOptions[$brandId] = $product['ten_thuong_hieu'];
        }
    }

    asort($categoryOptions);
    asort($brandOptions);

} catch (PDOException $e) {
    error_log('PROMOTION PRODUCTS: ' . $e->getMessage());
    exit('Không thể tải danh sách sản phẩm.');
}

// =====================================================
// 6. LAY SAN PHAM DANG THAM GIA CHUONG TRINH
// =====================================================

try {
    $stmt = $pdo->prepare("
        SELECT
            ma_san_pham,
            loai_giam_gia,
            gia_tri_giam,
            gia_tri_giam_toi_da

        FROM san_pham_khuyen_mai
        WHERE ma_chuong_trinh = ?
    ");

    $stmt->execute([$maChuongTrinh]);

    $existingProducts = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingProducts[(int)$row['ma_san_pham']] = $row;
    }

} catch (PDOException $e) {
    error_log('PROMOTION PRODUCT LINKS: ' . $e->getMessage());
    exit('Không thể tải sản phẩm thuộc chương trình.');
}

// =====================================================
// 7. XU LY FORM
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tenChuongTrinh = trim($_POST['ten_chuong_trinh'] ?? '');
    $moTa = trim($_POST['mo_ta'] ?? '');
    $ngayBatDau = trim($_POST['ngay_bat_dau'] ?? '');
    $ngayKetThuc = trim($_POST['ngay_ket_thuc'] ?? '');
    $trangThai = $_POST['trang_thai'] ?? '';

    $csrfToken = $_POST['csrf_token'] ?? '';

    // -------------------------------------------------
    // 7.1 KIEM TRA CSRF
    // -------------------------------------------------

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    }

    // -------------------------------------------------
    // 7.2 KIEM TRA TEN
    // -------------------------------------------------

    if ($tenChuongTrinh === '') {
        $errors[] = 'Vui lòng nhập tên chương trình.';
    } elseif (mb_strlen($tenChuongTrinh, 'UTF-8') > 200) {
        $errors[] = 'Tên chương trình không được vượt quá 200 ký tự.';
    }

    // -------------------------------------------------
    // 7.3 KIEM TRA NGAY GIO
    // -------------------------------------------------

    $batDau = DateTime::createFromFormat('!Y-m-d\TH:i', $ngayBatDau);
    $ketThuc = DateTime::createFromFormat('!Y-m-d\TH:i', $ngayKetThuc);

    if (!$batDau || $batDau->format('Y-m-d\TH:i') !== $ngayBatDau) {
        $errors[] = 'Ngày bắt đầu không hợp lệ.';
    }

    if (!$ketThuc || $ketThuc->format('Y-m-d\TH:i') !== $ngayKetThuc) {
        $errors[] = 'Ngày kết thúc không hợp lệ.';
    }

    if ($batDau && $ketThuc && $ketThuc <= $batDau) {
        $errors[] = 'Ngày kết thúc phải sau ngày bắt đầu.';
    }

    // -------------------------------------------------
    // 7.4 KIEM TRA TRANG THAI
    // -------------------------------------------------

    $trangThaiHopLe = [
        'DRAFT',
        'ACTIVE',
        'INACTIVE',
        'EXPIRED',
        'CANCELLED'
    ];

    if (!in_array($trangThai, $trangThaiHopLe, true)) {
        $errors[] = 'Trạng thái chương trình không hợp lệ.';
    }

    // -------------------------------------------------
    // 7.5 LAY SAN PHAM DUOC CHON
    // -------------------------------------------------

    $selectedRaw = $_POST['selected_products'] ?? [];

    if (!is_array($selectedRaw)) {
        $selectedRaw = [];
        $errors[] = 'Danh sách sản phẩm không hợp lệ.';
    }

    $selectedProductIds = [];

    foreach ($selectedRaw as $id) {

        $id = filter_var($id, FILTER_VALIDATE_INT);

        if ($id && $id > 0) {
            $selectedProductIds[] = (int)$id;
        }
    }

    $selectedProductIds = array_values(
        array_unique($selectedProductIds)
    );

    if (empty($selectedProductIds)) {
        $errors[] = 'Vui lòng chọn ít nhất một sản phẩm áp dụng khuyến mãi.';
    }

    // -------------------------------------------------
    // 7.6 KIEM TRA MUC GIAM TUNG SAN PHAM
    // -------------------------------------------------

    $postedProducts = $_POST['products'] ?? [];

    if (!is_array($postedProducts)) {
        $postedProducts = [];
        $errors[] = 'Thông tin giảm giá sản phẩm không hợp lệ.';
    }

    $validatedProducts = [];

    foreach ($selectedProductIds as $productId) {

        if (!isset($productMap[$productId])) {
            $errors[] = 'Một sản phẩm được chọn không tồn tại.';
            continue;
        }

        $productData = $productMap[$productId];
        $productPrice = (float)$productData['gia_ban'];

        $discountData = $postedProducts[$productId] ?? [];

        if (!is_array($discountData)) {
            $errors[] = 'Thông tin giảm giá của sản phẩm không hợp lệ.';
            continue;
        }

        $discountType = $discountData['loai_giam_gia'] ?? '';
        $discountValue = trim((string)($discountData['gia_tri_giam'] ?? ''));
        $maxDiscount = trim((string)($discountData['gia_tri_giam_toi_da'] ?? ''));

        if (!in_array($discountType, ['PERCENT', 'FIXED_AMOUNT'], true)) {
            $errors[] = 'Loại giảm giá của sản phẩm ' .
                $productData['ten_san_pham'] . ' không hợp lệ.';
            continue;
        }

        if (
            !is_numeric($discountValue) ||
            !is_finite((float)$discountValue) ||
            (float)$discountValue <= 0
        ) {
            $errors[] = 'Mức giảm của sản phẩm ' .
                $productData['ten_san_pham'] . ' phải lớn hơn 0.';
            continue;
        }

        $discountValueNumber = (float)$discountValue;

        if ($discountType === 'PERCENT') {

            if ($discountValueNumber > 100) {
                $errors[] = 'Mức giảm phần trăm của sản phẩm ' .
                    $productData['ten_san_pham'] . ' không được vượt quá 100%.';
                continue;
            }

            if ($maxDiscount !== '') {

                if (
                    !is_numeric($maxDiscount) ||
                    !is_finite((float)$maxDiscount) ||
                    (float)$maxDiscount <= 0
                ) {
                    $errors[] = 'Giá trị giảm tối đa của sản phẩm ' .
                        $productData['ten_san_pham'] . ' không hợp lệ.';
                    continue;
                }

            } else {
                $maxDiscount = null;
            }

        } else {

            if ($discountValueNumber > $productPrice) {
                $errors[] = 'Số tiền giảm của sản phẩm ' .
                    $productData['ten_san_pham'] .
                    ' không được lớn hơn giá bán.';
                continue;
            }

            $maxDiscount = null;
        }

        $validatedProducts[$productId] = [
            'ma_san_pham' => $productId,
            'loai_giam_gia' => $discountType,
            'gia_tri_giam' => $discountValue,
            'gia_tri_giam_toi_da' => $maxDiscount
        ];
    }

    // -------------------------------------------------
    // 7.7 CAP NHAT DATABASE
    // -------------------------------------------------

    if (empty($errors)) {

        try {
            $pdo->beginTransaction();

            // Cap nhat thong tin chuong trinh
            $stmt = $pdo->prepare("
                UPDATE chuong_trinh_khuyen_mai
                SET
                    ten_chuong_trinh = :ten,
                    mo_ta = :mo_ta,
                    ngay_bat_dau = :bat_dau,
                    ngay_ket_thuc = :ket_thuc,
                    trang_thai = :trang_thai
                WHERE ma_chuong_trinh = :ma
            ");

            $stmt->execute([
                ':ten' => $tenChuongTrinh,
                ':mo_ta' => $moTa !== '' ? $moTa : null,
                ':bat_dau' => $batDau->format('Y-m-d H:i:s'),
                ':ket_thuc' => $ketThuc->format('Y-m-d H:i:s'),
                ':trang_thai' => $trangThai,
                ':ma' => $maChuongTrinh
            ]);

            // Xoa san pham da bo chon
            $placeholders = implode(
                ',',
                array_fill(0, count($selectedProductIds), '?')
            );

            $deleteSql = "
                DELETE FROM san_pham_khuyen_mai
                WHERE ma_chuong_trinh = ?
                AND ma_san_pham NOT IN ($placeholders)
            ";

            $deleteParams = array_merge(
                [$maChuongTrinh],
                $selectedProductIds
            );

            $stmt = $pdo->prepare($deleteSql);
            $stmt->execute($deleteParams);

            // Them moi hoac cap nhat muc giam tung san pham
            $stmt = $pdo->prepare("
                INSERT INTO san_pham_khuyen_mai (
                    ma_chuong_trinh,
                    ma_san_pham,
                    loai_giam_gia,
                    gia_tri_giam,
                    gia_tri_giam_toi_da
                ) VALUES (
                    :ma_chuong_trinh,
                    :ma_san_pham,
                    :loai_giam_gia,
                    :gia_tri_giam,
                    :gia_tri_giam_toi_da
                )
                ON DUPLICATE KEY UPDATE
                    loai_giam_gia = VALUES(loai_giam_gia),
                    gia_tri_giam = VALUES(gia_tri_giam),
                    gia_tri_giam_toi_da = VALUES(gia_tri_giam_toi_da)
            ");

            foreach ($validatedProducts as $product) {

                $stmt->execute([
                    ':ma_chuong_trinh' => $maChuongTrinh,
                    ':ma_san_pham' => $product['ma_san_pham'],
                    ':loai_giam_gia' => $product['loai_giam_gia'],
                    ':gia_tri_giam' => $product['gia_tri_giam'],
                    ':gia_tri_giam_toi_da' => $product['gia_tri_giam_toi_da']
                ]);
            }

            $pdo->commit();

            header(
                'Location: ' . BASE_URL . '/promotions.php?updated=1'
            );
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('PROMOTION UPDATE: ' . $e->getMessage());

            $errors[] = 'Không thể cập nhật chương trình. Vui lòng kiểm tra database và thử lại.';
        }
    }

    // -------------------------------------------------
    // 7.8 GIU LAI DU LIEU KHI CO LOI
    // -------------------------------------------------

    $promotion['ten_chuong_trinh'] = $tenChuongTrinh;
    $promotion['mo_ta'] = $moTa;
    $promotion['ngay_bat_dau'] = $ngayBatDau;
    $promotion['ngay_ket_thuc'] = $ngayKetThuc;
    $promotion['trang_thai'] = $trangThai;

} else {

    $selectedProductIds = array_keys($existingProducts);
    $postedProducts = [];
}

// =====================================================
// 8. DINH DANG DU LIEU HIEN THI
// =====================================================

$ngayBatDauValue = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? $promotion['ngay_bat_dau']
    : $formatDate($promotion['ngay_bat_dau']);

$ngayKetThucValue = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? $promotion['ngay_ket_thuc']
    : $formatDate($promotion['ngay_ket_thuc']);

$selectedLookup = array_flip($selectedProductIds);

// =====================================================
// 9. HEADER VA NAVBAR
// =====================================================

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';

?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="container-fluid py-3">

            <!-- TIEU DE -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

                <div>
                    <h4 class="fw-bold mb-1">
                        Chỉnh sửa chương trình khuyến mãi
                    </h4>

                    <p class="text-muted mb-0">
                        Mã chương trình: #<?= (int)$maChuongTrinh ?>
                    </p>
                </div>

                <a href="<?= $escape(BASE_URL) ?>/promotions.php"
                   class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Quay lại
                </a>

            </div>

            <!-- THONG BAO LOI -->
            <?php if (!empty($errors)): ?>

                <div class="alert alert-danger">
                    <strong>Vui lòng kiểm tra lại:</strong>

                    <ul class="mb-0 mt-2">
                        <?php foreach (array_unique($errors) as $error): ?>
                            <li><?= $escape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            <?php endif; ?>

            <form method="POST"
                  action="<?= $escape(BASE_URL) ?>/modules/promotions/edit.php?ma_chuong_trinh=<?= (int)$maChuongTrinh ?>"
                  id="promotionForm">

                <input type="hidden"
                       name="ma_chuong_trinh"
                       value="<?= (int)$maChuongTrinh ?>">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= $escape($_SESSION['csrf_token']) ?>">

                <!-- =====================================
                     PHAN A: THONG TIN CHUONG TRINH
                ====================================== -->

                <div class="card border-0 shadow-sm mb-4">

                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-pencil-square me-2"></i>
                            Thông tin chương trình
                        </h5>
                    </div>

                    <div class="card-body">

                        <div class="row g-3">

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">
                                    Tên chương trình
                                    <span class="text-danger">*</span>
                                </label>

                                <input type="text"
                                       name="ten_chuong_trinh"
                                       class="form-control"
                                       maxlength="200"
                                       required
                                       value="<?= $escape($promotion['ten_chuong_trinh']) ?>">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">
                                    Mô tả
                                </label>

                                <textarea name="mo_ta"
                                          class="form-control"
                                          rows="3"><?= $escape($promotion['mo_ta']) ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    Ngày bắt đầu
                                    <span class="text-danger">*</span>
                                </label>

                                <input type="datetime-local"
                                       name="ngay_bat_dau"
                                       class="form-control"
                                       required
                                       value="<?= $escape($ngayBatDauValue) ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    Ngày kết thúc
                                    <span class="text-danger">*</span>
                                </label>

                                <input type="datetime-local"
                                       name="ngay_ket_thuc"
                                       class="form-control"
                                       required
                                       value="<?= $escape($ngayKetThucValue) ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    Trạng thái
                                    <span class="text-danger">*</span>
                                </label>

                                <select name="trang_thai"
                                        class="form-select"
                                        required>

                                    <option value="DRAFT"
                                        <?= $promotion['trang_thai'] === 'DRAFT' ? 'selected' : '' ?>>
                                        Bản nháp
                                    </option>

                                    <option value="ACTIVE"
                                        <?= $promotion['trang_thai'] === 'ACTIVE' ? 'selected' : '' ?>>
                                        Đang hoạt động
                                    </option>

                                    <option value="INACTIVE"
                                        <?= $promotion['trang_thai'] === 'INACTIVE' ? 'selected' : '' ?>>
                                        Ngừng hoạt động
                                    </option>

                                    <option value="EXPIRED"
                                        <?= $promotion['trang_thai'] === 'EXPIRED' ? 'selected' : '' ?>>
                                        Đã hết hạn
                                    </option>

                                    <option value="CANCELLED"
                                        <?= $promotion['trang_thai'] === 'CANCELLED' ? 'selected' : '' ?>>
                                        Đã hủy
                                    </option>

                                </select>
                            </div>

                        </div>

                    </div>
                </div>

                <!-- =====================================
                     PHAN B: SAN PHAM KHUYEN MAI
                ====================================== -->

                <div class="card border-0 shadow-sm mb-4">

                    <div class="card-header bg-white py-3">

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                            <h5 class="fw-bold mb-0">
                                <i class="bi bi-box-seam me-2"></i>
                                Sản phẩm áp dụng khuyến mãi
                            </h5>

                            <span class="badge bg-primary"
                                  id="selectedCount">
                                <?= count($selectedProductIds) ?> sản phẩm được chọn
                            </span>

                        </div>

                    </div>

                    <div class="card-body">

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-1"></i>

                            Tích chọn sản phẩm muốn áp dụng. Mỗi sản phẩm có thể
                            sử dụng một hình thức và mức giảm giá khác nhau.
                        </div>

                        <!-- BO LOC SAN PHAM -->
                        <div class="row g-3 mb-3">

                            <!-- TIM KIEM -->
                            <div class="col-lg-6">

                                <label class="form-label fw-semibold">
                                    Tìm kiếm sản phẩm
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-search"></i>
                                    </span>

                                    <input type="text"
                                           id="productSearch"
                                           class="form-control"
                                           placeholder="Tên sản phẩm hoặc mã SKU...">
                                </div>

                            </div>

                            <!-- LOC DANH MUC -->
                            <div class="col-lg-3">

                                <label class="form-label fw-semibold">
                                    Danh mục
                                </label>

                                <select id="categoryFilter"
                                        class="form-select">

                                    <option value="all">
                                        Tất cả danh mục
                                    </option>

                                    <?php foreach ($categoryOptions as $id => $name): ?>

                                        <option value="<?= (int)$id ?>">
                                            <?= $escape($name) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <!-- LOC THUONG HIEU -->
                            <div class="col-lg-3">

                                <label class="form-label fw-semibold">
                                    Thương hiệu
                                </label>

                                <select id="brandFilter"
                                        class="form-select">

                                    <option value="all">
                                        Tất cả thương hiệu
                                    </option>

                                    <?php foreach ($brandOptions as $id => $name): ?>

                                        <option value="<?= (int)$id ?>">
                                            <?= $escape($name) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <!-- LOC TRANG THAI -->
                            <div class="col-md-6">

                                <label class="form-label fw-semibold">
                                    Trạng thái lựa chọn
                                </label>

                                <select id="productFilter"
                                        class="form-select">

                                    <option value="all">Tất cả sản phẩm</option>
                                    <option value="selected">Đã chọn</option>
                                    <option value="unselected">Chưa chọn</option>

                                </select>

                            </div>

                            <!-- THONG KE -->
                            <div class="col-md-6 d-flex align-items-end justify-content-md-end">

                                <div class="text-muted small">
                                    Đang hiển thị:
                                    <strong id="visibleCount">0</strong>
                                    /
                                    <strong><?= count($products) ?></strong>
                                    sản phẩm
                                </div>

                            </div>

                        </div>

                        <!-- CHON TAT CA DANG HIEN THI -->
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 border rounded p-3 mb-3">

                            <div class="form-check mb-0">

                                <input type="checkbox"
                                       class="form-check-input"
                                       id="selectAllVisible">

                                <label class="form-check-label fw-semibold"
                                       for="selectAllVisible">
                                    Chọn tất cả sản phẩm đang hiển thị
                                </label>

                            </div>

                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    id="clearVisibleSelection">

                                <i class="bi bi-x-circle me-1"></i>
                                Bỏ chọn sản phẩm đang hiển thị

                            </button>

                        </div>

                        <!-- BANG SAN PHAM -->
                        <div class="table-responsive">

                            <table class="table table-bordered table-hover align-middle">

                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 45px;" class="text-center">
                                            Chọn
                                        </th>

                                        <th>Sản phẩm</th>

                                        <th style="min-width: 145px;">
                                            Giá bán
                                        </th>

                                        <th style="min-width: 165px;">
                                            Hình thức giảm
                                        </th>

                                        <th style="min-width: 135px;">
                                            Mức giảm
                                        </th>

                                        <th style="min-width: 145px;">
                                            Giảm tối đa
                                        </th>

                                        <th style="min-width: 155px;">
                                            Giá sau giảm
                                        </th>
                                    </tr>
                                </thead>

                                <tbody id="productTableBody">

                                    <?php foreach ($products as $product): ?>

                                        <?php
                                        $productId = (int)$product['ma_san_pham'];

                                        $isSelected = isset($selectedLookup[$productId]);

                                        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                                            $discountData = $postedProducts[$productId] ?? [];
                                        } else {
                                            $discountData = $existingProducts[$productId] ?? [];
                                        }

                                        $discountType = $discountData['loai_giam_gia'] ?? 'PERCENT';
                                        $discountValue = $discountData['gia_tri_giam'] ?? '';
                                        $maxDiscount = $discountData['gia_tri_giam_toi_da'] ?? '';

                                        if (!in_array($discountType, ['PERCENT', 'FIXED_AMOUNT'], true)) {
                                            $discountType = 'PERCENT';
                                        }

                                        $categoryId = $product['ma_danh_muc'] ?? '';
                                        $brandId = $product['ma_thuong_hieu'] ?? '';

                                        $searchText = mb_strtolower(
                                            $product['ten_san_pham'] . ' ' .
                                            ($product['ma_sku'] ?? '') . ' ' .
                                            ($product['ten_danh_muc'] ?? '') . ' ' .
                                            ($product['ten_thuong_hieu'] ?? ''),
                                            'UTF-8'
                                        );
                                        ?>

                                        <tr class="product-row"
                                            data-product-id="<?= $productId ?>"
                                            data-search="<?= $escape($searchText) ?>"
                                            data-category="<?= $escape($categoryId) ?>"
                                            data-brand="<?= $escape($brandId) ?>"
                                            data-price="<?= $escape($product['gia_ban']) ?>">

                                            <!-- CHON SAN PHAM -->
                                            <td class="text-center">

                                                <input type="checkbox"
                                                       class="form-check-input product-checkbox"
                                                       name="selected_products[]"
                                                       value="<?= $productId ?>"
                                                       <?= $isSelected ? 'checked' : '' ?>>

                                            </td>

                                            <!-- THONG TIN SAN PHAM -->
                                            <td>

                                                <div class="fw-semibold">
                                                    <?= $escape($product['ten_san_pham']) ?>
                                                </div>

                                                <small class="text-muted">
                                                    SKU:
                                                    <?= $escape($product['ma_sku'] ?? 'Chưa có') ?>
                                                    | Mã: #<?= $productId ?>
                                                </small>

                                                <div class="small text-muted mt-1">
                                                    <?= $escape($product['ten_danh_muc'] ?? 'Chưa phân loại') ?>
                                                    <span class="mx-1">·</span>
                                                    <?= $escape($product['ten_thuong_hieu'] ?? 'Chưa có thương hiệu') ?>
                                                </div>

                                            </td>

                                            <!-- GIA BAN -->
                                            <td>
                                                <span class="fw-semibold">
                                                    <?= number_format((float)$product['gia_ban'], 0, ',', '.') ?>đ
                                                </span>
                                            </td>

                                            <!-- LOAI GIAM -->
                                            <td>

                                                <select
                                                    name="products[<?= $productId ?>][loai_giam_gia]"
                                                    class="form-select form-select-sm discount-type"
                                                    <?= $isSelected ? '' : 'disabled' ?>>

                                                    <option value="PERCENT"
                                                        <?= $discountType === 'PERCENT' ? 'selected' : '' ?>>
                                                        Phần trăm (%)
                                                    </option>

                                                    <option value="FIXED_AMOUNT"
                                                        <?= $discountType === 'FIXED_AMOUNT' ? 'selected' : '' ?>>
                                                        Số tiền (VNĐ)
                                                    </option>

                                                </select>

                                            </td>

                                            <!-- GIA TRI GIAM -->
                                            <td>

                                                <input
                                                    type="number"
                                                    name="products[<?= $productId ?>][gia_tri_giam]"
                                                    class="form-control form-control-sm discount-value"
                                                    min="0.01"
                                                    step="0.01"
                                                    placeholder="Nhập mức giảm"
                                                    value="<?= $escape($discountValue) ?>"
                                                    <?= $isSelected ? '' : 'disabled' ?>>

                                            </td>

                                            <!-- GIAM TOI DA -->
                                            <td>

                                                <input
                                                    type="number"
                                                    name="products[<?= $productId ?>][gia_tri_giam_toi_da]"
                                                    class="form-control form-control-sm max-discount"
                                                    min="0.01"
                                                    step="0.01"
                                                    placeholder="Không bắt buộc"
                                                    value="<?= $escape($maxDiscount) ?>"
                                                    <?= ($isSelected && $discountType === 'PERCENT') ? '' : 'disabled' ?>>

                                            </td>

                                            <!-- GIA SAU GIAM -->
                                            <td>

                                                <span class="fw-bold text-success final-price">
                                                    —
                                                </span>

                                                <div class="small text-muted discount-preview"></div>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                    <?php if (empty($products)): ?>

                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                Chưa có sản phẩm trong hệ thống.
                                            </td>
                                        </tr>

                                    <?php endif; ?>

                                </tbody>

                            </table>

                            <div id="noProductResult"
                                 class="alert alert-warning text-center d-none">

                                Không tìm thấy sản phẩm phù hợp với điều kiện lọc.

                            </div>

                        </div>

                        <div class="small text-muted mt-2">
                            * Bộ lọc chỉ thay đổi sản phẩm đang hiển thị.
                            Sản phẩm đã chọn sẽ không bị bỏ chọn khi bạn tìm kiếm hoặc lọc.
                        </div>

                    </div>
                </div>

                <!-- =====================================
                     NUT LUU
                ====================================== -->

                <div class="d-flex justify-content-end gap-2 mb-4">

                    <a href="<?= $escape(BASE_URL) ?>/promotions.php"
                       class="btn btn-light border">
                        Hủy
                    </a>

                    <button type="submit"
                            class="btn btn-primary"
                            id="saveButton">

                        <i class="bi bi-save me-1"></i>
                        Lưu thay đổi

                    </button>

                </div>

            </form>

        </div>

    </main>

</div>

<!-- =================================================
     JAVASCRIPT
================================================== -->

<script>
document.addEventListener('DOMContentLoaded', function () {

    const searchInput = document.getElementById('productSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const brandFilter = document.getElementById('brandFilter');
    const filterSelect = document.getElementById('productFilter');

    const tableBody = document.getElementById('productTableBody');
    const selectedCount = document.getElementById('selectedCount');
    const visibleCount = document.getElementById('visibleCount');

    const selectAllVisible = document.getElementById('selectAllVisible');
    const clearVisibleSelection = document.getElementById('clearVisibleSelection');

    const noProductResult = document.getElementById('noProductResult');

    const rows = Array.from(
        tableBody.querySelectorAll('.product-row')
    );

    const formatMoney = function (value) {
        return new Intl.NumberFormat('vi-VN', {
            maximumFractionDigits: 0
        }).format(value) + 'đ';
    };

    // =================================================
    // 1. DEM SO SAN PHAM
    // =================================================

    function updateSelectedCount() {

        const checked = tableBody.querySelectorAll(
            '.product-checkbox:checked'
        ).length;

        selectedCount.textContent = checked + ' sản phẩm được chọn';
    }

    // =================================================
    // 2. DEM SAN PHAM DANG HIEN THI
    // =================================================

    function updateVisibleCount() {

        const visibleRows = rows.filter(function (row) {
            return row.style.display !== 'none';
        });

        visibleCount.textContent = visibleRows.length;
    }

    // =================================================
    // 3. CAP NHAT TRANG THAI CHON TAT CA
    // =================================================

    function updateSelectAllState() {

        const visibleRows = rows.filter(function (row) {
            return row.style.display !== 'none';
        });

        if (visibleRows.length === 0) {
            selectAllVisible.checked = false;
            selectAllVisible.indeterminate = false;
            selectAllVisible.disabled = true;
            return;
        }

        selectAllVisible.disabled = false;

        const checkedCount = visibleRows.filter(function (row) {
            return row.querySelector('.product-checkbox').checked;
        }).length;

        selectAllVisible.checked =
            checkedCount === visibleRows.length;

        selectAllVisible.indeterminate =
            checkedCount > 0 && checkedCount < visibleRows.length;
    }

    // =================================================
    // 4. TINH GIA SAU GIAM
    // =================================================

    function calculateProduct(row) {

        const checkbox = row.querySelector('.product-checkbox');
        const type = row.querySelector('.discount-type');
        const value = row.querySelector('.discount-value');
        const max = row.querySelector('.max-discount');

        const finalPrice = row.querySelector('.final-price');
        const preview = row.querySelector('.discount-preview');

        const price = parseFloat(row.dataset.price) || 0;

        if (!checkbox.checked) {
            finalPrice.textContent = '—';
            preview.textContent = '';
            return;
        }

        const discountValue = parseFloat(value.value);

        if (!discountValue || discountValue <= 0) {
            finalPrice.textContent = 'Chưa nhập mức giảm';
            preview.textContent = '';
            return;
        }

        let discountAmount = 0;

        if (type.value === 'PERCENT') {

            if (discountValue > 100) {
                finalPrice.textContent = 'Mức giảm không hợp lệ';
                preview.textContent = '';
                return;
            }

            discountAmount = price * discountValue / 100;

            const maxValue = parseFloat(max.value);

            if (max.value !== '' && maxValue > 0) {
                discountAmount = Math.min(discountAmount, maxValue);
            }

        } else {

            discountAmount = discountValue;

        }

        if (discountAmount > price) {
            finalPrice.textContent = 'Mức giảm vượt giá bán';
            preview.textContent = '';
            return;
        }

        const result = Math.max(0, price - discountAmount);

        finalPrice.textContent = formatMoney(result);

        preview.textContent = 'Tiết kiệm ' + formatMoney(discountAmount);
    }

    // =================================================
    // 5. BAT/TAT TRUONG NHAP
    // =================================================

    function updateProductRow(row) {

        const checkbox = row.querySelector('.product-checkbox');
        const type = row.querySelector('.discount-type');
        const value = row.querySelector('.discount-value');
        const max = row.querySelector('.max-discount');

        const isSelected = checkbox.checked;

        type.disabled = !isSelected;
        value.disabled = !isSelected;

        if (!isSelected) {
            max.disabled = true;
        } else {
            max.disabled = type.value !== 'PERCENT';
        }

        calculateProduct(row);
        updateSelectedCount();
        updateSelectAllState();
    }

    // =================================================
    // 6. CAP NHAT LOAI GIAM
    // =================================================

    function updateDiscountType(row) {

        const type = row.querySelector('.discount-type');
        const max = row.querySelector('.max-discount');

        if (type.value === 'PERCENT') {
            max.disabled = false;
        } else {
            max.value = '';
            max.disabled = true;
        }

        calculateProduct(row);
    }

    // =================================================
    // 7. TIM KIEM VA LOC SAN PHAM
    // =================================================

    function filterProducts() {

        const keyword = searchInput.value
            .trim()
            .toLocaleLowerCase('vi');

        const category = categoryFilter.value;
        const brand = brandFilter.value;
        const status = filterSelect.value;

        rows.forEach(function (row) {

            const searchText = row.dataset.search || '';

            const productCategory = row.dataset.category || '';
            const productBrand = row.dataset.brand || '';

            const checkbox = row.querySelector('.product-checkbox');

            const matchKeyword = searchText.includes(keyword);

            const matchCategory =
                category === 'all' || productCategory === category;

            const matchBrand =
                brand === 'all' || productBrand === brand;

            let matchStatus = true;

            if (status === 'selected') {
                matchStatus = checkbox.checked;
            }

            if (status === 'unselected') {
                matchStatus = !checkbox.checked;
            }

            const shouldShow =
                matchKeyword &&
                matchCategory &&
                matchBrand &&
                matchStatus;

            row.style.display = shouldShow ? '' : 'none';
        });

        const hasVisibleProduct = rows.some(function (row) {
            return row.style.display !== 'none';
        });

        noProductResult.classList.toggle(
            'd-none',
            hasVisibleProduct
        );

        updateVisibleCount();
        updateSelectAllState();
    }

    // =================================================
    // 8. CHON TAT CA SAN PHAM DANG HIEN THI
    // =================================================

    selectAllVisible.addEventListener('change', function () {

        const shouldCheck = selectAllVisible.checked;

        rows.forEach(function (row) {

            if (row.style.display === 'none') {
                return;
            }

            const checkbox = row.querySelector('.product-checkbox');

            checkbox.checked = shouldCheck;

            updateProductRow(row);
        });

        filterProducts();
    });

    // =================================================
    // 9. BO CHON TAT CA SAN PHAM DANG HIEN THI
    // =================================================

    clearVisibleSelection.addEventListener('click', function () {

        rows.forEach(function (row) {

            if (row.style.display === 'none') {
                return;
            }

            const checkbox = row.querySelector('.product-checkbox');

            checkbox.checked = false;

            updateProductRow(row);
        });

        filterProducts();
    });

    // =================================================
    // 10. GAN SU KIEN CHO CAC DONG SAN PHAM
    // =================================================

    rows.forEach(function (row) {

        const checkbox = row.querySelector('.product-checkbox');
        const type = row.querySelector('.discount-type');
        const value = row.querySelector('.discount-value');
        const max = row.querySelector('.max-discount');

        checkbox.addEventListener('change', function () {
            updateProductRow(row);
            filterProducts();
        });

        type.addEventListener('change', function () {
            updateDiscountType(row);
        });

        value.addEventListener('input', function () {
            calculateProduct(row);
        });

        max.addEventListener('input', function () {
            calculateProduct(row);
        });

        updateProductRow(row);
    });

    // =================================================
    // 11. SU KIEN CAC BO LOC
    // =================================================

    searchInput.addEventListener('input', filterProducts);

    categoryFilter.addEventListener('change', filterProducts);

    brandFilter.addEventListener('change', filterProducts);

    filterSelect.addEventListener('change', filterProducts);

    // =================================================
    // 12. KIEM TRA TRUOC KHI GUI FORM
    // =================================================

    document.getElementById('promotionForm').addEventListener(
        'submit',
        function (event) {

            const checked = tableBody.querySelectorAll(
                '.product-checkbox:checked'
            );

            if (checked.length === 0) {
                event.preventDefault();
                alert('Vui lòng chọn ít nhất một sản phẩm.');
                return;
            }

            let invalid = false;

            checked.forEach(function (checkbox) {

                const row = checkbox.closest('.product-row');

                const type = row.querySelector('.discount-type');
                const value = row.querySelector('.discount-value');
                const max = row.querySelector('.max-discount');

                const price = parseFloat(row.dataset.price) || 0;
                const discountValue = parseFloat(value.value);

                if (!discountValue || discountValue <= 0) {
                    invalid = true;
                    value.classList.add('is-invalid');
                    return;
                }

                value.classList.remove('is-invalid');

                if (type.value === 'PERCENT') {

                    if (discountValue > 100) {
                        invalid = true;
                        value.classList.add('is-invalid');
                    }

                    if (
                        max.value !== '' &&
                        (
                            !Number.isFinite(parseFloat(max.value)) ||
                            parseFloat(max.value) <= 0
                        )
                    ) {
                        invalid = true;
                        max.classList.add('is-invalid');
                    } else {
                        max.classList.remove('is-invalid');
                    }

                } else {

                    if (discountValue > price) {
                        invalid = true;
                        value.classList.add('is-invalid');
                    }
                }

            });

            if (invalid) {
                event.preventDefault();

                alert(
                    'Vui lòng kiểm tra mức giảm giá của các sản phẩm đã chọn.'
                );
            }

        }
    );

    // =================================================
    // 13. KHOI TAO GIA TRI BAN DAU
    // =================================================

    filterProducts();
    updateSelectedCount();

});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
