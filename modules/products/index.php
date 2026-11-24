<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permission_check.php';
yeu_cau_quyen('products.view');

// =====================================================
// MODULE: QUAN LY SAN PHAM
// DU AN: HP COMPUTER
// =====================================================

require_once __DIR__ . '/../../config/database.php';

// =====================================================
// 1. HAM HO TRO
// =====================================================

if (!function_exists('productEscape')) {
    function productEscape($value)
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

// Dam bao session da duoc khoi tao
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Khoi tao CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =====================================================
// 2. LAY THAM SO TIM KIEM VA LOC
// =====================================================

$keyword = trim($_GET['keyword'] ?? '');
$category = $_GET['category'] ?? '';
$brand = $_GET['brand'] ?? '';
$status = $_GET['status'] ?? '';

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);

if (!$page || $page < 1) {
    $page = 1;
}

$limit = 10;
$offset = ($page - 1) * $limit;

// =====================================================
// 3. LAY DANH SACH DANH MUC
// =====================================================

$stmtCategories = $pdo->query("
    SELECT ma_danh_muc, ten_danh_muc
    FROM danh_muc
    ORDER BY ten_danh_muc ASC
");

$categories = $stmtCategories->fetchAll();

// =====================================================
// 4. LAY DANH SACH THUONG HIEU
// =====================================================

$stmtBrands = $pdo->query("
    SELECT ma_thuong_hieu, ten_thuong_hieu
    FROM thuong_hieu
    ORDER BY ten_thuong_hieu ASC
");

$brands = $stmtBrands->fetchAll();

// =====================================================
// 5. XAY DUNG DIEU KIEN LOC
// =====================================================

$where = [];
$params = [];

// Tim kiem ten san pham va SKU
if ($keyword !== '') {
    $where[] = "(
        sp.ten_san_pham LIKE :keyword_name
        OR sp.ma_sku LIKE :keyword_sku
    )";

    $params[':keyword_name'] = '%' . $keyword . '%';
    $params[':keyword_sku'] = '%' . $keyword . '%';
}

// Loc danh muc
if ($category !== '' && ctype_digit((string)$category)) {
    $where[] = "sp.ma_danh_muc = :category";
    $params[':category'] = (int)$category;
}

// Loc thuong hieu
if ($brand !== '' && ctype_digit((string)$brand)) {
    $where[] = "sp.ma_thuong_hieu = :brand";
    $params[':brand'] = (int)$brand;
}

// Loc trang thai
if (in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
    $where[] = "sp.trang_thai = :status";
    $params[':status'] = $status;
}

$whereSQL = '';

if (!empty($where)) {
    $whereSQL = ' WHERE ' . implode(' AND ', $where);
}

// =====================================================
// 6. DEM TONG SO SAN PHAM
// =====================================================

$sqlCount = "
    SELECT COUNT(*)
    FROM san_pham sp
    $whereSQL
";

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);

$totalProducts = (int)$stmtCount->fetchColumn();

$totalPages = max(1, (int)ceil($totalProducts / $limit));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $limit;

// =====================================================
// 7. LAY DANH SACH SAN PHAM
// =====================================================

$sqlProducts = "
    SELECT
        sp.ma_san_pham,
        sp.ma_sku,
        sp.ten_san_pham,
        sp.gia_ban,
        sp.thoi_han_bao_hanh,
        sp.trang_thai,

        dm.ten_danh_muc,
        th.ten_thuong_hieu,

        COALESCE(tk.so_luong, 0) AS so_luong_ton

    FROM san_pham sp

    INNER JOIN danh_muc dm
        ON sp.ma_danh_muc = dm.ma_danh_muc

    LEFT JOIN thuong_hieu th
        ON sp.ma_thuong_hieu = th.ma_thuong_hieu

    LEFT JOIN ton_kho tk
        ON sp.ma_san_pham = tk.ma_san_pham

    $whereSQL

    ORDER BY sp.ma_san_pham DESC

    LIMIT $limit OFFSET $offset
";

$stmtProducts = $pdo->prepare($sqlProducts);
$stmtProducts->execute($params);

$products = $stmtProducts->fetchAll();

// =====================================================
// 8. THONG KE TRANG THAI SAN PHAM
// =====================================================

$stmtActive = $pdo->query("
    SELECT COUNT(*)
    FROM san_pham
    WHERE trang_thai = 'ACTIVE'
");

$totalActive = (int)$stmtActive->fetchColumn();

$stmtInactive = $pdo->query("
    SELECT COUNT(*)
    FROM san_pham
    WHERE trang_thai = 'INACTIVE'
");

$totalInactive = (int)$stmtInactive->fetchColumn();

// =====================================================
// 9. DUONG DAN PHAN TRANG
// =====================================================

$queryParams = $_GET;
unset($queryParams['page']);

$paginationUrl = BASE_URL . '/products.php';

$buildPageUrl = function ($pageNumber) use (
    $paginationUrl,
    $queryParams
) {
    $params = $queryParams;
    $params['page'] = $pageNumber;

    return $paginationUrl . '?' . http_build_query($params);
};

// =====================================================
// 10. THONG BAO
// =====================================================

$deleteErrors = [
    'csrf' => 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.',
    'invalid' => 'Mã sản phẩm không hợp lệ.',
    'not_found' => 'Không tìm thấy sản phẩm.',
    'inactive' => 'Sản phẩm đã ngừng kinh doanh trước đó.',
    'database' => 'Không thể cập nhật trạng thái sản phẩm.'
];

?>

<div class="container-fluid">

    <!-- TIEU DE -->
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h4 class="fw-bold mb-1">
                Quản lý sản phẩm
            </h4>

            <p class="text-muted mb-0">
                Quản lý danh sách và thông tin sản phẩm
            </p>
        </div>

        <?php if (co_quyen('products.create')): ?>
        <a href="<?= BASE_URL ?>/modules/products/create.php"
           class="btn btn-primary">

            <i class="bi bi-plus-circle me-1"></i>
            Thêm sản phẩm

        </a>
        <?php endif; ?>

    </div>

    <!-- =================================================
         THONG BAO KET QUA
    ================================================== -->

    <?php if (isset($_GET['created']) && $_GET['created'] === '1'): ?>

        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-1"></i>
            Thêm sản phẩm thành công!

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"></button>
        </div>

    <?php endif; ?>


    <?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>

        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-1"></i>
            Cập nhật sản phẩm thành công!

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"></button>
        </div>

    <?php endif; ?>


    <?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?>

        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-1"></i>
            Đã ngừng kinh doanh sản phẩm thành công!

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"></button>
        </div>

    <?php endif; ?>


    <?php if (isset($_GET['delete_error'])): ?>

        <?php
        $errorCode = $_GET['delete_error'];

        $errorMessage = $deleteErrors[$errorCode]
            ?? 'Đã xảy ra lỗi khi xử lý sản phẩm.';
        ?>

        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <?= productEscape($errorMessage) ?>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"></button>
        </div>

    <?php endif; ?>


    <!-- =================================================
         THONG KE
    ================================================== -->

    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">

                    <div class="d-flex align-items-center">

                        <div class="rounded-circle bg-light p-3 me-3">
                            <i class="bi bi-box-seam fs-4"></i>
                        </div>

                        <div>
                            <div class="text-muted small">
                                Tổng sản phẩm
                            </div>

                            <h5 class="fw-bold mb-0">
                                <?= number_format($totalProducts) ?>
                            </h5>
                        </div>

                    </div>

                </div>
            </div>
        </div>


        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">

                    <div class="d-flex align-items-center">

                        <div class="rounded-circle bg-light p-3 me-3">
                            <i class="bi bi-check-circle fs-4"></i>
                        </div>

                        <div>
                            <div class="text-muted small">
                                Đang kinh doanh
                            </div>

                            <h5 class="fw-bold mb-0">
                                <?= number_format($totalActive) ?>
                            </h5>
                        </div>

                    </div>

                </div>
            </div>
        </div>


        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">

                    <div class="d-flex align-items-center">

                        <div class="rounded-circle bg-light p-3 me-3">
                            <i class="bi bi-x-circle fs-4"></i>
                        </div>

                        <div>
                            <div class="text-muted small">
                                Ngừng kinh doanh
                            </div>

                            <h5 class="fw-bold mb-0">
                                <?= number_format($totalInactive) ?>
                            </h5>
                        </div>

                    </div>

                </div>
            </div>
        </div>

    </div>


    <!-- =================================================
         TIM KIEM VA BO LOC
    ================================================== -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form method="GET"
                  action="<?= BASE_URL ?>/products.php">

                <div class="row g-3">

                    <div class="col-lg-3 col-md-6">

                        <label class="form-label">
                            Tìm kiếm
                        </label>

                        <input type="text"
                               name="keyword"
                               class="form-control"
                               placeholder="Tên sản phẩm hoặc SKU..."
                               value="<?= productEscape($keyword) ?>">

                    </div>


                    <div class="col-lg-3 col-md-6">

                        <label class="form-label">
                            Danh mục
                        </label>

                        <select name="category"
                                class="form-select">

                            <option value="">
                                Tất cả danh mục
                            </option>

                            <?php foreach ($categories as $item): ?>

                                <option value="<?= (int)$item['ma_danh_muc'] ?>"
                                    <?= (string)$category === (string)$item['ma_danh_muc'] ? 'selected' : '' ?>>

                                    <?= productEscape($item['ten_danh_muc']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="col-lg-2 col-md-6">

                        <label class="form-label">
                            Thương hiệu
                        </label>

                        <select name="brand"
                                class="form-select">

                            <option value="">
                                Tất cả
                            </option>

                            <?php foreach ($brands as $item): ?>

                                <option value="<?= (int)$item['ma_thuong_hieu'] ?>"
                                    <?= (string)$brand === (string)$item['ma_thuong_hieu'] ? 'selected' : '' ?>>

                                    <?= productEscape($item['ten_thuong_hieu']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="col-lg-2 col-md-6">

                        <label class="form-label">
                            Trạng thái
                        </label>

                        <select name="status"
                                class="form-select">

                            <option value="">
                                Tất cả
                            </option>

                            <option value="ACTIVE"
                                <?= $status === 'ACTIVE' ? 'selected' : '' ?>>
                                Đang kinh doanh
                            </option>

                            <option value="INACTIVE"
                                <?= $status === 'INACTIVE' ? 'selected' : '' ?>>
                                Ngừng kinh doanh
                            </option>

                        </select>

                    </div>


                    <div class="col-lg-2 col-md-12 d-flex align-items-end">

                        <button type="submit"
                                class="btn btn-primary w-100">

                            <i class="bi bi-search me-1"></i>
                            Lọc dữ liệu

                        </button>

                    </div>

                </div>


                <div class="mt-3">

                    <a href="<?= BASE_URL ?>/products.php"
                       class="text-decoration-none text-muted">

                        <i class="bi bi-arrow-counterclockwise"></i>
                        Xóa bộ lọc

                    </a>

                </div>

            </form>

        </div>

    </div>


    <!-- =================================================
         DANH SACH SAN PHAM
    ================================================== -->

    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3">

            <div class="d-flex justify-content-between align-items-center">

                <h6 class="fw-bold mb-0">
                    Danh sách sản phẩm
                </h6>

                <span class="text-muted small">
                    Có <?= number_format($totalProducts) ?> sản phẩm
                </span>

            </div>

        </div>


        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>
                            <th class="text-center" style="width: 60px;">
                                STT
                            </th>

                            <th>Mã SKU</th>

                            <th>Tên sản phẩm</th>

                            <th>Danh mục</th>

                            <th>Thương hiệu</th>

                            <th class="text-end">Giá bán</th>

                            <th class="text-center">Tồn kho</th>

                            <th class="text-center">Trạng thái</th>

                            <th class="text-center" style="width: 120px;">
                                Thao tác
                            </th>
                        </tr>

                    </thead>


                    <tbody>

                        <?php if (!empty($products)): ?>

                            <?php foreach ($products as $index => $product): ?>

                                <tr>

                                    <!-- STT -->
                                    <td class="text-center">
                                        <?= $offset + $index + 1 ?>
                                    </td>


                                    <!-- SKU -->
                                    <td>
                                        <span class="fw-semibold">
                                            <?= productEscape($product['ma_sku']) ?>
                                        </span>
                                    </td>


                                    <!-- TEN SAN PHAM -->
                                    <td>

                                        <div class="fw-semibold">
                                            <?= productEscape($product['ten_san_pham']) ?>
                                        </div>

                                        <small class="text-muted">
                                            Bảo hành:
                                            <?= (int)$product['thoi_han_bao_hanh'] ?>
                                            tháng
                                        </small>

                                    </td>


                                    <!-- DANH MUC -->
                                    <td>
                                        <?= productEscape($product['ten_danh_muc']) ?>
                                    </td>


                                    <!-- THUONG HIEU -->
                                    <td>
                                        <?= productEscape(
                                            $product['ten_thuong_hieu'] ?: 'Chưa có'
                                        ) ?>
                                    </td>


                                    <!-- GIA BAN -->
                                    <td class="text-end fw-semibold">
                                        <?= number_format(
                                            (float)$product['gia_ban'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?> ₫
                                    </td>


                                    <!-- TON KHO -->
                                    <td class="text-center">

                                        <?php if ((int)$product['so_luong_ton'] > 0): ?>

                                            <?= number_format(
                                                (int)$product['so_luong_ton']
                                            ) ?>

                                        <?php else: ?>

                                            <span class="text-danger fw-semibold">
                                                Hết hàng
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- TRANG THAI -->
                                    <td class="text-center">

                                        <?php if ($product['trang_thai'] === 'ACTIVE'): ?>

                                            <span class="badge bg-success">
                                                Đang kinh doanh
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-secondary">
                                                Ngừng kinh doanh
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- THAO TAC -->
                                    <td>

                                        <div class="d-flex justify-content-center gap-1">

                                            <!-- SUA -->
                                            <?php if (co_quyen('products.edit')): ?>
                                            <a href="<?= BASE_URL ?>/modules/products/edit.php?id=<?= (int)$product['ma_san_pham'] ?>"
                                               class="btn btn-sm btn-outline-secondary"
                                               title="Cập nhật sản phẩm">

                                                <i class="bi bi-pencil-square"></i>

                                            </a>
                                            <?php endif; ?>


                                            <!-- XOA MEM -->
                                            <?php if (co_quyen('products.delete')): ?>
                                            <?php if ($product['trang_thai'] === 'ACTIVE'): ?>

                                                <form method="POST"
                                                      action="<?= BASE_URL ?>/modules/products/delete.php"
                                                      onsubmit="return confirm('Bạn có chắc chắn muốn ngừng kinh doanh sản phẩm này?');">

                                                    <input type="hidden"
                                                           name="id"
                                                           value="<?= (int)$product['ma_san_pham'] ?>">

                                                    <input type="hidden"
                                                           name="csrf_token"
                                                           value="<?= productEscape($_SESSION['csrf_token']) ?>">

                                                    <button type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            title="Ngừng kinh doanh">

                                                        <i class="bi bi-trash"></i>

                                                    </button>

                                                </form>

                                            <?php else: ?>

                                                <button type="button"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        disabled
                                                        title="Sản phẩm đã ngừng kinh doanh">

                                                    <i class="bi bi-trash"></i>

                                                </button>

                                            <?php endif; ?>

                                            <?php endif; ?>
                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="9" class="text-center py-5">

                                    <i class="bi bi-inbox fs-1 text-muted"></i>

                                    <p class="text-muted mt-2 mb-0">
                                        Không tìm thấy sản phẩm phù hợp.
                                    </p>

                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- =================================================
             PHAN TRANG
        ================================================== -->

        <?php if ($totalPages > 1): ?>

            <div class="card-footer bg-white">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                    <div class="text-muted small">
                        Trang <?= $page ?> / <?= $totalPages ?>
                    </div>


                    <nav aria-label="Phân trang sản phẩm">

                        <ul class="pagination pagination-sm mb-0">

                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">

                                <a class="page-link"
                                   href="<?= productEscape($buildPageUrl(max(1, $page - 1))) ?>">

                                    <i class="bi bi-chevron-left"></i>

                                </a>

                            </li>


                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>

                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">

                                    <a class="page-link"
                                       href="<?= productEscape($buildPageUrl($i)) ?>">

                                        <?= $i ?>

                                    </a>

                                </li>

                            <?php endfor; ?>


                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">

                                <a class="page-link"
                                   href="<?= productEscape($buildPageUrl(min($totalPages, $page + 1))) ?>">

                                    <i class="bi bi-chevron-right"></i>

                                </a>

                            </li>

                        </ul>

                    </nav>

                </div>

            </div>

        <?php endif; ?>

    </div>

</div>
