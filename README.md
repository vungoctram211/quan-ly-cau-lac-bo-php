# Hệ thống Quản lý Câu lạc bộ Sinh viên (CLB ICT)

Website quản lý thành viên, tạo và công bố sự kiện, đăng ký/hủy đăng ký tham gia, điểm danh và thống kê hoạt động câu lạc bộ sinh viên, xây dựng bằng **PHP thuần (8.x)**, **MySQL/MariaDB (PDO)** và **Bootstrap 5**[cite: 74, 94, 127].

## Công nghệ & Kỹ thuật nổi bật
- **Backend & Database:** PHP 8.x, PDO (`Prepared Statement`), MySQL/MariaDB (`InnoDB`, `utf8mb4`)[cite: 74, 94, 96].
- **Frontend:** HTML5, CSS3, JavaScript, Bootstrap 5[cite: 74, 94].
- **Tính toàn vẹn dữ liệu:** Sử dụng `Transaction` kết hợp khóa dòng (`SELECT ... FOR UPDATE`) khi đăng ký sự kiện để tránh vượt quá số chỗ tối đa, kết hợp ràng buộc `UNIQUE` và không xóa cứng dữ liệu đã phát sinh lịch sử[cite: 104, 105, 111, 115].
- **Bảo mật:** Băm mật khẩu bằng `password_hash()` / `password_verify()` (bcrypt), chống XSS bằng `htmlspecialchars()`, chống CSRF token trên biểu mẫu/thao tác `POST`, quản lý session an toàn và kiểm soát quyền truy cập ở máy chủ[cite: 74, 100, 119, 120, 121].

## Hướng dẫn cài đặt & chạy trên XAMPP
1. Bật **Apache** và **MySQL** trong **XAMPP Control Panel**.
2. Mở **phpMyAdmin**, tạo database `club_management` (hoặc import trực tiếp tệp `database/club_management.sql` theo hướng dẫn trong tệp)[cite: 94, 96].
3. Đặt mã nguồn vào thư mục `C:\xampp\htdocs\clb` và kiểm tra thông tin kết nối trong `config/config.php`[cite: 94, 97].
4. Truy cập trình duyệt tại `http://localhost/clb/`[cite: 94].

## Tài khoản kiểm thử mẫu
- **Quản trị viên:** `admin@clb.local` / `Admin@123`[cite: 123]
- **Thành viên (đang hoạt động):** `SV2024001` – `SV2024006` / `Member@123`[cite: 123]
