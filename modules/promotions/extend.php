
<?php
// =====================================================
// HP COMPUTER
// FILE: modules/promotions/extend.php
// CHUC NANG: GIA HAN CHUONG TRINH KHUYEN MAI
// CHI ADMIN DUOC THUC HIEN
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/permission_check.php';

// -----------------------------------------------------
// 1. KIEM TRA QUYEN
// -----------------------------------------------------

$maVaiTro = lay_vai_tro_hien_tai();

if ($maVaiTro !== 1) {
    http_response_code(403);
    exit('Chỉ Admin được phép gia hạn chương trình khuyến mãi.');
}

yeu_cau_quyen('promotions.extend');

// -----------------------------------------------------
// 2. KHOI TAO
// -----------------------------------------------------

$pageTitle = 'Gia hạn chương trình khuyến mãi';

$escape = static function ($value) {
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
};

$baseUrl = rtrim(BASE_URL, '/');
$listUrl = $baseUrl . '/promotions.php';

$errorMessage = '';
$successMessage = '';

$promotionId = (int) ($_GET['ma_chuong_trinh'] ?? $_POST['ma_chuong_trinh'] ?? 0);

if ($promotionId <= 0) {
    http_response_code(400);
    exit('Mã chương trình khuyến mãi không hợp lệ.');
}

// -----------------------------------------------------
// 3. KHOI TAO CSRF TOKEN
// -----------------------------------------------------

if (
    !isset($_SESSION['csrf_token'])
    || !is_string($_SESSION['csrf_token'])
    || $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

// -----------------------------------------------------
// 4. XU LY FORM GIA HAN
// -----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken)
        || !hash_equals($csrfToken, $postedToken)
    ) {
        $errorMessage = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } else {

        $ngayKetThucMoiInput = trim(
            $_POST['ngay_ket_thuc_moi'] ?? ''
        );

        $lyDoGiaHan = trim(
            $_POST['ly_do_gia_han'] ?? ''
        );

        // Kiểm tra ngày giờ
        $ngayKetThucMoi = DateTime::createFromFormat(
            '!Y-m-d\TH:i',
            $ngayKetThucMoiInput
        );

        $dateErrors = DateTime::getLastErrors();

        if (
            !$ngayKetThucMoi
            || (
                $dateErrors !== false
                && (
                    $dateErrors['warning_count'] > 0
                    || $dateErrors['error_count'] > 0
                )
            )
            || $ngayKetThucMoi->format('Y-m-d\TH:i') !== $ngayKetThucMoiInput
        ) {
            $errorMessage = 'Ngày kết thúc mới không hợp lệ.';
        } elseif (mb_strlen($lyDoGiaHan, 'UTF-8') > 2000) {
            $errorMessage = 'Lý do gia hạn không được vượt quá 2000 ký tự.';
        } else {

            try {

                $pdo->beginTransaction();

                // Khóa chương trình để tránh gia hạn đồng thời
                $sql = "
                    SELECT
                        ma_chuong_trinh,
                        ten_chuong_trinh,
                        ngay_bat_dau,
                        ngay_ket_thuc,
                        trang_thai
                    FROM chuong_trinh_khuyen_mai
                    WHERE ma_chuong_trinh = :ma_chuong_trinh
                    LIMIT 1
                    FOR UPDATE
                ";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    ':ma_chuong_trinh' => $promotionId
                ]);

                $promotion = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$promotion) {
                    throw new RuntimeException(
                        'Không tìm thấy chương trình khuyến mãi.'
                    );
                }

                // Chỉ gia hạn chương trình đang hoạt động
                if ($promotion['trang_thai'] !== 'ACTIVE') {
                    throw new RuntimeException(
                        'Chỉ được gia hạn chương trình đang hoạt động.'
                    );
                }

                $ngayKetThucCu = new DateTime(
                    $promotion['ngay_ket_thuc']
                );

                // Ngày mới phải lớn hơn ngày kết thúc hiện tại
                if ($ngayKetThucMoi <= $ngayKetThucCu) {
                    throw new RuntimeException(
                        'Ngày kết thúc mới phải lớn hơn ngày kết thúc hiện tại.'
                    );
                }

                // Chuẩn hóa ngày giờ lưu vào CSDL
                $ngayKetThucCuSQL = $ngayKetThucCu->format(
                    'Y-m-d H:i:s'
                );

                $ngayKetThucMoiSQL = $ngayKetThucMoi->format(
                    'Y-m-d H:i:s'
                );

                $maNguoiThucHien = (int) $_SESSION['ma_nguoi_dung'];

                if ($maNguoiThucHien <= 0) {
                    throw new RuntimeException(
                        'Không xác định được tài khoản thực hiện.'
                    );
                }

                // 1. Lưu lịch sử gia hạn
                $sqlHistory = "
                    INSERT INTO lich_su_gia_han_khuyen_mai (
                        ma_chuong_trinh,
                        ngay_ket_thuc_cu,
                        ngay_ket_thuc_moi,
                        ly_do_gia_han,
                        ma_nguoi_thuc_hien
                    ) VALUES (
                        :ma_chuong_trinh,
                        :ngay_ket_thuc_cu,
                        :ngay_ket_thuc_moi,
                        :ly_do_gia_han,
                        :ma_nguoi_thuc_hien
                    )
                ";

                $stmtHistory = $pdo->prepare($sqlHistory);

                $stmtHistory->execute([
                    ':ma_chuong_trinh' => $promotionId,
                    ':ngay_ket_thuc_cu' => $ngayKetThucCuSQL,
                    ':ngay_ket_thuc_moi' => $ngayKetThucMoiSQL,
                    ':ly_do_gia_han' => $lyDoGiaHan !== ''
                        ? $lyDoGiaHan
                        : null,
                    ':ma_nguoi_thuc_hien' => $maNguoiThucHien
                ]);

                // 2. Cập nhật ngày kết thúc chương trình
                $sqlUpdate = "
                    UPDATE chuong_trinh_khuyen_mai
                    SET
                        ngay_ket_thuc = :ngay_ket_thuc,
                        ngay_cap_nhat = CURRENT_TIMESTAMP
                    WHERE ma_chuong_trinh = :ma_chuong_trinh
                ";

                $stmtUpdate = $pdo->prepare($sqlUpdate);

                $stmtUpdate->execute([
                    ':ngay_ket_thuc' => $ngayKetThucMoiSQL,
                    ':ma_chuong_trinh' => $promotionId
                ]);

                // Hoàn tất cả hai thao tác
                $pdo->commit();

                // Chuyển về danh sách
                header(
                    'Location: ' . $listUrl . '?extended=1',
                    true,
                    303
                );

                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'HP COMPUTER - PROMOTION EXTEND ERROR: '
                    . $e->getMessage()
                );

                if ($e instanceof RuntimeException) {
                    $errorMessage = $e->getMessage();
                } else {
                    $errorMessage = 'Không thể gia hạn chương trình. Vui lòng thử lại.';
                }
            }
        }
    }
}

// -----------------------------------------------------
// 5. LAY THONG TIN CHUONG TRINH
// -----------------------------------------------------

try {

    $sql = "
        SELECT
            ma_chuong_trinh,
            ten_chuong_trinh,
            ngay_bat_dau,
            ngay_ket_thuc,
            trang_thai
        FROM chuong_trinh_khuyen_mai
        WHERE ma_chuong_trinh = :ma_chuong_trinh
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':ma_chuong_trinh' => $promotionId
    ]);

    $promotion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promotion) {
        http_response_code(404);
        exit('Không tìm thấy chương trình khuyến mãi.');
    }

} catch (Throwable $e) {

    error_log(
        'HP COMPUTER - PROMOTION LOAD ERROR: '
        . $e->getMessage()
    );

    http_response_code(500);
    exit('Không thể tải thông tin chương trình khuyến mãi.');
}

// -----------------------------------------------------
// 6. DINH DANG NGAY
// -----------------------------------------------------

$ngayKetThucHienTai = new DateTime(
    $promotion['ngay_ket_thuc']
);

$ngayKetThucHienTaiInput = $ngayKetThucHienTai->format(
    'Y-m-d\TH:i'
);

$ngayKetThucHienTaiDisplay = $ngayKetThucHienTai->format(
    'd/m/Y H:i'
);

// -----------------------------------------------------
// 7. GIAO DIEN
// -----------------------------------------------------

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';

?>

<div class="admin-layout">

    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="container-fluid py-3">

            <!-- TIÊU ĐỀ -->
            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="bi bi-calendar-plus me-2"></i>
                        Gia hạn khuyến mãi
                    </h4>

                    <p class="text-muted mb-0">
                        Kéo dài thời gian áp dụng chương trình khuyến mãi.
                    </p>
                </div>

                <a href="<?= $escape($listUrl) ?>"
                   class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Quay lại
                </a>

            </div>

            <!-- THÔNG BÁO LỖI -->
            <?php if ($errorMessage !== ''): ?>

                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?= $escape($errorMessage) ?>
                </div>

            <?php endif; ?>

            <div class="row justify-content-center">

                <div class="col-xl-8 col-lg-10">

                    <div class="card border-0 shadow-sm">

                        <div class="card-header bg-white py-3">

                            <h5 class="fw-bold mb-0">
                                Thông tin gia hạn
                            </h5>

                        </div>

                        <div class="card-body">

                            <!-- THÔNG TIN CHƯƠNG TRÌNH -->
                            <div class="bg-light rounded p-3 mb-4">

                                <div class="mb-2">
                                    <span class="text-muted">
                                        Mã chương trình:
                                    </span>

                                    <strong>
                                        KM<?= str_pad(
                                            (string) $promotionId,
                                            4,
                                            '0',
                                            STR_PAD_LEFT
                                        ) ?>
                                    </strong>
                                </div>

                                <div class="mb-2">
                                    <span class="text-muted">
                                        Tên chương trình:
                                    </span>

                                    <strong>
                                        <?= $escape($promotion['ten_chuong_trinh']) ?>
                                    </strong>
                                </div>

                                <div class="mb-2">
                                    <span class="text-muted">
                                        Ngày bắt đầu:
                                    </span>

                                    <?= $escape(
                                        date(
                                            'd/m/Y H:i',
                                            strtotime($promotion['ngay_bat_dau'])
                                        )
                                    ) ?>
                                </div>

                                <div>
                                    <span class="text-muted">
                                        Ngày kết thúc hiện tại:
                                    </span>

                                    <strong class="text-danger">
                                        <?= $escape($ngayKetThucHienTaiDisplay) ?>
                                    </strong>
                                </div>

                            </div>

                            <?php if ($promotion['trang_thai'] !== 'ACTIVE'): ?>

                                <div class="alert alert-warning mb-0">
                                    <i class="bi bi-exclamation-circle me-2"></i>
                                    Chỉ chương trình đang hoạt động mới được gia hạn.
                                    Trạng thái hiện tại:
                                    <strong><?= $escape($promotion['trang_thai']) ?></strong>.
                                </div>

                            <?php else: ?>

                                <form method="POST"
                                      action="<?= $escape(
                                          $baseUrl
                                          . '/modules/promotions/extend.php?ma_chuong_trinh='
                                          . $promotionId
                                      ) ?>">

                                    <input type="hidden"
                                           name="csrf_token"
                                           value="<?= $escape($csrfToken) ?>">

                                    <input type="hidden"
                                           name="ma_chuong_trinh"
                                           value="<?= $promotionId ?>">

                                    <!-- NGÀY KẾT THÚC MỚI -->
                                    <div class="mb-3">

                                        <label for="ngay_ket_thuc_moi"
                                               class="form-label fw-semibold">

                                            Ngày kết thúc mới
                                            <span class="text-danger">*</span>

                                        </label>

                                        <input type="datetime-local"
                                               class="form-control"
                                               id="ngay_ket_thuc_moi"
                                               name="ngay_ket_thuc_moi"
                                               value="<?= $escape(
                                                   $_POST['ngay_ket_thuc_moi']
                                                   ?? ''
                                               ) ?>"
                                               min="<?= $escape(
                                                   $ngayKetThucHienTaiInput
                                               ) ?>"
                                               step="60"
                                               required>

                                        <div class="form-text">
                                            Ngày kết thúc mới phải lớn hơn
                                            ngày kết thúc hiện tại.
                                        </div>

                                    </div>

                                    <!-- LÝ DO GIA HẠN -->
                                    <div class="mb-4">

                                        <label for="ly_do_gia_han"
                                               class="form-label fw-semibold">

                                            Lý do gia hạn

                                        </label>

                                        <textarea class="form-control"
                                                  id="ly_do_gia_han"
                                                  name="ly_do_gia_han"
                                                  rows="4"
                                                  maxlength="2000"
                                                  placeholder="Nhập lý do gia hạn chương trình..."><?= $escape(
                                                      $_POST['ly_do_gia_han']
                                                      ?? ''
                                                  ) ?></textarea>

                                        <div class="form-text">
                                            Lý do gia hạn sẽ được lưu trong
                                            lịch sử để tra cứu sau này.
                                        </div>

                                    </div>

                                    <!-- XÁC NHẬN -->
                                    <div class="alert alert-info">

                                        <i class="bi bi-info-circle me-2"></i>

                                        Hệ thống sẽ lưu lại ngày kết thúc cũ,
                                        ngày kết thúc mới và tài khoản thực hiện.
                                        Lịch sử gia hạn trước đó không bị thay đổi.

                                    </div>

                                    <div class="d-flex justify-content-end gap-2">

                                        <a href="<?= $escape($listUrl) ?>"
                                           class="btn btn-outline-secondary">
                                            Hủy
                                        </a>

                                        <button type="submit"
                                                class="btn btn-primary"
                                                onclick="return confirm('Bạn có chắc chắn muốn gia hạn chương trình khuyến mãi này?')">

                                            <i class="bi bi-calendar-plus me-1"></i>
                                            Xác nhận gia hạn

                                        </button>

                                    </div>

                                </form>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
