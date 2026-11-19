<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Sự kiện';

// ---- Bộ lọc từ URL ----
$q          = trim((string)($_GET['q'] ?? ''));
$categoryId = (int)($_GET['category_id'] ?? 0);
$when       = (string)($_GET['when'] ?? 'upcoming');
if (!in_array($when, ['upcoming', 'past', 'all'], true)) {
    $when = 'upcoming';
}
$page = (int)($_GET['page'] ?? 1);

$now    = date('Y-m-d H:i:s');
$where  = [];
$params = [];
if ($q !== '') {
    $where[]  = 'e.title LIKE ?';
    $params[] = '%' . addcslashes($q, '%_\\') . '%';
}
if ($categoryId > 0) {
    $where[]  = 'e.category_id = ?';
    $params[] = $categoryId;
}
if ($when === 'upcoming') {          // chưa kết thúc và không bị hủy
    $where[]  = "e.status <> 'cancelled' AND e.end_time > ?";
    $params[] = $now;
} elseif ($when === 'past') {        // đã kết thúc
    $where[]  = "e.status <> 'cancelled' AND e.end_time <= ?";
    $params[] = $now;
}                                    // 'all': không lọc thời gian (có cả sự kiện đã hủy)
$whereSql = $where ? 'WHERE ' . implode(' AND ', array_map(fn($w) => '(' . $w . ')', $where)) : '';

$st = db()->prepare("SELECT COUNT(*) FROM events e $whereSql");
$st->execute($params);
$pg = paginate((int)$st->fetchColumn(), $page, 9);

// Mã thành viên đang xem (0 nếu là khách) để biết họ đã đăng ký sự kiện nào
$uid   = is_logged_in() ? (int)current_user()['id'] : 0;
$order = ($when === 'upcoming') ? 'ASC' : 'DESC';

$sql = "SELECT e.*, c.name AS category_name,
               (SELECT COUNT(*) FROM registrations r
                 WHERE r.event_id = e.id AND r.status = 'registered') AS registered_count,
               (SELECT r2.status FROM registrations r2
                 WHERE r2.event_id = e.id AND r2.user_id = ?) AS my_status
        FROM events e
        JOIN event_categories c ON c.id = e.category_id
        $whereSql
        ORDER BY e.start_time $order
        LIMIT ? OFFSET ?";
$st = db()->prepare($sql);
$i = 1;
foreach (array_merge([$uid], $params) as $v) {
    $st->bindValue($i++, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$st->bindValue($i++, $pg['perPage'], PDO::PARAM_INT);
$st->bindValue($i,   $pg['offset'],  PDO::PARAM_INT);
$st->execute();
$events = $st->fetchAll();

$categories = db()->query('SELECT id, name FROM event_categories ORDER BY name')->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<h1 class="h3 section-title">Sự kiện của <?= e(APP_NAME) ?></h1>

<form method="get" class="filter-bar row g-2 align-items-end mb-3">
    <div class="col-md-4">
        <label class="form-label small mb-1" for="q">Tìm theo tên</label>
        <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Nhập tên sự kiện">
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-1" for="category_id">Loại sự kiện</label>
        <select class="form-select" id="category_id" name="category_id">
            <option value="0">Tất cả</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-1" for="when">Thời gian</label>
        <select class="form-select" id="when" name="when">
            <option value="upcoming" <?= $when === 'upcoming' ? 'selected' : '' ?>>Sắp diễn ra</option>
            <option value="past" <?= $when === 'past' ? 'selected' : '' ?>>Đã diễn ra</option>
            <option value="all" <?= $when === 'all' ? 'selected' : '' ?>>Tất cả</option>
        </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1">Lọc</button>
        <a class="btn btn-outline-secondary" href="<?= e(url('events.php')) ?>" title="Xóa bộ lọc">Xóa</a>
    </div>
</form>

<div class="small text-muted mb-3">Tìm thấy <strong><?= $pg['total'] ?></strong> sự kiện</div>

<?php if (!$events): ?>
    <div class="alert alert-info">Không tìm thấy sự kiện phù hợp.</div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <?php foreach ($events as $ev):
            $ts   = strtotime($ev['start_time']);
            $eff  = event_effective_status($ev);
            $left = max(0, (int)$ev['max_participants'] - (int)$ev['registered_count']);
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="card event-card h-100">
                    <div class="card-body d-flex gap-3">
                        <div class="date-block flex-shrink-0">
                            <div class="day"><?= e(date('d', $ts)) ?></div>
                            <div class="mon">Tháng <?= e(date('n', $ts)) ?></div>
                        </div>
                        <div class="flex-grow-1">
                            <span class="badge badge-cat mb-2"><?= e($ev['category_name']) ?></span>
                            <h2 class="h6 mb-2">
                                <a class="text-decoration-none stretched-link"
                                   href="<?= e(url('event.php?id=' . (int)$ev['id'])) ?>"><?= e($ev['title']) ?></a>
                            </h2>
                            <div class="small text-muted"><?= e(date('H:i', $ts)) ?> &middot; <?= e($ev['location']) ?></div>
                            <div class="mt-2 d-flex flex-wrap gap-1">
                                <?= event_status_badge($eff) ?>
                                <?php if ($eff === 'open'): ?>
                                    <?php if ($left === 0): ?>
                                        <span class="badge badge-seat-full">Hết chỗ</span>
                                    <?php else: ?>
                                        <span class="badge <?= $left <= 3 ? 'badge-seat-low' : 'badge-seat-ok' ?>">Còn <?= $left ?> chỗ</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($ev['my_status'] === 'registered'): ?>
                                    <span class="badge badge-mine">Bạn đã đăng ký</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?= render_pagination($pg, ['q' => $q, 'category_id' => $categoryId, 'when' => $when]) ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
