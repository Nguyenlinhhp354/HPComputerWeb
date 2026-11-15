
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/promotions/create.php
// CHUC NANG: THEM CHUONG TRINH KHUYEN MAI
// =====================================================

// -----------------------------------------------------
// 1. KIEM TRA DANG NHAP VA KET NOI DATABASE
// -----------------------------------------------------

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

// -----------------------------------------------------
// 2. KHOI TAO CAC BIEN
// -----------------------------------------------------

$pageTitle = 'Thêm chương trình khuyến mãi';

$errors = [];

$formData = [
    'ten_chuong_trinh' => '',
    'mo_ta' => '',
    'loai_giam_gia' => 'PERCENT',
    'gia_tri_giam' => '',
    'gia_tri_giam_toi_da' => '',
    'ngay_bat_dau' => '',
    'ngay_ket_thuc' => '',
    'trang_thai' => 'DRAFT'
];

// -----------------------------------------------------
// 3. KHOI TAO CSRF TOKEN
// -----------------------------------------------------

if (empty($_SESSION['csrf_promotions'])) {
    $_SESSION['csrf_promotions'] = bin2hex(random_bytes(32));
}

// -----------------------------------------------------
// 4. HAM ESCAPE DU LIEU
// -----------------------------------------------------

$escape = static function ($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
};

// -----------------------------------------------------
// 5. XU LY FORM KHI NGUOI DUNG NHAN LUU
// -----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Lay du lieu tu form
    foreach ($formData as $key => $value) {
        if (isset($_POST[$key]) && !is_array($_POST[$key])) {
            $formData[$key] = trim((string) $_POST[$key]);
        }
    }

    // Kiem tra CSRF
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['csrf_promotions'], $csrfToken)
    ) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.';
    }

    // -------------------------------------------------
    // 5.1. KIEM TRA TEN CHUONG TRINH
    // -------------------------------------------------

    if ($formData['ten_chuong_trinh'] === '') {
        $errors[] = 'Vui lòng nhập tên chương trình khuyến mãi.';
    } elseif (mb_strlen($formData['ten_chuong_trinh'], 'UTF-8') > 200) {
        $errors[] = 'Tên chương trình không được vượt quá 200 ký tự.';
    }

    // -------------------------------------------------
    // 5.2. KIEM TRA LOAI GIAM GIA
    // -------------------------------------------------

    $allowedTypes = ['PERCENT', 'FIXED_AMOUNT'];

    if (!in_array($formData['loai_giam_gia'], $allowedTypes, true)) {
        $errors[] = 'Loại giảm giá không hợp lệ.';
    }

    // -------------------------------------------------
    // 5.3. KIEM TRA GIA TRI GIAM
    // -------------------------------------------------

    $discountValue = null;

    if (
        $formData['gia_tri_giam'] === '' ||
        !is_numeric($formData['gia_tri_giam'])
    ) {
        $errors[] = 'Vui lòng nhập giá trị giảm hợp lệ.';
    } else {
        $discountValue = (float) $formData['gia_tri_giam'];

        if ($discountValue <= 0) {
            $errors[] = 'Giá trị giảm phải lớn hơn 0.';
        } elseif (
            $formData['loai_giam_gia'] === 'PERCENT' &&
            $discountValue > 100
        ) {
            $errors[] = 'Mức giảm theo phần trăm không được vượt quá 100%.';
        }
    }

    // -------------------------------------------------
    // 5.4. KIEM TRA GIA TRI GIAM TOI DA
    // -------------------------------------------------

    $maxDiscount = null;

    if ($formData['loai_giam_gia'] === 'PERCENT') {

        if ($formData['gia_tri_giam_toi_da'] !== '') {

            if (!is_numeric($formData['gia_tri_giam_toi_da'])) {
                $errors[] = 'Giá trị giảm tối đa phải là một số hợp lệ.';
            } else {
                $maxDiscount = (float) $formData['gia_tri_giam_toi_da'];

                if ($maxDiscount <= 0) {
                    $errors[] = 'Giá trị giảm tối đa phải lớn hơn 0.';
                }
            }
        }

    } else {
        // Khong ap dung muc giam toi da voi giam theo so tien
        $maxDiscount = null;
        $formData['gia_tri_giam_toi_da'] = '';
    }

    // -------------------------------------------------
    // 5.5. KIEM TRA TRANG THAI
    // -------------------------------------------------

    $allowedStatuses = ['DRAFT', 'ACTIVE', 'INACTIVE'];

    if (!in_array($formData['trang_thai'], $allowedStatuses, true)) {
        $errors[] = 'Trạng thái chương trình không hợp lệ.';
    }

    // -------------------------------------------------
    // 5.6. KIEM TRA NGAY BAT DAU VA NGAY KET THUC
    // -------------------------------------------------

    $startDate = false;
    $endDate = false;

    if ($formData['ngay_bat_dau'] === '') {
        $errors[] = 'Vui lòng chọn ngày bắt đầu.';
    } else {
        $startDate = DateTime::createFromFormat(
            '!Y-m-d\TH:i',
            $formData['ngay_bat_dau']
        );

        $dateErrors = DateTime::getLastErrors();

        if (
            !$startDate ||
            (
                $dateErrors !== false &&
                ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)
            ) ||
            $startDate->format('Y-m-d\TH:i') !== $formData['ngay_bat_dau']
        ) {
            $errors[] = 'Ngày bắt đầu không hợp lệ.';
            $startDate = false;
        }
    }

    if ($formData['ngay_ket_thuc'] === '') {
        $errors[] = 'Vui lòng chọn ngày kết thúc.';
    } else {
        $endDate = DateTime::createFromFormat(
            '!Y-m-d\TH:i',
            $formData['ngay_ket_thuc']
        );

        $dateErrors = DateTime::getLastErrors();

        if (
            !$endDate ||
            (
                $dateErrors !== false &&
                ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)
            ) ||
            $endDate->format('Y-m-d\TH:i') !== $formData['ngay_ket_thuc']
        ) {
            $errors[] = 'Ngày kết thúc không hợp lệ.';
            $endDate = false;
        }
    }

    if ($startDate && $endDate && $endDate <= $startDate) {
        $errors[] = 'Ngày kết thúc phải sau ngày bắt đầu.';
    }

    // -------------------------------------------------
    // 6. LUU DU LIEU VAO DATABASE
    // -------------------------------------------------

    if (empty($errors)) {

        try {

            $sql = "
                INSERT INTO chuong_trinh_khuyen_mai (
                    ten_chuong_trinh,
                    mo_ta,
                    loai_giam_gia,
                    gia_tri_giam,
                    gia_tri_giam_toi_da,
                    ngay_bat_dau,
                    ngay_ket_thuc,
                    trang_thai,
                    ma_nguoi_tao
                ) VALUES (
                    :ten_chuong_trinh,
                    :mo_ta,
                    :loai_giam_gia,
                    :gia_tri_giam,
                    :gia_tri_giam_toi_da,
                    :ngay_bat_dau,
                    :ngay_ket_thuc,
                    :trang_thai,
                    :ma_nguoi_tao
                )
            ";

            $stmt = $pdo->prepare($sql);

            $creatorId = isset($_SESSION['ma_nguoi_dung'])
                ? (int) $_SESSION['ma_nguoi_dung']
                : null;

            $stmt->execute([
                ':ten_chuong_trinh' => $formData['ten_chuong_trinh'],
                ':mo_ta' => $formData['mo_ta'] !== ''
                    ? $formData['mo_ta']
                    : null,
                ':loai_giam_gia' => $formData['loai_giam_gia'],
                ':gia_tri_giam' => $discountValue,
                ':gia_tri_giam_toi_da' => $maxDiscount,
                ':ngay_bat_dau' => $startDate->format('Y-m-d H:i:s'),
                ':ngay_ket_thuc' => $endDate->format('Y-m-d H:i:s'),
                ':trang_thai' => $formData['trang_thai'],
                ':ma_nguoi_tao' => $creatorId
            ]);

            // Chuyen ve trang danh sach sau khi them thanh cong
            header('Location: ' . BASE_URL . '/promotions.php?created=1');
            exit;

        } catch (PDOException $e) {

            $errors[] = 'Không thể lưu chương trình khuyến mãi. Vui lòng kiểm tra lại dữ liệu hoặc kết nối cơ sở dữ liệu.';
        }
    }
}

// -----------------------------------------------------
// 7. HIEN THI GIAO DIEN
// -----------------------------------------------------

?>

<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="container-fluid py-4">

            <!-- TIEU DE -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">

                <div>
                    <h3 class="fw-bold mb-1">
                        Thêm chương trình khuyến mãi
                    </h3>

                    <p class="text-muted mb-0">
                        Nhập thông tin để tạo chương trình khuyến mãi mới.
                    </p>
                </div>

                <a href="<?= $escape(BASE_URL) ?>/promotions.php"
                   class="btn btn-outline-secondary">

                    <i class="bi bi-arrow-left"></i>
                    Quay lại

                </a>

            </div>

            <!-- THONG BAO LOI -->
            <?php if (!empty($errors)): ?>

                <div class="alert alert-danger">

                    <div class="fw-bold mb-2">
                        Không thể lưu chương trình khuyến mãi:
                    </div>

                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= $escape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>

                </div>

            <?php endif; ?>

            <!-- FORM THEM KHUYEN MAI -->
            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white py-3">

                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-tag me-2"></i>
                        Thông tin chương trình
                    </h5>

                </div>

                <div class="card-body p-4">

                    <form method="POST" action="" autocomplete="off">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= $escape($_SESSION['csrf_promotions']) ?>"
                        >

                        <div class="row g-3">

                            <!-- TEN CHUONG TRINH -->
                            <div class="col-md-12">

                                <label class="form-label fw-semibold">
                                    Tên chương trình
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="ten_chuong_trinh"
                                    class="form-control"
                                    maxlength="200"
                                    required
                                    value="<?= $escape($formData['ten_chuong_trinh']) ?>"
                                    placeholder="Nhập tên chương trình khuyến mãi"
                                >

                            </div>

                            <!-- MO TA -->
                            <div class="col-md-12">

                                <label class="form-label fw-semibold">
                                    Mô tả chương trình
                                </label>

                                <textarea
                                    name="mo_ta"
                                    class="form-control"
                                    rows="4"
                                    placeholder="Nhập nội dung mô tả chương trình..."
                                ><?= $escape($formData['mo_ta']) ?></textarea>

                            </div>

                            <!-- LOAI GIAM GIA -->
                            <div class="col-md-6">

                                <label class="form-label fw-semibold">
                                    Loại giảm giá
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="loai_giam_gia"
                                    id="loai_giam_gia"
                                    class="form-select"
                                    required
                                >

                                    <option
                                        value="PERCENT"
                                        <?= $formData['loai_giam_gia'] === 'PERCENT' ? 'selected' : '' ?>
                                    >
                                        Giảm theo phần trăm (%)
                                    </option>

                                    <option
                                        value="FIXED_AMOUNT"
                                        <?= $formData['loai_giam_gia'] === 'FIXED_AMOUNT' ? 'selected' : '' ?>
                                    >
                                        Giảm theo số tiền (VNĐ)
                                    </option>

                                </select>

                            </div>

                            <!-- GIA TRI GIAM -->
                            <div class="col-md-6">

                                <label class="form-label fw-semibold">
                                    Giá trị giảm
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="number"
                                    name="gia_tri_giam"
                                    id="gia_tri_giam"
                                    class="form-control"
                                    min="0.01"
                                    step="0.01"
                                    required
                                    value="<?= $escape($formData['gia_tri_giam']) ?>"
                                    placeholder="Nhập giá trị giảm"
                                >

                                <small class="text-muted" id="discountHint">
                                    Nhập mức giảm từ 0 đến 100%.
                                </small>

                            </div>

                            <!-- GIA TRI GIAM TOI DA -->
                            <div class="col-md-6" id="maxDiscountGroup">

                                <label class="form-label fw-semibold">
                                    Giá trị giảm tối đa (VNĐ)
                                </label>

                                <input
                                    type="number"
                                    name="gia_tri_giam_toi_da"
                                    id="gia_tri_giam_toi_da"
                                    class="form-control"
                                    min="0.01"
                                    step="0.01"
                                    value="<?= $escape($formData['gia_tri_giam_toi_da']) ?>"
                                    placeholder="Ví dụ: 500000"
                                >

                                <small class="text-muted">
                                    Chỉ áp dụng khi giảm theo phần trăm.
                                </small>

                            </div>

                            <!-- NGAY BAT DAU -->
                            <div class="col-md-6">

                                <label class="form-label fw-semibold">
                                    Ngày bắt đầu
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="datetime-local"
                                    name="ngay_bat_dau"
                                    class="form-control"
                                    required
                                    value="<?= $escape($formData['ngay_bat_dau']) ?>"
                                >

                            </div>

                            <!-- NGAY KET THUC -->
                            <div class="col-md-6">

                                <label class="form-label fw-semibold">
                                    Ngày kết thúc
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="datetime-local"
                                    name="ngay_ket_thuc"
                                    class="form-control"
                                    required
                                    value="<?= $escape($formData['ngay_ket_thuc']) ?>"
                                >

                            </div>

                            <!-- TRANG THAI -->
                            <div class="col-md-6">

                                <label class="form-label fw-semibold">
                                    Trạng thái
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="trang_thai"
                                    class="form-select"
                                    required
                                >

                                    <option
                                        value="DRAFT"
                                        <?= $formData['trang_thai'] === 'DRAFT' ? 'selected' : '' ?>
                                    >
                                        Bản nháp
                                    </option>

                                    <option
                                        value="ACTIVE"
                                        <?= $formData['trang_thai'] === 'ACTIVE' ? 'selected' : '' ?>
                                    >
                                        Đang hoạt động
                                    </option>

                                    <option
                                        value="INACTIVE"
                                        <?= $formData['trang_thai'] === 'INACTIVE' ? 'selected' : '' ?>
                                    >
                                        Ngừng hoạt động
                                    </option>

                                </select>

                            </div>

                        </div>

                        <hr class="my-4">

                        <!-- CAC NUT THAO TAC -->
                        <div class="d-flex flex-wrap gap-2 justify-content-end">

                            <a
                                href="<?= $escape(BASE_URL) ?>/promotions.php"
                                class="btn btn-light border"
                            >
                                Hủy
                            </a>

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                <i class="bi bi-save me-1"></i>
                                Lưu chương trình
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
    const maxDiscountGroup = document.getElementById('maxDiscountGroup');
    const maxDiscountInput = document.getElementById('gia_tri_giam_toi_da');
    const discountHint = document.getElementById('discountHint');

    function updateDiscountForm() {

        if (discountType.value === 'PERCENT') {

            maxDiscountGroup.style.display = '';

            maxDiscountInput.disabled = false;

            discountValue.max = '100';

            discountHint.textContent = 'Nhập mức giảm từ lớn hơn 0 đến 100%.';

        } else {

            maxDiscountGroup.style.display = 'none';

            maxDiscountInput.disabled = true;

            discountValue.removeAttribute('max');

            discountHint.textContent = 'Nhập số tiền muốn giảm (VNĐ).';

        }
    }

    discountType.addEventListener('change', updateDiscountForm);

    updateDiscountForm();

});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
