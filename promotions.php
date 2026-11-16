
<?php
// =====================================================
// HP COMPUTER - CUSTOMER PROMOTIONS
// FILE: modules/customers/promotions.php
// CHUC NANG: DANH SACH KHUYEN MAI CHO KHACH HANG
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

// =====================================================
// 1. HAM HO TRO
// =====================================================

if (!function_exists('customer_promotion_escape')) {
    function customer_promotion_escape($value): string
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('customer_promotion_format_money')) {
    function customer_promotion_format_money($amount): string
    {
        return number_format((float)$amount, 0, ',', '.') . 'đ';
    }
}

// =====================================================
// 2. TINH GIA SAU KHI GIAM
// =====================================================

if (!function_exists('customer_promotion_calculate_price')) {
    function customer_promotion_calculate_price(
        float $originalPrice,
        string $discountType,
        float $discountValue,
        ?float $discountMaximum
    ): array {
        $discountAmount = 0;

        if ($discountType === 'PERCENT') {
            $discountAmount = $originalPrice * $discountValue / 100;

            if (
                $discountMaximum !== null &&
                $discountMaximum > 0
            ) {
                $discountAmount = min(
                    $discountAmount,
                    $discountMaximum
                );
            }
        } elseif ($discountType === 'FIXED_AMOUNT') {
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

        return [
            'original_price' => $originalPrice,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice
        ];
    }
}

// =====================================================
// 3. LAY DANH SACH CHUONG TRINH KHUYEN MAI
// =====================================================

$promotions = [];
$pageError = '';

try {
    $sql = "
        SELECT
            ctkm.ma_chuong_trinh,
            ctkm.ten_chuong_trinh,
            ctkm.mo_ta,
            ctkm.loai_giam_gia,
            ctkm.gia_tri_giam,
            ctkm.gia_tri_giam_toi_da,
            ctkm.ngay_bat_dau,
            ctkm.ngay_ket_thuc,

            sp.ma_san_pham,
            sp.ma_sku,
            sp.ten_san_pham,
            sp.mo_ta AS mo_ta_san_pham,
            sp.gia_ban

        FROM chuong_trinh_khuyen_mai ctkm

        INNER JOIN san_pham_khuyen_mai spkm
            ON ctkm.ma_chuong_trinh = spkm.ma_chuong_trinh

        INNER JOIN san_pham sp
            ON spkm.ma_san_pham = sp.ma_san_pham

        WHERE
            ctkm.trang_thai = 'ACTIVE'
            AND ctkm.ngay_bat_dau <= NOW()
            AND ctkm.ngay_ket_thuc >= NOW()
            AND sp.trang_thai = 'ACTIVE'

        ORDER BY
            ctkm.ngay_ket_thuc ASC,
            ctkm.ma_chuong_trinh DESC,
            sp.ten_san_pham ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $promotionId = (int)$row['ma_chuong_trinh'];

        if (!isset($promotions[$promotionId])) {
            $promotions[$promotionId] = [
                'ma_chuong_trinh' => $promotionId,
                'ten_chuong_trinh' => $row['ten_chuong_trinh'],
                'mo_ta' => $row['mo_ta'],
                'loai_giam_gia' => $row['loai_giam_gia'],
                'gia_tri_giam' => (float)$row['gia_tri_giam'],
                'gia_tri_giam_toi_da' =>
                    $row['gia_tri_giam_toi_da'] !== null
                        ? (float)$row['gia_tri_giam_toi_da']
                        : null,
                'ngay_bat_dau' => $row['ngay_bat_dau'],
                'ngay_ket_thuc' => $row['ngay_ket_thuc'],
                'products' => []
            ];
        }

        $originalPrice = (float)$row['gia_ban'];

        $priceInfo = customer_promotion_calculate_price(
            $originalPrice,
            $row['loai_giam_gia'],
            (float)$row['gia_tri_giam'],
            $row['gia_tri_giam_toi_da'] !== null
                ? (float)$row['gia_tri_giam_toi_da']
                : null
        );

        $promotions[$promotionId]['products'][] = [
            'ma_san_pham' => (int)$row['ma_san_pham'],
            'ma_sku' => $row['ma_sku'],
            'ten_san_pham' => $row['ten_san_pham'],
            'mo_ta' => $row['mo_ta_san_pham'],
            'gia_goc' => $priceInfo['original_price'],
            'tien_giam' => $priceInfo['discount_amount'],
            'gia_sau_giam' => $priceInfo['final_price']
        ];
    }

} catch (PDOException $e) {
    error_log('Customer promotions error: ' . $e->getMessage());
    $pageError = 'Không thể tải danh sách khuyến mãi. Vui lòng thử lại sau.';
}

// =====================================================
// 4. THONG KE
// =====================================================

$totalPromotions = count($promotions);
$totalProducts = 0;

foreach ($promotions as $promotion) {
    $totalProducts += count($promotion['products']);
}

$pageTitle = 'Khuyến mãi - HP COMPUTER';

// =====================================================
// 5. GIAO DIEN KHACH HANG
// =====================================================

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
.customer-promotion-page {
    background: #f5f7fb;
    min-height: 65vh;
    padding: 35px 0 55px;
}

.promotion-banner {
    background: linear-gradient(135deg, #0d47a1, #1976d2);
    color: #fff;
    border-radius: 16px;
    padding: 35px;
    margin-bottom: 30px;
}

.promotion-banner h1 {
    font-size: 30px;
    font-weight: 800;
    margin-bottom: 12px;
}

.promotion-banner p {
    margin-bottom: 0;
    opacity: .9;
}

.promotion-stat {
    background: #fff;
    border-radius: 12px;
    padding: 18px;
    box-shadow: 0 3px 15px rgba(0,0,0,.04);
    height: 100%;
}

.promotion-stat .stat-number {
    font-size: 25px;
    font-weight: 800;
    color: #0d6efd;
}

.promotion-section {
    background: #fff;
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 28px;
    box-shadow: 0 3px 15px rgba(0,0,0,.04);
}

.promotion-section-title {
    font-size: 21px;
    font-weight: 750;
    color: #212529;
}

.promotion-time {
    color: #6c757d;
    font-size: 13px;
}

.promotion-product {
    height: 100%;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    overflow: hidden;
    transition: .2s;
    background: #fff;
}

.promotion-product:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 22px rgba(0,0,0,.08);
}

.promotion-product-icon {
    height: 145px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f3f6fb;
    color: #0d6efd;
    font-size: 65px;
    position: relative;
}

.promotion-discount {
    position: absolute;
    top: 12px;
    right: 12px;
    background: #dc3545;
    color: #fff;
    padding: 6px 10px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 13px;
}

.promotion-product-body {
    padding: 16px;
}

.promotion-product-name {
    min-height: 45px;
    font-size: 15px;
    font-weight: 700;
    color: #212529;
}

.promotion-sku {
    color: #8a8f98;
    font-size: 12px;
    margin-bottom: 10px;
}

.promotion-original-price {
    color: #8a8f98;
    text-decoration: line-through;
    font-size: 13px;
}

.promotion-final-price {
    color: #dc3545;
    font-size: 20px;
    font-weight: 800;
}

.promotion-saving {
    color: #198754;
    font-size: 12px;
    margin-bottom: 12px;
}

.promotion-empty {
    text-align: center;
    padding: 65px 20px;
    color: #6c757d;
}

.promotion-empty i {
    font-size: 65px;
    color: #adb5bd;
}

@media (max-width: 576px) {
    .promotion-banner {
        padding: 25px 20px;
    }

    .promotion-banner h1 {
        font-size: 24px;
    }

    .promotion-section {
        padding: 16px;
    }
}
</style>

<main class="customer-promotion-page">

    <div class="container">

        <!-- BANNER -->
        <section class="promotion-banner">

            <div class="row align-items-center">

                <div class="col-lg-8">

                    <h1>
                        <i class="bi bi-tags-fill me-2"></i>
                        Ưu đãi đặc biệt tại HP COMPUTER
                    </h1>

                    <p>
                        Khám phá các chương trình khuyến mãi
                        và sở hữu sản phẩm công nghệ với mức giá tốt nhất.
                    </p>

                </div>

                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">

                    <i class="bi bi-gift-fill"
                       style="font-size: 85px; opacity: .85;"></i>

                </div>

            </div>

        </section>

        <!-- THONG KE -->
        <div class="row g-3 mb-4">

            <div class="col-md-6">

                <div class="promotion-stat">

                    <div class="text-muted small">
                        Chương trình đang áp dụng
                    </div>

                    <div class="stat-number">
                        <?= $totalPromotions ?>
                    </div>

                    <div class="small text-muted">
                        Chương trình khuyến mãi
                    </div>

                </div>

            </div>

            <div class="col-md-6">

                <div class="promotion-stat">

                    <div class="text-muted small">
                        Sản phẩm được ưu đãi
                    </div>

                    <div class="stat-number">
                        <?= $totalProducts ?>
                    </div>

                    <div class="small text-muted">
                        Sản phẩm đang áp dụng khuyến mãi
                    </div>

                </div>

            </div>

        </div>

        <!-- THONG BAO LOI -->
        <?php if ($pageError !== ''): ?>

            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= customer_promotion_escape($pageError) ?>
            </div>

        <?php elseif (empty($promotions)): ?>

            <section class="promotion-section promotion-empty">

                <i class="bi bi-gift"></i>

                <h4 class="mt-3">
                    Hiện chưa có chương trình khuyến mãi
                </h4>

                <p>
                    HP COMPUTER sẽ cập nhật các ưu đãi mới
                    trong thời gian tới.
                </p>

                <a href="<?= rtrim(BASE_URL, '/') ?>/modules/customers/products.php"
                   class="btn btn-primary mt-2">

                    <i class="bi bi-grid me-1"></i>
                    Xem tất cả sản phẩm

                </a>

            </section>

        <?php else: ?>

            <!-- DANH SACH CHUONG TRINH -->
            <?php foreach ($promotions as $promotion): ?>

                <section class="promotion-section">

                    <!-- THONG TIN CHUONG TRINH -->
                    <div class="d-flex flex-wrap justify-content-between
                                align-items-start gap-3 mb-4">

                        <div>

                            <h2 class="promotion-section-title mb-2">

                                <i class="bi bi-fire text-danger me-2"></i>

                                <?= customer_promotion_escape(
                                    $promotion['ten_chuong_trinh']
                                ) ?>

                            </h2>

                            <?php if (!empty($promotion['mo_ta'])): ?>

                                <p class="text-muted mb-2">
                                    <?= nl2br(customer_promotion_escape(
                                        $promotion['mo_ta']
                                    )) ?>
                                </p>

                            <?php endif; ?>

                            <div class="promotion-time">

                                <i class="bi bi-calendar-event me-1"></i>

                                Áp dụng từ
                                <?= date(
                                    'd/m/Y',
                                    strtotime($promotion['ngay_bat_dau'])
                                ) ?>

                                đến

                                <?= date(
                                    'd/m/Y',
                                    strtotime($promotion['ngay_ket_thuc'])
                                ) ?>

                            </div>

                        </div>

                        <div class="text-end">

                            <?php if ($promotion['loai_giam_gia'] === 'PERCENT'): ?>

                                <span class="badge bg-danger fs-6 px-3 py-2">

                                    Giảm
                                    <?= rtrim(
                                        rtrim(
                                            number_format(
                                                $promotion['gia_tri_giam'],
                                                2,
                                                '.',
                                                ''
                                            ),
                                            '0'
                                        ),
                                        '.'
                                    ) ?>%

                                </span>

                            <?php else: ?>

                                <span class="badge bg-danger fs-6 px-3 py-2">

                                    Giảm
                                    <?= customer_promotion_format_money(
                                        $promotion['gia_tri_giam']
                                    ) ?>

                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                    <!-- SAN PHAM KHUYEN MAI -->
                    <div class="row g-3">

                        <?php foreach ($promotion['products'] as $product): ?>

                            <div class="col-12 col-sm-6 col-lg-4 col-xl-3">

                                <div class="promotion-product">

                                    <div class="promotion-product-icon">

                                        <i class="bi bi-pc-display"></i>

                                        <?php
                                        $discountPercent = 0;

                                        if ($product['gia_goc'] > 0) {
                                            $discountPercent = round(
                                                $product['tien_giam'] /
                                                $product['gia_goc'] * 100
                                            );
                                        }
                                        ?>

                                        <span class="promotion-discount">

                                            -<?= $discountPercent ?>%

                                        </span>

                                    </div>

                                    <div class="promotion-product-body">

                                        <div class="promotion-sku">

                                            SKU:
                                            <?= customer_promotion_escape(
                                                $product['ma_sku']
                                            ) ?>

                                        </div>

                                        <div class="promotion-product-name">

                                            <?= customer_promotion_escape(
                                                $product['ten_san_pham']
                                            ) ?>

                                        </div>

                                        <div class="mt-3">

                                            <div class="promotion-original-price">

                                                <?= customer_promotion_format_money(
                                                    $product['gia_goc']
                                                ) ?>

                                            </div>

                                            <div class="promotion-final-price">

                                                <?= customer_promotion_format_money(
                                                    $product['gia_sau_giam']
                                                ) ?>

                                            </div>

                                            <div class="promotion-saving">

                                                <i class="bi bi-check-circle-fill me-1"></i>

                                                Tiết kiệm
                                                <?= customer_promotion_format_money(
                                                    $product['tien_giam']
                                                ) ?>

                                            </div>

                                        </div>

                                        <a href="<?= rtrim(BASE_URL, '/') ?>/modules/customers/product_detail.php?ma_san_pham=<?= (int)$product['ma_san_pham'] ?>"
                                           class="btn btn-outline-primary w-100">

                                            <i class="bi bi-eye me-1"></i>
                                            Xem sản phẩm

                                        </a>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</main>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
