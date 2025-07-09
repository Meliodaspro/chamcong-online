<?php
require_once 'includes/functions.php';

// Xử lý các hành động
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $result = addEmployee($_POST['employee_code'], $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['position']);
            $message = $result ? 'Thêm nhân viên thành công!' : 'Có lỗi xảy ra khi thêm nhân viên!';
            $messageType = $result ? 'success' : 'danger';
            break;
            
        case 'update':
            $result = updateEmployee($_POST['id'], $_POST['employee_code'], $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['position'], $_POST['status']);
            $message = $result ? 'Cập nhật thông tin thành công!' : 'Có lỗi xảy ra khi cập nhật!';
            $messageType = $result ? 'success' : 'danger';
            break;
            
        case 'delete':
            $result = deleteEmployee($_POST['id']);
            $message = $result ? 'Vô hiệu hóa nhân viên thành công!' : 'Có lỗi xảy ra khi vô hiệu hóa!';
            $messageType = $result ? 'success' : 'danger';
            break;
    }
}

// Lấy danh sách tất cả nhân viên (bao gồm cả inactive)
try {
    $stmt = $pdo->prepare("SELECT * FROM employees ORDER BY status DESC, employee_code");
    $stmt->execute();
    $employees = $stmt->fetchAll();
} catch (Exception $e) {
    $employees = [];
    $message = 'Có lỗi khi tải danh sách nhân viên!';
    $messageType = 'danger';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Nhân Viên - Hệ Thống Chấm Công</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-clock-history"></i> Hệ Thống Chấm Công
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-house"></i> Trang Chủ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="employees.php">
                            <i class="bi bi-people"></i> Quản Lý Nhân Viên
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="history.php">
                            <i class="bi bi-calendar-check"></i> Lịch Sử
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="export.php">
                            <i class="bi bi-file-earmark-excel"></i> Xuất Excel
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="bi bi-gear"></i> Cài Đặt
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Hiển thị thông báo -->
        <?php if (isset($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <!-- Form thêm/sửa nhân viên -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-person-plus"></i> Thêm Nhân Viên Mới
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="employeeForm">
                            <input type="hidden" name="action" value="add" id="formAction">
                            <input type="hidden" name="id" id="employeeId">
                            
                            <div class="mb-3">
                                <label for="employee_code" class="form-label">Mã NV *</label>
                                <input type="text" class="form-control" name="employee_code" id="employee_code" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Họ và Tên *</label>
                                <input type="text" class="form-control" name="name" id="name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="email">
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Điện Thoại</label>
                                <input type="text" class="form-control" name="phone" id="phone">
                            </div>
                            
                            <div class="mb-3">
                                <label for="position" class="form-label">Chức Vụ</label>
                                <input type="text" class="form-control" name="position" id="position" value="Nhân viên">
                            </div>
                            
                            <div class="mb-3" id="statusGroup" style="display: none;">
                                <label for="status" class="form-label">Trạng Thái</label>
                                <select class="form-select" name="status" id="status">
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Vô hiệu hóa</option>
                                </select>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="bi bi-plus-circle"></i> Thêm Nhân Viên
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="resetForm()" id="cancelBtn" style="display: none;">
                                    <i class="bi bi-x-circle"></i> Hủy
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <!-- Danh sách nhân viên -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-people"></i> Danh Sách Nhân Viên
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($employees)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2">Chưa có nhân viên nào</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Mã NV</th>
                                            <th>Họ Tên</th>
                                            <th>Email</th>
                                            <th>Điện Thoại</th>
                                            <th>Chức Vụ</th>
                                            <th>Trạng Thái</th>
                                            <th>Thao Tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employees as $emp): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($emp['employee_code']) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($emp['name']) ?></td>
                                                <td>
                                                    <?php if ($emp['email']): ?>
                                                        <a href="mailto:<?= htmlspecialchars($emp['email']) ?>">
                                                            <?= htmlspecialchars($emp['email']) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">--</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($emp['phone']): ?>
                                                        <a href="tel:<?= htmlspecialchars($emp['phone']) ?>">
                                                            <?= htmlspecialchars($emp['phone']) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">--</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($emp['position'] ?: '--') ?></td>
                                                <td>
                                                    <?php if ($emp['status'] === 'active'): ?>
                                                        <span class="badge bg-success">Hoạt động</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Vô hiệu hóa</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                onclick="editEmployee(<?= htmlspecialchars(json_encode($emp)) ?>)">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <?php if ($emp['status'] === 'active'): ?>
                                                            <button type="button" class="btn btn-outline-danger" 
                                                                    onclick="confirmDelete(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['name']) ?>')">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal xác nhận xóa -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Xác Nhận Vô Hiệu Hóa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Bạn có chắc chắn muốn vô hiệu hóa nhân viên <strong id="employeeName"></strong>?</p>
                    <p class="text-muted small">Nhân viên sẽ không thể chấm công nhưng dữ liệu lịch sử sẽ được giữ lại.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteEmployeeId">
                        <button type="submit" class="btn btn-danger">Vô Hiệu Hóa</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editEmployee(employee) {
            document.getElementById('formAction').value = 'update';
            document.getElementById('employeeId').value = employee.id;
            document.getElementById('employee_code').value = employee.employee_code;
            document.getElementById('name').value = employee.name;
            document.getElementById('email').value = employee.email || '';
            document.getElementById('phone').value = employee.phone || '';
            document.getElementById('position').value = employee.position || '';
            document.getElementById('status').value = employee.status;
            
            document.getElementById('statusGroup').style.display = 'block';
            document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-circle"></i> Cập Nhật';
            document.getElementById('cancelBtn').style.display = 'block';
            document.querySelector('.card-title').innerHTML = '<i class="bi bi-pencil"></i> Sửa Thông Tin Nhân Viên';
        }

        function resetForm() {
            document.getElementById('employeeForm').reset();
            document.getElementById('formAction').value = 'add';
            document.getElementById('employeeId').value = '';
            document.getElementById('position').value = 'Nhân viên';
            
            document.getElementById('statusGroup').style.display = 'none';
            document.getElementById('submitBtn').innerHTML = '<i class="bi bi-plus-circle"></i> Thêm Nhân Viên';
            document.getElementById('cancelBtn').style.display = 'none';
            document.querySelector('.card-title').innerHTML = '<i class="bi bi-person-plus"></i> Thêm Nhân Viên Mới';
        }

        function confirmDelete(id, name) {
            document.getElementById('deleteEmployeeId').value = id;
            document.getElementById('employeeName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
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