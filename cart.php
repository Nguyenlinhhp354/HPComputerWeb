
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/customers/cart.php
// CHUC NANG: QUAN LY GIO HANG KHACH HANG
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

// =====================================================
// 1. KHOI TAO GIO HANG VA CSRF
// =====================================================

if (!isset($_SESSION['customer_cart']) || !is_array($_SESSION['customer_cart'])) {
    $_SESSION['customer_cart'] = [];
}

if (empty($_SESSION['customer_cart_csrf'])) {
    $_SESSION['customer_cart_csrf'] = bin2hex(random_bytes(32));
}

$cart = &$_SESSION['customer_cart'];
$csrfToken = $_SESSION['customer_cart_csrf'];

$errorMessage = '';
$successMessage = '';

// =====================================================
// 2. XU LY CAC THAO TAC GIO HANG
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        $errorMessage = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } else {

        $action = $_POST['action'] ?? '';

        // ---------------------------------------------
        // CAP NHAT SO LUONG
        // ---------------------------------------------

        if ($action === 'update') {

            $productId = (int)($_POST['product_id'] ?? 0);
            $quantity = filter_var(
                $_POST['quantity'] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($productId <= 0 || $quantity === false || $quantity === null) {
                $errorMessage = 'Thông tin sản phẩm không hợp lệ.';
            } elseif ($quantity < 1 || $quantity > 99) {
                $errorMessage = 'Số lượng phải từ 1 đến 99.';
            } elseif (!isset($cart[$productId])) {
                $errorMessage = 'Sản phẩm không tồn tại trong giỏ hàng.';
            } else {
                $cart[$productId] = $quantity;
                $_SESSION['customer_cart_message'] = 'Đã cập nhật số lượng sản phẩm.';
            }

        // ---------------------------------------------
        // XOA MOT SAN PHAM
        // ---------------------------------------------

        } elseif ($action === 'remove') {

            $productId = (int)($_POST['product_id'] ?? 0);

            if ($productId > 0 && isset($cart[$productId])) {
                unset($cart[$productId]);
                $_SESSION['customer_cart_message'] = 'Đã xóa sản phẩm khỏi giỏ hàng.';
            }

        // ---------------------------------------------
        // XOA TOAN BO GIO HANG
        // ---------------------------------------------

        } elseif ($action === 'clear') {

            $_SESSION['customer_cart'] = [];
            $_SESSION['customer_cart_message'] = 'Đã xóa toàn bộ giỏ hàng.';

        }

    }

    // Chuyen huong sau POST
    header('Location: ' . BASE_URL . '/modules/customers/cart.php');
    exit;
}

// =====================================================
// 3. LAY THONG BAO
// =====================================================

if (!empty($_SESSION['customer_cart_message'])) {
    $successMessage = $_SESSION['customer_cart_message'];
    unset($_SESSION['customer_cart_message']);
}

// =====================================================
// 4. LAM SACH DU LIEU GIO HANG
// =====================================================

$cleanCart = [];

foreach ($cart as $productId => $quantity) {

    $productId = (int)$productId;
    $quantity = (int)$quantity;

    if ($productId > 0 && $quantity >= 1 && $quantity <= 99) {
        $cleanCart[$productId] = $quantity;
    }
}

$_SESSION['customer_cart'] = $cleanCart;
$cart = &$_SESSION['customer_cart'];

// =====================================================
// 5. LAY DANH SACH SAN PHAM
// =====================================================

$cartItems = [];
$subtotal = 0;
$totalDiscount = 0;
$grandTotal = 0;
$totalQuantity = 0;

if (!empty($cart)) {

    $productIds = array_keys($cart);

    $placeholders = [];
    $params = [];

    foreach ($productIds as $index => $productId) {
        $placeholder = ':product_' . $index;
        $placeholders[] = $placeholder;
        $params[$placeholder] = (int)$productId;
    }

    $sql = "
        SELECT
            sp.ma_san_pham,
            sp.ma_sku,
            sp.ten_san_pham,
            sp.gia_ban,
            sp.thoi_han_bao_hanh,
            th.ten_thuong_hieu
        FROM san_pham sp
        LEFT JOIN thuong_hieu th
            ON sp.ma_thuong_hieu = th.ma_thuong_hieu
        WHERE sp.ma_san_pham IN (" . implode(',', $placeholders) . ")
          AND sp.trang_thai = 'ACTIVE'
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $availableIds = [];

    foreach ($products as $product) {
        $availableIds[] = (int)$product['ma_san_pham'];
    }

    // Xoa san pham khong con kinh doanh
    foreach ($productIds as $productId) {
        if (!in_array((int)$productId, $availableIds, true)) {
            unset($cart[$productId]);
        }
    }

    // ---------------------------------------------
    // LAY KHUYEN MAI
    // ---------------------------------------------

    $promotionMap = [];

    if (!empty($availableIds)) {

        $promotionPlaceholders = [];
        $promotionParams = [];

        foreach ($availableIds as $index => $productId) {
            $placeholder = ':promotion_product_' . $index;
            $promotionPlaceholders[] = $placeholder;
            $promotionParams[$placeholder] = $productId;
        }

        $promotionSql = "
            SELECT
                spkm.ma_chuong_trinh,
                spkm.ma_san_pham,
                spkm.loai_giam_gia,
                spkm.gia_tri_giam,
                spkm.gia_tri_giam_toi_da
            FROM san_pham_khuyen_mai spkm
            INNER JOIN chuong_trinh_khuyen_mai ctkm
                ON spkm.ma_chuong_trinh = ctkm.ma_chuong_trinh
            WHERE spkm.ma_san_pham IN (
                " . implode(',', $promotionPlaceholders) . "
            )
              AND ctkm.trang_thai = 'ACTIVE'
              AND NOW() BETWEEN ctkm.ngay_bat_dau AND ctkm.ngay_ket_thuc
        ";

        $promotionStmt = $pdo->prepare($promotionSql);
        $promotionStmt->execute($promotionParams);

        $promotions = $promotionStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($promotions as $promotion) {
            $productId = (int)$promotion['ma_san_pham'];
            $promotionMap[$productId][] = $promotion;
        }
    }

    // ---------------------------------------------
    // TINH GIA VA TONG TIEN
    // ---------------------------------------------

    foreach ($products as $product) {

        $productId = (int)$product['ma_san_pham'];
        $quantity = (int)($cart[$productId] ?? 0);

        if ($quantity < 1) {
            continue;
        }

        $originalPrice = (float)$product['gia_ban'];
        $discountPerUnit = 0;

        if (isset($promotionMap[$productId])) {

            foreach ($promotionMap[$productId] as $promotion) {

                if ($promotion['loai_giam_gia'] === 'PERCENT') {

                    $candidateDiscount = $originalPrice
                        * (float)$promotion['gia_tri_giam'] / 100;

                    if ($promotion['gia_tri_giam_toi_da'] !== null) {
                        $candidateDiscount = min(
                            $candidateDiscount,
                            (float)$promotion['gia_tri_giam_toi_da']
                        );
                    }

                } else {

                    $candidateDiscount = (float)$promotion['gia_tri_giam'];

                }

                $candidateDiscount = max(
                    0,
                    min($candidateDiscount, $originalPrice)
                );

                if ($candidateDiscount > $discountPerUnit) {
                    $discountPerUnit = $candidateDiscount;
                }
            }
        }

        $finalPrice = max(0, $originalPrice - $discountPerUnit);

        $lineSubtotal = $originalPrice * $quantity;
        $lineDiscount = $discountPerUnit * $quantity;
        $lineTotal = $finalPrice * $quantity;

        $cartItems[] = [
            'id' => $productId,
            'sku' => $product['ma_sku'],
            'name' => $product['ten_san_pham'],
            'brand' => $product['ten_thuong_hieu'],
            'quantity' => $quantity,
            'original_price' => $originalPrice,
            'discount_per_unit' => $discountPerUnit,
            'final_price' => $finalPrice,
            'line_subtotal' => $lineSubtotal,
            'line_discount' => $lineDiscount,
            'line_total' => $lineTotal
        ];

        $subtotal += $lineSubtotal;
        $totalDiscount += $lineDiscount;
        $grandTotal += $lineTotal;
        $totalQuantity += $quantity;
    }
}

// =====================================================
// 6. HAM DINH DANG TIEN
// =====================================================

function customer_cart_money($value)
{
    return number_format((float)$value, 0, ',', '.') . 'đ';
}

function customer_cart_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// =====================================================
// 7. GIAO DIEN
// =====================================================

$pageTitle = 'Giỏ hàng của tôi';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

?>

<style>
.customer-cart-page {
    background: #f5f7fa;
    min-height: 600px;
    padding: 35px 0 60px;
}

.cart-title {
    color: #10243e;
    font-size: 27px;
    font-weight: 800;
    margin-bottom: 25px;
}

.cart-card {
    background: #fff;
    border: 1px solid #e9edf2;
    border-radius: 12px;
    padding: 22px;
    margin-bottom: 20px;
}

.cart-product {
    display: flex;
    align-items: center;
    gap: 15px;
}

.cart-product-icon {
    width: 80px;
    height: 80px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f2f5f9;
    border-radius: 10px;
    color: #8192a7;
    font-size: 36px;
}

.cart-product-name {
    color: #24364b;
    font-weight: 700;
    font-size: 15px;
    text-decoration: none;
}

.cart-product-name:hover {
    color: #0878d1;
}

.cart-product-meta {
    color: #8994a2;
    font-size: 12px;
    margin-top: 5px;
}

.cart-price {
    font-weight: 800;
    color: #dc3545;
}

.cart-old-price {
    font-size: 12px;
    color: #999;
    text-decoration: line-through;
}

.cart-quantity-input {
    width: 75px;
    text-align: center;
}

.cart-summary-row {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 14px;
    font-size: 14px;
}

.cart-grand-total {
    border-top: 1px solid #e9edf2;
    padding-top: 17px;
    margin-top: 17px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 800;
}

.cart-grand-total strong {
    color: #dc3545;
    font-size: 23px;
}

.cart-checkout-btn {
    width: 100%;
    min-height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: none;
    border-radius: 8px;
    background: #0878d1;
    color: #fff;
    text-decoration: none;
    font-weight: 700;
    transition: .2s;
}

.cart-checkout-btn:hover {
    background: #123b70;
    color: #fff;
}

.cart-empty {
    background: #fff;
    border-radius: 12px;
    padding: 65px 20px;
    text-align: center;
}

@media (max-width: 767px) {
    .cart-card {
        padding: 15px;
    }

    .cart-product-icon {
        width: 65px;
        height: 65px;
        font-size: 28px;
    }

    .cart-title {
        font-size: 23px;
    }
}
</style>

<div class="customer-cart-page">

    <div class="container">

        <h2 class="cart-title">
            <i class="bi bi-cart3 me-2"></i>
            Giỏ hàng của tôi
        </h2>

        <?php if ($errorMessage !== ''): ?>

            <div class="alert alert-danger">
                <?= customer_cart_escape($errorMessage) ?>
            </div>

        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>

            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                <?= customer_cart_escape($successMessage) ?>
            </div>

        <?php endif; ?>

        <?php if (empty($cartItems)): ?>

            <div class="cart-empty">

                <i class="bi bi-cart-x display-2 text-secondary"></i>

                <h4 class="mt-3">
                    Giỏ hàng của bạn đang trống
                </h4>

                <p class="text-muted">
                    Hãy chọn những sản phẩm bạn yêu thích và thêm vào giỏ hàng.
                </p>

                <a href="<?= BASE_URL ?>/modules/customers/products.php"
                   class="btn btn-primary mt-2">

                    <i class="bi bi-shop me-1"></i>
                    Tiếp tục mua sắm

                </a>

            </div>

        <?php else: ?>

            <div class="row g-4">

                <!-- DANH SACH SAN PHAM -->
                <div class="col-lg-8">

                    <div class="cart-card">

                        <div class="d-flex justify-content-between align-items-center mb-3">

                            <h5 class="fw-bold mb-0">
                                Sản phẩm (<?= (int)$totalQuantity ?>)
                            </h5>

                            <form method="POST"
                                  action="<?= BASE_URL ?>/modules/customers/cart.php"
                                  onsubmit="return confirm('Bạn có chắc muốn xóa toàn bộ giỏ hàng?');">

                                <input type="hidden"
                                       name="csrf_token"
                                       value="<?= customer_cart_escape($csrfToken) ?>">

                                <input type="hidden"
                                       name="action"
                                       value="clear">

                                <button type="submit"
                                        class="btn btn-sm btn-outline-danger">

                                    <i class="bi bi-trash me-1"></i>
                                    Xóa tất cả

                                </button>

                            </form>

                        </div>

                        <?php foreach ($cartItems as $item): ?>

                            <div class="border-top py-3">

                                <div class="row align-items-center g-3">

                                    <div class="col-md-5">

                                        <div class="cart-product">

                                            <div class="cart-product-icon">
                                                <i class="bi bi-laptop"></i>
                                            </div>

                                            <div>

                                                <a href="<?= BASE_URL ?>/modules/customers/product_detail.php?id=<?= (int)$item['id'] ?>"
                                                   class="cart-product-name">

                                                    <?= customer_cart_escape($item['name']) ?>

                                                </a>

                                                <div class="cart-product-meta">
                                                    SKU: <?= customer_cart_escape($item['sku']) ?>
                                                </div>

                                                <div class="cart-product-meta">
                                                    Thương hiệu:
                                                    <?= customer_cart_escape($item['brand'] ?: 'Chưa cập nhật') ?>
                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                    <div class="col-6 col-md-2">

                                        <div class="cart-product-meta">
                                            Đơn giá
                                        </div>

                                        <?php if ($item['discount_per_unit'] > 0): ?>

                                            <div class="cart-old-price">
                                                <?= customer_cart_money($item['original_price']) ?>
                                            </div>

                                        <?php endif; ?>

                                        <div class="cart-price">
                                            <?= customer_cart_money($item['final_price']) ?>
                                        </div>

                                    </div>

                                    <div class="col-6 col-md-2">

                                        <form method="POST"
                                              action="<?= BASE_URL ?>/modules/customers/cart.php">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?= customer_cart_escape($csrfToken) ?>">

                                            <input type="hidden"
                                                   name="action"
                                                   value="update">

                                            <input type="hidden"
                                                   name="product_id"
                                                   value="<?= (int)$item['id'] ?>">

                                            <label class="cart-product-meta">
                                                Số lượng
                                            </label>

                                            <input type="number"
                                                   name="quantity"
                                                   class="form-control form-control-sm cart-quantity-input"
                                                   min="1"
                                                   max="99"
                                                   value="<?= (int)$item['quantity'] ?>"
                                                   onchange="this.form.submit()">

                                        </form>

                                    </div>

                                    <div class="col-md-3 text-md-end">

                                        <div class="cart-product-meta">
                                            Thành tiền
                                        </div>

                                        <div class="cart-price mb-2">
                                            <?= customer_cart_money($item['line_total']) ?>
                                        </div>

                                        <form method="POST"
                                              action="<?= BASE_URL ?>/modules/customers/cart.php">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?= customer_cart_escape($csrfToken) ?>">

                                            <input type="hidden"
                                                   name="action"
                                                   value="remove">

                                            <input type="hidden"
                                                   name="product_id"
                                                   value="<?= (int)$item['id'] ?>">

                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-danger">

                                                <i class="bi bi-trash"></i>
                                                Xóa

                                            </button>

                                        </form>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                    <a href="<?= BASE_URL ?>/modules/customers/products.php"
                       class="btn btn-outline-primary">

                        <i class="bi bi-arrow-left me-1"></i>
                        Tiếp tục mua sắm

                    </a>

                </div>

                <!-- TONG TIEN -->
                <div class="col-lg-4">

                    <div class="cart-card">

                        <h5 class="fw-bold mb-4">
                            Tổng quan đơn hàng
                        </h5>

                        <div class="cart-summary-row">

                            <span>Tạm tính</span>

                            <strong>
                                <?= customer_cart_money($subtotal) ?>
                            </strong>

                        </div>

                        <div class="cart-summary-row">

                            <span>Giảm giá</span>

                            <strong class="text-success">
                                -<?= customer_cart_money($totalDiscount) ?>
                            </strong>

                        </div>

                        <div class="cart-grand-total">

                            <span>Tổng tiền</span>

                            <strong>
                                <?= customer_cart_money($grandTotal) ?>
                            </strong>

                        </div>

                        <div class="small text-muted mt-3">
                            Giá sản phẩm đã được tính theo chương trình khuyến mãi hiện hành.
                        </div>

                        <!-- NUT THANH TOAN -->
                        <a href="<?= BASE_URL ?>/modules/customers/checkout.php"
                           class="cart-checkout-btn mt-4">

                            <i class="bi bi-credit-card"></i>
                            Tiến hành thanh toán

                        </a>

                    </div>

                </div>

            </div>

        <?php endif; ?>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
