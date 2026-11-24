-- Dữ liệu mẫu cho database ezyro: 1 danh mục, 2 sản phẩm, 1 khách, 1 đơn.
-- Chọn đúng database trong phpMyAdmin trước khi import.
-- Không sửa dữ liệu đã có; không tạo tài khoản, thanh toán hoặc thay đổi tồn kho.
-- Chạy lần lượt, không chạy đồng thời file này ở hai cửa sổ.
SET NAMES utf8mb4;
START TRANSACTION;

INSERT INTO danh_muc (ten_danh_muc, mo_ta, trang_thai)
SELECT '[DEMO-NVBH] Thiết bị máy tính', 'Dữ liệu mẫu thử quản lý đơn hàng', 'ACTIVE'
WHERE NOT EXISTS (
    SELECT 1 FROM danh_muc WHERE ten_danh_muc = '[DEMO-NVBH] Thiết bị máy tính'
);
SET @demo_category = (
    SELECT ma_danh_muc FROM danh_muc
    WHERE ten_danh_muc = '[DEMO-NVBH] Thiết bị máy tính'
    ORDER BY ma_danh_muc LIMIT 1
);

INSERT INTO san_pham (ma_danh_muc, ma_sku, ten_san_pham, gia_ban, thoi_han_bao_hanh, trang_thai)
SELECT @demo_category, 'DEMO-NVBH-LAPTOP-001', '[DEMO] Laptop học tập', 9500000, 12, 'ACTIVE'
WHERE NOT EXISTS (SELECT 1 FROM san_pham WHERE ma_sku = 'DEMO-NVBH-LAPTOP-001');

INSERT INTO san_pham (ma_danh_muc, ma_sku, ten_san_pham, gia_ban, thoi_han_bao_hanh, trang_thai)
SELECT @demo_category, 'DEMO-NVBH-MOUSE-001', '[DEMO] Chuột không dây', 250000, 6, 'ACTIVE'
WHERE NOT EXISTS (SELECT 1 FROM san_pham WHERE ma_sku = 'DEMO-NVBH-MOUSE-001');

SET @demo_laptop = (SELECT ma_san_pham FROM san_pham WHERE ma_sku = 'DEMO-NVBH-LAPTOP-001');
SET @demo_mouse = (SELECT ma_san_pham FROM san_pham WHERE ma_sku = 'DEMO-NVBH-MOUSE-001');

INSERT INTO khach_hang (ten_khach_hang, so_dien_thoai, email, dia_chi)
SELECT '[DEMO] Khách thử đơn hàng', '0000000000', 'demo.nvbh@example.invalid', '[DEMO] Hải Phòng'
WHERE NOT EXISTS (SELECT 1 FROM khach_hang WHERE email = 'demo.nvbh@example.invalid');
SET @demo_customer = (
    SELECT ma_khach_hang FROM khach_hang WHERE email = 'demo.nvbh@example.invalid'
    ORDER BY ma_khach_hang LIMIT 1
);
-- Nếu chưa có nhân viên bán hàng đang hoạt động thì người tạo để NULL.
SET @demo_staff = (
    SELECT ma_nguoi_dung FROM nguoi_dung
    WHERE ma_vai_tro = 3 AND trang_thai = 'ACTIVE'
    ORDER BY ma_nguoi_dung LIMIT 1
);

-- Chỉ thêm chi tiết/lịch sử khi đơn được tạo mới trong lần chạy này.
-- Import lại không làm sống lại đơn đã hủy hoặc ghi đè đơn đã chỉnh sửa.
SET @demo_new_order = NOT EXISTS (
    SELECT 1 FROM don_hang WHERE ma_so_don_hang = 'DH-DEMO-NVBH-001'
);
INSERT INTO don_hang (
    ma_khach_hang, ma_nguoi_tao, ma_so_don_hang,
    ten_nguoi_nhan, sdt_nguoi_nhan, dia_chi_giao_hang,
    tam_tinh, tien_giam_gia, phi_van_chuyen, tong_tien,
    trang_thai_don_hang, trang_thai_thanh_toan, ghi_chu
)
SELECT @demo_customer, @demo_staff, 'DH-DEMO-NVBH-001',
    '[DEMO] Khách thử đơn hàng', '0000000000', '[DEMO] Hải Phòng',
    10000000, 0, 30000, 10030000, 'PENDING', 'UNPAID',
    '[DEMO-NVBH] Chỉ dùng thử xem/duyệt đơn, chưa ghi nhận xuất kho.'
WHERE @demo_new_order;
SET @demo_order = (SELECT ma_don_hang FROM don_hang WHERE ma_so_don_hang = 'DH-DEMO-NVBH-001');

INSERT INTO chi_tiet_don_hang (
    ma_don_hang, ma_san_pham, ten_san_pham, so_luong,
    don_gia, tien_giam_gia, thanh_tien, thoi_han_bao_hanh
)
SELECT @demo_order, @demo_laptop, '[DEMO] Laptop học tập', 1, 9500000, 0, 9500000, 12
WHERE @demo_new_order;

INSERT INTO chi_tiet_don_hang (
    ma_don_hang, ma_san_pham, ten_san_pham, so_luong,
    don_gia, tien_giam_gia, thanh_tien, thoi_han_bao_hanh
)
SELECT @demo_order, @demo_mouse, '[DEMO] Chuột không dây', 2, 250000, 0, 500000, 6
WHERE @demo_new_order;

INSERT INTO lich_su_trang_thai_don_hang (
    ma_don_hang, ma_nguoi_thuc_hien, trang_thai_cu, trang_thai_moi, ghi_chu
)
SELECT @demo_order, @demo_staff, NULL, 'PENDING', '[DEMO-NVBH] Tạo đơn mẫu'
WHERE @demo_new_order;

COMMIT;

-- Kết quả: 2 dòng hàng, tiền hàng 10.000.000, vận chuyển 30.000, tổng 10.030.000.
SELECT dh.ma_so_don_hang, dh.trang_thai_don_hang, dh.trang_thai_thanh_toan,
       dh.tam_tinh, dh.phi_van_chuyen, dh.tong_tien,
       COUNT(ct.ma_chi_tiet_don_hang) AS so_dong_hang,
       SUM(ct.thanh_tien) AS tong_chi_tiet
FROM don_hang dh
LEFT JOIN chi_tiet_don_hang ct ON ct.ma_don_hang = dh.ma_don_hang
WHERE dh.ma_so_don_hang = 'DH-DEMO-NVBH-001'
GROUP BY dh.ma_don_hang, dh.ma_so_don_hang, dh.trang_thai_don_hang,
         dh.trang_thai_thanh_toan, dh.tam_tinh, dh.phi_van_chuyen, dh.tong_tien;
