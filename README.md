# 🏢 Hệ Thống Chấm Công Authentication 

Hệ thống chấm công đa người dùng với authentication và phân quyền hoàn chỉnh.

## 🔑 **Tính năng Authentication**

### **2 Loại Tài Khoản:**
- **👤 Employee**: Đăng nhập và tự chấm công của mình
- **🔧 Admin**: Quản lý tất cả nhân viên + xuất báo cáo tổng hợp

### **Bảo mật:**
- Mật khẩu được hash bằng `password_hash()` PHP
- Session management với timeout
- Phân quyền strict (employee chỉ xem được của mình)
- Protection cho file config và includes

## 🎯 **Tài Khoản Demo**

### **Admin:**
- **Username**: `admin`
- **Password**: `admin123`

### **Nhân viên:**
- **Username**: `NV001` / **Password**: `NV001`
- **Username**: `NV002` / **Password**: `NV002`
- **Username**: `NV003` / **Password**: `NV003`
- **Username**: `NV004` / **Password**: `NV004`
- **Username**: `NV005` / **Password**: `NV005`
- **Username**: `NV006` / **Password**: `NV006`

## 📊 **Chức Năng Employee**

### **Dashboard Cá Nhân:**
- Chấm công vào/ra cho chính mình
- Xem thống kê tháng cá nhân
- Đồng hồ thời gian thực
- Lịch sử chấm công gần đây

### **Lịch Sử:**
- Xem lịch sử chấm công của chính mình
- Lọc theo tháng
- Pagination

### **Nhập Công Thủ Công:**
- Chỉnh sửa/thêm chấm công của chính mình
- Calendar view tháng

## 🛠️ **Chức Năng Admin**

### **Admin Dashboard:**
- Thống kê tổng quan toàn hệ thống
- Xem trạng thái tất cả nhân viên hôm nay
- Hoạt động chấm công gần đây
- Quick actions

### **Quản Lý Nhân Viên:**
- CRUD nhân viên
- Tạo/xóa tài khoản user
- Quản lý trạng thái active/inactive

### **Báo Cáo & Export:**
- Xem lịch sử của tất cả nhân viên
- Xuất Excel theo nhân viên hoặc tất cả
- Lọc theo tháng/khoảng thời gian

### **Nhập Công Thủ Công:**
- Chỉnh sửa chấm công cho bất kỳ nhân viên nào
- Chế độ xem calendar tổng hợp

## 🗄️ **Cấu Trúc Database**

### **Bảng `users`:**
```sql
- id: Primary key
- username: Tên đăng nhập (unique)
- password: Mật khẩu đã hash
- role: 'admin' hoặc 'employee'
- employee_id: Link đến bảng employees (nullable cho admin)
- is_active: Trạng thái hoạt động
- last_login: Lần đăng nhập cuối
```

### **Bảng `employees`:**
```sql
- id: Primary key
- employee_code: Mã nhân viên (unique)
- name: Họ tên
- email, phone, position: Thông tin liên hệ
- status: 'active' hoặc 'inactive'
```

### **Bảng `attendance`:**
```sql
- id: Primary key
- employee_id: Foreign key to employees
- date: Ngày chấm công
- checkin_time, checkout_time: Giờ vào/ra
- checkin_note, checkout_note: Ghi chú
- total_hours: Tổng giờ làm
- status: 'complete', 'incomplete', 'half_day'
```

## 🚀 **Cài Đặt**

### **Requirements:**
- PHP 7.4+
- MySQL 5.7+
- XAMPP/WAMP/MAMP

### **Setup:**

1. **Copy files vào web directory:**
```bash
cp -r * /Applications/XAMPP/htdocs/cham-cong/
chmod -R 755 /Applications/XAMPP/xamppfiles/htdocs/cham-cong/
```

2. **Truy cập:**
```
http://localhost/cham-cong
```

3. **Database tự động tạo** với dữ liệu mẫu sẵn!

## 🔐 **Authentication Flow**

### **Đăng Nhập:**
1. Truy cập `http://localhost/cham-cong` → redirect đến `login.php`
2. Nhập username/password
3. Hệ thống verify và tạo session
4. Redirect theo role:
   - Admin → `admin-dashboard.php`
   - Employee → `dashboard.php`

### **Phân Quyền:**
- **Employee**: Chỉ truy cập được dashboard, history, manual-entry của chính mình
- **Admin**: Full access + quản lý + xuất báo cáo

### **Session Management:**
- Auto logout khi session expired
- Protect tất cả trang với `requireLogin()`
- Admin pages protect với `requireAdmin()`

## 📱 **Responsive Design**

- **Bootstrap 5** với **Bootstrap Icons**
- **Glass morphism** design với gradient background
- **Mobile-first** responsive
- **Real-time clock** và auto-refresh

## ⚡ **Tối Ưu Performance**

- **Database indexing** trên các cột quan trọng
- **Pagination** cho history lớn
- **Lazy loading** dữ liệu
- **Session timeout** hợp lý

## 🔒 **Bảo Mật**

- **Password hashing** với `PASSWORD_DEFAULT`
- **SQL injection protection** với prepared statements
- **XSS protection** với `htmlspecialchars()`
- **File access control** với .htaccess
- **Session hijacking protection**

## 🌐 **Repository GitHub**

📍 **Live Repository**: [https://github.com/Meliodaspro/chamcong-online.git](https://github.com/Meliodaspro/chamcong-online.git)

### **Clone Repository:**
```bash
git clone https://github.com/Meliodaspro/chamcong-online.git
cd chamcong-online
```

### **Cập nhật từ GitHub:**
```bash
git pull origin main
```

## 📞 **Hỗ Trợ**

Hệ thống này được thiết kế để:
- ✅ **Đơn giản** để sử dụng
- ✅ **Bảo mật** cao  
- ✅ **Linh hoạt** trong quản lý
- ✅ **Responsive** trên mọi thiết bị

**🎉 Hệ thống đã sẵn sàng sử dụng ngay!** 

---

**💻 Developed by: Nguyễn Hồng Sơn**  
📧 Contact: [GitHub Profile](https://github.com/Meliodaspro) 