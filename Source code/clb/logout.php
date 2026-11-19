<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Chỉ đăng xuất khi nhận POST hợp lệ (nút Đăng xuất trên menu có CSRF token)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    logout_user();
    set_flash('success', 'Bạn đã đăng xuất.');
}
redirect('index.php');
