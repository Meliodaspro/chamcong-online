# Chấm Công 0.2 — cập nhật admin

Giữ nguyên database hiện tại. Chạy **supabase/migrations/002_admin.sql** một lần trong Supabase SQL Editor. Không chạy lại schema.sql.

## Cấp admin đầu tiên

Sau khi chạy migration, thay email mẫu bằng email đăng nhập của bạn rồi chạy:

```sql
update public.profiles
set role = 'admin', is_active = true
where id = (select id from auth.users where lower(email) = lower('EMAIL_CUA_BAN'));

select p.display_name, p.role, p.is_active, u.email
from public.profiles p join auth.users u on u.id=p.id
where p.role='admin';
```

Nếu câu SELECT chưa thấy email đúng: kiểm tra lại email và tạo user trong Authentication → Users trước. Không có tài khoản admin/mật khẩu mặc định. Không tự động cấp admin cho user đầu tiên vì có thể cấp nhầm người.

Cài APK/IPA mới và đăng xuất/đăng nhập lại. Tài khoản admin mở **Quản trị Chấm Công**; nhân viên mở màn hình chấm công cá nhân.

## Các chức năng

- Tổng quan: nhân viên hoạt động, ca trong tháng, giờ hoàn tất, yêu cầu chờ duyệt, trạng thái hôm nay và 100 thay đổi quản trị gần nhất.
- Nhân viên: tìm kiếm, cập nhật tên, mã, điện thoại, chức vụ, cấp admin/employee và khóa/mở tài khoản. Tạo tài khoản email/mật khẩu mới và đặt lại mật khẩu tại Supabase Authentication. Chưa có tạo/xóa Auth user trực tiếp trong app. Khóa tài khoản giữ lại dữ liệu công.
- Bảng công: lọc tháng/nhân viên, xem giờ vào/ra, nhập/sửa công kèm lý do, xuất CSV UTF-8 dùng được trong Excel qua bảng chia sẻ của Android/iOS.
- Duyệt công: nhân viên gửi yêu cầu, admin duyệt hoặc từ chối. Duyệt thành công mới thay đổi bảng công; lỗi xung đột giữ nguyên yêu cầu.
- Nhật ký: mọi thay đổi nhân viên, chỉnh công và quyết định duyệt lưu người thực hiện, trước/sau, thời gian, lý do.
- Một ca mỗi ngày, giờ Việt Nam; giờ ra nhỏ hơn giờ vào được tính là ngày hôm sau. Giữ quy tắc chống nhiều ca chưa kết thúc cùng lúc.

Dữ liệu PHP/MySQL, tài khoản demo của website không tự chuyển sang Supabase. Bản này chưa bao gồm tính lương, ca linh hoạt, nghỉ phép, GPS/QR. Giao diện Flutter được bố trí cho điện thoại; không phải bản sao từng điểm ảnh của Bootstrap trên web.
