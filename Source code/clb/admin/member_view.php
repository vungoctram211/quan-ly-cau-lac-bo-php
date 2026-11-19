<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);

$st = db()->prepare(
    "SELECT id, email, full_name, student_code, class_name, phone, status
     FROM users WHERE id = ? AND role = 'member'"
);
$st->execute([$id]);
$m = $st->fetch();
if (!$m) {
    set_flash('danger', 'Không tìm thấy thành viên.');
    redirect('admin/members.php');
}

// ---- Thống kê tham gia ----
$st = db()->prepare(
    "SELECT COALESCE(SUM(status = 'registered'), 0)                              AS regs,
            COALESCE(SUM(status = 'registered' AND attendance = 'present'), 0)  AS present_cnt,
            COALESCE(SUM(status = 'registered' AND attendance = 'absent'), 0)   AS absent_cnt,
            COALESCE(SUM(status = 'cancelled'), 0)                              AS cancelled_cnt
     FROM registrations WHERE user_id = ?"
);
$st->execute([$id]);
$sum = $st->fetch();
$regs      = (int)$sum['regs'];
$present   = (int)$sum['present_cnt'];
$absent    = (int)$sum['absent_cnt'];
$cancelled = (int)$sum['cancelled_cnt'];
$checked   = $present + $absent;
$rate      = $checked > 0 ? (int)round($present / $checked * 100) : null;

// ---- Lịch sử đăng ký ----
$st = db()->prepare(
    "SELECT r.status AS reg_status, r.registered_at, r.attendance, r.checked_at,
            e.id AS event_id, e.title, e.start_time
     FROM registrations r
     JOIN events e ON e.id = r.event_id
     WHERE r.user_id = ?
     ORDER BY e.start_time DESC
     LIMIT 100"
);
$st->execute([$id]);
$history = $st->fetchAll();

$parts   = preg_split('/\s+/', trim($m['full_name']));
$initial = mb_strtoupper(mb_substr((string)end($parts), 0, 1));

$pageTitle = 'Hồ sơ thành viên';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>
<div class="page-head">
    <h1 class="h4">Hồ sơ thành viên</h1>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-brand btn-sm" href="<?= e(url('admin/member_form.php?id=' . $id)) ?>">Sửa</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/members.php')) ?>">Quay lại danh sách</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-3">
                <span class="profile-avatar"><?= e($initial) ?></span>
                <div>
                    <div class="h5 mb-1"><?= e($m['full_name']) ?></div>
                    <?= user_status_badge($m['status']) ?>
                </div>
            </div>
            <dl class="row mb-0 small">
                <dt class="col-5 text-muted">Mã số sinh viên</dt><dd class="col-7"><?= e($m['student_code']) ?></dd>
                <dt class="col-5 text-muted">Lớp</dt><dd class="col-7"><?= e($m['class_name']) ?></dd>
                <dt class="col-5 text-muted">Email</dt><dd class="col-7"><?= e($m['email']) ?></dd>
                <dt class="col-5 text-muted">Điện thoại</dt><dd class="col-7"><?= e($m['phone'] ?: '—') ?></dd>
            </dl>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="row g-3 h-100">
            <div class="col-6 col-md-3"><div class="stat-chip orange"><div class="num"><?= $regs ?></div><div class="lbl">Đăng ký hợp lệ</div></div></div>
            <div class="col-6 col-md-3"><div class="stat-chip blue"><div class="num"><?= $present ?></div><div class="lbl">Có mặt</div></div></div>
            <div class="col-6 col-md-3"><div class="stat-chip orange"><div class="num"><?= $absent ?></div><div class="lbl">Vắng mặt</div></div></div>
            <div class="col-6 col-md-3"><div class="stat-chip blue"><div class="num"><?= $rate === null ? '—' : $rate . '%' ?></div><div class="lbl">Tỷ lệ có mặt</div></div></div>
            <div class="col-12 small text-muted">Đã hủy <?= $cancelled ?> lượt đăng ký. Tỷ lệ có mặt tính trên các lượt đã được điểm danh.</div>
        </div>
    </div>
</div>

<h2 class="h5 section-title">Lịch sử đăng ký</h2>
<div class="table-responsive rounded">
    <table class="table table-brand align-middle">
        <thead>
        <tr>
            <th>Sự kiện</th>
            <th>Thời gian diễn ra</th>
            <th>Đăng ký lúc</th>
            <th>Đăng ký</th>
            <th>Điểm danh</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$history): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">Thành viên chưa đăng ký sự kiện nào.</td></tr>
        <?php endif; ?>
        <?php foreach ($history as $h): ?>
            <tr>
                <td>
                    <a class="fw-semibold text-decoration-none"
                       href="<?= e(url('admin/event_form.php?id=' . (int)$h['event_id'])) ?>"><?= e($h['title']) ?></a>
                </td>
                <td class="small"><?= e(fmt_datetime($h['start_time'])) ?></td>
                <td class="small"><?= e(fmt_datetime($h['registered_at'])) ?></td>
                <td><?= e(reg_status_label($h['reg_status'])) ?></td>
                <td><?= attendance_badge($h['attendance'], $h['reg_status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
