<?php
declare(strict_types=1);

/** Mọi trang PHP gọi file này đầu tiên: nạp cấu hình, kết nối CSDL và các hàm dùng chung. */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/members.php';

// Dự phòng: nếu config.php cũ chưa khai báo khẩu hiệu thì dùng giá trị mặc định
if (!defined('APP_TAGLINE')) {
    define('APP_TAGLINE', 'Future Incubation');
}

// ---- Tiêu đề HTTP bảo mật cơ bản ----
header('X-Content-Type-Options: nosniff');                    // trình duyệt không tự đoán kiểu tệp
header('X-Frame-Options: SAMEORIGIN');                        // chống nhúng trang vào khung của web khác (clickjacking)
header('Referrer-Policy: strict-origin-when-cross-origin');   // hạn chế lộ địa chỉ trang khi chuyển sang web khác

// ---- Hiển thị lỗi: mặc định ẨN để không lộ đường dẫn, lỗi vẫn được ghi vào log của Apache ----
// Khi cần xem lỗi chi tiết lúc phát triển: thêm define('APP_DEBUG', true); vào config/config.php
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', false);
}
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
