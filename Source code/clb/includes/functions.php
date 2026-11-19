<?php
declare(strict_types=1);

/* ===================== Xuất dữ liệu & đường dẫn ===================== */

/** Mã hóa dữ liệu khi in ra HTML (chống XSS). Luôn dùng khi echo dữ liệu từ người dùng/CSDL. */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Tạo đường dẫn tuyệt đối tới một trang trong website. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Chuyển hướng tới một trang trong website rồi dừng chương trình. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Định dạng ngày giờ từ CSDL sang dd/mm/yyyy HH:mm. */
function fmt_datetime(?string $dt): string
{
    return $dt ? date('d/m/Y H:i', strtotime($dt)) : '—';
}

/* ===================== Thông báo (flash message) ===================== */

/** Lưu thông báo, hiển thị một lần ở lần tải trang tiếp theo. $type: success|danger|warning|info */
function set_flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

/** Lấy và xóa toàn bộ thông báo đang chờ. */
function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ===================== CSRF token ===================== */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** In ô ẩn chứa token. Đặt bên trong mọi <form method="post">. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $t = $_POST['csrf_token'] ?? '';
    return is_string($t) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

/** Gọi đầu xử lý POST: token sai thì dừng ngay. */
function require_csrf(): void
{
    if (!csrf_verify()) {
        http_response_code(419);
        exit('Phiên làm việc không hợp lệ hoặc đã hết hạn. Vui lòng quay lại, tải lại trang và thử lại.');
    }
}

/* ===================== Đọc & kiểm tra dữ liệu form ===================== */

/** Đọc một trường POST dạng chuỗi, đã cắt khoảng trắng đầu/cuối. */
function input(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

/** Giá trị cũ của ô nhập (đã mã hóa), để không mất dữ liệu khi form báo lỗi. */
function old(string $key, string $default = ''): string
{
    return e((string)($_POST[$key] ?? $default));
}

/** Thêm class is-invalid của Bootstrap nếu trường có lỗi. */
function invalid_class(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' is-invalid' : '';
}

/** In thông báo lỗi nằm dưới ô nhập. */
function field_error(array $errors, string $key): string
{
    return isset($errors[$key])
        ? '<div class="invalid-feedback">' . e($errors[$key]) . '</div>'
        : '';
}

/* ===================== Phân trang ===================== */

/** Tính thông tin phân trang. Trả về: total, page, pages, perPage, offset. */
function paginate(int $total, int $page, int $perPage = PER_PAGE): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);
    return [
        'total'   => $total,
        'page'    => $page,
        'pages'   => $pages,
        'perPage' => $perPage,
        'offset'  => ($page - 1) * $perPage,
    ];
}

/** Vẽ thanh phân trang, giữ nguyên các tham số lọc/tìm kiếm trong $params. */
function render_pagination(array $pg, array $params = []): string
{
    if ($pg['pages'] <= 1) {
        return '';
    }
    unset($params['page']);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== 0 && $v !== null);
    $base = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');

    $link = function (int $p) use ($base, $params): string {
        return e($base . '?' . http_build_query($params + ['page' => $p]));
    };

    $html  = '<nav aria-label="Phân trang"><ul class="pagination justify-content-center mb-0">';
    $html .= '<li class="page-item' . ($pg['page'] <= 1 ? ' disabled' : '') . '">'
           . '<a class="page-link" href="' . $link(max(1, $pg['page'] - 1)) . '">&laquo;</a></li>';

    $start = max(1, $pg['page'] - 2);
    $end   = min($pg['pages'], $pg['page'] + 2);
    for ($p = $start; $p <= $end; $p++) {
        $html .= '<li class="page-item' . ($p === $pg['page'] ? ' active' : '') . '">'
               . '<a class="page-link" href="' . $link($p) . '">' . $p . '</a></li>';
    }

    $html .= '<li class="page-item' . ($pg['page'] >= $pg['pages'] ? ' disabled' : '') . '">'
           . '<a class="page-link" href="' . $link(min($pg['pages'], $pg['page'] + 1)) . '">&raquo;</a></li>';
    return $html . '</ul></nav>';
}

/* ===================== Ngày giờ cho ô nhập datetime-local ===================== */

/** Chuyển 'Y-m-d H:i:s' (CSDL) sang 'Y-m-dTH:i' (giá trị của ô datetime-local). */
function to_input_datetime(?string $dt): string
{
    return $dt ? date('Y-m-d\TH:i', strtotime($dt)) : '';
}

/** Chuyển 'Y-m-dTH:i' sang 'Y-m-d H:i:00'. Trả về null nếu sai định dạng hoặc ngày không tồn tại. */
function parse_input_datetime(string $v): ?string
{
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $v);
    return ($d && $d->format('Y-m-d\TH:i') === $v) ? $d->format('Y-m-d H:i:00') : null;
}

/* ===================== Trạng thái sự kiện ===================== */

/**
 * Trạng thái thực tế của sự kiện, tính từ cột status và thời gian hiện tại:
 *   cancelled > finished (đã quá giờ kết thúc) > closed (Admin đóng hoặc quá hạn đăng ký) > open
 */
function event_effective_status(array $ev): string
{
    if ($ev['status'] === 'cancelled') {
        return 'cancelled';
    }
    $now = time();
    if ($ev['status'] === 'finished' || strtotime($ev['end_time']) <= $now) {
        return 'finished';
    }
    if ($ev['status'] === 'closed' || strtotime($ev['registration_deadline']) <= $now) {
        return 'closed';
    }
    return 'open';
}

function event_status_label(string $s): string
{
    return [
        'open'      => 'Đang mở đăng ký',
        'closed'    => 'Đã đóng đăng ký',
        'finished'  => 'Đã kết thúc',
        'cancelled' => 'Đã hủy',
    ][$s] ?? $s;
}

/** Nhãn màu của trạng thái (nhận giá trị từ event_effective_status). */
function event_status_badge(string $s): string
{
    return '<span class="badge badge-st-' . e($s) . '">' . e(event_status_label($s)) . '</span>';
}

/* ===================== Nhãn trạng thái thành viên, đăng ký, điểm danh ===================== */

function user_status_label(string $s): string
{
    return [
        'pending'  => 'Chờ duyệt',
        'active'   => 'Đang hoạt động',
        'inactive' => 'Ngừng hoạt động',
        'rejected' => 'Từ chối',
    ][$s] ?? $s;
}

function user_status_badge(string $s): string
{
    return '<span class="badge badge-us-' . e($s) . '">' . e(user_status_label($s)) . '</span>';
}

function reg_status_label(string $s): string
{
    return ['registered' => 'Đã đăng ký', 'cancelled' => 'Đã hủy'][$s] ?? $s;
}

/** Nhãn điểm danh. Đăng ký đã hủy thì không có điểm danh. */
function attendance_badge(?string $att, string $regStatus = 'registered'): string
{
    if ($regStatus === 'cancelled') {
        return '<span class="text-muted">—</span>';
    }
    if ($att === 'present') {
        return '<span class="badge badge-att-present">Có mặt</span>';
    }
    if ($att === 'absent') {
        return '<span class="badge badge-att-absent">Vắng mặt</span>';
    }
    return '<span class="badge badge-att-none">Chưa điểm danh</span>';
}
