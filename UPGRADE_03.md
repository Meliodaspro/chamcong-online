# Chấm Công 0.3

## Giao diện và chức năng
- Giao diện sáng, xanh dương, bố cục theo chiều rộng điện thoại, kiểm thử ở 360px và cỡ chữ 120%.
- Tổng quan hôm nay độc lập với tháng đang lọc. Nhật ký đầy đủ có trang riêng.
- Tạo tài khoản nhân viên bằng họ tên, email, mật khẩu ngay trong app. Tài khoản mới luôn là employee; không đăng xuất admin khi tạo.
- Mã nhân viên tự sinh từ PostgreSQL dạng NV000001, giữ nguyên mã cũ. Client không sửa được mã.
- Nhân viên tự đổi mật khẩu; admin đặt lại mật khẩu tài khoản employee. Mật khẩu không lưu trong bảng công hoặc nhật ký.
- Lọc nhân viên hoạt động/khóa; lọc công đã hoàn tất/thiếu giờ ra; xem lịch sử yêu cầu duyệt/từ chối.
- Chọn ngày, giờ bằng hộp chọn trên điện thoại; Xuất Excel (.xlsx) và CSV theo bộ lọc đang xem; nhân viên cũng xuất được công cá nhân.

## Cập nhật Supabase một lần

Dự án đã chạy schema.sql và 002_admin.sql: chỉ chạy [mã SQL cập nhật](supabase/migrations/003_employee_accounts.sql) trong SQL Editor. Mở mã trên GitHub rồi sao chép, không cần tải file SQL về điện thoại. Không chạy lại các file cũ. Giữ nguyên quyền admin hiện tại.

### Triển khai dịch vụ tạo tài khoản

Ưu tiên kết nối Supabase trong ChatGPT để hỗ trợ triển khai. Nếu tự làm trên điện thoại:
1. Supabase → Edge Functions → Deploy a new function → Via Editor.
2. Tạo function tên chính xác **admin-employees**.
3. Dán bản một file trong [mã server dạng văn bản](admin-employees-deploy.txt) vào index.ts và Deploy.
4. Trong phần cấu hình function, tắt kiểm tra JWT kiểu legacy của gateway (Verify JWT). Hàm tự xác minh user JWT bằng Supabase Auth và kiểm tra admin còn hoạt động cho mỗi yêu cầu. Không bỏ đoạn xác minh trong hàm.
5. Dùng app 0.3 → Nhân viên → Thêm nhân viên. Tạo tài khoản thử của bạn, đăng nhập và kiểm tra trước khi cấp cho người khác.

SUPABASE_URL và SUPABASE_SERVICE_ROLE_KEY lấy từ môi trường server của Edge Functions; không đưa secret/service_role key vào app, GitHub hoặc chat.

Nếu triển khai bằng CLI: `supabase functions deploy admin-employees --project-ref dakfmywklmcynwavpepu --no-verify-jwt`.

Nếu mạng gián đoạn sau khi tạo tài khoản, tải lại danh sách trước khi tạo lại. Email không được trùng. Tài khoản mới có quyền nhân viên và email được xác nhận bởi admin; quản lý phải kiểm tra email người nhận và gửi thông tin đăng nhập qua kênh riêng.

## Phạm vi
Chưa tự suy đoán quy tắc tính lương, tăng ca, phép năm, GPS/QR và ca linh hoạt. Đây là những phần cần cấu hình theo nơi làm việc, không tự tính thành tiền từ tổng giờ thô.
