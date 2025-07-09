<?php
require_once 'includes/functions.php';

// Kiểm tra trạng thái đăng nhập
if (isLoggedIn()) {
    // Đã đăng nhập -> redirect đến dashboard phù hợp
    if (isAdmin()) {
        header('Location: admin-dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
} else {
    // Chưa đăng nhập -> redirect đến login
    header('Location: login.php');
}

exit;
?> 