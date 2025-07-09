<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Xử lý reset dữ liệu
if (isset($_POST['reset_data'])) {
    try {
        $pdo->exec("TRUNCATE TABLE attendance");
        $message = "Đã xóa toàn bộ dữ liệu chấm công thành công!";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "Có lỗi xảy ra khi xóa dữ liệu: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Lấy thông tin thống kê
try {
    $totalRecords = $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();
    $oldestRecord = $pdo->query("SELECT MIN(date) FROM attendance")->fetchColumn();
    $newestRecord = $pdo->query("SELECT MAX(date) FROM attendance")->fetchColumn();
    
    // Thống kê database
    $dbSize = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB' 
                          FROM information_schema.tables 
                          WHERE table_schema = '" . DB_NAME . "'")->fetchColumn();
} catch (Exception $e) {
    $totalRecords = 0;
    $oldestRecord = null;
    $newestRecord = null;
    $dbSize = 0;
}

// Thông tin PHP
$phpVersion = phpversion();
$mysqlVersion = $pdo->query("SELECT VERSION()")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài Đặt Hệ Thống</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .settings-card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .danger-zone {
            border: 2px solid #dc3545;
            border-radius: 10px;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                        <li class="breadcrumb-item active">Cài đặt</li>
                    </ol>
                </nav>
                <h1><i class="fas fa-cog text-primary"></i> Cài Đặt Hệ Thống</h1>
            </div>
        </div>

        <!-- Thông báo -->
        <?php if (isset($message)): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Thông tin hệ thống -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card info-card">
                    <div class="card-body">
                        <h5><i class="fas fa-info-circle"></i> Thông Tin Hệ Thống</h5>
                        <table class="table table-borderless text-white">
                            <tr>
                                <td>Phiên bản PHP:</td>
                                <td><strong><?= $phpVersion ?></strong></td>
                            </tr>
                            <tr>
                                <td>Phiên bản MySQL:</td>
                                <td><strong><?= $mysqlVersion ?></strong></td>
                            </tr>
                            <tr>
                                <td>Database:</td>
                                <td><strong><?= DB_NAME ?></strong></td>
                            </tr>
                            <tr>
                                <td>Kích thước DB:</td>
                                <td><strong><?= $dbSize ?: '0' ?> MB</strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card info-card">
                    <div class="card-body">
                        <h5><i class="fas fa-chart-bar"></i> Thống Kê Dữ Liệu</h5>
                        <table class="table table-borderless text-white">
                            <tr>
                                <td>Tổng bản ghi:</td>
                                <td><strong><?= $totalRecords ?></strong></td>
                            </tr>
                            <tr>
                                <td>Ngày đầu tiên:</td>
                                <td><strong><?= $oldestRecord ? formatDate($oldestRecord) : 'Chưa có' ?></strong></td>
                            </tr>
                            <tr>
                                <td>Ngày gần nhất:</td>
                                <td><strong><?= $newestRecord ? formatDate($newestRecord) : 'Chưa có' ?></strong></td>
                            </tr>
                            <tr>
                                <td>Trạng thái:</td>
                                <td><strong><i class="fas fa-check-circle"></i> Hoạt động tốt</strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cấu hình database -->
        <div class="card settings-card mb-4">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-database"></i> Cấu Hình Database</h5>
                <p class="text-muted">Thông tin kết nối database hiện tại</p>
                
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-striped">
                            <tr>
                                <td><strong>Host:</strong></td>
                                <td><?= DB_HOST ?></td>
                            </tr>
                            <tr>
                                <td><strong>Database:</strong></td>
                                <td><?= DB_NAME ?></td>
                            </tr>
                            <tr>
                                <td><strong>User:</strong></td>
                                <td><?= DB_USER ?></td>
                            </tr>
                            <tr>
                                <td><strong>Kết nối:</strong></td>
                                <td><span class="badge bg-success">Thành công</span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Lưu ý:</strong> Để thay đổi cấu hình database, 
                            vui lòng chỉnh sửa file <code>config/database.php</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hướng dẫn sử dụng -->
        <div class="card settings-card mb-4">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-question-circle"></i> Hướng Dẫn Sử Dụng</h5>
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-play-circle text-primary"></i> Chấm công hàng ngày:</h6>
                        <ul class="list-unstyled ms-3">
                            <li>• Bấm <strong>Check In</strong> khi bắt đầu làm việc</li>
                            <li>• Bấm <strong>Check Out</strong> khi kết thúc làm việc</li>
                            <li>• Có thể thêm ghi chú cho mỗi lần chấm</li>
                            <li>• Hệ thống tự động tính toán số công</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-chart-line text-success"></i> Tính công:</h6>
                        <ul class="list-unstyled ms-3">
                            <li>• <strong>1 công:</strong> Check in + Check out đủ 8h</li>
                            <li>• <strong>0.5 công:</strong> Thiếu 1 trong 2 mốc hoặc < 8h</li>
                            <li>• <strong>0 công:</strong> Không chấm công</li>
                            <li>• Xem lịch sử và xuất CSV theo tháng</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vùng nguy hiểm -->
        <div class="card settings-card danger-zone">
            <div class="card-body">
                <h5 class="card-title text-danger">
                    <i class="fas fa-exclamation-triangle"></i> Vùng Nguy Hiểm
                </h5>
                <p class="text-muted">Các thao tác dưới đây không thể hoàn tác!</p>
                
                <div class="alert alert-warning">
                    <i class="fas fa-trash-alt"></i>
                    <strong>Xóa toàn bộ dữ liệu chấm công</strong>
                    <p class="mb-2 mt-2">Thao tác này sẽ xóa tất cả dữ liệu chấm công đã lưu. Bạn có chắc chắn?</p>
                    
                    <form method="POST" style="display: inline;">
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
                            <i class="fas fa-trash-alt"></i> Xóa Toàn Bộ Dữ Liệu
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Nút về trang chủ -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Về trang chủ
                </a>
            </div>
        </div>
    </div>

    <!-- Modal xác nhận xóa -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle"></i> Xác Nhận Xóa Dữ Liệu
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong>Cảnh báo!</strong> Thao tác này sẽ xóa vĩnh viễn tất cả dữ liệu chấm công.
                    </div>
                    <p>Bạn có chắc chắn muốn xóa <strong><?= $totalRecords ?></strong> bản ghi chấm công?</p>
                    <p class="text-muted">Thao tác này không thể hoàn tác!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Hủy
                    </button>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="reset_data" class="btn btn-danger">
                            <i class="fas fa-trash-alt"></i> Xóa Ngay
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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