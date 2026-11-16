
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/customers/products.php
// CHUC NANG: DANH SACH SAN PHAM KHACH HANG
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

// =====================================================
// 1. NHAN THAM SO TIM KIEM VA LOC
// =====================================================

$keyword = trim($_GET['q'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$brandFilter = (int)($_GET['brand'] ?? 0);
$priceFilter = trim($_GET['price'] ?? '');
$sort = trim($_GET['sort'] ?? 'newest');
$promotionOnly = isset($_GET['promotion']) && $_GET['promotion'] == '1';

$allowedSorts = [
    'newest' => 'sp.ngay_tao DESC, sp.ma_san_pham DESC',
    'price_asc' => 'sp.gia_ban ASC',
    'price_desc' => 'sp.gia_ban DESC',
    'name_asc' => 'sp.ten_san_pham ASC'
];

$orderBy = $allowedSorts[$sort] ?? $allowedSorts['newest'];

// =====================================================
// 2. LAY DANH MUC VA THUONG HIEU
// =====================================================

$stmt = $pdo->query("
    SELECT ma_danh_muc, ten_danh_muc
    FROM danh_muc
    WHERE trang_thai = 'ACTIVE'
    ORDER BY ten_danh_muc ASC
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT ma_thuong_hieu, ten_thuong_hieu
    FROM thuong_hieu
    WHERE trang_thai = 'ACTIVE'
    ORDER BY ten_thuong_hieu ASC
");

$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// 3. XAY DUNG DIEU KIEN LOC
// =====================================================

$where = ["sp.trang_thai = 'ACTIVE'"];
$params = [];

if ($keyword !== '') {
    $where[] = "(
        sp.ten_san_pham LIKE :keyword_name
        OR sp.ma_sku LIKE :keyword_sku
    )";

    // Khong dung lai ten placeholder trong PDO
    $params[':keyword_name'] = '%' . $keyword . '%';
    $params[':keyword_sku'] = '%' . $keyword . '%';
}

if ($categoryFilter !== '') {
    if (ctype_digit($categoryFilter)) {
        $where[] = 'sp.ma_danh_muc = :category_id';
        $params[':category_id'] = (int)$categoryFilter;
    } elseif ($categoryFilter === 'computer') {
        $where[] = "dm.ten_danh_muc LIKE :category_name";
        $params[':category_name'] = '%máy tính%';
    } elseif ($categoryFilter === 'accessory') {
        $where[] = "dm.ten_danh_muc LIKE :category_name";
        $params[':category_name'] = '%phụ kiện%';
    }
}

if ($brandFilter > 0) {
    $where[] = 'sp.ma_thuong_hieu = :brand_id';
    $params[':brand_id'] = $brandFilter;
}

switch ($priceFilter) {
    case 'under5':
        $where[] = 'sp.gia_ban < 5000000';
        break;

    case '5to10':
        $where[] = 'sp.gia_ban >= 5000000 AND sp.gia_ban < 10000000';
        break;

    case '10to20':
        $where[] = 'sp.gia_ban >= 10000000 AND sp.gia_ban < 20000000';
        break;

    case 'over20':
        $where[] = 'sp.gia_ban >= 20000000';
        break;
}

if ($promotionOnly) {
    $where[] = "
        EXISTS (
            SELECT 1
            FROM san_pham_khuyen_mai spkm
            INNER JOIN chuong_trinh_khuyen_mai ctkm
                ON spkm.ma_chuong_trinh = ctkm.ma_chuong_trinh
            WHERE spkm.ma_san_pham = sp.ma_san_pham
              AND ctkm.trang_thai = 'ACTIVE'
              AND NOW() BETWEEN ctkm.ngay_bat_dau AND ctkm.ngay_ket_thuc
        )
    ";
}

$whereSQL = implode(' AND ', $where);

// =====================================================
// 4. DEM TONG SO SAN PHAM
// =====================================================

$countSQL = "
    SELECT COUNT(*)
    FROM san_pham sp
    LEFT JOIN danh_muc dm
        ON sp.ma_danh_muc = dm.ma_danh_muc
    WHERE $whereSQL
";

$countStmt = $pdo->prepare($countSQL);
$countStmt->execute($params);

$totalProducts = (int)$countStmt->fetchColumn();

$perPage = 12;
$totalPages = max(1, (int)ceil($totalProducts / $perPage));

$currentPage = max(1, (int)($_GET['page'] ?? 1));
$currentPage = min($currentPage, $totalPages);

$offset = ($currentPage - 1) * $perPage;

// =====================================================
// 5. LAY DANH SACH SAN PHAM
// =====================================================

$productSQL = "
    SELECT
        sp.ma_san_pham,
        sp.ma_sku,
        sp.ten_san_pham,
        sp.mo_ta,
        sp.gia_ban,
        sp.thoi_han_bao_hanh,
        sp.ngay_tao,
        dm.ten_danh_muc,
        th.ten_thuong_hieu
    FROM san_pham sp
    LEFT JOIN danh_muc dm
        ON sp.ma_danh_muc = dm.ma_danh_muc
    LEFT JOIN thuong_hieu th
        ON sp.ma_thuong_hieu = th.ma_thuong_hieu
    WHERE $whereSQL
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
";

$productStmt = $pdo->prepare($productSQL);
$productStmt->execute($params);

$products = $productStmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// 6. LAY KHUYEN MAI CUA CAC SAN PHAM TRONG TRANG
// =====================================================

$promotionMap = [];

if (!empty($products)) {
    $productIds = array_column($products, 'ma_san_pham');

    $placeholders = implode(
        ',',
        array_fill(0, count($productIds), '?')
    );

    $promotionSQL = "
        SELECT
            spkm.ma_san_pham,
            spkm.loai_giam_gia,
            spkm.gia_tri_giam,
            spkm.gia_tri_giam_toi_da
        FROM san_pham_khuyen_mai spkm
        INNER JOIN chuong_trinh_khuyen_mai ctkm
            ON spkm.ma_chuong_trinh = ctkm.ma_chuong_trinh
        WHERE spkm.ma_san_pham IN ($placeholders)
          AND ctkm.trang_thai = 'ACTIVE'
          AND NOW() BETWEEN ctkm.ngay_bat_dau AND ctkm.ngay_ket_thuc
    ";

    $promotionStmt = $pdo->prepare($promotionSQL);
    $promotionStmt->execute($productIds);

    $promotionRows = $promotionStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($promotionRows as $promotion) {
        $productId = (int)$promotion['ma_san_pham'];

        $product = null;

        foreach ($products as $item) {
            if ((int)$item['ma_san_pham'] === $productId) {
                $product = $item;
                break;
            }
        }

        if (!$product) {
            continue;
        }

        $originalPrice = (float)$product['gia_ban'];

        if ($promotion['loai_giam_gia'] === 'PERCENT') {
            $discount = $originalPrice
                * (float)$promotion['gia_tri_giam'] / 100;

            if ($promotion['gia_tri_giam_toi_da'] !== null) {
                $discount = min(
                    $discount,
                    (float)$promotion['gia_tri_giam_toi_da']
                );
            }
        } else {
            $discount = (float)$promotion['gia_tri_giam'];
        }

        $discount = max(0, min($discount, $originalPrice));

        $finalPrice = max(0, $originalPrice - $discount);

        if (
            !isset($promotionMap[$productId])
            || $finalPrice < $promotionMap[$productId]['final_price']
        ) {
            $promotionMap[$productId] = [
                'original_price' => $originalPrice,
                'discount' => $discount,
                'final_price' => $finalPrice
            ];
        }
    }
}

// =====================================================
// 7. HAM HO TRO
// =====================================================

function customer_products_price($price)
{
    return number_format((float)$price, 0, ',', '.') . 'đ';
}

function customer_products_url($page)
{
    $params = $_GET;
    $params['page'] = $page;

    return '?' . http_build_query($params);
}

$pageTitle = 'Sản phẩm';

// =====================================================
// 8. GIAO DIEN
// =====================================================

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
.products-page {
    padding: 30px 0 50px;
    background: #f5f6f8;
    min-height: 600px;
}

.products-heading {
    margin-bottom: 25px;
}

.products-heading h2 {
    font-size: 25px;
    font-weight: 700;
    color: #222;
}

.products-sidebar,
.products-toolbar,
.customer-product-item {
    background: #fff;
    border-radius: 10px;
    border: 1px solid #e9e9e9;
}

.products-sidebar {
    padding: 20px;
}

.filter-section {
    margin-bottom: 25px;
}

.filter-section h5 {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 14px;
}

.filter-section .form-check {
    margin-bottom: 9px;
}

.products-toolbar {
    padding: 15px;
    margin-bottom: 20px;
}

.customer-product-item {
    height: 100%;
    padding: 18px;
    transition: .2s;
}

.customer-product-item:hover {
    border-color: #0d6efd;
    transform: translateY(-3px);
    box-shadow: 0 5px 18px rgba(0,0,0,.07);
}

.product-image-placeholder {
    height: 155px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f7f8fa;
    border-radius: 8px;
    margin-bottom: 15px;
    color: #8a929d;
}

.product-image-placeholder i {
    font-size: 65px;
}

.customer-product-item h6 {
    font-size: 15px;
    line-height: 1.5;
    min-height: 45px;
    font-weight: 600;
}

.product-sku {
    color: #888;
    font-size: 12px;
}

.product-price {
    color: #dc3545;
    font-size: 18px;
    font-weight: 700;
}

.product-old-price {
    color: #999;
    text-decoration: line-through;
    font-size: 13px;
}

.product-discount {
    display: inline-block;
    background: #fff0f0;
    color: #dc3545;
    padding: 3px 7px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.products-empty {
    background: #fff;
    padding: 60px 20px;
    text-align: center;
    border-radius: 10px;
}

.products-empty i {
    font-size: 50px;
    color: #adb5bd;
}

.products-pagination {
    margin-top: 30px;
}

@media (max-width: 767px) {
    .products-sidebar {
        margin-bottom: 20px;
    }
}
</style>

<div class="products-page">
    <div class="container">

        <!-- TIEU DE -->
        <div class="products-heading">
            <h2>
                <?= $promotionOnly ? 'Sản phẩm khuyến mãi' : 'Danh sách sản phẩm' ?>
            </h2>

            <div class="text-muted">
                Tìm thấy <?= number_format($totalProducts) ?> sản phẩm
            </div>
        </div>

        <div class="row">

            <!-- BO LOC -->
            <div class="col-lg-3">
                <form action="" method="GET" class="products-sidebar">

                    <input
                        type="hidden"
                        name="q"
                        value="<?= htmlspecialchars($keyword) ?>"
                    >

                    <div class="filter-section">
                        <h5>Danh mục sản phẩm</h5>

                        <select name="category" class="form-select">
                            <option value="">Tất cả danh mục</option>

                            <?php foreach ($categories as $category): ?>
                                <option
                                    value="<?= $category['ma_danh_muc'] ?>"
                                    <?= $categoryFilter == $category['ma_danh_muc'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($category['ten_danh_muc']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-section">
                        <h5>Thương hiệu</h5>

                        <select name="brand" class="form-select">
                            <option value="0">Tất cả thương hiệu</option>

                            <?php foreach ($brands as $brand): ?>
                                <option
                                    value="<?= $brand['ma_thuong_hieu'] ?>"
                                    <?= $brandFilter === (int)$brand['ma_thuong_hieu'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($brand['ten_thuong_hieu']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-section">
                        <h5>Mức giá</h5>

                        <?php
                        $priceOptions = [
                            '' => 'Tất cả mức giá',
                            'under5' => 'Dưới 5 triệu',
                            '5to10' => 'Từ 5 - 10 triệu',
                            '10to20' => 'Từ 10 - 20 triệu',
                            'over20' => 'Trên 20 triệu'
                        ];
                        ?>

                        <?php foreach ($priceOptions as $value => $label): ?>
                            <div class="form-check">
                                <input
                                    class="form-check-input"
                                    type="radio"
                                    name="price"
                                    id="price_<?= $value === '' ? 'all' : $value ?>"
                                    value="<?= $value ?>"
                                    <?= $priceFilter === $value ? 'checked' : '' ?>
                                >

                                <label
                                    class="form-check-label"
                                    for="price_<?= $value === '' ? 'all' : $value ?>"
                                >
                                    <?= $label ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="filter-section">
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="promotion"
                                value="1"
                                id="promotion"
                                <?= $promotionOnly ? 'checked' : '' ?>
                            >

                            <label class="form-check-label" for="promotion">
                                Chỉ xem sản phẩm khuyến mãi
                            </label>
                        </div>
                    </div>

                    <input
                        type="hidden"
                        name="sort"
                        value="<?= htmlspecialchars($sort) ?>"
                    >

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i>
                        Áp dụng bộ lọc
                    </button>

                    <a href="products.php" class="btn btn-outline-secondary w-100 mt-2">
                        Xóa bộ lọc
                    </a>

                </form>
            </div>

            <!-- DANH SACH SAN PHAM -->
            <div class="col-lg-9">

                <!-- THANH SAP XEP -->
                <div class="products-toolbar">
                    <form method="GET" class="row align-items-center g-2">

                        <?php foreach ($_GET as $key => $value): ?>
                            <?php if ($key !== 'sort' && $key !== 'page'): ?>
                                <input
                                    type="hidden"
                                    name="<?= htmlspecialchars($key) ?>"
                                    value="<?= htmlspecialchars((string)$value) ?>"
                                >
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <div class="col-md-6">
                            <span class="text-muted">
                                Sắp xếp sản phẩm:
                            </span>
                        </div>

                        <div class="col-md-6">
                            <select
                                name="sort"
                                class="form-select"
                                onchange="this.form.submit()"
                            >
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>
                                    Mới nhất
                                </option>

                                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>
                                    Giá thấp đến cao
                                </option>

                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>
                                    Giá cao đến thấp
                                </option>

                                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>
                                    Tên A - Z
                                </option>
                            </select>
                        </div>

                    </form>
                </div>

                <?php if (empty($products)): ?>

                    <div class="products-empty">
                        <i class="bi bi-search"></i>

                        <h5 class="mt-3">
                            Không tìm thấy sản phẩm
                        </h5>

                        <p class="text-muted">
                            Bạn hãy thử thay đổi từ khóa hoặc bộ lọc.
                        </p>

                        <a href="products.php" class="btn btn-primary">
                            Xem tất cả sản phẩm
                        </a>
                    </div>

                <?php else: ?>

                    <div class="row g-3">

                        <?php foreach ($products as $product): ?>

                            <?php
                            $productId = (int)$product['ma_san_pham'];
                            $originalPrice = (float)$product['gia_ban'];

                            $hasPromotion = isset($promotionMap[$productId]);

                            $finalPrice = $hasPromotion
                                ? $promotionMap[$productId]['final_price']
                                : $originalPrice;

                            $discountPercent = $hasPromotion && $originalPrice > 0
                                ? round((1 - $finalPrice / $originalPrice) * 100)
                                : 0;
                            ?>

                            <div class="col-6 col-md-4">
                                <div class="customer-product-item">

                                    <div class="product-image-placeholder">
                                        <i class="bi bi-laptop"></i>
                                    </div>

                                    <?php if ($hasPromotion): ?>
                                        <div class="mb-2">
                                            <span class="product-discount">
                                                Giảm <?= $discountPercent ?>%
                                            </span>
                                        </div>
                                    <?php endif; ?>

                                    <h6>
                                        <?= htmlspecialchars($product['ten_san_pham']) ?>
                                    </h6>

                                    <div class="product-sku mb-2">
                                        SKU: <?= htmlspecialchars($product['ma_sku']) ?>
                                    </div>

                                    <div class="text-muted small mb-2">
                                        <?= htmlspecialchars($product['ten_thuong_hieu'] ?? 'Chưa phân loại thương hiệu') ?>
                                    </div>

                                    <div class="product-price">
                                        <?= customer_products_price($finalPrice) ?>
                                    </div>

                                    <?php if ($hasPromotion): ?>
                                        <div class="product-old-price">
                                            <?= customer_products_price($originalPrice) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="small text-muted mt-2 mb-3">
                                        Bảo hành: <?= (int)$product['thoi_han_bao_hanh'] ?> tháng
                                    </div>

                                    <a
                                        href="product_detail.php?id=<?= $productId ?>"
                                        class="btn btn-outline-primary btn-sm w-100"
                                    >
                                        Xem chi tiết
                                    </a>

                                </div>
                            </div>

                        <?php endforeach; ?>

                    </div>

                    <!-- PHAN TRANG -->
                    <?php if ($totalPages > 1): ?>

                        <nav class="products-pagination">
                            <ul class="pagination justify-content-center">

                                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                    <a
                                        class="page-link"
                                        href="<?= htmlspecialchars(customer_products_url(max(1, $currentPage - 1))) ?>"
                                    >
                                        Trước
                                    </a>
                                </li>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                                        <a
                                            class="page-link"
                                            href="<?= htmlspecialchars(customer_products_url($i)) ?>"
                                        >
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                    <a
                                        class="page-link"
                                        href="<?= htmlspecialchars(customer_products_url(min($totalPages, $currentPage + 1))) ?>"
                                    >
                                        Sau
                                    </a>
                                </li>

                            </ul>
                        </nav>

                    <?php endif; ?>

                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
