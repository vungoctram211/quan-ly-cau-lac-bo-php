# Website CLB ICT – Hệ thống quản lý câu lạc bộ sinh viên

Bài tập lớn môn Lập trình PHP cơ bản – Đề tài 21: Quản lý thành viên, sự kiện, đăng ký tham gia và điểm danh.

## 1. Yêu cầu môi trường
- Windows + XAMPP (hoặc WAMP) có Apache, PHP 8.0 trở lên, MariaDB 10.2+ hoặc MySQL 8.0.16+
- Trình duyệt Chrome / Edge / Firefox. Cần có mạng để tải Bootstrap từ CDN.

## 2. Cài đặt (làm lần lượt)
1. Chép thư mục `clb` vào `C:\xampp\htdocs\` (kết quả: `C:\xampp\htdocs\clb\index.php`).
2. Mở XAMPP Control Panel, bấm **Start** cho **Apache** và **MySQL**.
3. Mở http://localhost/phpmyadmin → tab **Import** → chọn `database/club_management.sql` → **Import**.
   (Tệp tự tạo database `club_management`, 4 bảng và dữ liệu mẫu.)
4. Mở `config/config.php`, kiểm tra:
   - `DB_USER` / `DB_PASS`: XAMPP mặc định là `root` và rỗng.
   - `BASE_URL`: `/clb` nếu thư mục tên `clb`.
5. Mở http://localhost/clb/

Nếu Apache không Start được vì cổng 80 bận: đổi `Listen 80` thành `Listen 8080` trong `httpd.conf`
rồi truy cập http://localhost:8080/clb/ (BASE_URL vẫn giữ `/clb`).

## 3. Tài khoản demo
| Vai trò    | Đăng nhập bằng                          | Mật khẩu   | Ghi chú |
|------------|------------------------------------------|------------|---------|
| Quản trị   | admin@clb.local                          | Admin@123  | Vào được khu vực /admin |
| Thành viên | SV2024001 hoặc an.nv@student.local       | Member@123 | Đang hoạt động |
| Thành viên | SV2024002 ... SV2024006                  | Member@123 | Đang hoạt động |
| Thành viên | SV2024007                                | Member@123 | Chờ duyệt, chưa đăng nhập được |
| Thành viên | SV2024008                                | Member@123 | Ngừng hoạt động, bị chặn |

## 4. Chức năng
**Khách:** xem trang chủ, danh sách và chi tiết sự kiện (tìm kiếm, lọc, phân trang), đăng ký tài khoản, đăng nhập.

**Thành viên:** đăng ký và hủy đăng ký sự kiện (kiểm tra còn chỗ, chống trùng, hạn đăng ký), xem lịch sử tham gia và thống kê điểm danh, cập nhật hồ sơ, đổi mật khẩu.

**Quản trị viên:** thống kê tổng quan; quản lý thành viên (duyệt, từ chối, thêm, sửa, ngừng hoạt động, xóa, xem hồ sơ); quản lý loại sự kiện; quản lý sự kiện (thêm, sửa, đóng/mở đăng ký, hủy, xóa); xem danh sách đăng ký theo sự kiện; điểm danh.

## 5. Cấu trúc thư mục
    clb/
    ├── index.php, events.php, event.php, event_register.php, my_registrations.php,
    │   profile.php, register.php, login.php, logout.php      (trang công khai và của thành viên)
    ├── config/        cấu hình (config.php) và kết nối CSDL (database.php)
    ├── includes/      hàm dùng chung, kiểm tra quyền, giao diện chung (header, footer)
    ├── admin/         khu vực quản trị (mọi trang gọi require_admin())
    ├── assets/        css/style.css, img/logo.png, img/favicon.png
    ├── uploads/       dành cho tệp tải lên (hiện chưa dùng)
    └── database/      club_management.sql (cấu trúc 4 bảng + dữ liệu mẫu)

## 6. Các biện pháp bảo mật đã áp dụng
- Prepared statement (PDO) cho mọi câu lệnh có dữ liệu đầu vào → chống SQL Injection.
- `password_hash()` / `password_verify()` → không lưu mật khẩu thô.
- `htmlspecialchars()` qua hàm `e()` khi in dữ liệu → chống XSS.
- CSRF token cho mọi form thay đổi dữ liệu; xóa/hủy/duyệt/đăng xuất chỉ nhận POST.
- `session_regenerate_id()` khi đăng nhập; kiểm tra lại trạng thái tài khoản ở mỗi trang cần đăng nhập.
- Phân quyền: `require_login()`, `require_admin()`; mã người dùng luôn lấy từ session.
- Kiểm tra dữ liệu phía máy chủ; chuyển trạng thái (duyệt, đóng, hủy...) được kiểm tra lại ở máy chủ.
- Giới hạn đăng nhập sai (5 lần / 5 phút); báo lỗi chung "Sai tài khoản hoặc mật khẩu".
- Tiêu đề HTTP bảo mật; ẩn lỗi chi tiết (bật lại bằng `define('APP_DEBUG', true);` trong config.php).
- `.htaccess` chặn truy cập trực tiếp `config/`, `includes/`, `database/` và tắt liệt kê thư mục.

## 7. Xử lý sự cố
| Hiện tượng | Cách xử lý |
|------------|-----------|
| "Không kết nối được cơ sở dữ liệu" | Chưa Start MySQL, chưa import SQL, hoặc sai DB_NAME/DB_PASS trong config.php |
| Link hỏng, mất giao diện | `BASE_URL` không khớp tên thư mục trong htdocs |
| Lỗi 500 ngay khi mở trang | Máy không cho dùng `.htaccess`: xóa các tệp `.htaccess` trong thư mục dự án |
| Trang trắng | Thêm `define('APP_DEBUG', true);` vào config.php, hoặc xem `C:\xampp\apache\logs\error.log` |
| Mất giao diện Bootstrap | Máy không có mạng (Bootstrap tải từ CDN) |
| Import SQL báo lỗi ở `CHECK` | MariaDB/MySQL quá cũ, dùng XAMPP bản PHP 8.x |
| Số liệu mẫu bị lệch giờ | Giờ sự kiện mẫu tính theo thời điểm nhập SQL; nhập lại SQL để làm mới |

## 8. Lưu ý
- Môi trường chạy cục bộ để học tập; XAMPP mặc định MySQL `root` không mật khẩu, không dùng cấu hình này khi đưa lên mạng.
- Hệ thống phục vụ một CLB; không gồm gửi email, thanh toán, điểm danh bằng QR, quên mật khẩu.
