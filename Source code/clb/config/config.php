<?php
declare(strict_types=1);

/**
 * Cấu hình chung của website.
 * Chỉ cần sửa file này khi đổi máy hoặc đổi tên thư mục.
 */

// --- Kết nối cơ sở dữ liệu (XAMPP mặc định: user root, mật khẩu rỗng) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'club_management');
define('DB_USER', 'root');
define('DB_PASS', '');

// --- Đường dẫn website trên localhost, KHÔNG có dấu / ở cuối ---
// Ví dụ chép thư mục vào htdocs/clb  =>  http://localhost/clb  =>  '/clb'
define('BASE_URL', '/clb');

define('APP_NAME', 'CLB ICT');
define('APP_TAGLINE', 'Future Incubation');   // khẩu hiệu trên logo
define('PER_PAGE', 10);          // số dòng mỗi trang khi phân trang (dùng ở giai đoạn 2)

date_default_timezone_set('Asia/Ho_Chi_Minh');

// --- Khởi tạo session với cookie an toàn hơn ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,      // JavaScript không đọc được cookie phiên
        'samesite' => 'Lax',     // hạn chế gửi cookie từ trang web khác
    ]);
    session_start();
}
