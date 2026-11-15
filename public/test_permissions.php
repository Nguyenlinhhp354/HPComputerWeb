
<?php

// =====================================================
// HP COMPUTER
// FILE: public/test_permissions.php
// KIEM TRA HOAT DONG PHAN QUYEN
// =====================================================

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/permission_check.php';

// Lay thong tin vai tro hien tai
$stmt = $pdo->prepare("
    SELECT ten_vai_tro
    FROM vai_tro
    WHERE ma_vai_tro = :ma_vai_tro
");

$stmt->execute([
    ':ma_vai_tro' => $_SESSION['ma_vai_tro']
]);

$tenVaiTro = $stmt->fetchColumn();

// Cac quyen dung de kiem tra
$danhSachKiemTra = [
    'dashboard.view'       => 'Xem tong quan',
    'users.view'           => 'Xem tai khoan',
    'products.view'        => 'Xem san pham',
    'inventory.import'     => 'Nhap kho',
    'warranty.receive'     => 'Tiep nhan bao hanh',
    'shop.cart.view'       => 'Xem gio hang',
    'reports.view'         => 'Xem bao cao'
];

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiểm tra phân quyền - HP Computer</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-5">

    <div class="card shadow-sm">

        <div class="card-body p-4">

            <h4 class="mb-3">Kiểm tra phân quyền HP Computer</h4>

            <p>
                <strong>Tài khoản:</strong>
                <?= htmlspecialchars($_SESSION['ten_dang_nhap'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </p>

            <p>
                <strong>Vai trò:</strong>
                <?= htmlspecialchars($tenVaiTro ?: 'Không xác định', ENT_QUOTES, 'UTF-8') ?>
            </p>

            <hr>

            <div class="table-responsive">

                <table class="table table-bordered align-middle">

                    <thead class="table-light">
                        <tr>
                            <th>Mã chức năng</th>
                            <th>Chức năng</th>
                            <th>Kết quả</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($danhSachKiemTra as $maQuyen => $tenChucNang): ?>

                            <?php $duocPhep = co_quyen($maQuyen); ?>

                            <tr>
                                <td>
                                    <?= htmlspecialchars($maQuyen, ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($tenChucNang, ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?php if ($duocPhep): ?>
                                        <span class="badge bg-success">
                                            Được phép
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">
                                            Không được phép
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <a href="../index.php" class="btn btn-secondary">
                Quay lại trang chủ
            </a>

        </div>

    </div>

</div>

</body>
</html>
