
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/customers/checkout.php
// CHUC NANG: THANH TOAN VA TAO DON HANG
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

// =====================================================
// 1. KIEM TRA DANG NHAP
// =====================================================

if (
    empty($_SESSION['ma_nguoi_dung']) ||
    (int)($_SESSION['ma_vai_tro'] ?? 0) !== 6
) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$userId = (int)$_SESSION['ma_nguoi_dung'];

// =====================================================
// 2. KHOI TAO GIO HANG VA CSRF
// =====================================================

if (
    !isset($_SESSION['customer_cart']) ||
    !is_array($_SESSION['customer_cart'])
) {
    $_SESSION['customer_cart'] = [];
}

if (empty($_SESSION['customer_checkout_csrf'])) {
    $_SESSION['customer_checkout_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['customer_checkout_csrf'];
$cart = &$_SESSION['customer_cart'];

$errorMessage = '';
$successMessage = '';
$orderCode = '';

$shippingFee = 0;

// =====================================================
// 3. HAM HO TRO
// =====================================================

function customer_checkout_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function customer_checkout_money($value)
{
    return number_format((float)$value, 0, ',', '.') . 'đ';
}

/**
 * Lay san pham trong gio va tinh lai khuyen mai.
 * $lock = true: khoa dong san pham khi tao don hang.
 */
function customer_checkout_build_cart($pdo, $cart, $lock = false)
{
    $result = [
        'items' => [],
        'subtotal' => 0,
        'discount' => 0,
        'total' => 0,
        'quantity' => 0,
        'campaigns' => []
    ];

    if (empty($cart)) {
        return $result;
    }

    $productIds = [];

    foreach ($cart as $productId => $quantity) {
        $productId = (int)$productId;
        $quantity = (int)$quantity;

        if ($productId > 0 && $quantity >= 1 && $quantity <= 99) {
            $productIds[] = $productId;
        }
    }

    $productIds = array_values(array_unique($productIds));

    if (empty($productIds)) {
        return $result;
    }

    $placeholders = [];
    $params = [];

    foreach ($productIds as $index => $productId) {
        $placeholder = ':product_' . $index;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $productId;
    }

    $sql = "
        SELECT
            sp.ma_san_pham,
            sp.ma_sku,
            sp.ten_san_pham,
            sp.gia_ban,
            sp.thoi_han_bao_hanh,
            sp.trang_thai
        FROM san_pham sp
        WHERE sp.ma_san_pham IN (" . implode(',', $placeholders) . ")
          AND sp.trang_thai = 'ACTIVE'
        ORDER BY sp.ma_san_pham
    ";

    if ($lock) {
        $sql .= " FOR UPDATE";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $availableIds = [];

    foreach ($products as $product) {
        $availableIds[] = (int)$product['ma_san_pham'];
    }

    // Lay khuyen mai dang co hieu luc
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

    // Tinh tien tung san pham
    foreach ($products as $product) {
        $productId = (int)$product['ma_san_pham'];
        $quantity = (int)($cart[$productId] ?? 0);

        if ($quantity < 1 || $quantity > 99) {
            continue;
        }

        $originalPrice = (float)$product['gia_ban'];
        $discountPerUnit = 0;
        $selectedCampaignId = null;

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
                    $selectedCampaignId = (int)$promotion['ma_chuong_trinh'];
                }
            }
        }

        $finalPrice = max(0, $originalPrice - $discountPerUnit);

        $lineSubtotal = $originalPrice * $quantity;
        $lineDiscount = $discountPerUnit * $quantity;
        $lineTotal = $finalPrice * $quantity;

        $result['items'][] = [
            'id' => $productId,
            'sku' => $product['ma_sku'],
            'name' => $product['ten_san_pham'],
            'warranty' => (int)$product['thoi_han_bao_hanh'],
            'quantity' => $quantity,
            'original_price' => $originalPrice,
            'discount_per_unit' => $discountPerUnit,
            'line_subtotal' => $lineSubtotal,
            'line_discount' => $lineDiscount,
            'final_price' => $finalPrice,
            'line_total' => $lineTotal,
            'campaign_id' => $selectedCampaignId
        ];

        $result['subtotal'] += $lineSubtotal;
        $result['discount'] += $lineDiscount;
        $result['total'] += $lineTotal;
        $result['quantity'] += $quantity;

        if ($selectedCampaignId !== null && $lineDiscount > 0) {
            if (!isset($result['campaigns'][$selectedCampaignId])) {
                $result['campaigns'][$selectedCampaignId] = 0;
            }

            $result['campaigns'][$selectedCampaignId] += $lineDiscount;
        }
    }

    return $result;
}

// =====================================================
// 4. LAY THONG TIN KHACH HANG
// =====================================================

$userStmt = $pdo->prepare("
    SELECT
        ma_nguoi_dung,
        ho_ten,
        email,
        so_dien_thoai
    FROM nguoi_dung
    WHERE ma_nguoi_dung = :user_id
      AND ma_vai_tro = 6
      AND trang_thai = 'ACTIVE'
    LIMIT 1
");

$userStmt->execute([
    ':user_id' => $userId
]);

$currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    unset(
        $_SESSION['ma_nguoi_dung'],
        $_SESSION['ma_vai_tro'],
        $_SESSION['ten_dang_nhap'],
        $_SESSION['ho_ten'],
        $_SESSION['email'],
        $_SESSION['ten_vai_tro']
    );

    header('Location: ' . BASE_URL . '/login.php?error=account');
    exit;
}

// Lay ho so khach hang neu da ton tai
$customerStmt = $pdo->prepare("
    SELECT
        ma_khach_hang,
        ten_khach_hang,
        so_dien_thoai,
        email,
        dia_chi
    FROM khach_hang
    WHERE ma_nguoi_dung = :user_id
    LIMIT 1
");

$customerStmt->execute([
    ':user_id' => $userId
]);

$customer = $customerStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$receiverName = $customer['ten_khach_hang'] ?? $currentUser['ho_ten'];
$receiverPhone = $customer['so_dien_thoai'] ?? $currentUser['so_dien_thoai'];
$receiverEmail = $customer['email'] ?? $currentUser['email'];
$receiverAddress = $customer['dia_chi'] ?? '';

// =====================================================
// 5. XU LY DAT HANG
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        $errorMessage = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $receiverName = trim($_POST['ten_nguoi_nhan'] ?? '');
        $receiverPhone = trim($_POST['sdt_nguoi_nhan'] ?? '');
        $receiverAddress = trim($_POST['dia_chi_giao_hang'] ?? '');
        $paymentMethod = $_POST['phuong_thuc'] ?? '';
        $orderNote = trim($_POST['ghi_chu'] ?? '');

        if ($receiverName === '' || mb_strlen($receiverName) > 150) {
            $errorMessage = 'Vui lòng nhập họ tên người nhận hợp lệ.';
        } elseif (
            $receiverPhone === '' ||
            strlen($receiverPhone) > 20 ||
            !preg_match('/^[0-9+().\s-]+$/', $receiverPhone)
        ) {
            $errorMessage = 'Vui lòng nhập số điện thoại hợp lệ.';
        } elseif (
            $receiverAddress === '' ||
            mb_strlen($receiverAddress) > 255
        ) {
            $errorMessage = 'Vui lòng nhập địa chỉ giao hàng hợp lệ.';
        } elseif (!in_array(
            $paymentMethod,
            ['CASH', 'BANK_TRANSFER', 'COD'],
            true
        )) {
            $errorMessage = 'Phương thức thanh toán không hợp lệ.';
        } elseif (mb_strlen($orderNote) > 2000) {
            $errorMessage = 'Ghi chú không được vượt quá 2000 ký tự.';
        } elseif (empty($cart)) {
            $errorMessage = 'Giỏ hàng đang trống. Vui lòng chọn sản phẩm trước.';
        } else {
            try {
                $pdo->beginTransaction();

                // Tinh lai gia va khuyen mai trong transaction
                $checkoutData = customer_checkout_build_cart(
                    $pdo,
                    $cart,
                    true
                );

                if (empty($checkoutData['items'])) {
                    throw new Exception(
                        'Không còn sản phẩm hợp lệ trong giỏ hàng. Vui lòng kiểm tra lại.'
                    );
                }

                $subtotal = (float)$checkoutData['subtotal'];
                $discount = (float)$checkoutData['discount'];
                $total = $subtotal - $discount + $shippingFee;

                if ($total < 0) {
                    throw new Exception('Tổng tiền đơn hàng không hợp lệ.');
                }

                // -------------------------------------
                // TAO HO SO KHACH HANG
                // -------------------------------------

                $findCustomerStmt = $pdo->prepare("
                    SELECT ma_khach_hang
                    FROM khach_hang
                    WHERE ma_nguoi_dung = :user_id
                    LIMIT 1
                    FOR UPDATE
                ");

                $findCustomerStmt->execute([
                    ':user_id' => $userId
                ]);

                $customerId = $findCustomerStmt->fetchColumn();

                if ($customerId) {
                    $updateCustomerStmt = $pdo->prepare("
                        UPDATE khach_hang
                        SET
                            ten_khach_hang = :name,
                            so_dien_thoai = :phone,
                            email = :email,
                            dia_chi = :address
                        WHERE ma_khach_hang = :customer_id
                    ");

                    $updateCustomerStmt->execute([
                        ':name' => $receiverName,
                        ':phone' => $receiverPhone,
                        ':email' => $receiverEmail ?: null,
                        ':address' => $receiverAddress,
                        ':customer_id' => $customerId
                    ]);

                    $customerId = (int)$customerId;
                } else {
                    $insertCustomerStmt = $pdo->prepare("
                        INSERT INTO khach_hang (
                            ma_nguoi_dung,
                            ten_khach_hang,
                            so_dien_thoai,
                            email,
                            dia_chi
                        ) VALUES (
                            :user_id,
                            :name,
                            :phone,
                            :email,
                            :address
                        )
                    ");

                    $insertCustomerStmt->execute([
                        ':user_id' => $userId,
                        ':name' => $receiverName,
                        ':phone' => $receiverPhone,
                        ':email' => $receiverEmail ?: null,
                        ':address' => $receiverAddress
                    ]);

                    $customerId = (int)$pdo->lastInsertId();
                }

                // -------------------------------------
                // TAO MA DON HANG
                // -------------------------------------

                $orderCode = 'HP'
                    . date('YmdHis')
                    . strtoupper(bin2hex(random_bytes(3)));

                // -------------------------------------
                // TAO DON HANG
                // -------------------------------------

                $orderStmt = $pdo->prepare("
                    INSERT INTO don_hang (
                        ma_khach_hang,
                        ma_nguoi_tao,
                        ma_so_don_hang,
                        ten_nguoi_nhan,
                        sdt_nguoi_nhan,
                        dia_chi_giao_hang,
                        tam_tinh,
                        tien_giam_gia,
                        phi_van_chuyen,
                        tong_tien,
                        trang_thai_don_hang,
                        trang_thai_thanh_toan,
                        ghi_chu
                    ) VALUES (
                        :customer_id,
                        NULL,
                        :order_code,
                        :receiver_name,
                        :receiver_phone,
                        :shipping_address,
                        :subtotal,
                        :discount,
                        :shipping_fee,
                        :total,
                        'PENDING',
                        'UNPAID',
                        :note
                    )
                ");

                $orderStmt->execute([
                    ':customer_id' => $customerId,
                    ':order_code' => $orderCode,
                    ':receiver_name' => $receiverName,
                    ':receiver_phone' => $receiverPhone,
                    ':shipping_address' => $receiverAddress,
                    ':subtotal' => $subtotal,
                    ':discount' => $discount,
                    ':shipping_fee' => $shippingFee,
                    ':total' => $total,
                    ':note' => $orderNote !== '' ? $orderNote : null
                ]);

                $orderId = (int)$pdo->lastInsertId();

                // -------------------------------------
                // TAO CHI TIET DON HANG
                // -------------------------------------

                $detailStmt = $pdo->prepare("
                    INSERT INTO chi_tiet_don_hang (
                        ma_don_hang,
                        ma_san_pham,
                        ten_san_pham,
                        so_luong,
                        don_gia,
                        tien_giam_gia,
                        thanh_tien,
                        thoi_han_bao_hanh
                    ) VALUES (
                        :order_id,
                        :product_id,
                        :product_name,
                        :quantity,
                        :unit_price,
                        :discount,
                        :line_total,
                        :warranty
                    )
                ");

                foreach ($checkoutData['items'] as $item) {
                    $detailStmt->execute([
                        ':order_id' => $orderId,
                        ':product_id' => $item['id'],
                        ':product_name' => $item['name'],
                        ':quantity' => $item['quantity'],
                        ':unit_price' => $item['original_price'],
                        ':discount' => $item['line_discount'],
                        ':line_total' => $item['line_total'],
                        ':warranty' => $item['warranty']
                    ]);
                }

                // -------------------------------------
                // LUU KHUYEN MAI AP DUNG CHO DON HANG
                // -------------------------------------

                if (!empty($checkoutData['campaigns'])) {
                    $campaignStmt = $pdo->prepare("
                        INSERT INTO khuyen_mai_don_hang (
                            ma_don_hang,
                            ma_chuong_trinh,
                            so_tien_giam
                        ) VALUES (
                            :order_id,
                            :campaign_id,
                            :discount_amount
                        )
                    ");

                    foreach ($checkoutData['campaigns'] as $campaignId => $campaignDiscount) {
                        $campaignStmt->execute([
                            ':order_id' => $orderId,
                            ':campaign_id' => $campaignId,
                            ':discount_amount' => $campaignDiscount
                        ]);
                    }
                }

                // -------------------------------------
                // TAO BAN GHI THANH TOAN
                // -------------------------------------

                $paymentStmt = $pdo->prepare("
                    INSERT INTO thanh_toan (
                        ma_don_hang,
                        phuong_thuc,
                        so_tien,
                        ma_giao_dich,
                        ngay_thanh_toan,
                        trang_thai,
                        ghi_chu
                    ) VALUES (
                        :order_id,
                        :payment_method,
                        :amount,
                        NULL,
                        NULL,
                        'PENDING',
                        :note
                    )
                ");

                $paymentNote = 'Khách hàng tạo đơn hàng trên website.';

                $paymentStmt->execute([
                    ':order_id' => $orderId,
                    ':payment_method' => $paymentMethod,
                    ':amount' => $total,
                    ':note' => $paymentNote
                ]);

                // -------------------------------------
                // LUU LICH SU TRANG THAI
                // -------------------------------------

                $historyStmt = $pdo->prepare("
                    INSERT INTO lich_su_trang_thai_don_hang (
                        ma_don_hang,
                        ma_nguoi_thuc_hien,
                        trang_thai_cu,
                        trang_thai_moi,
                        ly_do_thay_doi,
                        ghi_chu
                    ) VALUES (
                        :order_id,
                        :user_id,
                        NULL,
                        'PENDING',
                        :reason,
                        :note
                    )
                ");

                $historyStmt->execute([
                    ':order_id' => $orderId,
                    ':user_id' => $userId,
                    ':reason' => 'Khách hàng đặt hàng trên website.',
                    ':note' => 'Đơn hàng được tạo thành công.'
                ]);

                // -------------------------------------
                // HOAN TAT TRANSACTION
                // -------------------------------------

                $pdo->commit();

                // Chi xoa gio hang sau khi commit thanh cong
                $_SESSION['customer_cart'] = [];

                $_SESSION['customer_order_success'] = [
                    'order_code' => $orderCode,
                    'total' => $total
                ];

                header(
                    'Location: ' . BASE_URL
                    . '/modules/customers/checkout.php?success=1'
                );
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('HPComputer checkout error: ' . $e->getMessage());

                $errorMessage = $e instanceof PDOException
                    ? 'Không thể lưu đơn hàng. Vui lòng kiểm tra lại dữ liệu hoặc thử lại sau.'
                    : $e->getMessage();
            }
        }
    }
}

// =====================================================
// 6. THONG BAO DAT HANG THANH CONG
// =====================================================

if (
    isset($_GET['success']) &&
    $_GET['success'] === '1' &&
    isset($_SESSION['customer_order_success'])
) {
    $successMessage = 'Đặt hàng thành công! Cảm ơn bạn đã mua sắm tại HP Computer.';

    $orderCode = $_SESSION['customer_order_success']['order_code'];
    $successTotal = $_SESSION['customer_order_success']['total'];

    unset($_SESSION['customer_order_success']);
} else {
    $successTotal = 0;
}

// =====================================================
// 7. TINH LAI GIO HANG DE HIEN THI
// =====================================================

$checkoutData = customer_checkout_build_cart($pdo, $cart);

$pageTitle = 'Thanh toán';

// =====================================================
// 8. GIAO DIEN
// =====================================================

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

?>

<style>
.checkout-page {
    background: #f5f7fa;
    min-height: 600px;
    padding: 35px 0 60px;
}

.checkout-title {
    color: #10243e;
    font-size: 27px;
    font-weight: 800;
    margin-bottom: 25px;
}

.checkout-card {
    background: #fff;
    border: 1px solid #e9edf2;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
}

.checkout-card-title {
    color: #10243e;
    font-size: 19px;
    font-weight: 800;
    margin-bottom: 20px;
}

.checkout-label {
    font-weight: 600;
    color: #34465b;
    margin-bottom: 7px;
}

.checkout-form .form-control,
.checkout-form .form-select {
    min-height: 44px;
    border-radius: 7px;
}

.checkout-form textarea.form-control {
    min-height: 95px;
}

.checkout-product {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 0;
    border-bottom: 1px solid #edf0f3;
}

.checkout-product:last-child {
    border-bottom: none;
}

.checkout-product-icon {
    width: 65px;
    height: 65px;
    flex-shrink: 0;
    border-radius: 8px;
    background: #f2f5f9;
    display: flex;
    justify-content: center;
    align-items: center;
    color: #8092a8;
    font-size: 30px;
}

.checkout-product-name {
    font-size: 14px;
    font-weight: 700;
    color: #24364b;
}

.checkout-product-meta {
    color: #888;
    font-size: 12px;
    margin-top: 4px;
}

.checkout-product-price {
    color: #dc3545;
    font-weight: 800;
    white-space: nowrap;
}

.checkout-summary-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 13px;
    font-size: 14px;
}

.checkout-grand-total {
    border-top: 1px solid #e9edf2;
    padding-top: 18px;
    margin-top: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 800;
}

.checkout-grand-total strong {
    color: #dc3545;
    font-size: 23px;
}

.checkout-payment-option {
    display: block;
    border: 1px solid #e3e8ef;
    border-radius: 8px;
    padding: 13px 15px;
    margin-bottom: 10px;
    cursor: pointer;
}

.checkout-payment-option:hover {
    border-color: #0878d1;
    background: #f7fbff;
}

.checkout-payment-option input {
    margin-right: 8px;
}

.checkout-submit {
    width: 100%;
    min-height: 48px;
    border: none;
    border-radius: 8px;
    background: #0878d1;
    color: #fff;
    font-weight: 700;
    transition: .2s;
}

.checkout-submit:hover {
    background: #123b70;
}

.checkout-empty {
    background: #fff;
    padding: 60px 20px;
    border-radius: 12px;
    text-align: center;
}

.checkout-success {
    background: #fff;
    border-radius: 12px;
    padding: 45px 25px;
    text-align: center;
    border: 1px solid #e9edf2;
}

.checkout-success-icon {
    color: #198754;
    font-size: 65px;
    margin-bottom: 15px;
}

.checkout-order-code {
    font-size: 21px;
    font-weight: 800;
    color: #0878d1;
    margin: 15px 0;
}

@media (max-width: 767px) {
    .checkout-card {
        padding: 17px;
    }

    .checkout-title {
        font-size: 23px;
    }
}
</style>

<div class="checkout-page">
    <div class="container">

        <h2 class="checkout-title">
            <i class="bi bi-credit-card me-2"></i>
            Thanh toán đơn hàng
        </h2>

        <?php if ($successMessage !== ''): ?>

            <div class="checkout-success">

                <div class="checkout-success-icon">
                    <i class="bi bi-check-circle-fill"></i>
                </div>

                <h3 class="fw-bold text-success">
                    Đặt hàng thành công!
                </h3>

                <p class="text-muted">
                    Đơn hàng của bạn đã được ghi nhận trên hệ thống HP Computer.
                </p>

                <div class="checkout-order-code">
                    <?= customer_checkout_escape($orderCode) ?>
                </div>

                <div class="mb-4">
                    Tổng tiền:
                    <strong class="text-danger">
                        <?= customer_checkout_money($successTotal) ?>
                    </strong>
                </div>

                <a href="<?= BASE_URL ?>/modules/customers/products.php"
                   class="btn btn-primary">

                    <i class="bi bi-shop me-1"></i>
                    Tiếp tục mua sắm

                </a>

                <a href="<?= BASE_URL ?>/modules/customers/index.php"
                   class="btn btn-outline-secondary ms-2">

                    Về trang chủ

                </a>

            </div>

        <?php elseif (empty($checkoutData['items'])): ?>

            <div class="checkout-empty">

                <i class="bi bi-cart-x display-2 text-secondary"></i>

                <h4 class="mt-3">
                    Giỏ hàng đang trống
                </h4>

                <p class="text-muted">
                    Bạn cần thêm sản phẩm vào giỏ hàng trước khi thanh toán.
                </p>

                <a href="<?= BASE_URL ?>/modules/customers/products.php"
                   class="btn btn-primary mt-2">

                    <i class="bi bi-arrow-left me-1"></i>
                    Tiếp tục mua sắm

                </a>

            </div>

        <?php else: ?>

            <?php if ($errorMessage !== ''): ?>

                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <?= customer_checkout_escape($errorMessage) ?>
                </div>

            <?php endif; ?>

            <form method="POST"
                  action="<?= BASE_URL ?>/modules/customers/checkout.php"
                  class="checkout-form">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= customer_checkout_escape($csrfToken) ?>">

                <div class="row g-4">

                    <!-- THONG TIN NHAN HANG -->
                    <div class="col-lg-7">

                        <div class="checkout-card">

                            <h4 class="checkout-card-title">
                                <i class="bi bi-person-lines-fill me-2"></i>
                                Thông tin nhận hàng
                            </h4>

                            <div class="mb-3">

                                <label class="checkout-label">
                                    Họ và tên người nhận *
                                </label>

                                <input type="text"
                                       name="ten_nguoi_nhan"
                                       class="form-control"
                                       maxlength="150"
                                       value="<?= customer_checkout_escape($receiverName) ?>"
                                       required>

                            </div>

                            <div class="mb-3">

                                <label class="checkout-label">
                                    Số điện thoại *
                                </label>

                                <input type="tel"
                                       name="sdt_nguoi_nhan"
                                       class="form-control"
                                       maxlength="20"
                                       value="<?= customer_checkout_escape($receiverPhone) ?>"
                                       required>

                            </div>

                            <div class="mb-3">

                                <label class="checkout-label">
                                    Email
                                </label>

                                <input type="email"
                                       class="form-control"
                                       value="<?= customer_checkout_escape($receiverEmail) ?>"
                                       disabled>

                            </div>

                            <div class="mb-3">

                                <label class="checkout-label">
                                    Địa chỉ giao hàng *
                                </label>

                                <textarea name="dia_chi_giao_hang"
                                          class="form-control"
                                          maxlength="255"
                                          rows="3"
                                          required><?= customer_checkout_escape($receiverAddress) ?></textarea>

                            </div>

                            <div class="mb-0">

                                <label class="checkout-label">
                                    Ghi chú đơn hàng
                                </label>

                                <textarea name="ghi_chu"
                                          class="form-control"
                                          maxlength="2000"
                                          rows="3"
                                          placeholder="Ví dụ: Giao hàng ngoài giờ hành chính..."><?= customer_checkout_escape($_POST['ghi_chu'] ?? '') ?></textarea>

                            </div>

                        </div>

                        <!-- PHUONG THUC THANH TOAN -->
                        <div class="checkout-card">

                            <h4 class="checkout-card-title">
                                <i class="bi bi-wallet2 me-2"></i>
                                Phương thức thanh toán
                            </h4>

                            <label class="checkout-payment-option">

                                <input type="radio"
                                       name="phuong_thuc"
                                       value="COD"
                                       checked>

                                <strong>Thanh toán khi nhận hàng (COD)</strong>

                                <div class="small text-muted ms-4">
                                    Thanh toán cho nhân viên giao hàng khi nhận sản phẩm.
                                </div>

                            </label>

                            <label class="checkout-payment-option">

                                <input type="radio"
                                       name="phuong_thuc"
                                       value="BANK_TRANSFER"
                                       <?= (($_POST['phuong_thuc'] ?? '') === 'BANK_TRANSFER') ? 'checked' : '' ?>>

                                <strong>Chuyển khoản ngân hàng</strong>

                                <div class="small text-muted ms-4">
                                    Thông tin chuyển khoản sẽ được cửa hàng hướng dẫn.
                                </div>

                            </label>

                            <label class="checkout-payment-option">

                                <input type="radio"
                                       name="phuong_thuc"
                                       value="CASH"
                                       <?= (($_POST['phuong_thuc'] ?? '') === 'CASH') ? 'checked' : '' ?>>

                                <strong>Thanh toán tiền mặt tại cửa hàng</strong>

                                <div class="small text-muted ms-4">
                                    Thanh toán trực tiếp tại cửa hàng HP Computer.
                                </div>

                            </label>

                        </div>

                    </div>

                    <!-- DON HANG -->
                    <div class="col-lg-5">

                        <div class="checkout-card">

                            <h4 class="checkout-card-title">
                                <i class="bi bi-bag-check me-2"></i>
                                Đơn hàng của bạn
                            </h4>

                            <?php foreach ($checkoutData['items'] as $item): ?>

                                <div class="checkout-product">

                                    <div class="checkout-product-icon">
                                        <i class="bi bi-laptop"></i>
                                    </div>

                                    <div class="flex-grow-1">

                                        <div class="checkout-product-name">
                                            <?= customer_checkout_escape($item['name']) ?>
                                        </div>

                                        <div class="checkout-product-meta">
                                            SKU: <?= customer_checkout_escape($item['sku']) ?>
                                        </div>

                                        <div class="checkout-product-meta">
                                            <?= customer_checkout_money($item['final_price']) ?>
                                            × <?= (int)$item['quantity'] ?>
                                        </div>

                                    </div>

                                    <div class="checkout-product-price">
                                        <?= customer_checkout_money($item['line_total']) ?>
                                    </div>

                                </div>

                            <?php endforeach; ?>

                            <div class="mt-4">

                                <div class="checkout-summary-row">
                                    <span>Tạm tính</span>
                                    <strong>
                                        <?= customer_checkout_money($checkoutData['subtotal']) ?>
                                    </strong>
                                </div>

                                <div class="checkout-summary-row">
                                    <span>Giảm giá</span>
                                    <strong class="text-success">
                                        -<?= customer_checkout_money($checkoutData['discount']) ?>
                                    </strong>
                                </div>

                                <div class="checkout-summary-row">
                                    <span>Phí vận chuyển</span>
                                    <strong>
                                        <?= customer_checkout_money($shippingFee) ?>
                                    </strong>
                                </div>

                                <div class="checkout-grand-total">

                                    <span>Tổng thanh toán</span>

                                    <strong>
                                        <?= customer_checkout_money(
                                            $checkoutData['total'] + $shippingFee
                                        ) ?>
                                    </strong>

                                </div>

                            </div>

                            <button type="submit"
                                    class="checkout-submit mt-4"
                                    onclick="return confirm('Bạn xác nhận đặt đơn hàng này?');">

                                <i class="bi bi-check-circle me-2"></i>
                                Xác nhận đặt hàng

                            </button>

                            <a href="<?= BASE_URL ?>/modules/customers/cart.php"
                               class="btn btn-outline-secondary w-100 mt-2">

                                <i class="bi bi-arrow-left me-1"></i>
                                Quay lại giỏ hàng

                            </a>

                            <div class="small text-muted text-center mt-3">
                                Vui lòng kiểm tra kỹ thông tin trước khi đặt hàng.
                            </div>

                        </div>

                    </div>

                </div>

            </form>

        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
