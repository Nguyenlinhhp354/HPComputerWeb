
<?php
require_once __DIR__ . '/../../config/database.php';

// Lay danh sach san pham va so luong ton kho
$sql = "
    SELECT
        sp.ma_san_pham,
        sp.ma_sku,
        sp.ten_san_pham,
        COALESCE(tk.so_luong_ton, 0) AS so_luong_ton
    FROM san_pham sp
    LEFT JOIN (
        SELECT
            ma_san_pham,
            SUM(so_luong) AS so_luong_ton
        FROM ton_kho
        GROUP BY ma_san_pham
    ) tk ON sp.ma_san_pham = tk.ma_san_pham
    ORDER BY sp.ten_san_pham ASC
";

$stmt = $pdo->query($sql);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tinh thong ke
$totalQuantity = 0;
$lowStock = 0;
$outOfStock = 0;

// Nguong canh bao tam thoi
$lowStockThreshold = 5;

foreach ($products as $product) {
    $quantity = (int)$product['so_luong_ton'];

    $totalQuantity += $quantity;

    if ($quantity <= 0) {
        $outOfStock++;
    } elseif ($quantity <= $lowStockThreshold) {
        $lowStock++;
    }
}

function inventoryEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<div class="container-fluid py-4">

    <h2 class="fw-bold mb-2">Quản lý kho</h2>
    <p class="text-muted mb-4">
        Theo dõi số lượng và tình trạng hàng hóa
    </p>

<?php if (
    (int)($_SESSION['ma_vai_tro'] ?? 0) === 1 ||
    co_quyen('inventory.create')
): ?>

<div class="mb-4">
    <a href="<?= htmlspecialchars(
        BASE_URL,
        ENT_QUOTES,
        'UTF-8'
    ) ?>/inventory_receipts.php"
       class="btn btn-primary">

        <i class="bi bi-plus-circle me-1"></i>
        Tạo phiếu nhập kho

    </a>
</div>

<?php endif; ?>

<?php if (
    (int)($_SESSION['ma_vai_tro'] ?? 0) === 1
    || co_quyen('inventory.create')
): ?>

<div class="mb-4">
    <a href="<?= htmlspecialchars(
        BASE_URL,
        ENT_QUOTES,
        'UTF-8'
    ) ?>/inventory_issues.php"
       class="btn btn-warning">

        <i class="bi bi-box-arrow-up me-1"></i>
        Tạo phiếu xuất kho

    </a>
</div>

<?php endif; ?>

<div class="mb-3">
    <a href="<?= htmlspecialchars(
        BASE_URL,
        ENT_QUOTES,
        'UTF-8'
    ) ?>/inventory_issue_list.php"
       class="btn btn-outline-primary">

        <i class="bi bi-list-ul me-1"></i>
        Danh sách phiếu xuất kho

    </a>
</div>

    <!-- THONG KE -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card p-4">
                <h6>Tổng số lượng hàng tồn</h6>
                <h2><?= number_format($totalQuantity) ?></h2>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-4">
                <h6>Sản phẩm sắp hết</h6>
                <h2 class="text-warning">
                    <?= $lowStock ?>
                </h2>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-4">
                <h6>Sản phẩm hết hàng</h6>
                <h2 class="text-danger">
                    <?= $outOfStock ?>
                </h2>
            </div>
        </div>

    </div>

    <!-- BANG TON KHO -->
    <div class="card p-4">

        <h5>Danh sách tồn kho</h5>

        <div class="table-responsive">
            <table class="table table-bordered
                          table-hover mt-3">

                <thead class="table-light">
                    <tr>
                        <th>STT</th>
                        <th>Mã SKU</th>
                        <th>Tên sản phẩm</th>
                        <th>Số lượng tồn</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($products as $index => $product): ?>

                    <?php
                    $quantity = (int)$product['so_luong_ton'];

                    if ($quantity <= 0) {
                        $status = 'Hết hàng';
                        $color = 'danger';
                    } elseif ($quantity <= $lowStockThreshold) {
                        $status = 'Sắp hết';
                        $color = 'warning';
                    } else {
                        $status = 'Còn hàng';
                        $color = 'success';
                    }
                    ?>

                    <tr>
                        <td><?= $index + 1 ?></td>

                        <td>
                            <?= inventoryEscape($product['ma_sku']) ?>
                        </td>

                        <td>
                            <?= inventoryEscape($product['ten_san_pham']) ?>
                        </td>

                        <td><?= $quantity ?></td>

                        <td>
                            <span class="badge bg-<?= $color ?>">
                                <?= $status ?>
                            </span>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="5" class="text-center">
                            Chưa có dữ liệu sản phẩm.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>

            </table>
        </div>

    </div>

</div>
