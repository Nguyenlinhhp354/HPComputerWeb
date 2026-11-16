
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/customer/index.php
// CHUC NANG: TRANG CHU KHACH HANG
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

// Neu da dang nhap voi tai khoan khong phai khach hang
// thi chuyen ve khu vuc quan tri
if (
    !empty($_SESSION['ma_nguoi_dung']) &&
    (int)($_SESSION['ma_vai_tro'] ?? 0) !== 6
) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Kiem tra tai khoan khach hang con hoat dong
if (
    !empty($_SESSION['ma_nguoi_dung']) &&
    (int)($_SESSION['ma_vai_tro'] ?? 0) === 6
) {
    $accountStmt = $pdo->prepare("
        SELECT ma_vai_tro, trang_thai, ho_ten
        FROM nguoi_dung
        WHERE ma_nguoi_dung = :ma_nguoi_dung
        LIMIT 1
    ");

    $accountStmt->execute([
        ':ma_nguoi_dung' => $_SESSION['ma_nguoi_dung']
    ]);

    $account = $accountStmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$account ||
        (int)$account['ma_vai_tro'] !== 6 ||
        $account['trang_thai'] !== 'ACTIVE'
    ) {
        $_SESSION = [];
        session_regenerate_id(true);
    } else {
        $_SESSION['ho_ten'] = $account['ho_ten'];
    }
}

// =====================================================
// KHOI TAO DU LIEU
// =====================================================

$categories = [];
$newProducts = [];
$promotionProducts = [];

try {

    // -------------------------------------------------
    // 1. DANH MUC SAN PHAM
    // -------------------------------------------------

    $categoryStmt = $pdo->query("
        SELECT
            dm.ma_danh_muc,
            dm.ten_danh_muc,
            COUNT(sp.ma_san_pham) AS so_san_pham
        FROM danh_muc dm
        LEFT JOIN san_pham sp
            ON dm.ma_danh_muc = sp.ma_danh_muc
            AND sp.trang_thai = 'ACTIVE'
        WHERE dm.trang_thai = 'ACTIVE'
        GROUP BY
            dm.ma_danh_muc,
            dm.ten_danh_muc
        ORDER BY so_san_pham DESC, dm.ten_danh_muc ASC
        LIMIT 8
    ");

    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

    // -------------------------------------------------
    // 2. SAN PHAM MOI NHAT
    // -------------------------------------------------

    $newProductStmt = $pdo->query("
        SELECT
            sp.ma_san_pham,
            sp.ma_sku,
            sp.ten_san_pham,
            sp.gia_ban,
            sp.thoi_han_bao_hanh,
            dm.ten_danh_muc,
            th.ten_thuong_hieu
        FROM san_pham sp
        LEFT JOIN danh_muc dm
            ON sp.ma_danh_muc = dm.ma_danh_muc
        LEFT JOIN thuong_hieu th
            ON sp.ma_thuong_hieu = th.ma_thuong_hieu
        WHERE sp.trang_thai = 'ACTIVE'
        ORDER BY sp.ngay_tao DESC, sp.ma_san_pham DESC
        LIMIT 8
    ");

    $newProducts = $newProductStmt->fetchAll(PDO::FETCH_ASSOC);

    // -------------------------------------------------
    // 3. SAN PHAM DANG KHUYEN MAI
    // -------------------------------------------------

    $promotionStmt = $pdo->query("
        SELECT
            sp.ma_san_pham,
            sp.ma_sku,
            sp.ten_san_pham,
            sp.gia_ban,
            dm.ten_danh_muc,
            th.ten_thuong_hieu,
            ctkm.ma_chuong_trinh,
            ctkm.ten_chuong_trinh,
            spkm.loai_giam_gia,
            spkm.gia_tri_giam,
            spkm.gia_tri_giam_toi_da
        FROM san_pham_khuyen_mai spkm
        INNER JOIN chuong_trinh_khuyen_mai ctkm
            ON spkm.ma_chuong_trinh = ctkm.ma_chuong_trinh
        INNER JOIN san_pham sp
            ON spkm.ma_san_pham = sp.ma_san_pham
        LEFT JOIN danh_muc dm
            ON sp.ma_danh_muc = dm.ma_danh_muc
        LEFT JOIN thuong_hieu th
            ON sp.ma_thuong_hieu = th.ma_thuong_hieu
        WHERE
            sp.trang_thai = 'ACTIVE'
            AND ctkm.trang_thai = 'ACTIVE'
            AND NOW() BETWEEN ctkm.ngay_bat_dau AND ctkm.ngay_ket_thuc
        ORDER BY sp.ma_san_pham ASC
    ");

    $promotionRows = $promotionStmt->fetchAll(PDO::FETCH_ASSOC);

    // -------------------------------------------------
    // 4. TINH GIA SAU KHI GIAM
    // Neu mot san pham co nhieu chuong trinh,
    // chon chuong trinh co gia sau giam thap nhat.
    // -------------------------------------------------

    foreach ($promotionRows as $promotion) {

        $originalPrice = (float)$promotion['gia_ban'];
        $discountValue = (float)$promotion['gia_tri_giam'];

        if ($originalPrice <= 0) {
            continue;
        }

        if ($promotion['loai_giam_gia'] === 'PERCENT') {

            $discountAmount = $originalPrice * $discountValue / 100;

            if ($promotion['gia_tri_giam_toi_da'] !== null) {
                $maxDiscount = (float)$promotion['gia_tri_giam_toi_da'];

                $discountAmount = min(
                    $discountAmount,
                    $maxDiscount
                );
            }

        } else {

            $discountAmount = $discountValue;
        }

        $discountAmount = min(
            max(0, $discountAmount),
            $originalPrice
        );

        $finalPrice = max(
            0,
            $originalPrice - $discountAmount
        );

        $productId = (int)$promotion['ma_san_pham'];

        $promotion['gia_goc'] = $originalPrice;
        $promotion['so_tien_giam'] = $discountAmount;
        $promotion['gia_sau_giam'] = $finalPrice;

        // Chi giu lai chuong trinh tot nhat cho san pham
        if (
            !isset($promotionProducts[$productId]) ||
            $finalPrice < $promotionProducts[$productId]['gia_sau_giam']
        ) {
            $promotionProducts[$productId] = $promotion;
        }
    }

    $promotionProducts = array_values($promotionProducts);

    // Sap xep san pham co muc giam cao nhat len truoc
    usort($promotionProducts, function ($a, $b) {
        return $b['so_tien_giam'] <=> $a['so_tien_giam'];
    });

    $promotionProducts = array_slice($promotionProducts, 0, 8);

} catch (PDOException $e) {

    error_log($e->getMessage());

    $categories = [];
    $newProducts = [];
    $promotionProducts = [];
}

// =====================================================
// HAM DINH DANG TIEN
// =====================================================

function customer_format_price($price)
{
    return number_format((float)$price, 0, ',', '.') . 'đ';
}

// =====================================================
// HAM HIEN THI THE SAN PHAM
// =====================================================

function customer_product_card($product, $isPromotion = false)
{
    $productId = (int)$product['ma_san_pham'];

    $productName = htmlspecialchars(
        $product['ten_san_pham'],
        ENT_QUOTES,
        'UTF-8'
    );

    $categoryName = htmlspecialchars(
        $product['ten_danh_muc'] ?? 'Sản phẩm',
        ENT_QUOTES,
        'UTF-8'
    );

    $brandName = htmlspecialchars(
        $product['ten_thuong_hieu'] ?? 'Đang cập nhật',
        ENT_QUOTES,
        'UTF-8'
    );

    $price = (float)$product['gia_ban'];

    $finalPrice = $isPromotion
        ? (float)$product['gia_sau_giam']
        : $price;

    $discountPercent = $isPromotion && $price > 0
        ? round(($price - $finalPrice) / $price * 100)
        : 0;
    ?>

    <div class="col-6 col-md-4 col-lg-3">

        <div class="product-card">

            <a href="<?= BASE_URL ?>/modules/customer/product_detail.php?ma_san_pham=<?= $productId ?>"
               class="text-decoration-none">

                <div class="product-image">

                    <i class="bi bi-laptop"></i>

                    <?php if ($isPromotion && $discountPercent > 0): ?>
                        <span class="product-badge">
                            -<?= $discountPercent ?>%
                        </span>
                    <?php endif; ?>

                </div>

                <div class="product-info">

                    <div class="product-category">
                        <?= $categoryName ?>
                    </div>

                    <span class="product-name">
                        <?= $productName ?>
                    </span>

                    <?php if ($isPromotion): ?>

                        <div class="product-price">
                            <?= customer_format_price($finalPrice) ?>
                        </div>

                        <div class="product-old-price">
                            <?= customer_format_price($price) ?>
                        </div>

                    <?php else: ?>

                        <div class="product-price">
                            <?= customer_format_price($price) ?>
                        </div>

                    <?php endif; ?>

                    <div class="product-brand">
                        Thương hiệu: <?= $brandName ?>
                    </div>

                </div>

            </a>

            <div class="product-card-footer">
                <a href="<?= BASE_URL ?>/modules/customer/product_detail.php?ma_san_pham=<?= $productId ?>"
                   class="btn btn-product">
                    <i class="bi bi-eye me-1"></i>
                    Xem chi tiết
                </a>
            </div>

        </div>

    </div>

    <?php
}

// =====================================================
// HIEN THI GIAO DIEN
// =====================================================

$pageTitle = 'Trang chủ';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="customer-main">

    <div class="container">

        <!-- BANNER -->
        <section class="home-banner">

            <div class="home-banner-content">

                <span class="badge bg-warning text-dark mb-3">
                    HP COMPUTER
                </span>

                <h1>
                    Công nghệ cho mọi nhu cầu của bạn
                </h1>

                <p>
                    Khám phá laptop, máy tính, linh kiện và phụ kiện
                    công nghệ với nhiều lựa chọn phù hợp.
                </p>

                <a href="<?= BASE_URL ?>/modules/customer/products.php"
                   class="btn btn-light fw-bold px-4 py-2">
                    Khám phá sản phẩm
                    <i class="bi bi-arrow-right ms-2"></i>
                </a>

            </div>

            <div class="banner-decoration">
                <i class="bi bi-pc-display"></i>
            </div>

        </section>

        <!-- DICH VU -->
        <section class="service-strip">

            <div class="row g-3">

                <div class="col-6 col-lg-3">
                    <div class="service-item">
                        <i class="bi bi-truck"></i>
                        <div>
                            <strong>Giao hàng toàn quốc</strong>
                            <small>Giao hàng tận nơi</small>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="service-item">
                        <i class="bi bi-shield-check"></i>
                        <div>
                            <strong>Bảo hành sản phẩm</strong>
                            <small>Hỗ trợ theo chính sách</small>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="service-item">
                        <i class="bi bi-headset"></i>
                        <div>
                            <strong>Hỗ trợ khách hàng</strong>
                            <small>Tư vấn sản phẩm</small>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="service-item">
                        <i class="bi bi-credit-card"></i>
                        <div>
                            <strong>Thanh toán linh hoạt</strong>
                            <small>Nhiều phương thức</small>
                        </div>
                    </div>
                </div>

            </div>

        </section>

        <!-- DANH MUC -->
        <section>

            <div class="section-heading">
                <h2>
                    <i class="bi bi-grid-fill me-2 text-primary"></i>
                    Danh mục nổi bật
                </h2>

                <a href="<?= BASE_URL ?>/modules/customer/products.php">
                    Xem tất cả
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div class="row g-3">

                <?php if (!empty($categories)): ?>

                    <?php foreach ($categories as $category): ?>

                        <div class="col-6 col-md-3 col-lg-3">

                            <a href="<?= BASE_URL ?>/modules/customer/products.php?ma_danh_muc=<?= (int)$category['ma_danh_muc'] ?>"
                               class="category-card">

                                <i class="bi bi-laptop"></i>

                                <span>
                                    <?= htmlspecialchars(
                                        $category['ten_danh_muc'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                                <div class="small text-secondary mt-2">
                                    <?= (int)$category['so_san_pham'] ?> sản phẩm
                                </div>

                            </a>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="col-12">
                        <div class="alert alert-light border">
                            Chưa có danh mục sản phẩm để hiển thị.
                        </div>
                    </div>

                <?php endif; ?>

            </div>

        </section>

        <!-- SAN PHAM KHUYEN MAI -->
        <section>

            <div class="section-heading">

                <h2>
                    <i class="bi bi-lightning-charge-fill text-danger me-2"></i>
                    Sản phẩm khuyến mãi
                </h2>

                <a href="<?= BASE_URL ?>/modules/customer/products.php?promotion=1">
                    Xem tất cả
                    <i class="bi bi-arrow-right"></i>
                </a>

            </div>

            <div class="row g-3">

                <?php if (!empty($promotionProducts)): ?>

                    <?php foreach ($promotionProducts as $product): ?>

                        <?php customer_product_card($product, true); ?>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="col-12">
                        <div class="alert alert-light border">
                            Hiện chưa có sản phẩm đang áp dụng khuyến mãi.
                        </div>
                    </div>

                <?php endif; ?>

            </div>

        </section>

        <!-- SAN PHAM MOI -->
        <section>

            <div class="section-heading">

                <h2>
                    <i class="bi bi-stars text-primary me-2"></i>
                    Sản phẩm mới
                </h2>

                <a href="<?= BASE_URL ?>/modules/customer/products.php">
                    Xem tất cả
                    <i class="bi bi-arrow-right"></i>
                </a>

            </div>

            <div class="row g-3">

                <?php if (!empty($newProducts)): ?>

                    <?php foreach ($newProducts as $product): ?>

                        <?php customer_product_card($product, false); ?>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="col-12">
                        <div class="alert alert-light border">
                            Chưa có sản phẩm nào được đăng bán.
                        </div>
                    </div>

                <?php endif; ?>

            </div>

        </section>

    </div>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
