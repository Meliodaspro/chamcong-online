# Chấm Công Android + Supabase — bản thử nghiệm 0.1

App Flutter riêng, không cần hosting PHP. Các trang PHP/MySQL hiện có không bị thay đổi và chưa đồng bộ với app mới.

## Có gì trong bản đầu?

- Đăng nhập email/mật khẩu bằng Supabase Auth; tài khoản do quản lý tạo.
- Chấm vào/ra bằng giờ server, múi giờ Việt Nam.
- Một ca mỗi ngày; ca qua đêm thuộc ngày bắt đầu ca.
- Bảng công tháng, số ca hoàn tất, tổng giờ thực tế (chưa trừ giờ nghỉ).
- Nhân viên chỉ xem công của mình. Không cho client sửa trực tiếp giờ hoặc trạng thái tài khoản.
- Lưu phiên bằng bộ nhớ mã hóa Android. Lưu mã yêu cầu để thử lại an toàn khi mất phản hồi mạng.
- Không có GPS, QR, tính lương, tự sửa công, giao diện admin hoặc nhập dữ liệu MySQL cũ trong phiên bản này.
- App cần mạng. Không ghi nhận công offline và không giả báo thành công.

## Cấu hình đã điền sẵn

Bản mã nguồn này đã điền Project URL và publishable key của dự án bạn cung cấp. Khi build, app mở thẳng màn hình đăng nhập. Không có mật khẩu tài khoản hoặc database trong mã. Các bước tạo dự án và schema bên dưới chỉ dành cho dự án mới; bạn đã chạy schema thành công thì không chạy lại.

## Bước 1 — Tạo Supabase miễn phí trên điện thoại

1. Mở https://supabase.com/dashboard bằng Chrome. Đăng ký/đăng nhập, có thể dùng tài khoản GitHub.
2. Tạo organization gói **Free**, rồi tạo project tên `cham-cong`.
3. Chọn khu vực Singapore nếu có. Tạo và tự lưu mật khẩu database; không gửi mật khẩu vào chat hoặc commit lên GitHub.
4. Đợi dự án sẵn sàng. Nếu giao diện khó thao tác, bật **Trang web cho máy tính** trong Chrome.

## Bước 2 — Tạo database

1. Mở file [`supabase/schema.sql`](supabase/schema.sql), sao chép toàn bộ.
2. Trong Supabase mở **SQL Editor → New query**, dán và bấm **Run**.
3. Chỉ chạy một lần trên dự án mới. Script không phải migration cho database cũ.
4. Giữ Data API bật, schema `public` được expose. Script tự thiết lập quyền bảng và quyền gọi hàm.
5. Không chạy các file `supabase/tests/*.sql` trên dự án thật: đây là test harness cho CI.

## Bước 3 — Cấp tài khoản

1. Vào **Authentication → Sign In / Providers → Email**, bật đăng nhập email/mật khẩu.
2. Tắt **Allow new users to sign up** để người ngoài không tự đăng ký vào hệ thống. Tên menu có thể thay đổi.
3. Vào **Authentication → Users → Add user → Create new user**, nhập email và mật khẩu mạnh.
4. Xác nhận email của tài khoản do quản lý tạo (tùy chọn Auto Confirm User nếu có).
5. Trigger sẽ tạo hồ sơ trong `public.profiles`. Vào Table Editor đổi `display_name` cho từng người.
6. Để ngừng quyền chấm công, đặt `profiles.is_active = false`. Nhân viên không tự đổi được trường này.
7. Quên mật khẩu: quản lý đặt lại từ dashboard. Bản đầu chưa có luồng khôi phục mật khẩu trong app.

Không có tài khoản admin mặc định hoặc mật khẩu demo. Quản lý sử dụng dashboard Supabase ở bản đầu; chỉ chủ dự án/người được ủy quyền có quyền quản trị.

## Bước 4 — Lấy cấu hình công khai

Trong trang **Connect** hoặc **Project Settings → API / API Keys**, lấy:

- **Project URL**: dạng `https://PROJECT_REF.supabase.co`.
- **Publishable key**: dạng `sb_publishable_...`; hoặc legacy **anon** key.

Hai giá trị này dùng ở màn hình thiết lập của APK. Không dùng `sb_secret_...`, `service_role`, mật khẩu database hoặc personal access token. Bảo vệ dữ liệu bằng RLS và các hàm SQL đã cung cấp; không dựa vào việc giấu publishable key.

## Bước 5 — Lấy APK từ GitHub bằng điện thoại

1. Mở repository → **Actions → Android APK**.
2. Chọn lần chạy của nhánh `feature/flutter-supabase`. Khi có dấu tích xanh, kéo tới **Artifacts**.
3. Tải `cham-cong-android-test`, giải nén và mở `app-debug.apk` trên Android.
4. Cho phép trình duyệt/trình quản lý file cài ứng dụng khi Android yêu cầu.
5. Mở **Chấm Công**, dán URL và publishable/anon key, rồi đăng nhập tài khoản đã tạo.

Workflow chạy kiểm tra database, Flutter analyze và Flutter test trước khi build APK. Nếu Actions chưa chạy, xem trạng thái workflow/quyền Actions của repo. `workflow_dispatch` chỉ xuất hiện khi workflow có trên nhánh mặc định; bản trên nhánh phát triển tự chạy khi push/PR phù hợp.

**APK này là bản debug để kiểm thử**, chưa phải bản phát hành Google Play. Khóa ký debug của GitHub runner có thể thay đổi giữa các lần build; nếu Android từ chối cập nhật, gỡ bản thử cũ rồi cài lại và nhập lại cấu hình. Dữ liệu đã ghi trên Supabase không bị xóa, nhưng yêu cầu chưa xác nhận đang lưu trên máy có thể mất khi gỡ. Trước khi gỡ, hoàn tất “Kiểm tra / gửi lại”. Bản phát hành lâu dài cần khóa ký cố định được lưu bằng GitHub Secrets.

## Cách sử dụng

- Bấm **Chấm vào** → xác nhận → đợi thông báo server đã ghi nhận.
- Bấm **Chấm ra** khi kết thúc ca. Không cho chấm vào lần thứ hai sau khi đã hoàn tất ngày đó.
- Nếu mất phản hồi, dùng **Kiểm tra / gửi lại**. Cùng mã yêu cầu trả lại kết quả cũ, không chấm trùng.
- **Bảng công**: chuyển tháng bằng hai mũi tên, kéo xuống để tải lại.
- Ca chưa ra không cộng vào tổng giờ hoàn tất; ca qua đêm tính về tháng bắt đầu.
- Nút bấm không xác minh vị trí hoặc việc có mặt tại nơi làm. Chưa dùng để tự động tính lương.

## Kiểm thử trước khi dùng thật

Dùng hai tài khoản thử, xác nhận mỗi tài khoản chỉ thấy công của mình. Kiểm tra chấm vào/ra, bấm lặp, mất mạng khi bấm, mở lại app, ca qua đêm, chuyển tháng, vô hiệu hóa tài khoản và đăng xuất. Không có dữ liệu công thật đi kèm.

## Phát triển khi có máy tính

Cần Flutter stable, Android SDK, Java và Python 3. Từ gốc repo:

```bash
bash tool/prepare_android.sh
cd .generated/android_app
flutter analyze
flutter test
flutter run
```

Android scaffold được sinh bằng Flutter, mã app nằm ở `mobile/`; không sửa bản sinh rồi quên chuyển thay đổi về nguồn. Kiểm tra phiên bản Flutter trong log Actions. Dependency trực tiếp Supabase được ghim; các dependency khác resolve khi build, `pubspec.lock` nằm trong thư mục sinh. Nên ghim toolchain và commit lockfile khi ổn định bản phát hành.

## Miễn phí và sao lưu

Gói Free có giới hạn và có thể tạm dừng khi không hoạt động theo chính sách Supabase. Theo dõi Usage; tự xuất/sao lưu dữ liệu định kỳ. Không bật gói trả phí nếu chưa chủ động quyết định. Không có cam kết khả dụng 24/7 trong bản thử.
