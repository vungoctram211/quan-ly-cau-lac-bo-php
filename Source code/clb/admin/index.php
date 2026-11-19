<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();                       // chỉ Admin được vào; người khác bị chuyển hướng

$pageTitle = 'Tổng quan quản trị';
$now = date('Y-m-d H:i:s');

// ---- Các số liệu tổng quan ----
$members = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'member' AND status = 'active'")->fetchColumn();
$pending = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'member' AND status = 'pending'")->fetchColumn();
$regs    = (int)db()->query("SELECT COUNT(*) FROM registrations WHERE status = 'registered'")->fetchColumn();

$st = db()->prepare("SELECT COUNT(*) FROM events WHERE status = 'open' AND start_time > ?");
$st->execute([$now]);
$upcomingCount = (int)$st->fetchColumn();

$row = db()->query(
    "SELECT COALESCE(SUM(attendance = 'present'), 0) AS p, COALESCE(SUM(attendance = 'absent'), 0) AS a
     FROM registrations WHERE status = 'registered'"
)->fetch();
$totalPresent = (int)$row['p'];
$totalAbsent  = (int)$row['a'];
$overallRate  = ($totalPresent + $totalAbsent) > 0
    ? (int)round($totalPresent / ($totalPresent + $totalAbsent) * 100) : null;

$stats = [
    ['Thành viên đang hoạt động', (string)$members],
    ['Tài khoản chờ duyệt',       (string)$pending],
    ['Sự kiện sắp diễn ra',       (string)$upcomingCount],
    ['Lượt đăng ký hợp lệ',       (string)$regs],
    ['Tỷ lệ có mặt toàn CLB',     $overallRate === null ? '—' : $overallRate . '%'],
];

// ---- 5 sự kiện sắp diễn ra gần nhất ----
$st = db()->prepare(
    "SELECT e.id, e.title, e.start_time, e.max_participants,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'registered') AS registered_count
     FROM events e
     WHERE e.status = 'open' AND e.start_time > ?
     ORDER BY e.start_time ASC
     LIMIT 5"
);
$st->execute([$now]);
$upcoming = $st->fetchAll();

// ---- Tỷ lệ tham dự của 5 sự kiện gần nhất đã có điểm danh ----
$attendanceByEvent = db()->query(
    "SELECT e.id, e.title, e.start_time,
            SUM(r.attendance = 'present') AS p,
            SUM(r.attendance = 'absent')  AS a
     FROM events e
     JOIN registrations r ON r.event_id = e.id AND r.status = 'registered'
     GROUP BY e.id, e.title, e.start_time
     HAVING SUM(r.attendance IN ('present', 'absent')) > 0
     ORDER BY e.start_time DESC
     LIMIT 5"
)->fetchAll();

// ---- 5 thành viên có số buổi có mặt nhiều nhất ----
$topMembers = db()->query(
    "SELECT u.id, u.full_name, u.student_code, u.class_name,
            SUM(r.attendance = 'present') AS present_cnt,
            SUM(r.status = 'registered')  AS regs
     FROM users u
     JOIN registrations r ON r.user_id = u.id
     WHERE u.role = 'member'
     GROUP BY u.id, u.full_name, u.student_code, u.class_name
     HAVING SUM(r.attendance = 'present') > 0
     ORDER BY present_cnt DESC, regs DESC, u.full_name ASC
     LIMIT 5"
)->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>
<div class="page-head">
    <h1 class="h4">Tổng quan quản trị</h1>
    <span class="text-muted small">Khu vực dành cho Ban quản trị <?= e(APP_NAME) ?></span>
</div>

<?php if ($pending > 0): ?>
    <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Có <strong><?= $pending ?></strong> tài khoản đang chờ duyệt.</span>
        <a class="btn btn-primary btn-sm" href="<?= e(url('admin/members.php?status=pending')) ?>">Duyệt ngay</a>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach ($stats as $i => [$label, $value]): $tone = ($i % 2 === 0) ? 'orange' : 'blue'; ?>
        <div class="col-sm-6 col-lg">
            <div class="card stat-card <?= $tone ?> h-100">
                <div class="card-body">
                    <div class="stat-number"><?= e($value) ?></div>
                    <div class="text-muted small"><?= e($label) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <!-- Sự kiện sắp diễn ra -->
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 form-section-title mb-0">Sự kiện sắp diễn ra</h2>
                <a class="small fw-semibold" href="<?= e(url('admin/events.php')) ?>">Xem tất cả</a>
            </div>
            <?php if (!$upcoming): ?>
                <p class="text-muted mb-0">Chưa có sự kiện nào sắp diễn ra.</p>
            <?php else: ?>
                <?php foreach ($upcoming as $ev):
                    $reg = (int)$ev['registered_count']; $max = (int)$ev['max_participants'];
                    $pct = $max > 0 ? min(100, (int)round($reg / $max * 100)) : 0;
                ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between gap-2">
                            <a class="fw-semibold text-decoration-none"
                               href="<?= e(url('admin/registrations.php?event_id=' . (int)$ev['id'])) ?>"><?= e($ev['title']) ?></a>
                            <span class="small text-muted text-nowrap"><?= $reg ?> / <?= $max ?></span>
                        </div>
                        <div class="small text-muted mb-1"><?= e(fmt_datetime($ev['start_time'])) ?></div>
                        <div class="progress"><div class="progress-bar" style="width: <?= $pct ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div></div>
    </div>

    <!-- Tỷ lệ tham dự theo sự kiện -->
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 form-section-title mb-0">Tỷ lệ tham dự theo sự kiện</h2>
                <a class="small fw-semibold" href="<?= e(url('admin/registrations.php')) ?>">Điểm danh</a>
            </div>
            <?php if (!$attendanceByEvent): ?>
                <p class="text-muted mb-0">Chưa có sự kiện nào được điểm danh.</p>
            <?php else: ?>
                <?php foreach ($attendanceByEvent as $a):
                    $p = (int)$a['p']; $ab = (int)$a['a'];
                    $pct = (int)round($p / ($p + $ab) * 100);
                ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between gap-2">
                            <a class="fw-semibold text-decoration-none"
                               href="<?= e(url('admin/registrations.php?event_id=' . (int)$a['id'])) ?>"><?= e($a['title']) ?></a>
                            <span class="small fw-semibold text-nowrap"><?= $pct ?>%</span>
                        </div>
                        <div class="small text-muted mb-1"><?= $p ?> có mặt, <?= $ab ?> vắng mặt</div>
                        <div class="progress"><div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div></div>
    </div>
</div>

<!-- Thành viên tích cực -->
<div class="card"><div class="card-body">
    <h2 class="h6 form-section-title">Thành viên tham gia nhiều nhất</h2>
    <?php if (!$topMembers): ?>
        <p class="text-muted mb-0">Chưa có dữ liệu điểm danh.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>#</th><th>Thành viên</th><th>MSSV</th><th>Lớp</th><th class="text-center">Số buổi có mặt</th></tr></thead>
                <tbody>
                <?php foreach ($topMembers as $n => $m): ?>
                    <tr>
                        <td><?= $n + 1 ?></td>
                        <td>
                            <a class="fw-semibold text-decoration-none"
                               href="<?= e(url('admin/member_view.php?id=' . (int)$m['id'])) ?>"><?= e($m['full_name']) ?></a>
                        </td>
                        <td><?= e($m['student_code']) ?></td>
                        <td><?= e($m['class_name']) ?></td>
                        <td class="text-center"><span class="badge badge-att-present"><?= (int)$m['present_cnt'] ?> / <?= (int)$m['regs'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
