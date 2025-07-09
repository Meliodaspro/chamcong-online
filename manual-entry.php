<?php
require_once 'includes/functions.php';

// Kiểm tra đăng nhập
requireLogin();
$currentUser = getCurrentUser();

// Lấy nhân viên được chọn
if (isAdmin()) {
    // Admin có thể chọn bất kỳ nhân viên nào
    $selectedEmployeeId = (int)($_GET['employee_id'] ?? 0);
} else {
    // Nhân viên chỉ được nhập công cho chính mình
    $selectedEmployeeId = $currentUser['employee_id'];
}

$selectedEmployee = null;

if ($selectedEmployeeId) {
    $selectedEmployee = getEmployeeById($selectedEmployeeId);
    if (!$selectedEmployee || $selectedEmployee['status'] !== 'active') {
        $selectedEmployeeId = null;
        $selectedEmployee = null;
    }
}

// Lấy danh sách nhân viên cho dropdown (chỉ admin mới thấy)
$employees = isAdmin() ? getAllEmployees() : [];

// Lấy tháng/năm từ URL hoặc mặc định tháng hiện tại
$currentMonth = (int)($_GET['month'] ?? date('m'));
$currentYear = (int)($_GET['year'] ?? date('Y'));

// Xử lý form submit
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedEmployeeId) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $date = $_POST['date'];
        $checkinTime = $_POST['checkin_time'] ?: null;
        $checkoutTime = $_POST['checkout_time'] ?: null;
        $checkinNote = $_POST['checkin_note'] ?? '';
        $checkoutNote = $_POST['checkout_note'] ?? '';
        $manualStatus = $_POST['manual_status'] ?? 'auto';
        
        $result = addManualAttendance($selectedEmployeeId, $date, $checkinTime, $checkoutTime, $checkinNote, $checkoutNote, $manualStatus);
        
        if ($result) {
            $message = 'Lưu dữ liệu chấm công thành công!';
            $messageType = 'success';
        } else {
            $message = 'Có lỗi xảy ra khi lưu dữ liệu!';
            $messageType = 'danger';
        }
    } elseif ($action === 'delete') {
        $date = $_POST['date'];
        $result = deleteAttendance($selectedEmployeeId, $date);
        
        if ($result) {
            $message = 'Xóa dữ liệu chấm công thành công!';
            $messageType = 'success';
        } else {
            $message = 'Có lỗi xảy ra khi xóa dữ liệu!';
            $messageType = 'danger';
        }
    }
}

// Lấy dữ liệu chấm công của tháng để hiển thị
$monthlyData = [];
if ($selectedEmployeeId) {
    $monthlyData = getMonthlyAttendanceData($selectedEmployeeId, $currentMonth, $currentYear);
}

// Tính số ngày trong tháng
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$firstDayOfWeek = date('N', strtotime("$currentYear-$currentMonth-01"));
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhập Công Thủ Công - Hệ Thống Chấm Công</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .calendar-cell {
            height: 120px;
            border: 1px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        .calendar-cell:hover {
            background-color: #f8f9fa;
            border-color: #007bff;
        }
        .calendar-cell.today {
            border-color: #007bff;
            border-width: 2px;
        }
        .calendar-cell.weekend {
            background-color: #f8f9fa;
        }
        .calendar-cell.has-data {
            background-color: #e3f2fd;
        }
        .calendar-cell.complete {
            background-color: #d4edda;
        }
        .calendar-cell.half-day {
            background-color: #fff3cd;
        }
        .calendar-cell.incomplete {
            background-color: #f8d7da;
        }
        .day-number {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .day-status {
            font-size: 0.8rem;
        }
        .employee-selector {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light" style="background: rgba(255, 255, 255, 0.95) !important; backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.2);">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= isAdmin() ? 'admin-dashboard.php' : 'dashboard.php' ?>">
                <i class="bi bi-clock-history me-2"></i>
                Hệ thống chấm công
            </a>
            
            <div class="d-flex align-items-center ms-auto">

                
                <div class="navbar-nav d-flex align-items-center">
                    <!-- User info - hiện luôn -->
                    <span class="navbar-text me-3">
                        <i class="bi bi-<?= isAdmin() ? 'person-gear' : 'person-circle' ?> me-1"></i>
                        <span class="d-none d-sm-inline"><?php echo htmlspecialchars($currentUser['employee_name'] ?? $currentUser['username']); ?></span>
                        <?php if (isAdmin()): ?>
                            <span class="badge bg-danger ms-1">ADMIN</span>
                        <?php else: ?>
                            <span class="badge bg-primary ms-1"><?php echo htmlspecialchars($currentUser['employee_code']); ?></span>
                        <?php endif; ?>
                    </span>
                    
                    <!-- Desktop buttons - hiện từ màn hình nhỏ trở lên -->
                    <div class="d-none d-sm-flex align-items-center">
                        <a href="<?= isAdmin() ? 'admin-dashboard.php' : 'dashboard.php' ?>" class="btn btn-sm btn-outline-secondary me-2">
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
                        <a href="history.php<?= $selectedEmployeeId ? '?employee_id=' . $selectedEmployeeId : '' ?>" class="btn btn-sm btn-outline-info me-2">
                            <i class="bi bi-clock-history me-1"></i>
                            <span class="d-none d-lg-inline">Lịch sử</span>
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
                            <li><a class="dropdown-item" href="<?= isAdmin() ? 'admin-dashboard.php' : 'dashboard.php' ?>">
                                <i class="bi bi-house me-2"></i>Trang chủ</a></li>
                            <?php if (isAdmin()): ?>
                                <li><a class="dropdown-item" href="employees.php"><i class="bi bi-people me-2"></i>Quản lý nhân viên</a></li>
                                <li><a class="dropdown-item" href="export.php"><i class="bi bi-file-earmark-excel me-2"></i>Xuất báo cáo</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="history.php<?= $selectedEmployeeId ? '?employee_id=' . $selectedEmployeeId : '' ?>">
                                <i class="bi bi-clock-history me-2"></i>Lịch sử chấm công</a></li>
                            <li><a class="dropdown-item active" href="manual-entry.php<?= $selectedEmployeeId ? '?employee_id=' . $selectedEmployeeId : '' ?>">
                                <i class="bi bi-calendar-plus me-2"></i>Nhập công thủ công</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Hiển thị thông báo -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$selectedEmployee && isAdmin()): ?>
            <!-- Chọn nhân viên (chỉ admin) -->
            <div class="employee-selector text-center">
                <h2 class="mb-4">
                    <i class="bi bi-calendar-plus"></i> Nhập Công Thủ Công
                </h2>
                <p class="mb-4">Chọn nhân viên để nhập công thủ công</p>
                
                <?php if (empty($employees)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> 
                        Chưa có nhân viên nào. <a href="employees.php" class="text-warning"><u>Thêm nhân viên</u></a>
                    </div>
                <?php else: ?>
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <select class="form-select form-select-lg" onchange="selectEmployee(this.value)">
                                <option value="">-- Chọn nhân viên --</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?= $employee['id'] ?>">
                                        <?= htmlspecialchars($employee['employee_code']) ?> - <?= htmlspecialchars($employee['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif (!$selectedEmployee && !isAdmin()): ?>
            <!-- Employee không có employee_id -->
            <div class="alert alert-danger text-center">
                <h4><i class="bi bi-exclamation-triangle"></i> Lỗi truy cập</h4>
                <p>Không tìm thấy thông tin nhân viên của bạn. Vui lòng liên hệ quản trị viên.</p>
                <a href="dashboard.php" class="btn btn-primary">Về trang chủ</a>
            </div>
        <?php else: ?>
            <!-- Header với thông tin nhân viên -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h4 class="mb-0">
                                <i class="bi bi-calendar-plus text-primary"></i> 
                                Nhập Công Thủ Công
                            </h4>
                            <p class="text-muted mb-0">
                                <strong><?= htmlspecialchars($selectedEmployee['name']) ?></strong> 
                                (<?= htmlspecialchars($selectedEmployee['employee_code']) ?>)
                                <?php if (!isAdmin()): ?>
                                    <span class="badge bg-info ms-2">Chính mình</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <?php if (isAdmin()): ?>
                                <select class="form-select d-inline-block w-auto me-2" onchange="selectEmployee(this.value)">
                                    <option value="">-- Đổi nhân viên --</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?= $employee['id'] ?>" <?= $employee['id'] == $selectedEmployeeId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($employee['employee_code']) ?> - <?= htmlspecialchars($employee['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            
                            <!-- Chọn tháng/năm -->
                            <div class="d-inline-block">
                                <select class="form-select d-inline-block w-auto" onchange="changeMonth(this.value)">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?= $i ?>" <?= $i == $currentMonth ? 'selected' : '' ?>>
                                            Tháng <?= $i ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <select class="form-select d-inline-block w-auto" onchange="changeYear(this.value)">
                                    <?php for ($year = date('Y') - 1; $year <= date('Y') + 1; $year++): ?>
                                        <option value="<?= $year ?>" <?= $year == $currentYear ? 'selected' : '' ?>>
                                            <?= $year ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chú thích -->
            <div class="card mb-4">
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap gap-1 justify-content-center">
                        <span class="badge bg-success">1 công</span>
                        <span class="badge bg-warning">Nửa ngày</span>
                        <span class="badge bg-info">Hôm nay</span>
                        <span class="badge bg-light text-dark">Chưa nhập</span>
                    </div>
                </div>
            </div>

            <!-- Calendar -->
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-calendar3"></i> 
                                Tháng <?= $currentMonth ?>/<?= $currentYear ?>
                            </h5>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="goToCurrentMonth()">
                                <i class="bi bi-calendar-today"></i> Tháng hiện tại
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center">Thứ 2</th>
                                    <th class="text-center">Thứ 3</th>
                                    <th class="text-center">Thứ 4</th>
                                    <th class="text-center">Thứ 5</th>
                                    <th class="text-center">Thứ 6</th>
                                    <th class="text-center">Thứ 7</th>
                                    <th class="text-center">Chủ nhật</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $day = 1;
                                $today = date('Y-m-d');
                                
                                // Tạo calendar
                                for ($week = 0; $week < 6; $week++) {
                                    if ($day > $daysInMonth) break;
                                    echo "<tr>";
                                    
                                    for ($dayOfWeek = 1; $dayOfWeek <= 7; $dayOfWeek++) {
                                        if (($week == 0 && $dayOfWeek < $firstDayOfWeek) || $day > $daysInMonth) {
                                            echo '<td class="calendar-cell"></td>';
                                        } else {
                                            $currentDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                                            $dayData = $monthlyData[$day] ?? null;
                                            
                                            // Xác định class CSS
                                            $classes = ['calendar-cell'];
                                            if ($currentDate === $today) $classes[] = 'today';
                                            if ($dayOfWeek >= 6) $classes[] = 'weekend';
                                            
                                            if ($dayData) {
                                                $classes[] = 'has-data';
                                                $classes[] = $dayData['status'];
                                            }
                                            
                                            echo '<td class="' . implode(' ', $classes) . '" onclick="openEditModal(\'' . $currentDate . '\')">';
                                            echo '<div class="p-2">';
                                            echo '<div class="day-number">' . $day . '</div>';
                                            
                                            if ($dayData) {
                                                echo '<div class="day-status">';
                                                echo '<small><i class="bi bi-clock"></i> ' . formatTime($dayData['checkin_time']) . '</small><br>';
                                                echo '<small><i class="bi bi-clock-fill"></i> ' . formatTime($dayData['checkout_time']) . '</small><br>';
                                                echo '<span class="badge bg-' . getStatusClass($dayData['status']) . ' badge-sm">' . getStatusText($dayData['status']) . '</span>';
                                                echo '</div>';
                                            }
                                            
                                            echo '</div>';
                                            echo '</td>';
                                            $day++;
                                        }
                                    }
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal nhập/sửa công -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-calendar-plus"></i> Nhập/Sửa Chấm Công
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="date" id="editDate">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ngày</label>
                                    <input type="text" class="form-control" id="displayDate" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Trạng thái</label>
                                    <select class="form-select" name="manual_status" id="manualStatus">
                                        <option value="auto">Tự động tính</option>
                                        <option value="complete">1 công</option>
                                        <option value="half_day">Nửa ngày</option>
                                        <option value="incomplete">Nghỉ không công</option>
                                        <option value="nghi_phep">Nghỉ phép</option>
                                        <option value="nghi_co_cong">Nghỉ có công</option>
                                        <option value="nghi_khong_cong">Nghỉ không công</option>
                                        <option value="nghi_khong_ly_do">Nghỉ không lý do</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Giờ vào</label>
                                    <input type="time" class="form-control" name="checkin_time" id="checkinTime">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Giờ ra</label>
                                    <input type="time" class="form-control" name="checkout_time" id="checkoutTime">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ghi chú check in</label>
                                    <textarea class="form-control" name="checkin_note" id="checkinNote" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ghi chú check out</label>
                                    <textarea class="form-control" name="checkout_note" id="checkoutNote" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" onclick="deleteEntry()" id="deleteBtn" style="display: none;">
                            <i class="bi bi-trash"></i> Xóa
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Lưu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal xác nhận xóa -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Xác nhận xóa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Bạn có chắc chắn muốn xóa dữ liệu chấm công ngày <strong id="deleteDateText"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="date" id="deleteDate">
                        <button type="submit" class="btn btn-danger">Xóa</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const monthlyData = <?= json_encode($monthlyData) ?>;
        
        function selectEmployee(employeeId) {
            if (employeeId) {
                <?php if (isAdmin()): ?>
                    window.location.href = 'manual-entry.php?employee_id=' + employeeId + '&month=<?= $currentMonth ?>&year=<?= $currentYear ?>';
                <?php else: ?>
                    // Nhân viên không thể đổi
                    alert('Bạn chỉ có thể nhập công cho chính mình.');
                <?php endif; ?>
            }
        }

        function changeMonth(month) {
            window.location.href = `manual-entry.php?employee_id=<?= $selectedEmployeeId ?>&month=${month}&year=<?= $currentYear ?>`;
        }

        function changeYear(year) {
            window.location.href = `manual-entry.php?employee_id=<?= $selectedEmployeeId ?>&month=<?= $currentMonth ?>&year=${year}`;
        }

        function goToCurrentMonth() {
            const now = new Date();
            window.location.href = `manual-entry.php?employee_id=<?= $selectedEmployeeId ?>&month=${now.getMonth() + 1}&year=${now.getFullYear()}`;
        }

        function openEditModal(date) {
            const day = parseInt(date.split('-')[2]);
            const dayData = monthlyData[day] || {};
            
            document.getElementById('editDate').value = date;
            document.getElementById('displayDate').value = formatDateDisplay(date);
            document.getElementById('checkinTime').value = dayData.checkin_time || '';
            document.getElementById('checkoutTime').value = dayData.checkout_time || '';
            document.getElementById('checkinNote').value = dayData.checkin_note || '';
            document.getElementById('checkoutNote').value = dayData.checkout_note || '';
            document.getElementById('manualStatus').value = 'auto';
            
            // Hiển thị nút xóa nếu có dữ liệu
            const deleteBtn = document.getElementById('deleteBtn');
            if (dayData.checkin_time || dayData.checkout_time) {
                deleteBtn.style.display = 'inline-block';
            } else {
                deleteBtn.style.display = 'none';
            }
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function deleteEntry() {
            const date = document.getElementById('editDate').value;
            document.getElementById('deleteDate').value = date;
            document.getElementById('deleteDateText').textContent = formatDateDisplay(date);
            
            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function formatDateDisplay(date) {
            const d = new Date(date);
            const days = ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
            return `${days[d.getDay()]} - ${d.getDate()}/${d.getMonth() + 1}/${d.getFullYear()}`;
        }
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
    
    <!-- Developer Button -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1000;">
        <button class="btn btn-dark btn-sm rounded-pill shadow" type="button" data-bs-toggle="tooltip" data-bs-placement="left" title="Phát triển bởi Nguyễn Hồng Sơn">
            <i class="bi bi-code-slash me-1"></i>
            <small>dev by <strong>Nguyễn Hồng Sơn</strong></small>
        </button>
    </div>
</body>
</html> 