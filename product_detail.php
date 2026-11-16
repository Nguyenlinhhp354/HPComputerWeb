
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/customers/product_detail.php
// CHUC NANG: CHI TIET SAN PHAM VA DANH GIA SAN PHAM
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

// =====================================================
// 1. HAM HO TRO
// =====================================================

if (!function_exists('customer_detail_escape')) {
    function customer_detail_escape($value)
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('customer_detail_money')) {
    function customer_detail_money($value)
    {
        return number_format((float)$value, 0, ',', '.') . 'đ';
    }
}

if (!function_exists('customer_detail_stars')) {
    function customer_detail_stars($number)
    {
        $number = max(0, min(5, (int)$number));

        return str_repeat('★', $number)
            . str_repeat('☆', 5 - $number);
    }
}

// =====================================================
// 2. KHOI TAO SESSION VA CSRF
// =====================================================

if (empty($_SESSION['customer_detail_csrf'])) {
    $_SESSION['customer_detail_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['customer_detail_csrf'];

$userId = (int)($_SESSION['ma_nguoi_dung'] ?? 0);

$baseUrl = defined('BASE_URL') ? BASE_URL : '/HPComputer';

// =====================================================
// 3. LAY MA SAN PHAM
// =====================================================

$productId = 0;

if (isset($_GET['ma_san_pham'])) {
    $productId = (int)$_GET['ma_san_pham'];
} elseif (isset($_GET['id'])) {
    $productId = (int)$_GET['id'];
}

if ($productId <= 0) {
    http_response_code(404);
}

// =====================================================
// 4. KHOI TAO DU LIEU
// =====================================================

$product = null;
$promotions = [];
$relatedProducts = [];
$reviews = [];
$reviewStats = [
    'total' => 0,
    'average' => 0
];

$eligibleOrders = [];

$errorMessage = '';
$successMessage = '';
$reviewError = '';
$reviewSuccess = '';

$originalPrice = 0;
$finalPrice = 0;
$discountAmount = 0;

// =====================================================
// 5. LAY THONG TIN SAN PHAM
// =====================================================

if ($productId > 0) {

    try {

        $sqlProduct = "
            SELECT
                sp.ma_san_pham,
                sp.ma_danh_muc,
                sp.ma_thuong_hieu,
                sp.ma_sku,
                sp.ten_san_pham,
                sp.mo_ta,
                sp.gia_ban,
                sp.thoi_han_bao_hanh,
                sp.trang_thai,
                sp.ngay_tao,

                dm.ten_danh_muc,
                th.ten_thuong_hieu

            FROM san_pham sp

            LEFT JOIN danh_muc dm
                ON sp.ma_danh_muc = dm.ma_danh_muc

            LEFT JOIN thuong_hieu th
                ON sp.ma_thuong_hieu = th.ma_thuong_hieu

            WHERE sp.ma_san_pham = :product_id
              AND sp.trang_thai = 'ACTIVE'

            LIMIT 1
        ";

        $stmtProduct = $pdo->prepare($sqlProduct);

        $stmtProduct->execute([
            ':product_id' => $productId
        ]);

        $product = $stmtProduct->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        error_log('[HP COMPUTER] Product detail: ' . $e->getMessage());

        $errorMessage = 'Không thể tải thông tin sản phẩm.';
    }
}

// =====================================================
// 6. XU LY KHI KHONG TIM THAY SAN PHAM
// =====================================================

if (!$product) {
    http_response_code(404);
}

// =====================================================
// 7. TINH GIA KHUYEN MAI
// =====================================================

if ($product) {

    $originalPrice = (float)$product['gia_ban'];
    $finalPrice = $originalPrice;

    try {

        $sqlPromotions = "
            SELECT
                ctkm.ma_chuong_trinh,
                ctkm.loai_giam_gia,
                ctkm.gia_tri_giam,
                ctkm.gia_tri_giam_toi_da,

                ctk.ten_chuong_trinh,
                ctk.mo_ta,
                ctk.ngay_bat_dau,
                ctk.ngay_ket_thuc

            FROM san_pham_khuyen_mai ctkm

            INNER JOIN chuong_trinh_khuyen_mai ctk
                ON ctkm.ma_chuong_trinh = ctk.ma_chuong_trinh

            WHERE ctkm.ma_san_pham = :product_id
              AND ctk.trang_thai = 'ACTIVE'
              AND NOW() BETWEEN ctk.ngay_bat_dau AND ctk.ngay_ket_thuc

            ORDER BY ctk.ngay_bat_dau DESC
        ";

        $stmtPromotions = $pdo->prepare($sqlPromotions);

        $stmtPromotions->execute([
            ':product_id' => $productId
        ]);

        $promotions = $stmtPromotions->fetchAll(PDO::FETCH_ASSOC);

        foreach ($promotions as $promotion) {

            $discount = 0;

            if ($promotion['loai_giam_gia'] === 'PERCENT') {

                $discount = $originalPrice
                    * ((float)$promotion['gia_tri_giam'] / 100);

                if (
                    $promotion['gia_tri_giam_toi_da'] !== null
                    && (float)$promotion['gia_tri_giam_toi_da'] > 0
                ) {
                    $discount = min(
                        $discount,
                        (float)$promotion['gia_tri_giam_toi_da']
                    );
                }

            } else {

                $discount = (float)$promotion['gia_tri_giam'];
            }

            $discount = max(
                0,
                min($discount, $originalPrice)
            );

            $candidatePrice = $originalPrice - $discount;

            // Chon muc gia tot nhat cho khach hang
            if ($candidatePrice < $finalPrice) {

                $finalPrice = $candidatePrice;
                $discountAmount = $discount;
            }
        }

    } catch (PDOException $e) {

        error_log('[HP COMPUTER] Promotion: ' . $e->getMessage());

        $promotions = [];
    }
}

// =====================================================
// 8. XU LY THEM SAN PHAM VAO GIO HANG
// =====================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'add_to_cart'
) {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken)
        || empty($_SESSION['customer_detail_csrf'])
        || !hash_equals(
            $_SESSION['customer_detail_csrf'],
            $submittedToken
        )
    ) {

        $errorMessage = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';

    } elseif (!$product) {

        $errorMessage = 'Sản phẩm không tồn tại.';

    } else {

        $quantity = filter_var(
            $_POST['so_luong'] ?? 1,
            FILTER_VALIDATE_INT
        );

        if ($quantity === false || $quantity < 1 || $quantity > 99) {

            $errorMessage = 'Số lượng phải từ 1 đến 99.';

        } else {

            if (!isset($_SESSION['customer_cart'])) {
                $_SESSION['customer_cart'] = [];
            }

            $currentQuantity = (int)(
                $_SESSION['customer_cart'][$productId] ?? 0
            );

            if (($currentQuantity + $quantity) > 99) {

                $errorMessage = 'Số lượng sản phẩm trong giỏ không được vượt quá 99.';

            } else {

                $_SESSION['customer_cart'][$productId] =
                    $currentQuantity + $quantity;

                header(
                    'Location: ' . $baseUrl
                    . '/modules/customers/product_detail.php'
                    . '?ma_san_pham=' . $productId
                    . '&added=1'
                );

                exit;
            }
        }
    }
}

// =====================================================
// 9. XU LY GUI DANH GIA SAN PHAM
// =====================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'submit_review'
) {

    $submittedToken = $_POST['csrf_token'] ?? '';

    $rating = filter_var(
        $_POST['so_sao'] ?? null,
        FILTER_VALIDATE_INT
    );

    $reviewContent = trim($_POST['noi_dung'] ?? '');

    $orderId = filter_var(
        $_POST['ma_don_hang'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        !is_string($submittedToken)
        || empty($_SESSION['customer_detail_csrf'])
        || !hash_equals(
            $_SESSION['customer_detail_csrf'],
            $submittedToken
        )
    ) {

        $reviewError = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';

    } elseif ($userId <= 0) {

        $reviewError = 'Vui lòng đăng nhập để đánh giá sản phẩm.';

    } elseif (!$product) {

        $reviewError = 'Sản phẩm không tồn tại.';

    } elseif (
        $rating === false
        || $rating < 1
        || $rating > 5
    ) {

        $reviewError = 'Vui lòng chọn số sao từ 1 đến 5.';

    } elseif ($reviewContent === '') {

        $reviewError = 'Vui lòng nhập nội dung đánh giá.';

    } elseif (mb_strlen($reviewContent, 'UTF-8') > 2000) {

        $reviewError = 'Nội dung đánh giá không được vượt quá 2000 ký tự.';

    } elseif ($orderId === false || $orderId <= 0) {

        $reviewError = 'Vui lòng chọn đơn hàng hợp lệ.';

    } else {

        try {

            // Kiem tra don hang da hoan thanh,
            // thuoc khach hang hien tai va co san pham nay.

            $sqlCheckOrder = "
                SELECT
                    dh.ma_don_hang

                FROM don_hang dh

                INNER JOIN khach_hang kh
                    ON dh.ma_khach_hang = kh.ma_khach_hang

                INNER JOIN chi_tiet_don_hang ct
                    ON dh.ma_don_hang = ct.ma_don_hang

                WHERE dh.ma_don_hang = :order_id
                  AND kh.ma_nguoi_dung = :user_id
                  AND ct.ma_san_pham = :product_id
                  AND dh.trang_thai_don_hang = 'COMPLETED'

                LIMIT 1
            ";

            $stmtCheckOrder = $pdo->prepare($sqlCheckOrder);

            $stmtCheckOrder->execute([
                ':order_id' => $orderId,
                ':user_id' => $userId,
                ':product_id' => $productId
            ]);

            $validOrder = $stmtCheckOrder->fetch(PDO::FETCH_ASSOC);

            if (!$validOrder) {

                $reviewError = 'Bạn chỉ có thể đánh giá sản phẩm đã mua trong đơn hàng hoàn thành.';

            } else {

                // Kiem tra danh gia trung

                $sqlDuplicate = "
                    SELECT ma_danh_gia

                    FROM danh_gia_san_pham

                    WHERE ma_san_pham = :product_id
                      AND ma_nguoi_dung = :user_id
                      AND ma_don_hang = :order_id

                    LIMIT 1
                ";

                $stmtDuplicate = $pdo->prepare($sqlDuplicate);

                $stmtDuplicate->execute([
                    ':product_id' => $productId,
                    ':user_id' => $userId,
                    ':order_id' => $orderId
                ]);

                if ($stmtDuplicate->fetch()) {

                    $reviewError = 'Bạn đã đánh giá sản phẩm này trong đơn hàng đã chọn.';

                } else {

                    // Cong khai ngay sau khi gui
                    // Khong can Admin duyet

                    $sqlInsertReview = "
                        INSERT INTO danh_gia_san_pham (
                            ma_san_pham,
                            ma_nguoi_dung,
                            ma_don_hang,
                            so_sao,
                            noi_dung,
                            trang_thai
                        ) VALUES (
                            :product_id,
                            :user_id,
                            :order_id,
                            :rating,
                            :content,
                            'PUBLISHED'
                        )
                    ";

                    $stmtInsertReview = $pdo->prepare($sqlInsertReview);

                    $stmtInsertReview->execute([
                        ':product_id' => $productId,
                        ':user_id' => $userId,
                        ':order_id' => $orderId,
                        ':rating' => $rating,
                        ':content' => $reviewContent
                    ]);

                    header(
                        'Location: ' . $baseUrl
                        . '/modules/customers/product_detail.php'
                        . '?ma_san_pham=' . $productId
                        . '&reviewed=1#reviews'
                    );

                    exit;
                }
            }

        } catch (PDOException $e) {

            error_log('[HP COMPUTER] Submit review: ' . $e->getMessage());

            $reviewError = 'Không thể gửi đánh giá. Vui lòng thử lại.';
        }
    }
}

// =====================================================
// 10. THONG BAO SAU KHI CHUYEN HUONG
// =====================================================

if (isset($_GET['added'])) {
    $successMessage = 'Đã thêm sản phẩm vào giỏ hàng thành công.';
}

if (isset($_GET['reviewed'])) {
    $reviewSuccess = 'Cảm ơn bạn! Đánh giá đã được công khai thành công.';
}

// =====================================================
// 11. LAY DON HANG DU DIEU KIEN DANH GIA
// =====================================================

if ($product && $userId > 0) {

    try {

        $sqlEligibleOrders = "
            SELECT DISTINCT
                dh.ma_don_hang,
                dh.ma_so_don_hang,
                dh.ngay_dat

            FROM don_hang dh

            INNER JOIN khach_hang kh
                ON dh.ma_khach_hang = kh.ma_khach_hang

            INNER JOIN chi_tiet_don_hang ct
                ON dh.ma_don_hang = ct.ma_don_hang

            WHERE kh.ma_nguoi_dung = :user_id
              AND ct.ma_san_pham = :product_id
              AND dh.trang_thai_don_hang = 'COMPLETED'

              AND NOT EXISTS (
                  SELECT 1

                  FROM danh_gia_san_pham dg

                  WHERE dg.ma_don_hang = dh.ma_don_hang
                    AND dg.ma_san_pham = :review_product_id
                    AND dg.ma_nguoi_dung = :review_user_id
              )

            ORDER BY dh.ngay_dat DESC
        ";

        $stmtEligibleOrders = $pdo->prepare($sqlEligibleOrders);

        $stmtEligibleOrders->execute([
            ':user_id' => $userId,
            ':product_id' => $productId,
            ':review_product_id' => $productId,
            ':review_user_id' => $userId
        ]);

        $eligibleOrders = $stmtEligibleOrders->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        error_log('[HP COMPUTER] Eligible orders: ' . $e->getMessage());
    }
}

// =====================================================
// 12. LAY THONG KE DANH GIA
// =====================================================

if ($product) {

    try {

        $sqlReviewStats = "
            SELECT
                COUNT(*) AS total,
                COALESCE(AVG(so_sao), 0) AS average

            FROM danh_gia_san_pham

            WHERE ma_san_pham = :product_id
              AND trang_thai = 'PUBLISHED'
        ";

        $stmtReviewStats = $pdo->prepare($sqlReviewStats);

        $stmtReviewStats->execute([
            ':product_id' => $productId
        ]);

        $reviewStats = $stmtReviewStats->fetch(PDO::FETCH_ASSOC);

        $reviewStats['total'] = (int)$reviewStats['total'];
        $reviewStats['average'] = (float)$reviewStats['average'];

    } catch (PDOException $e) {

        error_log('[HP COMPUTER] Review stats: ' . $e->getMessage());
    }
}

// =====================================================
// 13. LAY DANH SACH DANH GIA CONG KHAI
// =====================================================

if ($product) {

    try {

        $sqlReviews = "
            SELECT
                dg.ma_danh_gia,
                dg.so_sao,
                dg.noi_dung,
                dg.ngay_danh_gia,

                nd.ho_ten

            FROM danh_gia_san_pham dg

            INNER JOIN nguoi_dung nd
                ON dg.ma_nguoi_dung = nd.ma_nguoi_dung

            WHERE dg.ma_san_pham = :product_id
              AND dg.trang_thai = 'PUBLISHED'

            ORDER BY dg.ngay_danh_gia DESC

            LIMIT 50
        ";

        $stmtReviews = $pdo->prepare($sqlReviews);

        $stmtReviews->execute([
            ':product_id' => $productId
        ]);

        $reviews = $stmtReviews->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        error_log('[HP COMPUTER] Review list: ' . $e->getMessage());
    }
}

// =====================================================
// 14. LAY SAN PHAM LIEN QUAN
// =====================================================

if ($product) {

    try {

        $sqlRelated = "
            SELECT
                ma_san_pham,
                ma_sku,
                ten_san_pham,
                gia_ban,
                thoi_han_bao_hanh

            FROM san_pham

            WHERE ma_danh_muc = :category_id
              AND ma_san_pham <> :product_id
              AND trang_thai = 'ACTIVE'

            ORDER BY ngay_tao DESC

            LIMIT 4
        ";

        $stmtRelated = $pdo->prepare($sqlRelated);

        $stmtRelated->execute([
            ':category_id' => $product['ma_danh_muc'],
            ':product_id' => $productId
        ]);

        $relatedProducts = $stmtRelated->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        error_log('[HP COMPUTER] Related products: ' . $e->getMessage());
    }
}

// =====================================================
// 15. NAP GIAO DIEN KHACH HANG
// =====================================================

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

?>

<style>
.customer-detail {
    background: #f5f7fa;
    min-height: 100vh;
    padding: 30px 0;
}

.customer-detail .card {
    border: 0;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,.05);
}

.product-placeholder {
    min-height: 330px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    border-radius: 12px;
}

.product-placeholder i {
    font-size: 120px;
    color: #cbd5e1;
}

.product-title {
    font-size: 26px;
    font-weight: 700;
    color: #172033;
}

.product-price {
    font-size: 30px;
    color: #dc3545;
    font-weight: 700;
}

.old-price {
    color: #999;
    text-decoration: line-through;
}

.review-stars {
    color: #f59e0b;
    letter-spacing: 2px;
}

.review-item {
    border-bottom: 1px solid #e9ecef;
    padding: 20px 0;
}

.review-item:last-child {
    border-bottom: 0;
}

.related-product {
    height: 100%;
    transition: .2s;
}

.related-product:hover {
    transform: translateY(-4px);
}

.related-icon {
    background: #f8fafc;
    height: 130px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
}

.related-icon i {
    font-size: 55px;
    color: #94a3b8;
}

.section-title {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 20px;
}

.review-average {
    font-size: 42px;
    font-weight: 700;
    color: #f59e0b;
}

@media (max-width: 768px) {
    .product-title {
        font-size: 21px;
    }

    .product-price {
        font-size: 25px;
    }
}
</style>

<main class="customer-detail">

    <div class="container">

        <!-- BREADCRUMB -->

        <nav aria-label="breadcrumb" class="mb-4">

            <ol class="breadcrumb">

                <li class="breadcrumb-item">
                    <a href="<?= customer_detail_escape($baseUrl) ?>/modules/customers/index.php">
                        Trang chủ
                    </a>
                </li>

                <li class="breadcrumb-item">
                    <a href="<?= customer_detail_escape($baseUrl) ?>/modules/customers/products.php">
                        Sản phẩm
                    </a>
                </li>

                <li class="breadcrumb-item active">
                    Chi tiết sản phẩm
                </li>

            </ol>

        </nav>

        <!-- THONG BAO -->

        <?php if ($successMessage !== ''): ?>

            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                <?= customer_detail_escape($successMessage) ?>
            </div>

        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>

            <div class="alert alert-danger">
                <?= customer_detail_escape($errorMessage) ?>
            </div>

        <?php endif; ?>

        <?php if (!$product): ?>

            <div class="card p-5 text-center">

                <i class="bi bi-exclamation-circle text-warning"
                   style="font-size:60px"></i>

                <h3 class="mt-3">Không tìm thấy sản phẩm</h3>

                <p class="text-muted">
                    Sản phẩm không tồn tại hoặc đã ngừng kinh doanh.
                </p>

                <div>
                    <a
                        href="<?= customer_detail_escape($baseUrl) ?>/modules/customers/products.php"
                        class="btn btn-primary"
                    >
                        Quay lại cửa hàng
                    </a>
                </div>

            </div>

        <?php else: ?>

            <!-- ================================================= -->
            <!-- THONG TIN CHI TIET SAN PHAM -->
            <!-- ================================================= -->

            <div class="card p-4 mb-4">

                <div class="row g-4">

                    <!-- HINH ANH SAN PHAM -->

                    <div class="col-lg-5">

                        <div class="product-placeholder">

                            <i class="bi bi-laptop"></i>

                        </div>

                    </div>

                    <!-- THONG TIN SAN PHAM -->

                    <div class="col-lg-7">

                        <div class="mb-2">

                            <span class="badge bg-primary">
                                <?= customer_detail_escape(
                                    $product['ten_danh_muc'] ?? 'Sản phẩm'
                                ) ?>
                            </span>

                            <?php if (!empty($product['ten_thuong_hieu'])): ?>

                                <span class="badge bg-secondary">
                                    <?= customer_detail_escape(
                                        $product['ten_thuong_hieu']
                                    ) ?>
                                </span>

                            <?php endif; ?>

                        </div>

                        <h1 class="product-title">
                            <?= customer_detail_escape($product['ten_san_pham']) ?>
                        </h1>

                        <p class="text-muted mb-2">

                            Mã SKU:
                            <strong>
                                <?= customer_detail_escape($product['ma_sku']) ?>
                            </strong>

                        </p>

                        <div class="mb-3">

                            <span class="review-stars">
                                <?= customer_detail_stars(
                                    (int)round($reviewStats['average'])
                                ) ?>
                            </span>

                            <span class="text-muted ms-2">
                                <?= number_format($reviewStats['average'], 1) ?>/5
                                (<?= (int)$reviewStats['total'] ?> đánh giá)
                            </span>

                        </div>

                        <hr>

                        <!-- GIA BAN -->

                        <div class="mb-3">

                            <div class="product-price">

                                <?= customer_detail_money($finalPrice) ?>

                            </div>

                            <?php if ($discountAmount > 0): ?>

                                <div class="mt-1">

                                    <span class="old-price">
                                        <?= customer_detail_money($originalPrice) ?>
                                    </span>

                                    <span class="badge bg-danger ms-2">
                                        Giảm <?= customer_detail_money($discountAmount) ?>
                                    </span>

                                </div>

                            <?php endif; ?>

                        </div>

                        <!-- BAO HANH -->

                        <p>

                            <i class="bi bi-shield-check text-success me-2"></i>

                            Bảo hành:

                            <strong>
                                <?= (int)$product['thoi_han_bao_hanh'] ?> tháng
                            </strong>

                        </p>

                        <!-- KHUYEN MAI -->

                        <?php if (!empty($promotions)): ?>

                            <div class="alert alert-light border">

                                <strong>
                                    <i class="bi bi-gift text-danger me-2"></i>
                                    Chương trình khuyến mãi
                                </strong>

                                <?php foreach ($promotions as $promotion): ?>

                                    <div class="mt-2">

                                        <?= customer_detail_escape(
                                            $promotion['ten_chuong_trinh']
                                        ) ?>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                        <!-- THEM GIO HANG -->

                        <form method="POST" class="mt-4">

                            <input
                                type="hidden"
                                name="action"
                                value="add_to_cart"
                            >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= customer_detail_escape($csrfToken) ?>"
                            >

                            <div class="row align-items-end g-3">

                                <div class="col-md-3">

                                    <label class="form-label">
                                        Số lượng
                                    </label>

                                    <input
                                        type="number"
                                        name="so_luong"
                                        class="form-control"
                                        value="1"
                                        min="1"
                                        max="99"
                                        required
                                    >

                                </div>

                                <div class="col-md-9">

                                    <button
                                        type="submit"
                                        class="btn btn-primary btn-lg w-100"
                                    >

                                        <i class="bi bi-cart-plus me-2"></i>

                                        Thêm vào giỏ hàng

                                    </button>

                                </div>

                            </div>

                        </form>

                        <div class="mt-3">

                            <a
                                href="<?= customer_detail_escape($baseUrl) ?>/modules/customers/cart.php"
                                class="btn btn-outline-secondary"
                            >
                                <i class="bi bi-cart me-1"></i>
                                Xem giỏ hàng
                            </a>

                        </div>

                    </div>

                </div>

            </div>

            <!-- ================================================= -->
            <!-- MO TA SAN PHAM -->
            <!-- ================================================= -->

            <div class="card p-4 mb-4">

                <h2 class="section-title">
                    <i class="bi bi-info-circle me-2 text-primary"></i>
                    Mô tả sản phẩm
                </h2>

                <div style="white-space:pre-line; line-height:1.8">

                    <?php if (!empty($product['mo_ta'])): ?>

                        <?= customer_detail_escape($product['mo_ta']) ?>

                    <?php else: ?>

                        <p class="text-muted">
                            Sản phẩm hiện chưa có mô tả chi tiết.
                        </p>

                    <?php endif; ?>

                </div>

            </div>

            <!-- ================================================= -->
            <!-- DANH GIA SAN PHAM -->
            <!-- ================================================= -->

            <div class="card p-4 mb-4" id="reviews">

                <h2 class="section-title">

                    <i class="bi bi-star me-2 text-warning"></i>

                    Đánh giá sản phẩm

                </h2>

                <!-- THONG KE DANH GIA -->

                <div class="row align-items-center mb-4">

                    <div class="col-md-4 text-center">

                        <div class="review-average">

                            <?= number_format(
                                $reviewStats['average'],
                                1
                            ) ?>

                            <small class="fs-5">/5</small>

                        </div>

                        <div class="review-stars fs-4">

                            <?= customer_detail_stars(
                                (int)round($reviewStats['average'])
                            ) ?>

                        </div>

                        <p class="text-muted mb-0">

                            <?= (int)$reviewStats['total'] ?>
                            đánh giá công khai

                        </p>

                    </div>

                    <div class="col-md-8">

                        <p class="text-muted mb-0">

                            Đánh giá được công khai ngay sau khi gửi.
                            Hệ thống không yêu cầu Admin duyệt hoặc chỉnh sửa
                            nội dung và số sao của khách hàng.

                        </p>

                    </div>

                </div>

                <hr>

                <!-- FORM DANH GIA -->

                <h5 class="fw-bold mb-3">
                    Viết đánh giá của bạn
                </h5>

                <?php if ($reviewSuccess !== ''): ?>

                    <div class="alert alert-success">

                        <i class="bi bi-check-circle me-2"></i>

                        <?= customer_detail_escape($reviewSuccess) ?>

                    </div>

                <?php endif; ?>

                <?php if ($reviewError !== ''): ?>

                    <div class="alert alert-danger">

                        <?= customer_detail_escape($reviewError) ?>

                    </div>

                <?php endif; ?>

                <?php if ($userId <= 0): ?>

                    <div class="alert alert-info">

                        Vui lòng

                        <a href="<?= customer_detail_escape($baseUrl) ?>/login.php">
                            đăng nhập
                        </a>

                        để đánh giá sản phẩm.

                    </div>

                <?php elseif (empty($eligibleOrders)): ?>

                    <div class="alert alert-info">

                        <i class="bi bi-info-circle me-2"></i>

                        Bạn cần mua sản phẩm và có đơn hàng hoàn thành
                        mới được gửi đánh giá.

                        <br>

                        Mỗi đơn hàng chỉ được đánh giá một lần cho sản phẩm này.

                    </div>

                <?php else: ?>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="action"
                            value="submit_review"
                        >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= customer_detail_escape($csrfToken) ?>"
                        >

                        <!-- CHON DON HANG -->

                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Đơn hàng đã mua
                            </label>

                            <select
                                name="ma_don_hang"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    -- Chọn đơn hàng --
                                </option>

                                <?php foreach ($eligibleOrders as $order): ?>

                                    <option
                                        value="<?= (int)$order['ma_don_hang'] ?>"
                                    >

                                        <?= customer_detail_escape(
                                            $order['ma_so_don_hang']
                                        ) ?>

                                        -

                                        <?= date(
                                            'd/m/Y',
                                            strtotime($order['ngay_dat'])
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <!-- CHON SO SAO -->

                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Số sao đánh giá
                            </label>

                            <select
                                name="so_sao"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    -- Chọn số sao --
                                </option>

                                <option value="5">
                                    5 sao - Rất hài lòng
                                </option>

                                <option value="4">
                                    4 sao - Hài lòng
                                </option>

                                <option value="3">
                                    3 sao - Bình thường
                                </option>

                                <option value="2">
                                    2 sao - Không hài lòng
                                </option>

                                <option value="1">
                                    1 sao - Rất không hài lòng
                                </option>

                            </select>

                        </div>

                        <!-- NOI DUNG -->

                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Nội dung đánh giá
                            </label>

                            <textarea
                                name="noi_dung"
                                class="form-control"
                                rows="4"
                                maxlength="2000"
                                placeholder="Chia sẻ trải nghiệm của bạn về sản phẩm..."
                                required
                            ></textarea>

                            <small class="text-muted">
                                Tối đa 2000 ký tự.
                            </small>

                        </div>

                        <div class="alert alert-light border">

                            <i class="bi bi-shield-check text-success me-2"></i>

                            Đánh giá sẽ được công khai ngay sau khi gửi.
                            Nội dung và số sao do khách hàng tự quyết định.

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >

                            <i class="bi bi-send me-2"></i>

                            Gửi đánh giá

                        </button>

                    </form>

                <?php endif; ?>

                <hr class="my-4">

                <!-- DANH SACH DANH GIA -->

                <h5 class="fw-bold mb-3">

                    Đánh giá từ khách hàng

                    <span class="badge bg-secondary">
                        <?= (int)$reviewStats['total'] ?>
                    </span>

                </h5>

                <?php if (empty($reviews)): ?>

                    <div class="text-center py-4">

                        <i
                            class="bi bi-chat-square-text text-muted"
                            style="font-size:45px"
                        ></i>

                        <p class="text-muted mt-3 mb-0">

                            Chưa có đánh giá nào cho sản phẩm này.

                        </p>

                    </div>

                <?php else: ?>

                    <?php foreach ($reviews as $review): ?>

                        <div class="review-item">

                            <div class="d-flex justify-content-between flex-wrap">

                                <div>

                                    <strong>

                                        <?= customer_detail_escape(
                                            $review['ho_ten']
                                        ) ?>

                                    </strong>

                                    <div class="review-stars mt-1">

                                        <?= customer_detail_stars(
                                            $review['so_sao']
                                        ) ?>

                                    </div>

                                </div>

                                <small class="text-muted">

                                    <?= date(
                                        'd/m/Y H:i',
                                        strtotime($review['ngay_danh_gia'])
                                    ) ?>

                                </small>

                            </div>

                            <p class="mt-3 mb-0" style="white-space:pre-line">

                                <?= customer_detail_escape(
                                    $review['noi_dung']
                                ) ?>

                            </p>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

            <!-- ================================================= -->
            <!-- SAN PHAM LIEN QUAN -->
            <!-- ================================================= -->

            <?php if (!empty($relatedProducts)): ?>

                <div class="mb-4">

                    <h2 class="section-title">

                        <i class="bi bi-grid me-2 text-primary"></i>

                        Sản phẩm liên quan

                    </h2>

                    <div class="row g-3">

                        <?php foreach ($relatedProducts as $related): ?>

                            <div class="col-6 col-md-3">

                                <div class="card related-product p-3">

                                    <div class="related-icon mb-3">

                                        <i class="bi bi-laptop"></i>

                                    </div>

                                    <h6 class="fw-bold">

                                        <?= customer_detail_escape(
                                            $related['ten_san_pham']
                                        ) ?>

                                    </h6>

                                    <p class="text-danger fw-bold mb-2">

                                        <?= customer_detail_money(
                                            $related['gia_ban']
                                        ) ?>

                                    </p>

                                    <small class="text-muted mb-3">

                                        SKU:
                                        <?= customer_detail_escape(
                                            $related['ma_sku']
                                        ) ?>

                                    </small>

                                    <a
                                        href="<?= customer_detail_escape($baseUrl) ?>/modules/customers/product_detail.php?ma_san_pham=<?= (int)$related['ma_san_pham'] ?>"
                                        class="btn btn-outline-primary btn-sm mt-auto"
                                    >

                                        Xem chi tiết

                                    </a>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>

</main>

<?php

// =====================================================
// 16. FOOTER KHACH HANG
// =====================================================

require_once __DIR__ . '/includes/footer.php';

?>
