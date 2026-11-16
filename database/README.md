# Phân quyền nhân viên

Chạy trong Terminal PowerShell ở thư mục dự án (MySQL của XAMPP cần đang bật):

```powershell
& D:\xampp\php\php.exe scripts/seed_staff_permissions.php
```

Script dùng kết nối trong `config/database.php`, kiểm tra tên vai trò 1, 3, 5,
và chạy toàn bộ seed trong transaction. Có thể chạy lại mà không tạo quyền
trùng hoặc xóa quyền đã được cấp. Script chỉ chạy qua CLI.

- Admin (1): 26 quyền được định nghĩa trong seed.
- Nhân viên bán hàng (3): 10 quyền xem tổng quan, sản phẩm, khách hàng,
  quản lý đơn, lập/xem hóa đơn và xem báo cáo.
- Nhân viên kỹ thuật (5): 7 quyền xem tổng quan, sản phẩm, đơn hàng để
  tra cứu và tiếp nhận/cập nhật/xuất trả bảo hành.
- Vai trò khác: giữ nguyên phân quyền hiện có.

Tên quyền dùng chữ không dấu vì bảng `quyen` hiện dùng charset `latin1`.
Quyền thao tác không tự tạo ra module nghiệp vụ. Menu có `href="#"` vẫn
chờ triển khai các bước tiếp theo. Các trang xử lý mới phải kiểm tra quyền
trước khi xuất HTML và trước khi cập nhật dữ liệu, ví dụ:

```php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/permission_check.php';
yeu_cau_quyen('orders.approve');
```

Kiểm tra bằng cách đăng nhập tài khoản tương ứng rồi mở
`/HPComputerWeb/public/test_permissions.php`. Nhân viên không được truy cập
trang quản lý tài khoản hoặc trang tạo/sửa/xóa sản phẩm, khuyến mãi qua URL.
Quyền tra cứu đơn của kỹ thuật không bao gồm duyệt/hủy/cập nhật trạng thái đơn.

Commit các file SQL/PHP để chia sẻ mã nguồn; database trên máy khác cần
chạy lại lệnh seed ở trên sau khi lấy mã nguồn.
