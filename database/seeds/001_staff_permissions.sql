-- Permission labels use ASCII because the existing quyen table uses latin1.
-- Add missing permissions and assignments without removing existing grants.
INSERT INTO quyen (ma_chuc_nang, ten_quyen, phan_he)
VALUES
('dashboard.view', 'Xem tong quan', 'dashboard'),
('users.view', 'Xem tai khoan', 'users'),
('products.view', 'Xem san pham', 'products'),
('products.create', 'Them san pham', 'products'),
('products.edit', 'Sua san pham', 'products'),
('products.delete', 'Xoa san pham', 'products'),
('categories.view', 'Xem danh muc', 'categories'),
('brands.view', 'Xem thuong hieu', 'brands'),
('suppliers.view', 'Xem nha cung cap', 'suppliers'),
('inventory.view', 'Xem kho', 'inventory'),
('customers.view', 'Xem khach hang', 'customers'),
('orders.view', 'Xem don hang', 'orders'),
('orders.approve', 'Duyet don hang', 'orders'),
('orders.update_status', 'Cap nhat trang thai don', 'orders'),
('orders.cancel', 'Huy don hang', 'orders'),
('invoices.create', 'Lap hoa don', 'invoices'),
('invoices.view', 'Xem va in hoa don', 'invoices'),
('promotions.view', 'Xem khuyen mai', 'promotions'),
('promotions.create', 'Tao khuyen mai', 'promotions'),
('promotions.edit', 'Sua khuyen mai', 'promotions'),
('warranty.view', 'Tra cuu bao hanh', 'warranty'),
('warranty.receive', 'Tiep nhan bao hanh', 'warranty'),
('warranty.update', 'Cap nhat xu ly bao hanh', 'warranty'),
('warranty.return', 'Xuat tra bao hanh', 'warranty'),
('reports.view', 'Xem bao cao ban hang', 'reports'),
('activity_logs.view', 'Xem nhat ky hoat dong', 'activity_logs')
ON DUPLICATE KEY UPDATE ma_chuc_nang = VALUES(ma_chuc_nang);

-- Admin receives the permissions defined by this seed.
INSERT INTO vai_tro_quyen (ma_vai_tro, ma_quyen)
SELECT 1, q.ma_quyen FROM quyen q
WHERE q.ma_chuc_nang IN (
 'dashboard.view', 'users.view', 'products.view', 'products.create',
 'products.edit', 'products.delete', 'categories.view', 'brands.view',
 'suppliers.view', 'inventory.view', 'customers.view', 'orders.view',
 'orders.approve', 'orders.update_status', 'orders.cancel',
 'invoices.create', 'invoices.view', 'promotions.view', 'promotions.create',
 'promotions.edit', 'warranty.view', 'warranty.receive', 'warranty.update',
 'warranty.return', 'reports.view', 'activity_logs.view'
)
ON DUPLICATE KEY UPDATE ma_quyen = VALUES(ma_quyen);

-- Role 3: sales staff. Viewing products does not grant editing rights.
INSERT INTO vai_tro_quyen (ma_vai_tro, ma_quyen)
SELECT 3, q.ma_quyen FROM quyen q
WHERE q.ma_chuc_nang IN (
 'dashboard.view', 'products.view', 'customers.view', 'orders.view',
 'orders.approve', 'orders.update_status', 'orders.cancel',
 'invoices.create', 'invoices.view', 'reports.view'
)
ON DUPLICATE KEY UPDATE ma_quyen = VALUES(ma_quyen);

-- Role 5: technical staff. Order access supports warranty lookup.
INSERT INTO vai_tro_quyen (ma_vai_tro, ma_quyen)
SELECT 5, q.ma_quyen FROM quyen q
WHERE q.ma_chuc_nang IN (
 'dashboard.view', 'products.view', 'orders.view', 'warranty.view',
 'warranty.receive', 'warranty.update', 'warranty.return'
)
ON DUPLICATE KEY UPDATE ma_quyen = VALUES(ma_quyen);
