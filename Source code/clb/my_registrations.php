<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle = 'Lịch sử tham gia';
$userId    = (int)current_user()['id'];

$statusFilter = (string)($_GET['status'] ?? '');
if (!in_array($statusFilter, ['registered', 'cancelled'], true)) {
    $statusFilter = '';
}
$page = (int)($_GET['page'] ?? 1);

// ---- Thống kê của riêng người đang đăng nhập ----
$st = db()->prepare(
    "SELECT COALESCE(SUM(status = 'registered'), 0)                              AS regs,
            COALESCE(SUM(status = 'registered' AND attendance = 'present'), 0)  AS present_cnt,
            COALESCE(SUM(status = 'registered' AND attendance = 'absent'), 0)   AS absent_cnt,
            COALESCE(SUM(status = 'cancelled'), 0)                              AS cancelled_cnt
     FROM registrations WHERE user_id = ?"
);
$st->execute([$userId]);
$sum       = $st->fetch();
$regs      = (int)$sum['regs'];
$present   = (int)$sum['present_cnt'];
$absent    = (int)$sum['absent_cnt'];
$cancelled = (int)$sum['cancelled_cnt'];
$checked   = $present + $absent;
$rate      = $checked > 0 ? (int)round($present / $checked * 100) : null;

// ---- Danh sách (luôn lọc theo user_id của người đăng nhập, không nhận id từ URL) ----
$where  = 'r.user_id = ?';
$params = [$userId];
if ($statusFilter !== '') {
    $where   .= ' AND r.status = ?';
    $params[] = $statusFilter;
}

$st = db()->prepare("SELECT COUNT(*) FROM registrations r WHERE $where");
$st->execute($params);
$pg = paginate((int)$st->fetchColumn(), $page);

$st = db()->prepare(
    "SELECT r.status AS reg_status, r.registered_at, r.attendance,
            e.id AS event_id, e.title, e.location, e.start_time, e.end_time,
            e.registration_deadline, e.status AS status
     FROM registrations r
     JOIN events e ON e.id = r.event_id
     WHERE $where
     ORDER BY e.start_time DESC
     LIMIT ? OFFSET ?"
);
$i = 1;
foreach ($params as $v) {
    $st->bindValue($i++, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$st->bindValue($i++, $pg['perPage'], PDO::PARAM_INT);
$st->bindValue($i,   $pg['offset'],  PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<h1 class="h3 section-title">Lịch sử tham gia của tôi</h1>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-chip orange"><div class="num"><?= $regs ?></div><div class="lbl">Sự kiện đã đăng ký</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-chip blue"><div class="num"><?= $present ?></div><div class="lbl">Số buổi có mặt</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-chip orange"><div class="num"><?= $absent ?></div><div class="lbl">Số buổi vắng mặt</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-chip blue"><div class="num"><?= $rate === null ? '—' : $rate . '%' ?></div><div class="lbl">Tỷ lệ có mặt</div></div></div>
</div>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a class="status-pill<?= $statusFilter === '' ? ' active' : '' ?>" href="<?= e(url('my_registrations.php')) ?>">Tất cả</a>
    <a class="status-pill<?= $statusFilter === 'registered' ? ' active' : '' ?>"
       href="<?= e(url('my_registrations.php?status=registered')) ?>">Đang đăng ký (<?= $regs ?>)</a>
    <a class="status-pill<?= $statusFilter === 'cancelled' ? ' active' : '' ?>"
       href="<?= e(url('my_registrations.php?status=cancelled')) ?>">Đã hủy (<?= $cancelled ?>)</a>
</div>

<div class="table-responsive rounded mb-3">
    <table class="table table-brand align-middle">
        <thead>
        <tr>
            <th>Sự kiện</th>
            <th>Thời gian</th>
            <th>Đăng ký lúc</th>
            <th>Đăng ký</th>
            <th>Điểm danh</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr>
                <td colspan="5" class="text-center text-muted py-4">
                    Bạn chưa có lượt đăng ký nào.
                    <a href="<?= e(url('events.php')) ?>">Xem các sự kiện</a>
                </td>
            </tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td>
                    <a class="fw-semibold text-decoration-none"
                       href="<?= e(url('event.php?id=' . (int)$r['event_id'])) ?>"><?= e($r['title']) ?></a>
                    <div class="small text-muted"><?= e($r['location']) ?></div>
                </td>
                <td class="small">
                    <?= e(fmt_datetime($r['start_time'])) ?><br>
                    <?= event_status_badge(event_effective_status($r)) ?>
                </td>
                <td class="small"><?= e(fmt_datetime($r['registered_at'])) ?></td>
                <td>
                    <span class="badge <?= $r['reg_status'] === 'registered' ? 'badge-us-active' : 'badge-us-inactive' ?>">
                        <?= e(reg_status_label($r['reg_status'])) ?>
                    </span>
                </td>
                <td><?= attendance_badge($r['attendance'], $r['reg_status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= render_pagination($pg, ['status' => $statusFilter]) ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
