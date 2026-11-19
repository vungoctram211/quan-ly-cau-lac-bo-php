<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/registrations.php');
}
require_csrf();

$action = input('action');
$id     = (int)input('id');
$back   = preg_replace('/[^A-Za-z0-9=&%_.+\-]/', '', (string)($_POST['back'] ?? ''));

try {
    $st = db()->prepare(
        "SELECT r.id, r.event_id, r.status, r.attendance,
                e.status AS ev_status, e.end_time, e.registration_deadline,
                u.full_name
         FROM registrations r
         JOIN events e ON e.id = r.event_id
         JOIN users u ON u.id = r.user_id
         WHERE r.id = ?"
    );
    $st->execute([$id]);
    $r = $st->fetch();

    if (!$r) {
        set_flash('danger', 'Không tìm thấy lượt đăng ký.');
        redirect('admin/registrations.php');
    }
    $target = 'admin/registrations.php?' . ($back !== '' ? $back : 'event_id=' . (int)$r['event_id']);

    if ($action !== 'cancel') {
        set_flash('danger', 'Thao tác không hợp lệ.');
        redirect($target);
    }
    if ($r['status'] !== 'registered') {
        set_flash('danger', 'Lượt đăng ký này đã được hủy trước đó.');
        redirect($target);
    }
    if ($r['attendance'] !== null) {
        set_flash('danger', 'Thành viên đã được điểm danh nên không thể hủy đăng ký.');
        redirect($target);
    }
    if ($r['ev_status'] === 'cancelled' || strtotime($r['end_time']) <= time()) {
        set_flash('danger', 'Sự kiện đã kết thúc hoặc đã bị hủy, không thể hủy đăng ký.');
        redirect($target);
    }

    $st = db()->prepare(
        "UPDATE registrations SET status = 'cancelled'
         WHERE id = ? AND status = 'registered' AND attendance IS NULL"
    );
    $st->execute([$id]);
    set_flash('success', 'Đã hủy đăng ký của ' . $r['full_name'] . '. Số chỗ được trả lại cho sự kiện.');
    redirect($target);

} catch (PDOException $e) {
    error_log('Registration action error: ' . $e->getMessage());
    set_flash('danger', 'Không thể thực hiện thao tác lúc này. Vui lòng thử lại.');
    redirect('admin/registrations.php');
}
