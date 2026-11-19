<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$eventId = (int)($_GET['event_id'] ?? 0);
$page    = (int)($_GET['page'] ?? 1);

/* =====================================================================
   CHẾ ĐỘ 1: chưa chọn sự kiện -> hiển thị danh sách sự kiện để chọn
   ===================================================================== */
if ($eventId <= 0) {
    $pageTitle = 'Đăng ký và điểm danh';
    $q = trim((string)($_GET['q'] ?? ''));

    $where = [];
    $params = [];
    if ($q !== '') {
        $where[]  = 'e.title LIKE ?';
        $params[] = '%' . addcslashes($q, '%_\\') . '%';
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $st = db()->prepare("SELECT COUNT(*) FROM events e $whereSql");
    $st->execute($params);
    $pg = paginate((int)$st->fetchColumn(), $page);

    $st = db()->prepare(
        "SELECT e.*, c.name AS category_name,
                (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'registered') AS registered_count,
                (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'registered' AND r.attendance = 'present') AS present_count,
                (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'registered' AND r.attendance = 'absent') AS absent_count
         FROM events e
         JOIN event_categories c ON c.id = e.category_id
         $whereSql
         ORDER BY e.start_time DESC
         LIMIT ? OFFSET ?"
    );
    $i = 1;
    foreach ($params as $v) {
        $st->bindValue($i++, $v, PDO::PARAM_STR);
    }
    $st->bindValue($i++, $pg['perPage'], PDO::PARAM_INT);
    $st->bindValue($i,   $pg['offset'],  PDO::PARAM_INT);
    $st->execute();
    $events = $st->fetchAll();

    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../includes/admin_nav.php';
    ?>
    <div class="page-head">
        <h1 class="h4">Đăng ký và điểm danh</h1>
        <span class="text-muted small">Chọn một sự kiện để xem danh sách đăng ký hoặc điểm danh</span>
    </div>

    <form method="get" class="filter-bar row g-2 align-items-end mb-3">
        <div class="col-md-8">
            <label class="form-label small mb-1" for="q">Tìm theo tên sự kiện</label>
            <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Nhập tên sự kiện">
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Tìm</button>
            <a class="btn btn-outline-secondary" href="<?= e(url('admin/registrations.php')) ?>">Xóa</a>
        </div>
    </form>

    <div class="table-responsive rounded mb-3">
        <table class="table table-brand align-middle">
            <thead>
            <tr>
                <th>Sự kiện</th>
                <th>Thời gian</th>
                <th>Trạng thái</th>
                <th>Đăng ký</th>
                <th>Điểm danh</th>
                <th class="text-end">Thao tác</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$events): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Không tìm thấy sự kiện.</td></tr>
            <?php endif; ?>
            <?php foreach ($events as $ev):
                $id      = (int)$ev['id'];
                $eff     = event_effective_status($ev);
                $reg     = (int)$ev['registered_count'];
                $max     = (int)$ev['max_participants'];
                $present = (int)$ev['present_count'];
                $absent  = (int)$ev['absent_count'];
                $notYet  = $reg - $present - $absent;
                $canCheck = $ev['status'] !== 'cancelled' && strtotime($ev['start_time']) <= time();
            ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($ev['title']) ?></div>
                        <span class="badge badge-cat"><?= e($ev['category_name']) ?></span>
                    </td>
                    <td class="small"><?= e(fmt_datetime($ev['start_time'])) ?></td>
                    <td><?= event_status_badge($eff) ?></td>
                    <td><strong><?= $reg ?></strong> / <?= $max ?></td>
                    <td class="small">
                        <?php if ($reg === 0): ?>
                            <span class="text-muted">—</span>
                        <?php else: ?>
                            <span class="text-success"><?= $present ?> có mặt</span> ·
                            <span class="text-danger"><?= $absent ?> vắng</span> ·
                            <span class="text-muted"><?= $notYet ?> chưa</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                            <a class="btn btn-sm btn-outline-brand"
                               href="<?= e(url('admin/registrations.php?event_id=' . $id)) ?>">Danh sách</a>
                            <?php if ($canCheck): ?>
                                <a class="btn btn-sm btn-primary"
                                   href="<?= e(url('admin/attendance.php?event_id=' . $id)) ?>">Điểm danh</a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" disabled
                                        title="<?= $ev['status'] === 'cancelled' ? 'Sự kiện đã hủy' : 'Sự kiện chưa đến giờ bắt đầu' ?>">Điểm danh</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($pg, ['q' => $q]) ?>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

/* =====================================================================
   CHẾ ĐỘ 2: danh sách đăng ký của một sự kiện (UC07)
   ===================================================================== */
$st = db()->prepare(
    "SELECT e.*, c.name AS category_name
     FROM events e JOIN event_categories c ON c.id = e.category_id
     WHERE e.id = ?"
);
$st->execute([$eventId]);
$ev = $st->fetch();
if (!$ev) {
    set_flash('danger', 'Không tìm thấy sự kiện.');
    redirect('admin/registrations.php');
}
$eff = event_effective_status($ev);

// ---- Số liệu tổng hợp của sự kiện (không phụ thuộc bộ lọc) ----
$st = db()->prepare(
    "SELECT COALESCE(SUM(status = 'registered'), 0)                              AS regs,
            COALESCE(SUM(status = 'cancelled'), 0)                              AS cancelled_cnt,
            COALESCE(SUM(status = 'registered' AND attendance = 'present'), 0)  AS present_cnt,
            COALESCE(SUM(status = 'registered' AND attendance = 'absent'), 0)   AS absent_cnt
     FROM registrations WHERE event_id = ?"
);
$st->execute([$eventId]);
$sum       = $st->fetch();
$regs      = (int)$sum['regs'];
$cancelled = (int)$sum['cancelled_cnt'];
$present   = (int)$sum['present_cnt'];
$absent    = (int)$sum['absent_cnt'];
$notYet    = $regs - $present - $absent;
$max       = (int)$ev['max_participants'];
$left      = max(0, $max - $regs);

// ---- Bộ lọc ----
$q  = trim((string)($_GET['q'] ?? ''));
$rs = (string)($_GET['rs'] ?? 'registered');         // trạng thái đăng ký
if (!in_array($rs, ['registered', 'cancelled', 'all'], true)) {
    $rs = 'registered';
}
$att = (string)($_GET['att'] ?? '');                 // kết quả điểm danh
if (!in_array($att, ['present', 'absent', 'none'], true)) {
    $att = '';
}

$where  = ['r.event_id = ?'];
$params = [$eventId];
if ($rs !== 'all') {
    $where[]  = 'r.status = ?';
    $params[] = $rs;
}
if ($q !== '') {
    $like     = '%' . addcslashes($q, '%_\\') . '%';
    $where[]  = '(u.full_name LIKE ? OR u.student_code LIKE ? OR u.class_name LIKE ?)';
    array_push($params, $like, $like, $like);
}
if ($att === 'present' || $att === 'absent') {
    $where[]  = "r.status = 'registered' AND r.attendance = ?";
    $params[] = $att;
} elseif ($att === 'none') {
    $where[]  = "r.status = 'registered' AND r.attendance IS NULL";
}
$whereSql = 'WHERE ' . implode(' AND ', array_map(fn($w) => '(' . $w . ')', $where));

$st = db()->prepare("SELECT COUNT(*) FROM registrations r JOIN users u ON u.id = r.user_id $whereSql");
$st->execute($params);
$pg = paginate((int)$st->fetchColumn(), $page, 20);

$st = db()->prepare(
    "SELECT r.id, r.status, r.registered_at, r.attendance, r.checked_at,
            u.full_name, u.student_code, u.class_name, u.email, u.phone
     FROM registrations r
     JOIN users u ON u.id = r.user_id
     $whereSql
     ORDER BY r.registered_at ASC, r.id ASC
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

$canCheck  = $ev['status'] !== 'cancelled' && strtotime($ev['start_time']) <= time();
$canCancel = !in_array($eff, ['finished', 'cancelled'], true);   // hủy giúp thành viên: chỉ khi sự kiện chưa kết thúc
$back      = (string)($_SERVER['QUERY_STRING'] ?? '');

$pageTitle = 'Danh sách đăng ký';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>
<div class="page-head">
    <div>
        <h1 class="h4 mb-1"><?= e($ev['title']) ?></h1>
        <div class="small text-muted">
            <?= e(fmt_datetime($ev['start_time'])) ?> &middot; <?= e($ev['location']) ?> &middot;
            <?= event_status_badge($eff) ?>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($canCheck): ?>
            <a class="btn btn-primary" href="<?= e(url('admin/attendance.php?event_id=' . $eventId)) ?>">Điểm danh</a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/registrations.php')) ?>">Chọn sự kiện khác</a>
    </div>
</div>

<!-- Số liệu tổng hợp -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg"><div class="stat-chip orange"><div class="num"><?= $regs ?> <small class="fs-6 text-muted">/ <?= $max ?></small></div><div class="lbl">Đăng ký hợp lệ</div></div></div>
    <div class="col-6 col-lg"><div class="stat-chip blue"><div class="num"><?= $ev['status'] === 'cancelled' ? '—' : $left ?></div><div class="lbl">Số chỗ còn lại</div></div></div>
    <div class="col-6 col-lg"><div class="stat-chip orange"><div class="num"><?= $cancelled ?></div><div class="lbl">Đã hủy đăng ký</div></div></div>
    <div class="col-6 col-lg"><div class="stat-chip blue"><div class="num"><?= $present ?></div><div class="lbl">Có mặt</div></div></div>
    <div class="col-6 col-lg"><div class="stat-chip orange"><div class="num"><?= $absent ?></div><div class="lbl">Vắng mặt</div></div></div>
    <div class="col-6 col-lg"><div class="stat-chip blue"><div class="num"><?= $notYet ?></div><div class="lbl">Chưa điểm danh</div></div></div>
</div>

<!-- Bộ lọc -->
<form method="get" class="filter-bar row g-2 align-items-end mb-3">
    <input type="hidden" name="event_id" value="<?= $eventId ?>">
    <div class="col-md-4">
        <label class="form-label small mb-1" for="q">Tìm theo họ tên, MSSV hoặc lớp</label>
        <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-1" for="rs">Trạng thái đăng ký</label>
        <select class="form-select" id="rs" name="rs">
            <option value="registered" <?= $rs === 'registered' ? 'selected' : '' ?>>Đã đăng ký</option>
            <option value="cancelled" <?= $rs === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
            <option value="all" <?= $rs === 'all' ? 'selected' : '' ?>>Tất cả</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-1" for="att">Điểm danh</label>
        <select class="form-select" id="att" name="att">
            <option value="">Tất cả</option>
            <option value="present" <?= $att === 'present' ? 'selected' : '' ?>>Có mặt</option>
            <option value="absent" <?= $att === 'absent' ? 'selected' : '' ?>>Vắng mặt</option>
            <option value="none" <?= $att === 'none' ? 'selected' : '' ?>>Chưa điểm danh</option>
        </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1">Lọc</button>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/registrations.php?event_id=' . $eventId)) ?>">Xóa</a>
    </div>
</form>

<div class="small text-muted mb-2">Hiển thị <strong><?= $pg['total'] ?></strong> lượt đăng ký</div>

<?php if ($regs === 0 && $cancelled === 0): ?>
    <div class="alert alert-info">Chưa có thành viên nào đăng ký sự kiện này.</div>
<?php else: ?>
    <div class="table-responsive rounded mb-3">
        <table class="table table-brand align-middle">
            <thead>
            <tr>
                <th style="width:50px">STT</th>
                <th>Thành viên</th>
                <th>MSSV</th>
                <th>Lớp</th>
                <th>Đăng ký lúc</th>
                <th>Đăng ký</th>
                <th>Điểm danh</th>
                <th class="text-end">Thao tác</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Không tìm thấy thành viên phù hợp.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $n => $r): ?>
                <tr>
                    <td><?= $pg['offset'] + $n + 1 ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($r['full_name']) ?></div>
                        <div class="small text-muted"><?= e($r['email']) ?></div>
                    </td>
                    <td><?= e($r['student_code']) ?></td>
                    <td><?= e($r['class_name']) ?></td>
                    <td class="small"><?= e(fmt_datetime($r['registered_at'])) ?></td>
                    <td>
                        <span class="badge <?= $r['status'] === 'registered' ? 'badge-us-active' : 'badge-us-inactive' ?>">
                            <?= e(reg_status_label($r['status'])) ?>
                        </span>
                    </td>
                    <td>
                        <?= attendance_badge($r['attendance'], $r['status']) ?>
                        <?php if ($r['checked_at']): ?>
                            <div class="small text-muted"><?= e(fmt_datetime($r['checked_at'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if ($r['status'] === 'registered' && $r['attendance'] === null && $canCancel): ?>
                            <form method="post" action="<?= e(url('admin/registration_action.php')) ?>" class="d-inline"
                                  onsubmit="return confirm('Hủy đăng ký của thành viên này?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="back" value="<?= e($back) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Hủy ĐK</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= render_pagination($pg, ['event_id' => $eventId, 'q' => $q, 'rs' => $rs === 'registered' ? '' : $rs, 'att' => $att]) ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
