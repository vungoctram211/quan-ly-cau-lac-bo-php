<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Chỉ nhận POST từ nút trên trang chi tiết sự kiện
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('events.php');
}

require_login();                       // chưa đăng nhập, hoặc tài khoản không còn hoạt động -> chuyển sang đăng nhập
require_csrf();

$eventId = (int)input('event_id');
$action  = input('action');
$userId  = (int)current_user()['id'];  // luôn lấy từ session, không nhận từ form
$backUrl = 'event.php?id=' . $eventId;

if ((current_user()['role'] ?? '') !== 'member') {
    set_flash('danger', 'Chỉ tài khoản thành viên mới đăng ký được sự kiện.');
    redirect($backUrl);
}

$pdo = db();

/** Hoàn tác transaction (nếu đang mở), báo lỗi rồi quay lại trang sự kiện. */
$fail = function (string $type, string $msg, string $to = '') use ($pdo, $backUrl): void {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash($type, $msg);
    redirect($to !== '' ? $to : $backUrl);
};

try {
    /* ============================ ĐĂNG KÝ (UC02) ============================ */
    if ($action === 'register') {
        $pdo->beginTransaction();

        // Khóa dòng sự kiện: nếu nhiều người cùng bấm đăng ký, các yêu cầu được xử lý lần lượt
        $st = $pdo->prepare('SELECT * FROM events WHERE id = ? FOR UPDATE');
        $st->execute([$eventId]);
        $ev = $st->fetch();
        if (!$ev) {
            $fail('danger', 'Không tìm thấy sự kiện.', 'events.php');
        }

        // Kiểm tra điều kiện đăng ký (QT1, QT2, QT3)
        $eff = event_effective_status($ev);
        if ($eff === 'cancelled') {
            $fail('danger', 'Sự kiện đã bị hủy, không thể đăng ký.');
        }
        if ($eff === 'finished') {
            $fail('danger', 'Sự kiện đã kết thúc, không thể đăng ký.');
        }
        if ($eff === 'closed') {
            $fail('danger', strtotime($ev['registration_deadline']) <= time()
                ? 'Đã hết hạn đăng ký sự kiện này.'
                : 'Sự kiện đã đóng đăng ký.');
        }

        $st = $pdo->prepare('SELECT id, status FROM registrations WHERE event_id = ? AND user_id = ? FOR UPDATE');
        $st->execute([$eventId, $userId]);
        $existing = $st->fetch();
        if ($existing && $existing['status'] === 'registered') {
            $fail('info', 'Bạn đã đăng ký sự kiện này rồi.');
        }

        $st = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status = 'registered'");
        $st->execute([$eventId]);
        if ((int)$st->fetchColumn() >= (int)$ev['max_participants']) {
            $fail('danger', 'Sự kiện đã đủ số lượng, không còn chỗ.');
        }

        $nowStr = date('Y-m-d H:i:s');
        if ($existing) {
            // Từng đăng ký rồi hủy: cập nhật lại dòng cũ (không thêm dòng mới vì UNIQUE event_id + user_id)
            $st = $pdo->prepare(
                "UPDATE registrations
                 SET status = 'registered', registered_at = ?, attendance = NULL, checked_at = NULL
                 WHERE id = ?"
            );
            $st->execute([$nowStr, (int)$existing['id']]);
        } else {
            $st = $pdo->prepare(
                "INSERT INTO registrations (event_id, user_id, status, registered_at) VALUES (?, ?, 'registered', ?)"
            );
            $st->execute([$eventId, $userId, $nowStr]);
        }

        $pdo->commit();
        set_flash('success', 'Đăng ký thành công sự kiện "' . $ev['title'] . '".');
        redirect($backUrl);
    }

    /* ============================ HỦY ĐĂNG KÝ (QT4) ============================ */
    if ($action === 'cancel') {
        $st = $pdo->prepare(
            "SELECT r.id, r.status, r.attendance, e.status AS ev_status, e.end_time, e.registration_deadline
             FROM registrations r
             JOIN events e ON e.id = r.event_id
             WHERE r.event_id = ? AND r.user_id = ?"
        );
        $st->execute([$eventId, $userId]);
        $r = $st->fetch();

        if (!$r || $r['status'] !== 'registered') {
            $fail('danger', 'Bạn chưa đăng ký sự kiện này.');
        }
        if ($r['attendance'] !== null) {
            $fail('danger', 'Bạn đã được điểm danh nên không thể hủy đăng ký.');
        }
        if ($r['ev_status'] === 'cancelled' || strtotime($r['end_time']) <= time()) {
            $fail('danger', 'Sự kiện đã kết thúc hoặc đã bị hủy, không thể hủy đăng ký.');
        }
        if (strtotime($r['registration_deadline']) <= time()) {
            $fail('danger', 'Đã quá hạn đăng ký, không thể hủy đăng ký.');
        }

        // Điều kiện trong WHERE giúp an toàn nếu có thao tác chen ngang
        $st = $pdo->prepare(
            "UPDATE registrations SET status = 'cancelled'
             WHERE id = ? AND user_id = ? AND status = 'registered' AND attendance IS NULL"
        );
        $st->execute([(int)$r['id'], $userId]);

        set_flash('success', 'Đã hủy đăng ký sự kiện.');
        redirect($backUrl);
    }

    set_flash('danger', 'Thao tác không hợp lệ.');
    redirect($backUrl);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() === '23000') {       // vi phạm UNIQUE: bấm đăng ký hai lần cùng lúc
        set_flash('info', 'Bạn đã đăng ký sự kiện này rồi.');
    } else {
        error_log('Event register error: ' . $e->getMessage());
        set_flash('danger', 'Không thể xử lý yêu cầu lúc này. Vui lòng thử lại.');
    }
    redirect($backUrl);
}
