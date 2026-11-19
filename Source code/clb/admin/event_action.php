<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

// Chỉ nhận POST (nút trên danh sách sự kiện); mở trực tiếp bằng địa chỉ thì quay về danh sách
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/events.php');
}
require_csrf();

$action = input('action');
$id     = (int)input('id');

try {
    $st = db()->prepare(
        "SELECT e.*,
                (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS total_regs
         FROM events e WHERE e.id = ?"
    );
    $st->execute([$id]);
    $ev = $st->fetch();

    if (!$ev) {
        set_flash('danger', 'Không tìm thấy sự kiện.');
        redirect('admin/events.php');
    }

    $eff = event_effective_status($ev);

    switch ($action) {
        case 'close':   // Đóng đăng ký
            if ($eff !== 'open') {
                set_flash('danger', 'Chỉ đóng được đăng ký của sự kiện đang mở.');
                break;
            }
            db()->prepare("UPDATE events SET status = 'closed' WHERE id = ?")->execute([$id]);
            set_flash('success', 'Đã đóng đăng ký sự kiện. Các đăng ký hiện có được giữ nguyên.');
            break;

        case 'open':    // Mở lại đăng ký
            $canReopen = $ev['status'] === 'closed'
                      && strtotime($ev['registration_deadline']) > time()
                      && strtotime($ev['end_time']) > time();
            if (!$canReopen) {
                set_flash('danger', 'Không thể mở lại: sự kiện đã quá hạn đăng ký hoặc đã kết thúc. Hãy sửa hạn đăng ký trước.');
                break;
            }
            db()->prepare("UPDATE events SET status = 'open' WHERE id = ?")->execute([$id]);
            set_flash('success', 'Đã mở lại đăng ký sự kiện.');
            break;

        case 'cancel':  // Hủy sự kiện
            if (!in_array($eff, ['open', 'closed'], true)) {
                set_flash('danger', 'Chỉ hủy được sự kiện chưa kết thúc và chưa bị hủy.');
                break;
            }
            db()->prepare("UPDATE events SET status = 'cancelled' WHERE id = ?")->execute([$id]);
            set_flash('success', 'Đã hủy sự kiện.');
            break;

        case 'delete':  // Xóa (QT6): chưa có đăng ký thì xóa hẳn, đã có thì chuyển sang "đã hủy"
            if ((int)$ev['total_regs'] === 0) {
                db()->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
                set_flash('success', 'Đã xóa sự kiện.');
            } elseif ($ev['status'] === 'cancelled') {
                set_flash('info', 'Sự kiện đã hủy và đã có dữ liệu đăng ký nên được giữ lại để tra cứu.');
            } else {
                db()->prepare("UPDATE events SET status = 'cancelled' WHERE id = ?")->execute([$id]);
                set_flash('info', 'Sự kiện đã có dữ liệu đăng ký nên không bị xóa mà được chuyển sang trạng thái đã hủy.');
            }
            break;

        default:
            set_flash('danger', 'Thao tác không hợp lệ.');
    }
} catch (PDOException $e) {
    error_log('Event action error: ' . $e->getMessage());
    set_flash('danger', 'Không thể thực hiện thao tác lúc này. Vui lòng thử lại.');
}

redirect('admin/events.php');
