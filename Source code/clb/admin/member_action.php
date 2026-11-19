<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/members.php');
}
require_csrf();

$action = input('action');
$id     = (int)input('id');

// Quay lại đúng trang lọc/tìm kiếm trước đó (chỉ giữ ký tự an toàn của chuỗi truy vấn)
$back   = preg_replace('/[^A-Za-z0-9=&%_.+\-]/', '', (string)($_POST['back'] ?? ''));
$target = 'admin/members.php' . ($back !== '' ? '?' . $back : '');

try {
    $st = db()->prepare(
        "SELECT u.id, u.full_name, u.role, u.status,
                (SELECT COUNT(*) FROM registrations r WHERE r.user_id = u.id) AS total_regs
         FROM users u WHERE u.id = ?"
    );
    $st->execute([$id]);
    $u = $st->fetch();

    // Chỉ thao tác trên tài khoản thành viên, không động tới Admin
    if (!$u || $u['role'] !== 'member') {
        set_flash('danger', 'Không tìm thấy thành viên.');
        redirect($target);
    }
    if ($id === (int)current_user()['id']) {
        set_flash('danger', 'Không thể thực hiện thao tác này với tài khoản của bạn.');
        redirect($target);
    }

    $setStatus = function (string $status) use ($id): void {
        db()->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'member'")->execute([$status, $id]);
    };
    $name = $u['full_name'];

    switch ($action) {
        case 'approve':     // chờ duyệt / bị từ chối -> đang hoạt động
            if (!in_array($u['status'], ['pending', 'rejected'], true)) {
                set_flash('danger', 'Chỉ duyệt được tài khoản đang chờ duyệt hoặc đã bị từ chối.');
                break;
            }
            $setStatus('active');
            set_flash('success', 'Đã duyệt tài khoản của ' . $name . '. Thành viên có thể đăng nhập.');
            break;

        case 'reject':      // chờ duyệt -> từ chối
            if ($u['status'] !== 'pending') {
                set_flash('danger', 'Chỉ từ chối được tài khoản đang chờ duyệt.');
                break;
            }
            $setStatus('rejected');
            set_flash('success', 'Đã từ chối tài khoản của ' . $name . '.');
            break;

        case 'deactivate':  // đang hoạt động -> ngừng hoạt động
            if ($u['status'] !== 'active') {
                set_flash('danger', 'Chỉ ngừng hoạt động được tài khoản đang hoạt động.');
                break;
            }
            $setStatus('inactive');
            set_flash('success', 'Đã ngừng hoạt động tài khoản của ' . $name . '. Lịch sử đăng ký vẫn được giữ.');
            break;

        case 'activate':    // ngừng hoạt động -> đang hoạt động
            if ($u['status'] !== 'inactive') {
                set_flash('danger', 'Chỉ kích hoạt lại được tài khoản đang ngừng hoạt động.');
                break;
            }
            $setStatus('active');
            set_flash('success', 'Đã kích hoạt lại tài khoản của ' . $name . '.');
            break;

        case 'delete':      // QT6: chưa có lịch sử thì xóa hẳn, đã có thì chuyển sang ngừng hoạt động
            if ((int)$u['total_regs'] === 0) {
                db()->prepare("DELETE FROM users WHERE id = ? AND role = 'member'")->execute([$id]);
                set_flash('success', 'Đã xóa thành viên ' . $name . '.');
            } elseif ($u['status'] === 'inactive') {
                set_flash('info', 'Thành viên đã có lịch sử đăng ký và đang ngừng hoạt động nên được giữ lại để tra cứu.');
            } else {
                $setStatus('inactive');
                set_flash('info', 'Thành viên đã có lịch sử đăng ký nên không bị xóa mà được chuyển sang ngừng hoạt động.');
            }
            break;

        default:
            set_flash('danger', 'Thao tác không hợp lệ.');
    }
} catch (PDOException $e) {
    error_log('Member action error: ' . $e->getMessage());
    set_flash('danger', 'Không thể thực hiện thao tác lúc này. Vui lòng thử lại.');
}

redirect($target);
