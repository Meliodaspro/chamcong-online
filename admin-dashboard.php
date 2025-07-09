<?php
require_once 'includes/functions.php';

// Kiểm tra quyền admin
requireAdmin();

$currentUser = getCurrentUser();

// Lấy thống kê tổng quan
$totalEmployees = count(getAllEmployees());
$today = date('Y-m-d');
$currentMonth = date('Y-m');

// Thống kê hôm nay
$todayStats = [
    'total_checkin' => 0,
    'total_checkout' => 0,
    'present_employees' => 0
];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE date = ? AND checkin_time IS NOT NULL");
$stmt->execute([$today]);
$todayStats['total_checkin'] = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE date = ? AND checkout_time IS NOT NULL");
$stmt->execute([$today]);
$todayStats['total_checkout'] = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) as count FROM attendance WHERE date = ?");
$stmt->execute([$today]);
$todayStats['present_employees'] = $stmt->fetch()['count'];

// Thống kê tháng này
$monthlyStats = [
    'total_hours' => 0,
    'avg_hours' => 0,
    'complete_days' => 0,
    'total_attendance_records' => 0
];

$stmt = $pdo->prepare("SELECT 
    SUM(total_hours) as total_hours,
    AVG(total_hours) as avg_hours,
    COUNT(*) as total_records,
    SUM(CASE WHEN status = 'complete' THEN 1 ELSE 0 END) as complete_days
    FROM attendance 
    WHERE DATE_FORMAT(date, '%Y-%m') = ?");
$stmt->execute([$currentMonth]);
$monthlyData = $stmt->fetch();

$monthlyStats['total_hours'] = $monthlyData['total_hours'] ?? 0;
$monthlyStats['avg_hours'] = $monthlyData['avg_hours'] ?? 0;
$monthlyStats['complete_days'] = $monthlyData['complete_days'] ?? 0;
$monthlyStats['total_attendance_records'] = $monthlyData['total_records'] ?? 0;

// Lấy danh sách nhân viên với trạng thái hôm nay
$employees = getAllEmployees();
$employeeStatus = [];

foreach ($employees as $emp) {
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
    $stmt->execute([$emp['id'], $today]);
    $attendance = $stmt->fetch();
    
    $employeeStatus[$emp['id']] = [
        'employee' => $emp,
        'attendance' => $attendance,
        'status' => $attendance ? $attendance['status'] : 'absent'
    ];
}

// Lấy hoạt động gần đây
$stmt = $pdo->prepare("SELECT a.*, e.name as employee_name, e.employee_code 
                      FROM attendance a 
                      JOIN employees e ON a.employee_id = e.id 
                      ORDER BY a.updated_at DESC 
                      LIMIT 10");
$stmt->execute();
$recentActivity = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Hệ thống chấm công</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .main-content {
            padding: 2rem 0;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .content-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .employee-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-left: 4px solid #28a745;
            transition: all 0.3s ease;
        }
        
        .employee-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .employee-item.absent {
            border-left-color: #dc3545;
        }
        
        .employee-item.incomplete {
            border-left-color: #ffc107;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .activity-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .admin-actions {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 1.5rem;
            color: white;
        }
        
        .admin-actions .btn {
            margin: 0.25rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-shield-check me-2"></i>
                Admin Dashboard
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle fw-semibold" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-gear me-2"></i>
                        <?php echo htmlspecialchars($currentUser['username']); ?>
                        <span class="badge bg-danger ms-2">ADMIN</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="employees.php"><i class="bi bi-people me-2"></i>Quản lý nhân viên</a></li>
                        <li><a class="dropdown-item" href="export.php"><i class="bi bi-file-earmark-excel me-2"></i>Xuất báo cáo</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Cài đặt hệ thống</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container main-content">
        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="admin-actions">
                    <h5 class="mb-3"><i class="bi bi-lightning-fill me-2"></i>Thao tác nhanh</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <a href="employees.php" class="btn btn-light btn-sm">
                                <i class="bi bi-people me-1"></i>Quản lý nhân viên
                            </a>
                            <a href="export.php" class="btn btn-light btn-sm">
                                <i class="bi bi-file-earmark-excel me-1"></i>Xuất Excel
                            </a>
                            <a href="history.php" class="btn btn-light btn-sm">
                                <i class="bi bi-clock-history me-1"></i>Xem lịch sử
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="manual-entry.php" class="btn btn-light btn-sm">
                                <i class="bi bi-calendar-plus me-1"></i>Nhập công thủ công
                            </a>
                            <a href="settings.php" class="btn btn-light btn-sm">
                                <i class="bi bi-gear me-1"></i>Cài đặt
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Thống kê hôm nay -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-people display-4 text-primary mb-3"></i>
                        <h5 class="card-title">Tổng nhân viên</h5>
                        <h2 class="text-primary"><?php echo $totalEmployees; ?></h2>
                        <small class="text-muted">Đang hoạt động</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-box-arrow-in-right display-4 text-success mb-3"></i>
                        <h5 class="card-title">Đã chấm công vào</h5>
                        <h2 class="text-success"><?php echo $todayStats['total_checkin']; ?></h2>
                        <small class="text-muted">Hôm nay</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-box-arrow-right display-4 text-danger mb-3"></i>
                        <h5 class="card-title">Đã chấm công ra</h5>
                        <h2 class="text-danger"><?php echo $todayStats['total_checkout']; ?></h2>
                        <small class="text-muted">Hôm nay</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-person-check display-4 text-info mb-3"></i>
                        <h5 class="card-title">Có mặt</h5>
                        <h2 class="text-info"><?php echo $todayStats['present_employees']; ?></h2>
                        <small class="text-muted">Nhân viên</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Thống kê tháng -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-clock display-4 text-warning mb-3"></i>
                        <h5 class="card-title">Tổng giờ làm</h5>
                        <h2 class="text-warning"><?php echo number_format($monthlyStats['total_hours'], 1); ?></h2>
                        <small class="text-muted">Tháng <?php echo date('m/Y'); ?></small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-graph-up display-4 text-purple mb-3"></i>
                        <h5 class="card-title">Giờ trung bình</h5>
                        <h2 class="text-secondary"><?php echo number_format($monthlyStats['avg_hours'], 1); ?></h2>
                        <small class="text-muted">Giờ/ngày</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle display-4 text-success mb-3"></i>
                        <h5 class="card-title">Ngày hoàn thành</h5>
                        <h2 class="text-success"><?php echo $monthlyStats['complete_days']; ?></h2>
                        <small class="text-muted">Tổng ngày</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-journal-text display-4 text-info mb-3"></i>
                        <h5 class="card-title">Bản ghi chấm công</h5>
                        <h2 class="text-info"><?php echo $monthlyStats['total_attendance_records']; ?></h2>
                        <small class="text-muted">Trong tháng</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Trạng thái nhân viên hôm nay -->
            <div class="col-lg-8 mb-4">
                <div class="card content-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-people me-2"></i>
                            Trạng thái nhân viên hôm nay
                            <span class="badge bg-primary ms-2"><?php echo date('d/m/Y'); ?></span>
                        </h5>
                        
                        <?php foreach ($employeeStatus as $status): ?>
                            <div class="employee-item <?php echo $status['status']; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($status['employee']['name']); ?></strong>
                                        <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($status['employee']['employee_code']); ?></span>
                                        <?php if ($status['attendance']): ?>
                                            <br>
                                            <small class="text-muted">
                                                Vào: <?php echo $status['attendance']['checkin_time'] ? date('H:i', strtotime($status['attendance']['checkin_time'])) : '--:--'; ?> | 
                                                Ra: <?php echo $status['attendance']['checkout_time'] ? date('H:i', strtotime($status['attendance']['checkout_time'])) : '--:--'; ?>
                                                <?php if ($status['attendance']['total_hours'] > 0): ?>
                                                    | <?php echo number_format($status['attendance']['total_hours'], 1); ?>h
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php
                                        $statusClass = [
                                            'complete' => 'bg-success',
                                            'incomplete' => 'bg-warning',
                                            'half_day' => 'bg-info',
                                            'absent' => 'bg-danger'
                                        ];
                                        $statusText = [
                                            'complete' => 'Hoàn thành',
                                            'incomplete' => 'Chưa hoàn thành',
                                            'half_day' => 'Nửa ngày',
                                            'absent' => 'Vắng mặt'
                                        ];
                                        ?>
                                        <span class="status-badge <?php echo $statusClass[$status['status']]; ?>">
                                            <?php echo $statusText[$status['status']]; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3">
                            <a href="employees.php" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-right me-1"></i>
                                Quản lý nhân viên
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Hoạt động gần đây -->
            <div class="col-lg-4 mb-4">
                <div class="card content-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-activity me-2"></i>
                            Hoạt động gần đây
                        </h5>
                        
                        <?php if ($recentActivity): ?>
                            <?php foreach ($recentActivity as $activity): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong><?php echo htmlspecialchars($activity['employee_name']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('d/m H:i', strtotime($activity['updated_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($activity['checkin_time'] && $activity['checkout_time']): ?>
                                                <i class="bi bi-check-circle text-success" title="Hoàn thành"></i>
                                            <?php elseif ($activity['checkin_time']): ?>
                                                <i class="bi bi-clock text-warning" title="Đang làm việc"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="text-center mt-3">
                                <a href="history.php" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-arrow-right me-1"></i>
                                    Xem tất cả
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted">
                                <i class="bi bi-inbox display-4"></i>
                                <p class="mt-2">Chưa có hoạt động</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto refresh page every 5 minutes to update real-time data
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutes
        
        // Show current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('vi-VN');
            document.title = `[${timeString}] Admin Dashboard - Hệ thống chấm công`;
        }
        
        setInterval(updateTime, 1000);
        updateTime();
    </script>
    <!-- Developer Button -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1000;">
        <button class="btn btn-dark btn-sm rounded-pill shadow" type="button" data-bs-toggle="tooltip" data-bs-placement="left" title="Phát triển bởi Nguyễn Hồng Sơn">
            <i class="bi bi-code-slash me-1"></i>
            <small>dev by <strong>Nguyễn Hồng Sơn</strong></small>
        </button>
    </div>
    
    <script>
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html> 