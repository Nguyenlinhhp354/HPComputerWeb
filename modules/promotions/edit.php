<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/permission_check.php';
yeu_cau_quyen('promotions.edit');

// =====================================================
// HP COMPUTER
// FILE: modules/promotions/edit.php
// CHUC NANG: CHINH SUA CHUONG TRINH KHUYEN MAI
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

// -----------------------------------------------------
// 1. LAY MA CHUONG TRINH
// -----------------------------------------------------

$maChuongTrinh = filter_var(
    $_POST['ma_chuong_trinh'] ?? $_GET['ma_chuong_trinh'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$maChuongTrinh || $maChuongTrinh <= 0) {
    http_response_code(400);
    exit('Mã chương trình khuyến mãi không hợp lệ.');
}

// -----------------------------------------------------
// 2. LAY DU LIEU CHUONG TRINH
// -----------------------------------------------------

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
    error_log($e->getMessage());
    exit('Lỗi khi tải dữ liệu chương trình khuyến mãi.');
}

// -----------------------------------------------------
// 3. CSRF TOKEN
// -----------------------------------------------------

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// -----------------------------------------------------
// 4. XU LY FORM CAP NHAT
// -----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tenChuongTrinh = trim($_POST['ten_chuong_trinh'] ?? '');
    $moTa = trim($_POST['mo_ta'] ?? '');
    $loaiGiamGia = $_POST['loai_giam_gia'] ?? '';
    $giaTriGiam = trim($_POST['gia_tri_giam'] ?? '');
    $giaTriGiamToiDa = trim($_POST['gia_tri_giam_toi_da'] ?? '');
    $ngayBatDau = trim($_POST['ngay_bat_dau'] ?? '');
    $ngayKetThuc = trim($_POST['ngay_ket_thuc'] ?? '');
    $trangThai = $_POST['trang_thai'] ?? '';

    // Kiem tra CSRF
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    }

    // Kiem tra ten
    if ($tenChuongTrinh === '') {
        $errors[] = 'Vui lòng nhập tên chương trình.';
    } elseif (mb_strlen($tenChuongTrinh, 'UTF-8') > 200) {
        $errors[] = 'Tên chương trình không được vượt quá 200 ký tự.';
    }

    // Kiem tra loai giam gia
    if (!in_array($loaiGiamGia, ['PERCENT', 'FIXED_AMOUNT'], true)) {
        $errors[] = 'Loại giảm giá không hợp lệ.';
    }

    // Kiem tra gia tri giam
    if (
        !is_numeric($giaTriGiam) ||
        !is_finite((float)$giaTriGiam) ||
        (float)$giaTriGiam <= 0
    ) {
        $errors[] = 'Giá trị giảm phải lớn hơn 0.';
    } elseif (
        $loaiGiamGia === 'PERCENT' &&
        (float)$giaTriGiam > 100
    ) {
        $errors[] = 'Mức giảm phần trăm không được vượt quá 100%.';
    }

    // Kiem tra muc giam toi da
    if ($loaiGiamGia === 'PERCENT' && $giaTriGiamToiDa !== '') {
        if (
            !is_numeric($giaTriGiamToiDa) ||
            !is_finite((float)$giaTriGiamToiDa) ||
            (float)$giaTriGiamToiDa <= 0
        ) {
            $errors[] = 'Giá trị giảm tối đa phải lớn hơn 0.';
        }
    }

    if ($loaiGiamGia === 'FIXED_AMOUNT') {
        $giaTriGiamToiDa = '';
    }

    // Kiem tra ngay gio
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

    // Kiem tra trang thai
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

    // Cap nhat database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE chuong_trinh_khuyen_mai
                SET
                    ten_chuong_trinh = :ten,
                    mo_ta = :mo_ta,
                    loai_giam_gia = :loai,
                    gia_tri_giam = :gia_tri,
                    gia_tri_giam_toi_da = :toi_da,
                    ngay_bat_dau = :bat_dau,
                    ngay_ket_thuc = :ket_thuc,
                    trang_thai = :trang_thai
                WHERE ma_chuong_trinh = :ma
            ");

            $stmt->execute([
                ':ten' => $tenChuongTrinh,
                ':mo_ta' => $moTa !== '' ? $moTa : null,
                ':loai' => $loaiGiamGia,
                ':gia_tri' => $giaTriGiam,
                ':toi_da' => $giaTriGiamToiDa !== ''
                    ? $giaTriGiamToiDa
                    : null,
                ':bat_dau' => $batDau->format('Y-m-d H:i:s'),
                ':ket_thuc' => $ketThuc->format('Y-m-d H:i:s'),
                ':trang_thai' => $trangThai,
                ':ma' => $maChuongTrinh
            ]);

            header('Location: ' . BASE_URL . '/promotions.php?updated=1');
            exit;

        } catch (PDOException $e) {
            error_log('PROMOTION UPDATE: ' . $e->getMessage());
            $errors[] = 'Không thể cập nhật chương trình. Vui lòng thử lại.';
        }
    }

    // Giu lai du lieu form neu co loi
    $promotion['ten_chuong_trinh'] = $tenChuongTrinh;
    $promotion['mo_ta'] = $moTa;
    $promotion['loai_giam_gia'] = $loaiGiamGia;
    $promotion['gia_tri_giam'] = $giaTriGiam;
    $promotion['gia_tri_giam_toi_da'] = $giaTriGiamToiDa;
    $promotion['ngay_bat_dau'] = $ngayBatDau;
    $promotion['ngay_ket_thuc'] = $ngayKetThuc;
    $promotion['trang_thai'] = $trangThai;
}

// -----------------------------------------------------
// 5. DINH DANG NGAY
// -----------------------------------------------------

$formatDate = static function ($value) {
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('Y-m-d\TH:i', $timestamp)
        : '';
};

$ngayBatDauValue = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? $promotion['ngay_bat_dau']
    : $formatDate($promotion['ngay_bat_dau']);

$ngayKetThucValue = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? $promotion['ngay_ket_thuc']
    : $formatDate($promotion['ngay_ket_thuc']);

// -----------------------------------------------------
// 6. HEADER VA NAVBAR
// -----------------------------------------------------

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';

?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="container-fluid py-3">

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

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>Vui lòng kiểm tra lại:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?= $escape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-pencil-square me-2"></i>
                        Thông tin chương trình
                    </h5>
                </div>

                <div class="card-body">

                    <form method="POST"
                          action="<?= $escape(BASE_URL) ?>/modules/promotions/edit.php?ma_chuong_trinh=<?= (int)$maChuongTrinh ?>">

                        <input type="hidden"
                               name="ma_chuong_trinh"
                               value="<?= (int)$maChuongTrinh ?>">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= $escape($_SESSION['csrf_token']) ?>">

                        <div class="row g-3">

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">
                                    Tên chương trình <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       name="ten_chuong_trinh"
                                       class="form-control"
                                       maxlength="200"
                                       required
                                       value="<?= $escape($promotion['ten_chuong_trinh']) ?>">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Mô tả</label>
                                <textarea name="mo_ta"
                                          class="form-control"
                                          rows="3"><?= $escape($promotion['mo_ta']) ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    Loại giảm giá <span class="text-danger">*</span>
                                </label>
                                <select name="loai_giam_gia"
                                        id="loai_giam_gia"
                                        class="form-select"
                                        required>
                                    <option value="PERCENT"
                                        <?= $promotion['loai_giam_gia'] === 'PERCENT' ? 'selected' : '' ?>>
                                        Giảm theo phần trăm (%)
                                    </option>
                                    <option value="FIXED_AMOUNT"
                                        <?= $promotion['loai_giam_gia'] === 'FIXED_AMOUNT' ? 'selected' : '' ?>>
                                        Giảm số tiền cố định (VNĐ)
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    Giá trị giảm <span class="text-danger">*</span>
                                </label>
                                <input type="number"
                                       name="gia_tri_giam"
                                       id="gia_tri_giam"
                                       class="form-control"
                                       min="0.01"
                                       step="0.01"
                                       required
                                       value="<?= $escape($promotion['gia_tri_giam']) ?>">
                                <small id="discountHint" class="text-muted">
                                    Nhập mức giảm từ 0 đến 100%.
                                </small>
                            </div>

                            <div class="col-md-6" id="max_discount_wrapper">
                                <label class="form-label fw-semibold">
                                    Giá trị giảm tối đa (VNĐ)
                                </label>
                                <input type="number"
                                       name="gia_tri_giam_toi_da"
                                       id="gia_tri_giam_toi_da"
                                       class="form-control"
                                       min="0.01"
                                       step="0.01"
                                       value="<?= $escape($promotion['gia_tri_giam_toi_da']) ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    Ngày bắt đầu <span class="text-danger">*</span>
                                </label>
                                <input type="datetime-local"
                                       name="ngay_bat_dau"
                                       class="form-control"
                                       required
                                       value="<?= $escape($ngayBatDauValue) ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    Ngày kết thúc <span class="text-danger">*</span>
                                </label>
                                <input type="datetime-local"
                                       name="ngay_ket_thuc"
                                       class="form-control"
                                       required
                                       value="<?= $escape($ngayKetThucValue) ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    Trạng thái <span class="text-danger">*</span>
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

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= $escape(BASE_URL) ?>/promotions.php"
                               class="btn btn-light border">
                                Hủy
                            </a>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i>
                                Lưu thay đổi
                            </button>
                        </div>

                    </form>

                </div>
            </div>

        </div>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const discountType = document.getElementById('loai_giam_gia');
    const discountValue = document.getElementById('gia_tri_giam');
    const maxDiscountWrapper = document.getElementById('max_discount_wrapper');
    const maxDiscountInput = document.getElementById('gia_tri_giam_toi_da');
    const discountHint = document.getElementById('discountHint');

    function updateDiscountFields() {

        if (discountType.value === 'PERCENT') {

            maxDiscountWrapper.style.display = '';
            discountValue.max = '100';
            discountHint.textContent = 'Nhập mức giảm từ 0 đến 100%.';

        } else {

            maxDiscountWrapper.style.display = 'none';
            discountValue.removeAttribute('max');
            maxDiscountInput.value = '';
            discountHint.textContent = 'Nhập số tiền giảm bằng VNĐ.';
        }
    }

    discountType.addEventListener('change', updateDiscountFields);
    updateDiscountFields();

});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
