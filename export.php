<?php
require_once 'includes/functions.php';

// Kiểm tra khi export để tránh output lỗi
$isExporting = isset($_POST['export']);
if ($isExporting) {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
}

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Font;

// Kiểm tra đăng nhập
requireLogin();
$currentUser = getCurrentUser();

// Lấy nhân viên được chọn
$selectedEmployeeId = (int)($_GET['employee_id'] ?? 0);
$selectedEmployee = null;

if ($selectedEmployeeId) {
    $selectedEmployee = getEmployeeById($selectedEmployeeId);
    if (!$selectedEmployee || $selectedEmployee['status'] !== 'active') {
        $selectedEmployeeId = null;
        $selectedEmployee = null;
    }
}

// Lấy danh sách nhân viên
$employees = getAllEmployees();

// Xử lý xuất Excel với định dạng đẹp
if (isset($_POST['export']) && isset($_POST['start_date']) && isset($_POST['end_date'])) {
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $exportType = $_POST['export_type'] ?? 'single';
    $exportFormat = $_POST['export_format'] ?? 'summary';
    $exportEmployeeId = $_POST['export_employee_id'] ?? $selectedEmployeeId;
    
    try {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Lấy dữ liệu và cấu hình headers
        if ($exportFormat === 'summary') {
            // Định dạng tổng hợp
            if ($exportType === 'all') {
                $sheet->setTitle('Tong_Hop_Tat_Ca_NV');
                $title = 'BÁO CÁO TỔNG HỢP CHẤM CÔNG TẤT CẢ NHÂN VIÊN';
                $data = getEmployeesSummaryForExport($startDate, $endDate);
            } else {
                $employee = getEmployeeById($exportEmployeeId);
                if (!$employee) {
                    throw new Exception("Không tìm thấy thông tin nhân viên");
                }
                $sheet->setTitle('Tong_Hop_' . preg_replace('/[^A-Za-z0-9_]/', '_', $employee['employee_code'] ?? 'NV'));
                $title = 'BÁO CÁO TỔNG HỢP CHẤM CÔNG: ' . ($employee['name'] ?? 'N/A');
                $data = getEmployeeSummaryForExport($exportEmployeeId, $startDate, $endDate);
            }
            $headers = ['STT', 'Mã NV', 'Tên NV', 'Chức Vụ', 'Ngày Làm Việc', 'Tổng Giờ', 'Ngày Hoàn Thành', 'Ngày Nửa Công', 'Ngày Thiếu', 'TB Giờ/Ngày', 'Tỷ Lệ Hoàn Thành (%)', 'Tỷ Lệ Chuyên Cần (%)'];
        } else {
            // Định dạng chi tiết
            if ($exportType === 'all') {
                $sheet->setTitle('Chi_Tiet_Tat_Ca_NV');
                $title = 'BẢNG CHẤM CÔNG CHI TIẾT TẤT CẢ NHÂN VIÊN';
                $data = getAllEmployeesAttendanceForExport($startDate, $endDate);
                $headers = ['STT', 'Mã NV', 'Tên NV', 'Ngày', 'Thứ', 'Check In', 'Check Out', 'Tổng Giờ', 'Trạng Thái', 'Ghi Chú'];
            } else {
                $employee = getEmployeeById($exportEmployeeId);
                if (!$employee) {
                    throw new Exception("Không tìm thấy thông tin nhân viên");
                }
                $sheet->setTitle(preg_replace('/[^A-Za-z0-9_]/', '_', $employee['employee_code'] ?? 'Chi_Tiet'));
                $title = 'BẢNG CHẤM CÔNG CHI TIẾT: ' . ($employee['name'] ?? 'N/A');
                $data = getAttendanceForExport($exportEmployeeId, $startDate, $endDate);
                $headers = ['STT', 'Ngày', 'Thứ', 'Check In', 'Check Out', 'Tổng Giờ', 'Trạng Thái', 'Ghi Chú Check In', 'Ghi Chú Check Out'];
            }
        }
        
        // Kiểm tra dữ liệu
        if (empty($data)) {
            throw new Exception("Không có dữ liệu để xuất trong khoảng thời gian đã chọn");
        }
        
        // Logo/Header chính - dòng 1
        $sheet->setCellValue('A1', $title);
        if ($exportFormat === 'summary') {
            $lastCol = 'L'; // 12 cột cho format tổng hợp
        } else {
            $lastCol = ($exportType === 'all') ? 'J' : 'I'; // Format chi tiết
        }
        $sheet->mergeCells('A1:' . $lastCol . '1');
        
        // Thông tin thời gian - dòng 2
        $sheet->setCellValue('A2', 'Từ ngày: ' . date('d/m/Y', strtotime($startDate)) . ' đến ngày: ' . date('d/m/Y', strtotime($endDate)));
        $sheet->mergeCells('A2:' . $lastCol . '2');
        
        // Thông tin xuất - dòng 3
        $userName = $currentUser['employee_name'] ?? $currentUser['username'] ?? 'Admin';
        $sheet->setCellValue('A3', 'Xuất lúc: ' . date('d/m/Y H:i:s') . ' bởi ' . $userName);
        $sheet->mergeCells('A3:' . $lastCol . '3');
        
        // Header bảng - dòng 5
        $row = 5;
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, $row, $header);
            $col++;
        }
        
        // Dữ liệu - từ dòng 6
        $row = 6;
        $stt = 1;
        
        foreach ($data as $record) {
            $col = 1;
            
            if ($exportFormat === 'summary') {
                // Format tổng hợp
                $sheet->setCellValueByColumnAndRow($col++, $row, $stt);
                $sheet->setCellValueByColumnAndRow($col++, $row, $record['employee_code'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $row, $record['employee_name'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $row, $record['position'] ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $row, intval($record['total_work_days'] ?? 0));
                $sheet->setCellValueByColumnAndRow($col++, $row, round(floatval($record['total_hours'] ?? 0), 2));
                $sheet->setCellValueByColumnAndRow($col++, $row, intval($record['complete_days'] ?? 0));
                $sheet->setCellValueByColumnAndRow($col++, $row, intval($record['half_days'] ?? 0));
                $sheet->setCellValueByColumnAndRow($col++, $row, intval($record['incomplete_days'] ?? 0));
                $sheet->setCellValueByColumnAndRow($col++, $row, round(floatval($record['avg_hours_per_day'] ?? 0), 2));
                $sheet->setCellValueByColumnAndRow($col++, $row, round(floatval($record['completion_rate'] ?? 0), 2));
                $sheet->setCellValueByColumnAndRow($col++, $row, round(floatval($record['attendance_rate'] ?? 0), 2));
            } else {
                // Format chi tiết
                if ($exportType === 'all') {
                    $sheet->setCellValueByColumnAndRow($col++, $row, $stt);
                    $sheet->setCellValueByColumnAndRow($col++, $row, $record['employee_code'] ?? '');
                    $sheet->setCellValueByColumnAndRow($col++, $row, $record['employee_name'] ?? '');
                    $sheet->setCellValueByColumnAndRow($col++, $row, isset($record['date']) ? date('d/m/Y', strtotime($record['date'])) : '');
                    $sheet->setCellValueByColumnAndRow($col++, $row, isset($record['date']) ? getVietnameseDayName($record['date']) : '');
                    $sheet->setCellValueByColumnAndRow($col++, $row, formatTime($record['checkin_time'] ?? null));
                    $sheet->setCellValueByColumnAndRow($col++, $row, formatTime($record['checkout_time'] ?? null));
                    $sheet->setCellValueByColumnAndRow($col++, $row, round(floatval($record['total_hours'] ?? 0), 2));
                    $sheet->setCellValueByColumnAndRow($col++, $row, getStatusText($record['status'] ?? ''));
                    
                    $notes = array_filter([$record['checkin_note'] ?? '', $record['checkout_note'] ?? '']);
                    $sheet->setCellValueByColumnAndRow($col++, $row, implode(' | ', $notes));
                } else {
                    $sheet->setCellValueByColumnAndRow($col++, $row, $stt);
                    $sheet->setCellValueByColumnAndRow($col++, $row, isset($record['date']) ? date('d/m/Y', strtotime($record['date'])) : '');
                    $sheet->setCellValueByColumnAndRow($col++, $row, isset($record['date']) ? getVietnameseDayName($record['date']) : '');
                    $sheet->setCellValueByColumnAndRow($col++, $row, formatTime($record['checkin_time'] ?? null));
                    $sheet->setCellValueByColumnAndRow($col++, $row, formatTime($record['checkout_time'] ?? null));
                    $sheet->setCellValueByColumnAndRow($col++, $row, round(floatval($record['total_hours'] ?? 0), 2));
                    $sheet->setCellValueByColumnAndRow($col++, $row, getStatusText($record['status'] ?? ''));
                    $sheet->setCellValueByColumnAndRow($col++, $row, $record['checkin_note'] ?? '');
                    $sheet->setCellValueByColumnAndRow($col++, $row, $record['checkout_note'] ?? '');
                }
            }
            
            $row++;
            $stt++;
        }
        
        $lastRow = $row - 1;
        
        // === ĐỊNH DẠNG EXCEL ĐẸP ===
        
        // 1. Style cho tiêu đề chính
        $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
            'font' => [
                'bold' => true, 
                'size' => 18,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID, 
                'color' => ['rgb' => '2E5BBA']
            ]
        ]);
        
        // 2. Style cho thông tin thời gian
        $sheet->getStyle('A2:' . $lastCol . '3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID, 
                'color' => ['rgb' => 'E8F4FD']
            ]
        ]);
        
        // 3. Style cho header bảng
        $sheet->getStyle('A5:' . $lastCol . '5')->applyFromArray([
            'font' => [
                'bold' => true, 
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID, 
                'color' => ['rgb' => '4472C4']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['rgb' => '2E5BBA']
                ]
            ]
        ]);
        
        // 4. Style cho dữ liệu với màu xen kẽ
        for ($i = 6; $i <= $lastRow; $i++) {
            $fillColor = ($i % 2 == 0) ? 'F2F2F2' : 'FFFFFF';
            $sheet->getStyle('A' . $i . ':' . $lastCol . $i)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => $fillColor]
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
            ]);
        }
        
        // 5. Style đặc biệt cho các cột quan trọng
        if ($exportFormat === 'summary') {
            // Style cho format tổng hợp
            // Tô màu cho cột tỷ lệ hoàn thành (K) và tỷ lệ chuyên cần (L)
            for ($i = 6; $i <= $lastRow; $i++) {
                // Tỷ lệ hoàn thành
                $completionRate = $sheet->getCell('K' . $i)->getValue();
                $completionColor = '000000';
                if ($completionRate >= 90) {
                    $completionColor = '28A745'; // Xanh lá
                } elseif ($completionRate >= 70) {
                    $completionColor = 'FFC107'; // Vàng
                } else {
                    $completionColor = 'DC3545'; // Đỏ
                }
                
                $sheet->getStyle('K' . $i)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => $completionColor]
                    ]
                ]);
                
                // Tỷ lệ chuyên cần
                $attendanceRate = $sheet->getCell('L' . $i)->getValue();
                $attendanceColor = '000000';
                if ($attendanceRate >= 95) {
                    $attendanceColor = '28A745'; // Xanh lá
                } elseif ($attendanceRate >= 80) {
                    $attendanceColor = 'FFC107'; // Vàng
                } else {
                    $attendanceColor = 'DC3545'; // Đỏ
                }
                
                $sheet->getStyle('L' . $i)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => $attendanceColor]
                    ]
                ]);
            }
        } else {
            // Style cho format chi tiết - cột trạng thái
            $statusCol = $exportType === 'all' ? 'I' : 'G';
            for ($i = 6; $i <= $lastRow; $i++) {
                $status = $sheet->getCell($statusCol . $i)->getValue();
                $statusColor = '000000'; // Màu đen mặc định
                
                if ($status === '1 công') {
                    $statusColor = '28A745'; // Xanh lá
                } elseif ($status === 'Nửa ngày') {
                    $statusColor = 'FFC107'; // Vàng
                } elseif ($status === 'Thiếu') {
                    $statusColor = 'DC3545'; // Đỏ
                }
                
                $sheet->getStyle($statusCol . $i)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => $statusColor]
                    ]
                ]);
            }
        }
        
        // 6. Auto width cho tất cả cột
        foreach (range('A', $lastCol) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // 7. Set chiều cao cho header
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(5)->setRowHeight(25);
        
        // 8. Căn giữa cột STT và các cột số
        $sheet->getStyle('A6:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        if ($exportFormat === 'summary') {
            // Căn giữa các cột số trong format tổng hợp
            $sheet->getStyle('E6:L' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        } else {
            // Căn giữa cột số giờ trong format chi tiết
            if ($exportType === 'all') {
                $sheet->getStyle('H6:H' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            } else {
                $sheet->getStyle('F6:F' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }
        
        // Tên file
        $formatPrefix = $exportFormat === 'summary' ? 'TongHop_' : 'ChiTiet_';
        
        if ($exportType === 'all') {
            $filename = $formatPrefix . 'TatCa_' . date('dmY', strtotime($startDate)) . '_' . date('dmY', strtotime($endDate)) . '.xlsx';
        } else {
            $filename = $formatPrefix . ($employee['employee_code'] ?? 'NV') . '_' . date('dmY', strtotime($startDate)) . '_' . date('dmY', strtotime($endDate)) . '.xlsx';
        }
        
        // Clean output buffer để tránh lỗi
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Xuất file với headers đầy đủ
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
        
    } catch (Exception $e) {
        // Log lỗi để debug
        error_log("Excel Export Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        $error = 'Có lỗi xảy ra khi xuất file Excel: ' . $e->getMessage();
        
        // Nếu đã có headers được gửi, không thể redirect
        if (headers_sent()) {
            echo '<script>alert("Lỗi xuất Excel: ' . addslashes($e->getMessage()) . '"); history.back();</script>';
            exit;
        }
    }
}

// Lấy dữ liệu preview
$previewData = [];
$previewFormat = '';
if (isset($_POST['preview']) && isset($_POST['start_date']) && isset($_POST['end_date'])) {
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $exportType = $_POST['export_type'] ?? 'single';
    $exportFormat = $_POST['export_format'] ?? 'summary';
    $exportEmployeeId = $_POST['export_employee_id'] ?? $selectedEmployeeId;
    $previewFormat = $exportFormat;
    
    if ($exportFormat === 'summary') {
        // Xuất dạng tổng hợp
        if ($exportType === 'all') {
            $previewData = getEmployeesSummaryForExport($startDate, $endDate);
        } else {
            $previewData = getEmployeeSummaryForExport($exportEmployeeId, $startDate, $endDate);
        }
    } else {
        // Xuất dạng chi tiết
        if ($exportType === 'all') {
            $previewData = getAllEmployeesAttendanceForExport($startDate, $endDate);
        } else {
            $previewData = getAttendanceForExport($exportEmployeeId, $startDate, $endDate);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xuất Excel - Hệ Thống Chấm Công</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .employee-selector {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .preview-table {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
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
                        <a class="nav-link" href="employees.php">
                            <i class="bi bi-people"></i> Quản Lý Nhân Viên
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="history.php?employee_id=<?= $selectedEmployeeId ?>">
                            <i class="bi bi-calendar-check"></i> Lịch Sử
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manual-entry.php?employee_id=<?= $selectedEmployeeId ?>">
                            <i class="bi bi-calendar-plus"></i> Nhập Thủ Công
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="export.php">
                            <i class="bi bi-file-earmark-excel"></i> Xuất Excel
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="bi bi-gear"></i> Cài Đặt
                        </a>
                    </li>
                </ul>
                <?php if ($selectedEmployee): ?>
                    <span class="navbar-text">
                        <i class="bi bi-person-check"></i> 
                        <?= htmlspecialchars($selectedEmployee['name']) ?>
                        <small>(<?= htmlspecialchars($selectedEmployee['employee_code']) ?>)</small>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Hiển thị lỗi -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="employee-selector text-center">
            <h2 class="mb-4">
                                    <i class="bi bi-file-earmark-excel"></i> Xuất Báo Cáo Excel
                </h2>
                <p class="mb-0">Xuất dữ liệu chấm công ra file Excel theo khoảng thời gian</p>
        </div>

        <div class="row">
            <div class="col-md-6">
                <!-- Form xuất CSV -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-download"></i> Cấu Hình Xuất File
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Loại xuất</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="export_type" id="singleEmployee" value="single" checked onchange="toggleEmployeeSelect()">
                                    <label class="form-check-label" for="singleEmployee">
                                        Nhân viên cụ thể
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="export_type" id="allEmployees" value="all" onchange="toggleEmployeeSelect()">
                                    <label class="form-check-label" for="allEmployees">
                                        Tất cả nhân viên
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Định dạng dữ liệu</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="export_format" id="detailFormat" value="detail">
                                    <label class="form-check-label" for="detailFormat">
                                        Chi tiết (từng ngày)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="export_format" id="summaryFormat" value="summary" checked>
                                    <label class="form-check-label" for="summaryFormat">
                                        Tổng hợp (1 dòng/nhân viên)
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3" id="employeeSelectGroup">
                                <label class="form-label">Chọn nhân viên</label>
                                <select class="form-select" name="export_employee_id" id="exportEmployeeId">
                                    <?php if ($selectedEmployee): ?>
                                        <option value="<?= $selectedEmployee['id'] ?>" selected>
                                            <?= htmlspecialchars($selectedEmployee['employee_code']) ?> - <?= htmlspecialchars($selectedEmployee['name']) ?>
                                        </option>
                                    <?php endif; ?>
                                    <?php foreach ($employees as $employee): ?>
                                        <?php if (!$selectedEmployee || $employee['id'] != $selectedEmployeeId): ?>
                                            <option value="<?= $employee['id'] ?>">
                                                <?= htmlspecialchars($employee['employee_code']) ?> - <?= htmlspecialchars($employee['name']) ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Từ ngày</label>
                                        <input type="date" class="form-control" name="start_date" 
                                               value="<?= $_POST['start_date'] ?? date('Y-m-01') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Đến ngày</label>
                                        <input type="date" class="form-control" name="end_date" 
                                               value="<?= $_POST['end_date'] ?? date('Y-m-t') ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="preview" class="btn btn-info">
                                    <i class="bi bi-eye"></i> Xem Trước
                                </button>
                                <button type="submit" name="export" class="btn btn-success">
                                    <i class="bi bi-file-earmark-excel"></i> Tải Excel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Quick actions -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-lightning"></i> Xuất Nhanh
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="export_type" value="<?= $selectedEmployee ? 'single' : 'all' ?>">
                                <input type="hidden" name="export_format" value="summary">
                                <?php if ($selectedEmployee): ?>
                                    <input type="hidden" name="export_employee_id" value="<?= $selectedEmployeeId ?>">
                                <?php endif; ?>
                                <input type="hidden" name="start_date" value="<?= date('Y-m-01') ?>">
                                <input type="hidden" name="end_date" value="<?= date('Y-m-t') ?>">
                                <button type="submit" name="export" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="bi bi-calendar-month"></i> Tháng hiện tại (Tổng hợp)
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="export_type" value="<?= $selectedEmployee ? 'single' : 'all' ?>">
                                <input type="hidden" name="export_format" value="summary">
                                <?php if ($selectedEmployee): ?>
                                    <input type="hidden" name="export_employee_id" value="<?= $selectedEmployeeId ?>">
                                <?php endif; ?>
                                <input type="hidden" name="start_date" value="<?= date('Y-m-01', strtotime('-1 month')) ?>">
                                <input type="hidden" name="end_date" value="<?= date('Y-m-t', strtotime('-1 month')) ?>">
                                <button type="submit" name="export" class="btn btn-outline-secondary btn-sm w-100">
                                    <i class="bi bi-calendar-minus"></i> Tháng trước (Tổng hợp)
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <!-- Preview -->
                <?php if (!empty($previewData)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-eye"></i> Xem Trước Dữ Liệu
                            </h6>
                            <small class="text-muted">
                                Tìm thấy <?= count($previewData) ?> bản ghi từ <?= date('d/m/Y', strtotime($_POST['start_date'])) ?> 
                                đến <?= date('d/m/Y', strtotime($_POST['end_date'])) ?>
                            </small>
                        </div>
                        <div class="card-body">
                            <div class="preview-table">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <?php if ($previewFormat === 'summary'): ?>
                                                <th>Mã NV</th>
                                                <th>Tên</th>
                                                <th>Tổng Ngày</th>
                                                <th>Tổng Giờ</th>
                                                <th>Hoàn Thành</th>
                                                <th>Nửa Công</th>
                                                <th>Thiếu</th>
                                                <th>TL Hoàn Thành</th>
                                            <?php else: ?>
                                                <?php if ($_POST['export_type'] === 'all'): ?>
                                                    <th>Mã NV</th>
                                                    <th>Tên</th>
                                                <?php endif; ?>
                                                <th>Ngày</th>
                                                <th>Check In</th>
                                                <th>Check Out</th>
                                                <th>Trạng Thái</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($previewData, 0, 50) as $record): ?>
                                            <tr>
                                                <?php if ($previewFormat === 'summary'): ?>
                                                    <td><small><?= htmlspecialchars($record['employee_code']) ?></small></td>
                                                    <td><small><?= htmlspecialchars($record['employee_name']) ?></small></td>
                                                    <td><small><?= $record['total_work_days'] ?></small></td>
                                                    <td><small><?= round($record['total_hours'], 1) ?>h</small></td>
                                                    <td><small><?= $record['complete_days'] ?></small></td>
                                                    <td><small><?= $record['half_days'] ?></small></td>
                                                    <td><small><?= $record['incomplete_days'] ?></small></td>
                                                    <td>
                                                        <span class="badge <?= $record['completion_rate'] >= 90 ? 'bg-success' : ($record['completion_rate'] >= 70 ? 'bg-warning' : 'bg-danger') ?> badge-sm">
                                                            <?= $record['completion_rate'] ?>%
                                                        </span>
                                                    </td>
                                                <?php else: ?>
                                                    <?php if ($_POST['export_type'] === 'all'): ?>
                                                        <td><small><?= htmlspecialchars($record['employee_code']) ?></small></td>
                                                        <td><small><?= htmlspecialchars($record['employee_name']) ?></small></td>
                                                    <?php endif; ?>
                                                    <td><small><?= date('d/m', strtotime($record['date'])) ?></small></td>
                                                    <td><small><?= formatTime($record['checkin_time']) ?></small></td>
                                                    <td><small><?= formatTime($record['checkout_time']) ?></small></td>
                                                    <td>
                                                        <span class="badge bg-<?= getStatusClass($record['status']) ?> badge-sm">
                                                            <?= getStatusText($record['status']) ?>
                                                        </span>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (count($previewData) > 50): ?>
                                            <tr>
                                                <td colspan="<?= $previewFormat === 'summary' ? 8 : ($_POST['export_type'] === 'all' ? 6 : 4) ?>" class="text-center text-muted">
                                                    <small>... và <?= count($previewData) - 50 ?> bản ghi khác</small>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Hướng dẫn -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-info-circle"></i> Hướng Dẫn Sử Dụng
                            </h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success"></i>
                                    Chọn loại xuất: nhân viên cụ thể hoặc tất cả
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success"></i>
                                    Chọn định dạng: Chi tiết (từng ngày) hoặc Tổng hợp (1 dòng/nhân viên)
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success"></i>
                                    Chọn khoảng thời gian cần xuất
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success"></i>
                                    Nhấn "Xem Trước" để kiểm tra dữ liệu
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success"></i>
                                    Nhấn "Tải Excel" để tải file Excel về máy
                                </li>
                            </ul>
                            
                            <div class="alert alert-info mt-3">
                                <i class="bi bi-lightbulb"></i>
                                <strong>Mẹo:</strong> 
                                <ul class="mb-0 mt-2">
                                    <li>Dùng "Tổng hợp" để xem thống kê tổng quan của từng nhân viên</li>
                                    <li>Dùng "Chi tiết" để xem dữ liệu chấm công từng ngày</li>
                                    <li>Sử dụng "Xuất Nhanh" để nhanh chóng xuất dữ liệu dạng tổng hợp</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleEmployeeSelect() {
            const employeeGroup = document.getElementById('employeeSelectGroup');
            const singleRadio = document.getElementById('singleEmployee');
            
            if (singleRadio.checked) {
                employeeGroup.style.display = 'block';
            } else {
                employeeGroup.style.display = 'none';
            }
        }

        // Khởi tạo trạng thái ban đầu
        toggleEmployeeSelect();
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