<?php
require_once 'includes/functions.php';

// Đăng xuất user
logoutUser();

// Redirect về trang login
header('Location: login.php');
exit;
?> 