
<?php
// =====================================================
// MODULE: XÓA MỀM SẢN PHẨM
// Chuyển trạng thái ACTIVE thành INACTIVE
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

// Chỉ chấp nhận phương thức POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/products.php');
    exit;
}

// Kiểm tra CSRF
$csrfToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($csrfToken) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    header('Location: ' . BASE_URL . '/products.php?delete_error=csrf');
    exit;
}

// Kiểm tra mã sản phẩm
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header('Location: ' . BASE_URL . '/products.php?delete_error=invalid');
    exit;
}

try {

    // Kiểm tra sản phẩm tồn tại
    $stmt = $pdo->prepare("
        SELECT ma_san_pham, trang_thai
        FROM san_pham
        WHERE ma_san_pham = :id
        LIMIT 1
    ");

    $stmt->execute([':id' => $id]);

    $product = $stmt->fetch();

    if (!$product) {
        header('Location: ' . BASE_URL . '/products.php?delete_error=not_found');
        exit;
    }

    // Kiểm tra trạng thái hiện tại
    if ($product['trang_thai'] === 'INACTIVE') {
        header('Location: ' . BASE_URL . '/products.php?delete_error=inactive');
        exit;
    }

    // Xóa mềm: chuyển sản phẩm sang ngừng kinh doanh
    $stmtUpdate = $pdo->prepare("
        UPDATE san_pham
        SET
            trang_thai = 'INACTIVE',
            ngay_cap_nhat = NOW()
        WHERE ma_san_pham = :id
    ");

    $stmtUpdate->execute([':id' => $id]);

    header('Location: ' . BASE_URL . '/products.php?deleted=1');
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    header('Location: ' . BASE_URL . '/products.php?delete_error=database');
    exit;
}
