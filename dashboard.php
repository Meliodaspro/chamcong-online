<?php
require_once 'includes/functions.php';

// Kiểm tra đăng nhập
requireLogin();

$currentUser = getCurrentUser();
$employeeId = $currentUser['employee_id'];

// Kiểm tra nếu là admin thì redirect về admin dashboard
if (isAdmin()) {
    header('Location: admin-dashboard.php');
    exit;
}

// Lấy thông tin nhân viên
$employee = getEmployeeById($employeeId);
if (!$employee) {
    $error = 'Không tìm thấy thông tin nhân viên';
}

// Xử lý chấm công
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $note = trim($_POST['note'] ?? '');
    
    if (in_array($action, ['checkin', 'checkout'])) {
        $result = addAttendance($employeeId, $action, $note);
        
        if ($result) {
            $success = $action === 'checkin' ? 'Chấm công vào thành công!' : 'Chấm công ra thành công!';
        } else {
            $error = 'Có lỗi xảy ra khi chấm công';
        }
    }
}

// Lấy thông tin chấm công hôm nay
$today = date('Y-m-d');
$todayAttendance = getTodayAttendance($employeeId);

// Lấy thống kê tháng này
$currentMonth = date('Y-m');
$monthlyStats = getMonthlyStats($employeeId, $currentMonth);

// Lấy lịch sử gần đây
$recentHistory = getAttendanceHistory($employeeId, 1, 5);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($employee['name'] ?? 'Nhân viên'); ?></title>
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
        
        .attendance-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .clock-display {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .date-display {
            font-size: 1.2rem;
            color: #6c757d;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .btn-attendance {
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
            margin: 0.5rem;
        }
        
        .btn-checkin {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .btn-checkout {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            color: white;
        }
        
        .btn-attendance:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .status-incomplete {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }
        
        .status-complete {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .history-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-left: 4px solid #667eea;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-clock-history me-2"></i>
                Hệ thống chấm công
            </a>
            
            <div class="d-flex align-items-center ms-auto">

                
                <div class="navbar-nav d-flex align-items-center">
                <!-- User info - hiện luôn -->
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle me-1"></i>
                    <span class="d-none d-sm-inline"><?php echo htmlspecialchars($currentUser['employee_name'] ?? $currentUser['username']); ?></span>
                    <span class="badge bg-primary ms-1"><?php echo htmlspecialchars($currentUser['employee_code']); ?></span>
                </span>
                
                <!-- Desktop buttons - hiện từ màn hình nhỏ trở lên -->
                <div class="d-none d-sm-flex align-items-center">
                    <a href="history.php" class="btn btn-sm btn-outline-info me-2">
                        <i class="bi bi-clock-history me-1"></i>
                        <span class="d-none d-lg-inline">Lịch sử</span>
                    </a>
                    <a href="manual-entry.php" class="btn btn-sm btn-outline-primary me-2">
                        <i class="bi bi-calendar-plus me-1"></i>
                        <span class="d-none d-lg-inline">Nhập công</span>
                    </a>
                    <a href="logout.php" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-box-arrow-right me-1"></i>
                        <span class="d-none d-lg-inline">Đăng xuất</span>
                    </a>
                </div>
                
                <!-- Mobile dropdown -->
                <div class="d-sm-none dropdown">
                    <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-list"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="history.php"><i class="bi bi-clock-history me-2"></i>Lịch sử chấm công</a></li>
                        <li><a class="dropdown-item" href="manual-entry.php"><i class="bi bi-calendar-plus me-2"></i>Nhập công thủ công</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
                    </ul>
                </div>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container main-content">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Thống kê tháng -->
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-check display-4 text-primary mb-3"></i>
                        <h5 class="card-title">Tổng ngày công</h5>
                        <h2 class="text-primary"><?php echo $monthlyStats['total_days']; ?></h2>
                        <small class="text-muted">Tháng <?php echo date('m/Y'); ?></small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-clock display-4 text-success mb-3"></i>
                        <h5 class="card-title">Tổng giờ làm</h5>
                        <h2 class="text-success"><?php echo number_format($monthlyStats['total_hours'], 1); ?></h2>
                        <small class="text-muted">Giờ trong tháng</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-graph-up display-4 text-info mb-3"></i>
                        <h5 class="card-title">Giờ trung bình</h5>
                        <h2 class="text-info"><?php echo number_format($monthlyStats['avg_hours'], 1); ?></h2>
                        <small class="text-muted">Giờ/ngày</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle display-4 text-warning mb-3"></i>
                        <h5 class="card-title">Ngày hoàn thành</h5>
                        <h2 class="text-warning"><?php echo $monthlyStats['complete_days']; ?></h2>
                        <small class="text-muted">Ngày đủ giờ</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Chấm công hôm nay -->
            <div class="col-lg-8 mb-4">
                <div class="card attendance-card h-100">
                    <div class="card-body p-4">
                        <h4 class="card-title text-center mb-4">
                            <i class="bi bi-clock me-2"></i>
                            Chấm công hôm nay
                        </h4>
                        
                        <div class="clock-display" id="currentTime"></div>
                        <div class="date-display">
                            <?php echo getVietnameseDayName(time()) . ', ' . date('d/m/Y'); ?>
                        </div>
                        
                        <?php if ($todayAttendance): ?>
                            <div class="row text-center mb-4">
                                <div class="col-md-6">
                                    <h6 class="text-muted">Giờ vào</h6>
                                    <div class="h4 text-success">
                                        <?php echo $todayAttendance['checkin_time'] ? date('H:i', strtotime($todayAttendance['checkin_time'])) : '--:--'; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted">Giờ ra</h6>
                                    <div class="h4 text-danger">
                                        <?php echo $todayAttendance['checkout_time'] ? date('H:i', strtotime($todayAttendance['checkout_time'])) : '--:--'; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mb-4">
                                <span class="status-badge status-<?php echo $todayAttendance['status']; ?>">
                                    <?php 
                                    $statusText = [
                                        'incomplete' => 'Chưa hoàn thành',
                                        'complete' => 'Hoàn thành', 
                                        'half_day' => 'Nửa ngày'
                                    ];
                                    echo $statusText[$todayAttendance['status']] ?? 'Không xác định';
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="text-center">
                                <?php if (!$todayAttendance || !$todayAttendance['checkin_time']): ?>
                                    <button type="submit" name="action" value="checkin" class="btn btn-checkin btn-attendance btn-lg">
                                        <i class="bi bi-box-arrow-in-right me-2"></i>
                                        Chấm công vào
                                    </button>
                                <?php elseif (!$todayAttendance['checkout_time']): ?>
                                    <button type="submit" name="action" value="checkout" class="btn btn-checkout btn-attendance btn-lg">
                                        <i class="bi bi-box-arrow-right me-2"></i>
                                        Chấm công ra
                                    </button>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Bạn đã hoàn thành chấm công hôm nay
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mt-3">
                                <label for="note" class="form-label">Ghi chú (không bắt buộc):</label>
                                <input type="text" class="form-control" id="note" name="note" placeholder="Nhập ghi chú...">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Lịch sử gần đây -->
            <div class="col-lg-4 mb-4">
                <div class="card attendance-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-clock-history me-2"></i>
                            Lịch sử gần đây
                        </h5>
                        
                        <?php if ($recentHistory): ?>
                            <?php foreach ($recentHistory as $record): ?>
                                <div class="history-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo date('d/m/Y', strtotime($record['date'])); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo $record['checkin_time'] ? date('H:i', strtotime($record['checkin_time'])) : '--:--'; ?> - 
                                                <?php echo $record['checkout_time'] ? date('H:i', strtotime($record['checkout_time'])) : '--:--'; ?>
                                            </small>
                                        </div>
                                        <div>
                                            <span class="badge bg-<?php echo $record['status'] === 'complete' ? 'success' : 'warning'; ?>">
                                                <?php echo number_format($record['total_hours'], 1); ?>h
                                            </span>
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
                                <p class="mt-2">Chưa có lịch sử chấm công</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Developer Button -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1000;">
        <button class="btn btn-dark btn-sm rounded-pill shadow" type="button" data-bs-toggle="tooltip" data-bs-placement="left" title="Phát triển bởi Nguyễn Hồng Sơn">
            <i class="bi bi-code-slash me-1"></i>
            <small>dev by <strong>Nguyễn Hồng Sơn</strong></small>
        </button>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Cập nhật đồng hồ thời gian thực
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('vi-VN', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        
        // Cập nhật mỗi giây
        setInterval(updateClock, 1000);
        updateClock();
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html> 