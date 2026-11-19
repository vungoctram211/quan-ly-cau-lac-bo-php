<?php
declare(strict_types=1);

/** Thông tin người đang đăng nhập (lấy từ session) hoặc null. */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']['id']);
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === 'admin';
}

/**
 * Chặn người chưa đăng nhập. Gọi ở đầu mọi trang cần đăng nhập.
 * Mỗi lần còn kiểm tra lại trạng thái trong CSDL: tài khoản bị ngừng hoạt động, từ chối
 * hoặc bị xóa sẽ bị đăng xuất ngay, không phải chờ hết phiên.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Vui lòng đăng nhập để tiếp tục.');
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }

    $st = db()->prepare('SELECT role, status FROM users WHERE id = ?');
    $st->execute([(int)$_SESSION['user']['id']]);
    $row = $st->fetch();

    if (!$row || $row['status'] !== 'active') {
        logout_user();
        set_flash('danger', 'Tài khoản của bạn đã bị khóa hoặc không còn tồn tại.');
        redirect('login.php');
    }
    $_SESSION['user']['role'] = $row['role'];   // luôn dùng vai trò mới nhất
}

/** Chặn người không phải Admin. Gọi ở đầu mọi trang trong thư mục admin/. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        set_flash('danger', 'Bạn không có quyền truy cập khu vực quản trị.');
        redirect('index.php');
    }
}

/** Ghi nhận đăng nhập: đổi mã phiên (chống session fixation) rồi lưu thông tin tối thiểu. */
function login_user(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'        => (int)$u['id'],
        'full_name' => $u['full_name'],
        'role'      => $u['role'],
    ];
}

function logout_user(): void
{
    unset($_SESSION['user'], $_SESSION['csrf'], $_SESSION['redirect_after_login']);
    session_regenerate_id(true);
}
