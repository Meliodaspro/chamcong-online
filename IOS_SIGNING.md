# Chấm Công iOS — IPA chưa ký

## File tải về

Giải nén artifact GitHub `cham-cong-ios-unsigned`. Bên trong có:
- `ChamCong-unsigned.ipa`: app iPhone bản Release, chưa ký.
- `SHA256.txt`: mã kiểm tra file.

Bundle ID gốc: `com.wusdev.chamcong`. Yêu cầu iOS 15 trở lên. App kết nối cùng Supabase với Android. Không có mật khẩu tài khoản hoặc chứng chỉ ký trong mã.

## Ký lại và cài

Dùng công cụ ký bạn tin cậy, chẳng hạn ESign, với chứng chỉ chứa private key (.p12) và provisioning profile phù hợp. Việc cài thành công còn phụ thuộc chứng chỉ, profile, thiết bị và công cụ ký.

1. Nhập IPA và bộ chứng chỉ/profile của bạn vào công cụ ký.
2. Bundle ID khi ký phải phù hợp với App ID trong profile. Nếu đổi Bundle ID, giữ nguyên ID đó cho các lần cập nhật để tránh tách dữ liệu Keychain.
3. Ký cả executable chính và các framework bên trong; để công cụ ký cập nhật entitlements theo profile hợp lệ. Không giữ nguyên Team ID hoặc keychain access group của nhóm phát triển khác.
4. Với Ad Hoc/development, UDID iPhone phải được đăng ký trong provisioning profile.
5. Cài bản đã ký, mở app và đăng nhập tài khoản đã tạo tại Supabase Authentication.

Không gửi file .p12, mật khẩu private key hay service_role key vào chat hoặc commit lên repo. Không cần những thông tin này để build IPA chưa ký.

## Kiểm tra trên iPhone

- Đăng nhập thành công, đóng/mở lại app còn phiên.
- Chấm vào/ra, thử lại khi mất mạng, xem bảng công tháng.
- Đăng xuất và đăng nhập lại.

Nếu báo lỗi lưu phiên/Keychain, kiểm tra cấu hình ký và keychain entitlements trước. Build thành công không thay thế kiểm thử trên thiết bị thật. IPA chưa ký không cài trực tiếp và không phải gói upload TestFlight/App Store.

## Build lại

Workflow `.github/workflows/ios.yml` chạy trên macOS, dùng Flutter 3.47.9 và Xcode. Lệnh build là `flutter build ios --release --no-codesign`, sau đó đóng gói `Payload/Runner.app` thành IPA. Scaffold iOS được sinh tại runner bằng `tool/prepare_ios.sh`.
