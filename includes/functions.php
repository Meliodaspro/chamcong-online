<?php
require_once __DIR__ . '/../config/database.php';

// Thiết lập timezone cho Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bắt đầu session nếu chưa có
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * AUTHENTICATION FUNCTIONS
 */

/**
 * Xác thực đăng nhập
 */
function authenticateUser($username, $password) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT u.*, e.name as employee_name, e.employee_code 
                              FROM users u 
                              LEFT JOIN employees e ON u.employee_id = e.id 
                              WHERE u.username = ? AND u.is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Cập nhật last_login
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            return $user;
        }
        
        return false;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

/**
 * Đăng nhập user
 */
function loginUser($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['employee_id'] = $user['employee_id'];
    $_SESSION['employee_name'] = $user['employee_name'] ?? null;
    $_SESSION['employee_code'] = $user['employee_code'] ?? null;
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
}

/**
 * Đăng xuất user
 */
function logoutUser() {
    session_unset();
    session_destroy();
    session_start();
}

/**
 * Kiểm tra user đã đăng nhập chưa
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Kiểm tra user có quyền admin không
 */
function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

/**
 * Kiểm tra user có quyền employee không
 */
function isEmployee() {
    return isLoggedIn() && $_SESSION['role'] === 'employee';
}

/**
 * Lấy thông tin user hiện tại
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
        'employee_id' => $_SESSION['employee_id'],
        'employee_name' => $_SESSION['employee_name'],
        'employee_code' => $_SESSION['employee_code']
    ];
}

/**
 * Redirect đến trang login nếu chưa đăng nhập
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit;
    }
}

/**
 * Redirect đến trang login nếu không phải admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ../dashboard.php');
        exit;
    }
}

/**
 * Lấy danh sách tất cả users
 */
function getAllUsers() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT u.*, e.name as employee_name, e.employee_code 
                              FROM users u 
                              LEFT JOIN employees e ON u.employee_id = e.id 
                              ORDER BY u.role, e.employee_code");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log($e->getMessage());
        return [];
    }
}

/**
 * Tạo tài khoản user cho nhân viên
 */
function createUserForEmployee($employeeId, $username, $password) {
    global $pdo;
    
    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, employee_id) VALUES (?, ?, 'employee', ?)");
        return $stmt->execute([$username, $hashedPassword, $employeeId]);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

/**
 * Cập nhật mật khẩu user
 */
function updateUserPassword($userId, $newPassword) {
    global $pdo;
    
    try {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hashedPassword, $userId]);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

/**
 * Vô hiệu hóa tài khoản user
 */
function deactivateUser($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
        return $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

/**
 * EMPLOYEE MANAGEMENT FUNCTIONS
 */

/**
 * Lấy danh sách tất cả nhân viên đang hoạt động
 */
function getAllEmployees() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE status = 'active' ORDER BY employee_code");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log($e->getMessage());
        return [];
    }
}

/**
 * Lấy thông tin nhân viên theo ID
 */
function getEmployeeById($employeeId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$employeeId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log($e->getMessage());
        return null;
    }
}

/**
 * ATTENDANCE FUNCTIONS
 */

/**
 * Thêm bản ghi chấm công
 */
function addAttendance($employeeId, $action, $note = '') {
    global $pdo;
    
    $today = date('Y-m-d');
    $currentTime = date('H:i:s');
    
    try {
        // Kiểm tra xem đã có bản ghi hôm nay chưa
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$employeeId, $today]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Cập nhật bản ghi hiện có
            if ($action === 'checkin') {
                $sql = "UPDATE attendance SET checkin_time = ?, checkin_note = ?, updated_at = CURRENT_TIMESTAMP WHERE employee_id = ? AND date = ?";
                $params = [$currentTime, $note, $employeeId, $today];
            } else {
                $sql = "UPDATE attendance SET checkout_time = ?, checkout_note = ?, updated_at = CURRENT_TIMESTAMP WHERE employee_id = ? AND date = ?";
                $params = [$currentTime, $note, $employeeId, $today];
            }
        } else {
            // Tạo bản ghi mới
            if ($action === 'checkin') {
                $sql = "INSERT INTO attendance (employee_id, date, checkin_time, checkin_note) VALUES (?, ?, ?, ?)";
                $params = [$employeeId, $today, $currentTime, $note];
            } else {
                $sql = "INSERT INTO attendance (employee_id, date, checkout_time, checkout_note) VALUES (?, ?, ?, ?)";
                $params = [$employeeId, $today, $currentTime, $note];
            }
        }
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        // Cập nhật trạng thái và tổng giờ làm
        updateAttendanceStatus($employeeId, $today);
        
        return $result;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

/**
 * Thêm bản ghi chấm công thủ công
 */
function addManualAttendance($employeeId, $date, $checkinTime, $checkoutTime, $checkinNote, $checkoutNote, $manualStatus) {
    global $pdo;
    
    try {
        // Kiểm tra xem đã có bản ghi chưa
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$employeeId, $date]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Cập nhật bản ghi hiện có
            $sql = "UPDATE attendance SET 
                    checkin_time = ?, checkout_time = ?, 
                    checkin_note = ?, checkout_note = ?, 
                    updated_at = CURRENT_TIMESTAMP 
                    WHERE employee_id = ? AND date = ?";
            $params = [$checkinTime, $checkoutTime, $checkinNote, $checkoutNote, $employeeId, $date];
        } else {
            // Tạo bản ghi mới
            $sql = "INSERT INTO attendance (employee_id, date, checkin_time, checkout_time, checkin_note, checkout_note) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $params = [$employeeId, $date, $checkinTime, $checkoutTime, $checkinNote, $checkoutNote];
        }
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        // Cập nhật trạng thái và tổng giờ làm
        if ($manualStatus !== 'auto') {
            // Nếu chọn trạng thái thủ công, cập nhật trực tiếp
            updateManualStatus($employeeId, $date, $manualStatus);
        } else {
            // Nếu chọn auto, tính toán dựa vào giờ vào/ra
            updateAttendanceStatus($employeeId, $date);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

/**
 * Cập nhật trạng thái thủ công
 */
function updateManualStatus($employeeId, $date, $status) {
    global $pdo;
    
    try {
        $totalHours = 0;
        
        // Tính tổng giờ dựa vào trạng thái
        switch ($status) {
            case 'complete':
                $totalHours = 8;
                break;
            case 'half_day':
                $totalHours = 4;
                break;
            default:
                $totalHours = 0;
        }
        
        $sql = "UPDATE attendance SET status = ?, total_hours = ? WHERE employee_id = ? AND date = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $totalHours, $employeeId, $date]);
        
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

/**
 * Xóa bản ghi chấm công
 */
function deleteAttendance($employeeId, $date) {
    global $pdo;
    
    try {
        $sql = "DELETE FROM attendance WHERE employee_id = ? AND date = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$employeeId, $date]);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

/**
 * Lấy dữ liệu chấm công của cả tháng để hiển thị calendar
 */
function getMonthlyAttendanceData($employeeId, $month, $year) {
    global $pdo;
    
    try {
        $sql = "SELECT DAY(date) as day, checkin_time, checkout_time, 
                       checkin_note, checkout_note, total_hours, status 
                FROM attendance 
                WHERE employee_id = ? AND MONTH(date) = ? AND YEAR(date) = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$employeeId, $month, $year]);
        
        $data = [];
        while ($row = $stmt->fetch()) {
            $data[$row['day']] = $row;
        }
        
        return $data;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return [];
    }
}

/**
 * Cập nhật trạng thái và tính tổng giờ làm
 */
function updateAttendanceStatus($employeeId, $date) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$employeeId, $date]);
        $record = $stmt->fetch();
        
        if (!$record) return;
        
        $status = 'incomplete';
        $totalHours = 0;
        
        if ($record['checkin_time'] && $record['checkout_time']) {
            // Có đủ cả check in và check out
            $checkin = new DateTime($record['checkin_time']);
            $checkout = new DateTime($record['checkout_time']);
            $diff = $checkout->diff($checkin);
            $totalHours = $diff->h + ($diff->i / 60);
            
            // Kiểm tra có đủ 8 tiếng làm việc không
            if ($totalHours >= 8) {
                $status = 'complete';
            } else {
                $status = 'half_day';
            }
        } elseif ($record['checkin_time'] || $record['checkout_time']) {
            // Chỉ có 1 trong 2
            $status = 'half_day';
            $totalHours = 4;
        }
        
        $updateSql = "UPDATE attendance SET status = ?, total_hours = ? WHERE employee_id = ? AND date = ?";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([$status, $totalHours, $employeeId, $date]);
        
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

/**
 * Lấy dữ liệu chấm công hôm nay
 */
function getTodayAttendance($employeeId) {
    global $pdo;
    
    $today = date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$employeeId, $today]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log($e->getMessage());
        return null;
    }
}

/**
 * Lấy thống kê tháng
 */
function getMonthlyStats($employeeId, $month = null) {
    global $pdo;
    
    if (!$month) $month = date('Y-m');
    
    try {
        $sql = "SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'complete' THEN 1 ELSE 0 END) as complete_days,
                    SUM(CASE WHEN status = 'half_day' THEN 0.5 WHEN status = 'complete' THEN 1 ELSE 0 END) as work_units,
                    SUM(total_hours) as total_hours,
                    AVG(total_hours) as avg_hours
                FROM attendance 
                WHERE employee_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$employeeId, $month]);
        $result = $stmt->fetch();
        
        return [
            'total_days' => $result['total_days'] ?? 0,
            'complete_days' => $result['complete_days'] ?? 0,
            'work_units' => $result['work_units'] ?? 0,
            'total_hours' => $result['total_hours'] ?? 0,
            'avg_hours' => $result['avg_hours'] ?? 0
        ];
    } catch (Exception $e) {
        error_log($e->getMessage());
        return [
            'total_days' => 0,
            'complete_days' => 0,
            'work_units' => 0,
            'total_hours' => 0,
            'avg_hours' => 0
        ];
    }
}

/**
 * Lấy lịch sử chấm công
 */
function getAttendanceHistory($employeeId, $page = 1, $recordsPerPage = 20, $month = null) {
    global $pdo;
    
    try {
        $offset = ($page - 1) * $recordsPerPage;
        
        // Đảm bảo LIMIT và OFFSET là integers
        $recordsPerPage = (int)$recordsPerPage;
        $offset = (int)$offset;
        
        if ($month) {
            $sql = "SELECT a.*, e.name as employee_name, e.employee_code 
                    FROM attendance a 
                    JOIN employees e ON a.employee_id = e.id 
                    WHERE a.employee_id = ? AND DATE_FORMAT(a.date, '%Y-%m') = ? 
                    ORDER BY a.date DESC LIMIT $recordsPerPage OFFSET $offset";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$employeeId, $month]);
        } else {
            $sql = "SELECT a.*, e.name as employee_name, e.employee_code 
                    FROM attendance a 
                    JOIN employees e ON a.employee_id = e.id 
                    WHERE a.employee_id = ? 
                    ORDER BY a.date DESC LIMIT $recordsPerPage OFFSET $offset";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$employeeId]);
        }
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log($e->getMessage());
        return [];
    }
}

/**
 * Lấy tổng số bản ghi chấm công để phân trang
 */
function getTotalAttendanceRecords($employeeId, $month = null) {
    global $pdo;
    
    try {
        if ($month) {
            $sql = "SELECT COUNT(*) FROM attendance 
                    WHERE employee_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$employeeId, $month]);
        } else {
            $sql = "SELECT COUNT(*) FROM attendance WHERE employee_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$employeeId]);
        }
        
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log($e->getMessage());
        return 0;
    }
}

/**
 * Lấy dữ liệu để export
 */
function getAttendanceForExport($employeeId, $startDate, $endDate) {
    global $pdo;
    
    try {
        $sql = "SELECT a.*, e.name as employee_name, e.employee_code 
                FROM attendance a 
                JOIN employees e ON a.employee_id = e.id 
                WHERE a.employee_id = ? AND a.date BETWEEN ? AND ? 
                ORDER BY a.date";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$employeeId, $startDate, $endDate]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log($e->getMessage());
        return [];
    }
}

/**
 * Lấy dữ liệu tất cả nhân viên để export
 */
function getAllEmployeesAttendanceForExport($startDate, $endDate) {
    global $pdo;
    
    try {
        $sql = "SELECT a.*, e.name as employee_name, e.employee_code 
                FROM attendance a 
                JOIN employees e ON a.employee_id = e.id 
                WHERE a.date BETWEEN ? AND ? 
                ORDER BY e.employee_code, a.date";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log($e->getMessage());
        return [];
    }
}

/**
 * Quản lý nhân viên - Thêm nhân viên mới
 */
function addEmployee($employeeCode, $name, $email, $phone, $position) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO employees (employee_code, name, email, phone, position) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$employeeCode, $name, $email, $phone, $position]);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

/**
 * Quản lý nhân viên - Cập nhật thông tin nhân viên
 */
function updateEmployee($id, $employeeCode, $name, $email, $phone, $position, $status) {
    global $pdo;
    
    try {
        $sql = "UPDATE employees SET employee_code = ?, name = ?, email = ?, phone = ?, position = ?, status = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$employeeCode, $name, $email, $phone, $position, $status, $id]);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

/**
 * Quản lý nhân viên - Xóa nhân viên (chuyển trạng thái inactive)
 */
function deleteEmployee($id) {
    global $pdo;
    
    try {
        $sql = "UPDATE employees SET status = 'inactive' WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$id]);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

/**
 * Định dạng thời gian hiển thị
 */
function formatTime($time) {
    return $time ? date('H:i', strtotime($time)) : '--:--';
}

/**
 * Định dạng ngày hiển thị
 */
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

/**
 * Lấy tên trạng thái bằng tiếng Việt
 */
function getStatusText($status) {
    switch ($status) {
        case 'complete': return '1 công';
        case 'half_day': return 'Nửa ngày';
        case 'incomplete': return 'Thiếu';
        default: return 'Chưa xác định';
    }
}

/**
 * Lấy class CSS cho trạng thái
 */
function getStatusClass($status) {
    switch ($status) {
        case 'complete': return 'success';
        case 'half_day': return 'warning';
        case 'incomplete': return 'danger';
        default: return 'secondary';
    }
}

/**
 * Chuyển đổi tên ngày từ tiếng Anh sang tiếng Việt
 */
function getVietnameseDayName($date) {
    $dayNames = [
        'Monday' => 'Thứ Hai',
        'Tuesday' => 'Thứ Ba', 
        'Wednesday' => 'Thứ Tư',
        'Thursday' => 'Thứ Năm',
        'Friday' => 'Thứ Sáu',
        'Saturday' => 'Thứ Bảy',
        'Sunday' => 'Chủ Nhật'
    ];
    $englishDay = date('l', is_string($date) ? strtotime($date) : $date);
    return $dayNames[$englishDay] ?? $englishDay;
}

/**
 * Lấy dữ liệu tổng hợp nhân viên để export (1 dòng/nhân viên)
 */
function getEmployeesSummaryForExport($startDate, $endDate) {
    global $pdo;
    
    try {
        $sql = "SELECT 
                    e.id,
                    e.employee_code,
                    e.name as employee_name,
                    e.position,
                    COUNT(a.date) as total_work_days,
                    COALESCE(SUM(a.total_hours), 0) as total_hours,
                    COALESCE(SUM(CASE WHEN a.status = 'complete' THEN 1 ELSE 0 END), 0) as complete_days,
                    COALESCE(SUM(CASE WHEN a.status = 'half_day' THEN 1 ELSE 0 END), 0) as half_days,
                    COALESCE(SUM(CASE WHEN a.status = 'incomplete' THEN 1 ELSE 0 END), 0) as incomplete_days,
                    COALESCE(AVG(a.total_hours), 0) as avg_hours_per_day,
                    MIN(a.date) as first_work_date,
                    MAX(a.date) as last_work_date
                FROM employees e
                LEFT JOIN attendance a ON e.id = a.employee_id 
                    AND a.date BETWEEN ? AND ?
                WHERE e.status = 'active'
                GROUP BY e.id, e.employee_code, e.name, e.position
                ORDER BY e.employee_code";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        $results = $stmt->fetchAll();
        
        // Tính toán thêm một số thống kê
        foreach ($results as &$result) {
            // Tính tỷ lệ hoàn thành
            if ($result['total_work_days'] > 0) {
                $result['completion_rate'] = round(($result['complete_days'] / $result['total_work_days']) * 100, 2);
            } else {
                $result['completion_rate'] = 0;
            }
            
            // Tính số ngày làm việc trong khoảng thời gian (không tính weekend)
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $workingDays = 0;
            
            while ($start <= $end) {
                $dayOfWeek = $start->format('w');
                if ($dayOfWeek != 0 && $dayOfWeek != 6) { // Không phải CN (0) và T7 (6)
                    $workingDays++;
                }
                $start->add(new DateInterval('P1D'));
            }
            
            $result['expected_work_days'] = $workingDays;
            
            // Tính tỷ lệ chuyên cần
            if ($workingDays > 0) {
                $result['attendance_rate'] = round(($result['total_work_days'] / $workingDays) * 100, 2);
            } else {
                $result['attendance_rate'] = 0;
            }
        }
        
        return $results;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return [];
    }
}

/**
 * Lấy dữ liệu tổng hợp cho 1 nhân viên cụ thể
 */
function getEmployeeSummaryForExport($employeeId, $startDate, $endDate) {
    global $pdo;
    
    try {
        $sql = "SELECT 
                    e.id,
                    e.employee_code,
                    e.name as employee_name,
                    e.position,
                    COUNT(a.date) as total_work_days,
                    COALESCE(SUM(a.total_hours), 0) as total_hours,
                    COALESCE(SUM(CASE WHEN a.status = 'complete' THEN 1 ELSE 0 END), 0) as complete_days,
                    COALESCE(SUM(CASE WHEN a.status = 'half_day' THEN 1 ELSE 0 END), 0) as half_days,
                    COALESCE(SUM(CASE WHEN a.status = 'incomplete' THEN 1 ELSE 0 END), 0) as incomplete_days,
                    COALESCE(AVG(a.total_hours), 0) as avg_hours_per_day,
                    MIN(a.date) as first_work_date,
                    MAX(a.date) as last_work_date
                FROM employees e
                LEFT JOIN attendance a ON e.id = a.employee_id 
                    AND a.date BETWEEN ? AND ?
                WHERE e.id = ? AND e.status = 'active'
                GROUP BY e.id, e.employee_code, e.name, e.position";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$startDate, $endDate, $employeeId]);
        $result = $stmt->fetch();
        
        if ($result) {
            // Tính toán thêm một số thống kê
            if ($result['total_work_days'] > 0) {
                $result['completion_rate'] = round(($result['complete_days'] / $result['total_work_days']) * 100, 2);
            } else {
                $result['completion_rate'] = 0;
            }
            
            // Tính số ngày làm việc trong khoảng thời gian
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $workingDays = 0;
            
            while ($start <= $end) {
                $dayOfWeek = $start->format('w');
                if ($dayOfWeek != 0 && $dayOfWeek != 6) {
                    $workingDays++;
                }
                $start->add(new DateInterval('P1D'));
            }
            
            $result['expected_work_days'] = $workingDays;
            
            // Tính tỷ lệ chuyên cần
            if ($workingDays > 0) {
                $result['attendance_rate'] = round(($result['total_work_days'] / $workingDays) * 100, 2);
            } else {
                $result['attendance_rate'] = 0;
            }
            
            return [$result]; // Trả về array để tương thích với logic xuất
        }
        
        return [];
    } catch (Exception $e) {
        error_log($e->getMessage());
        return [];
    }
}
?> 