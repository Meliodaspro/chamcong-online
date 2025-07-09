<?php
// Thiết lập timezone cho Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Cấu hình database
define('DB_HOST', 'localhost');
define('DB_NAME', 'attendance_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Kết nối database
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Thiết lập timezone cho MySQL
    $pdo->exec("SET time_zone = '+07:00'");
} catch(PDOException $e) {
    // Nếu database chưa tồn tại, thử tạo mới
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Tạo database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE " . DB_NAME);
        
        // Tạo bảng users cho authentication
        $sql_users = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'employee') DEFAULT 'employee',
            employee_id INT NULL,
            is_active TINYINT(1) DEFAULT 1,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_employee_id (employee_id)
        )";
        $pdo->exec($sql_users);
        
        // Tạo bảng employees
        $sql_employees = "CREATE TABLE IF NOT EXISTS employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_code VARCHAR(20) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NULL,
            phone VARCHAR(20) NULL,
            position VARCHAR(100) NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql_employees);
        
        // Tạo bảng attendance với employee_id
        $sql_attendance = "CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            date DATE NOT NULL,
            checkin_time TIME NULL,
            checkout_time TIME NULL,
            checkin_note TEXT NULL,
            checkout_note TEXT NULL,
            total_hours DECIMAL(4,2) DEFAULT 0,
            status ENUM('incomplete', 'complete', 'half_day', 'nghi_phep', 'nghi_khong_cong', 'nghi_co_cong', 'nghi_khong_ly_do') DEFAULT 'incomplete',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            UNIQUE KEY unique_employee_date (employee_id, date)
        )";
        $pdo->exec($sql_attendance);
        
        // Thêm dữ liệu mẫu 6 nhân viên
        $employees = [
            ['NV001', 'Nguyễn Văn An', 'an@company.com', '0123456789', 'Nhân viên'],
            ['NV002', 'Trần Thị Bình', 'binh@company.com', '0123456790', 'Nhân viên'],
            ['NV003', 'Lê Văn Cường', 'cuong@company.com', '0123456791', 'Nhân viên'],
            ['NV004', 'Phạm Thị Dung', 'dung@company.com', '0123456792', 'Nhân viên'],
            ['NV005', 'Hoàng Văn Em', 'em@company.com', '0123456793', 'Nhân viên'],
            ['NV006', 'Ngô Thị Phượng', 'phuong@company.com', '0123456794', 'Nhân viên']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO employees (employee_code, name, email, phone, position) VALUES (?, ?, ?, ?, ?)");
        foreach ($employees as $emp) {
            $stmt->execute($emp);
        }
        
        // Tạo tài khoản admin
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password, role, employee_id) VALUES ('admin', '$adminPassword', 'admin', NULL)");
        
        // Tạo tài khoản cho 6 nhân viên (username = mã nhân viên, password = mã nhân viên)
        $users = [
            ['NV001', 'NV001', 'employee', 1],
            ['NV002', 'NV002', 'employee', 2],
            ['NV003', 'NV003', 'employee', 3],
            ['NV004', 'NV004', 'employee', 4],
            ['NV005', 'NV005', 'employee', 5],
            ['NV006', 'NV006', 'employee', 6]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, employee_id) VALUES (?, ?, ?, ?)");
        foreach ($users as $user) {
            $hashedPassword = password_hash($user[1], PASSWORD_DEFAULT);
            $stmt->execute([$user[0], $hashedPassword, $user[2], $user[3]]);
        }
        
        // Cấu hình lại kết nối với database mới
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Thiết lập timezone cho MySQL
        $pdo->exec("SET time_zone = '+07:00'");
        
    } catch(PDOException $e2) {
        die("Lỗi kết nối database: " . $e2->getMessage() . "<br>Vui lòng kiểm tra cấu hình database trong file config/database.php");
    }
}

// Kiểm tra và cập nhật cấu trúc database nếu cần
try {
    // Kiểm tra xem có bảng users chưa
    $result = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($result->rowCount() == 0) {
        // Tạo bảng users
        $sql_users = "CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'employee') DEFAULT 'employee',
            employee_id INT NULL,
            is_active TINYINT(1) DEFAULT 1,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_employee_id (employee_id)
        )";
        $pdo->exec($sql_users);
        
        // Tạo tài khoản admin
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password, role, employee_id) VALUES ('admin', '$adminPassword', 'admin', NULL)");
        
        // Tạo tài khoản cho nhân viên có sẵn
        $employees = $pdo->query("SELECT id, employee_code FROM employees WHERE status = 'active'")->fetchAll();
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, employee_id) VALUES (?, ?, 'employee', ?)");
        foreach ($employees as $emp) {
            $hashedPassword = password_hash($emp['employee_code'], PASSWORD_DEFAULT);
            $stmt->execute([$emp['employee_code'], $hashedPassword, $emp['id']]);
        }
    }
    
    // Kiểm tra xem có bảng employees chưa
    $result = $pdo->query("SHOW TABLES LIKE 'employees'");
    if ($result->rowCount() == 0) {
        // Tạo bảng employees
        $sql_employees = "CREATE TABLE employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_code VARCHAR(20) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NULL,
            phone VARCHAR(20) NULL,
            position VARCHAR(100) NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql_employees);
        
        // Thêm dữ liệu mẫu
        $employees = [
            ['NV001', 'Nguyễn Văn An', 'an@company.com', '0123456789', 'Nhân viên'],
            ['NV002', 'Trần Thị Bình', 'binh@company.com', '0123456790', 'Nhân viên'],
            ['NV003', 'Lê Văn Cường', 'cuong@company.com', '0123456791', 'Nhân viên'],
            ['NV004', 'Phạm Thị Dung', 'dung@company.com', '0123456792', 'Nhân viên'],
            ['NV005', 'Hoàng Văn Em', 'em@company.com', '0123456793', 'Nhân viên'],
            ['NV006', 'Ngô Thị Phượng', 'phuong@company.com', '0123456794', 'Nhân viên']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO employees (employee_code, name, email, phone, position) VALUES (?, ?, ?, ?, ?)");
        foreach ($employees as $emp) {
            $stmt->execute($emp);
        }
    }
    
    // Kiểm tra xem bảng attendance có cột employee_id chưa
    $result = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'employee_id'");
    if ($result->rowCount() == 0) {
        // Thêm cột employee_id vào bảng attendance
        $pdo->exec("ALTER TABLE attendance ADD COLUMN employee_id INT NOT NULL DEFAULT 1 FIRST");
        $pdo->exec("ALTER TABLE attendance ADD FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE");
        $pdo->exec("ALTER TABLE attendance DROP INDEX unique_date");
        $pdo->exec("ALTER TABLE attendance ADD UNIQUE KEY unique_employee_date (employee_id, date)");
    }
    
    // Cập nhật ENUM status để có thêm các trạng thái mới
    try {
        $pdo->exec("ALTER TABLE attendance MODIFY COLUMN status ENUM('incomplete', 'complete', 'half_day', 'nghi_phep', 'nghi_khong_cong', 'nghi_co_cong', 'nghi_khong_ly_do') DEFAULT 'incomplete'");
    } catch (Exception $e) {
        // Nếu lỗi, có thể enum đã được cập nhật
        error_log("Status enum update: " . $e->getMessage());
    }
    
    // Thêm foreign key constraint cho users.employee_id nếu chưa có
    $result = $pdo->query("SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'users' AND COLUMN_NAME = 'employee_id' AND CONSTRAINT_NAME != 'PRIMARY'");
    if ($result->rowCount() == 0) {
        try {
            $pdo->exec("ALTER TABLE users ADD FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE");
        } catch(PDOException $e) {
            // Ignore if constraint already exists
        }
    }
    
} catch(PDOException $e) {
    error_log("Database migration error: " . $e->getMessage());
}
?> 