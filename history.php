<?php
require_once 'includes/functions.php';

// Kiểm tra đăng nhập
requireLogin();

$currentUser = getCurrentUser();

// Kiểm tra và xử lý tham số
$selectedEmployeeId = null;
$selectedMonth = $_GET['month'] ?? date('Y-m');

if (isAdmin()) {
    // Admin có thể xem lịch sử của bất kỳ nhân viên nào
    $employees = getAllEmployees();
    $selectedEmployeeId = $_GET['employee_id'] ?? null;
    
    // Nếu admin chưa chọn nhân viên và có nhân viên trong hệ thống, chọn nhân viên đầu tiên
    if (!$selectedEmployeeId && !empty($employees)) {
        $selectedEmployeeId = $employees[0]['id'];
    }
} else {
    // Employee chỉ xem được lịch sử của chính mình
    $selectedEmployeeId = $currentUser['employee_id'];
    $employees = [getEmployeeById($selectedEmployeeId)];
}

$currentPage = (int)($_GET['page'] ?? 1);
$recordsPerPage = 20;

// Lấy lịch sử chấm công
if ($selectedEmployeeId) {
    $attendanceHistory = getAttendanceHistory($selectedEmployeeId, $currentPage, $recordsPerPage, $selectedMonth);
    $totalRecords = getTotalAttendanceRecords($selectedEmployeeId, $selectedMonth);
    $selectedEmployee = getEmployeeById($selectedEmployeeId);
} else {
    $attendanceHistory = [];
    $totalRecords = 0;
    $selectedEmployee = null;
}

$totalPages = ceil($totalRecords / $recordsPerPage);

// Lấy thống kê tháng nếu có employee được chọn
$monthlyStats = null;
if ($selectedEmployeeId) {
    $monthlyStats = getMonthlyStats($selectedEmployeeId, $selectedMonth);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử chấm công - Hệ thống chấm công</title>
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
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .stat-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .attendance-row {
            background: white;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: transform 0.2s ease;
        }
        
        .attendance-row:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .time-cell {
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo isAdmin() ? 'admin-dashboard.php' : 'dashboard.php'; ?>">
                <i class="bi bi-clock-history me-2"></i>
                Lịch sử chấm công
            </a>
            
            <div class="d-flex align-items-center ms-auto">

                
                <div class="navbar-nav d-flex align-items-center">
                <!-- User info - hiện luôn -->
                <span class="navbar-text me-3">
                    <i class="bi bi-<?php echo isAdmin() ? 'person-gear' : 'person-circle'; ?> me-1"></i>
                    <span class="d-none d-sm-inline"><?php echo htmlspecialchars($currentUser['employee_name'] ?? $currentUser['username']); ?></span>
                    <?php if (isAdmin()): ?>
                        <span class="badge bg-danger ms-1">ADMIN</span>
                    <?php else: ?>
                        <span class="badge bg-primary ms-1"><?php echo htmlspecialchars($currentUser['employee_code']); ?></span>
                    <?php endif; ?>
                </span>
                
                <!-- Desktop buttons - hiện từ màn hình nhỏ trở lên -->
                <div class="d-none d-sm-flex align-items-center">
                    <a href="<?php echo isAdmin() ? 'admin-dashboard.php' : 'dashboard.php'; ?>" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="bi bi-house me-1"></i>
                        <span class="d-none d-lg-inline">Trang chủ</span>
                    </a>
                    <?php if (isAdmin()): ?>
                        <a href="employees.php" class="btn btn-sm btn-outline-info me-2">
                            <i class="bi bi-people me-1"></i>
                            <span class="d-none d-lg-inline">Nhân viên</span>
                        </a>
                        <a href="export.php" class="btn btn-sm btn-outline-success me-2">
                            <i class="bi bi-file-earmark-excel me-1"></i>
                            <span class="d-none d-lg-inline">Xuất báo cáo</span>
                        </a>
                    <?php endif; ?>
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
                        <li><a class="dropdown-item" href="<?php echo isAdmin() ? 'admin-dashboard.php' : 'dashboard.php'; ?>">
                            <i class="bi bi-house me-2"></i>Trang chủ</a></li>
                        <?php if (isAdmin()): ?>
                            <li><a class="dropdown-item" href="employees.php"><i class="bi bi-people me-2"></i>Quản lý nhân viên</a></li>
                            <li><a class="dropdown-item" href="export.php"><i class="bi bi-file-earmark-excel me-2"></i>Xuất báo cáo</a></li>
                        <?php endif; ?>
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
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <?php if (isAdmin()): ?>
                        <div class="col-md-4">
                            <label for="employee_id" class="form-label">
                                <i class="bi bi-person me-1"></i>Nhân viên
                            </label>
                            <select class="form-select" id="employee_id" name="employee_id">
                                <option value="">-- Chọn nhân viên --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>" 
                                            <?php echo $selectedEmployeeId == $emp['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['employee_code'] . ' - ' . $emp['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="col-md-<?php echo isAdmin() ? '4' : '6'; ?>">
                        <label for="month" class="form-label">
                            <i class="bi bi-calendar me-1"></i>Tháng
                        </label>
                        <input type="month" class="form-control" id="month" name="month" value="<?php echo $selectedMonth; ?>">
                    </div>
                    
                    <div class="col-md-<?php echo isAdmin() ? '4' : '6'; ?> d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-search me-1"></i>Lọc
                        </button>
                        <a href="export.php<?php echo $selectedEmployeeId ? '?employee_id=' . $selectedEmployeeId . '&month=' . $selectedMonth : ''; ?>" 
                           class="btn btn-success">
                            <i class="bi bi-file-earmark-excel me-1"></i>Xuất Excel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($selectedEmployee && $monthlyStats): ?>
            <!-- Monthly Statistics -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-graph-up me-2"></i>
                        Thống kê tháng <?php echo date('m/Y', strtotime($selectedMonth)); ?>
                        <?php if ($selectedEmployee): ?>
                            - <?php echo htmlspecialchars($selectedEmployee['name']); ?>
                        <?php endif; ?>
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="stat-item">
                                <h4 class="text-primary"><?php echo $monthlyStats['total_days']; ?></h4>
                                <small class="text-muted">Tổng ngày công</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="stat-item">
                                <h4 class="text-success"><?php echo number_format($monthlyStats['total_hours'], 1); ?></h4>
                                <small class="text-muted">Tổng giờ làm</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="stat-item">
                                <h4 class="text-info"><?php echo number_format($monthlyStats['avg_hours'], 1); ?></h4>
                                <small class="text-muted">Giờ trung bình/ngày</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="stat-item">
                                <h4 class="text-warning"><?php echo $monthlyStats['complete_days']; ?></h4>
                                <small class="text-muted">Ngày hoàn thành</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Attendance History -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clock-history me-2"></i>
                        Lịch sử chấm công
                        <?php if ($totalRecords > 0): ?>
                            <span class="badge bg-primary"><?php echo $totalRecords; ?> bản ghi</span>
                        <?php endif; ?>
                    </h5>
                </div>
                
                <?php if (!empty($attendanceHistory)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th><i class="bi bi-calendar-date me-1"></i>Ngày</th>
                                    <th><i class="bi bi-clock me-1"></i>Giờ vào</th>
                                    <th><i class="bi bi-clock-fill me-1"></i>Giờ ra</th>
                                    <th><i class="bi bi-hourglass me-1"></i>Tổng giờ</th>
                                    <th><i class="bi bi-info-circle me-1"></i>Trạng thái</th>
                                    <th><i class="bi bi-chat-text me-1"></i>Ghi chú</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendanceHistory as $record): ?>
                                    <tr class="attendance-row">
                                        <td>
                                            <strong><?php echo date('d/m/Y', strtotime($record['date'])); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo getVietnameseDayName($record['date']); ?>
                                            </small>
                                        </td>
                                        <td class="time-cell">
                                            <?php if ($record['checkin_time']): ?>
                                                <span class="text-success">
                                                    <?php echo date('H:i:s', strtotime($record['checkin_time'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">--:--:--</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="time-cell">
                                            <?php if ($record['checkout_time']): ?>
                                                <span class="text-danger">
                                                    <?php echo date('H:i:s', strtotime($record['checkout_time'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">--:--:--</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($record['total_hours'] > 0): ?>
                                                <span class="badge bg-info"><?php echo number_format($record['total_hours'], 2); ?>h</span>
                                            <?php else: ?>
                                                <span class="text-muted">0h</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = [
                                                'complete' => 'bg-success',
                                                'incomplete' => 'bg-warning',
                                                'half_day' => 'bg-info'
                                            ];
                                            $statusText = [
                                                'complete' => 'Hoàn thành',
                                                'incomplete' => 'Chưa hoàn thành',
                                                'half_day' => 'Nửa ngày'
                                            ];
                                            ?>
                                            <span class="status-badge <?php echo $statusClass[$record['status']] ?? 'bg-secondary'; ?>">
                                                <?php echo $statusText[$record['status']] ?? 'Không xác định'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($record['checkin_note'] || $record['checkout_note']): ?>
                                                <small class="text-muted">
                                                    <?php if ($record['checkin_note']): ?>
                                                        <strong>Vào:</strong> <?php echo htmlspecialchars($record['checkin_note']); ?><br>
                                                    <?php endif; ?>
                                                    <?php if ($record['checkout_note']): ?>
                                                        <strong>Ra:</strong> <?php echo htmlspecialchars($record['checkout_note']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">--</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Pagination">
                            <ul class="pagination justify-content-center mt-4">
                                <?php if ($currentPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($currentPage < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                        <h4 class="text-muted mt-3">Không có dữ liệu chấm công</h4>
                        <p class="text-muted">
                            <?php if (isAdmin() && !$selectedEmployeeId): ?>
                                Vui lòng chọn nhân viên để xem lịch sử chấm công
                            <?php else: ?>
                                Không có bản ghi chấm công nào trong tháng này
                            <?php endif; ?>
                        </p>
                        <?php if (!isAdmin()): ?>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Bắt đầu chấm công
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
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
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html> 