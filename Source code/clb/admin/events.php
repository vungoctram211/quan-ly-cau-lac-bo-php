<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$pageTitle = 'Quản lý sự kiện';

// ---- Đọc bộ lọc từ URL (GET) ----
$q            = trim((string)($_GET['q'] ?? ''));
$categoryId   = (int)($_GET['category_id'] ?? 0);
$statusFilter = (string)($_GET['status'] ?? '');
if (!in_array($statusFilter, ['open', 'closed', 'finished', 'cancelled'], true)) {
    $statusFilter = '';
}
$page = (int)($_GET['page'] ?? 1);

// ---- Ghép điều kiện WHERE (luôn dùng dấu ? để tránh SQL Injection) ----
$where  = [];
$params = [];
$now    = date('Y-m-d H:i:s');   // dùng giờ của PHP để khớp với hàm event_effective_status()

if ($q !== '') {
    $where[]  = 'e.title LIKE ?';
    $params[] = '%' . addcslashes($q, '%_\\') . '%';
}
if ($categoryId > 0) {
    $where[]  = 'e.category_id = ?';
    $params[] = $categoryId;
}
switch ($statusFilter) {
    case 'cancelled':
        $where[] = "e.status = 'cancelled'";
        break;
    case 'finished':
        $where[]  = "e.status <> 'cancelled' AND (e.status = 'finished' OR e.end_time <= ?)";
        $params[] = $now;
        break;
    case 'closed':
        $where[] = "e.status NOT IN ('cancelled', 'finished') AND e.end_time > ?
                    AND (e.status = 'closed' OR e.registration_deadline <= ?)";
        array_push($params, $now, $now);
        break;
    case 'open':
        $where[] = "e.status = 'open' AND e.end_time > ? AND e.registration_deadline > ?";
        array_push($params, $now, $now);
        break;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', array_map(fn($w) => '(' . $w . ')', $where)) : '';

// ---- Đếm tổng để phân trang ----
$st = db()->prepare("SELECT COUNT(*) FROM events e $whereSql");
$st->execute($params);
$pg = paginate((int)$st->fetchColumn(), $page);

// ---- Lấy dữ liệu trang hiện tại (LIMIT/OFFSET phải bind kiểu số nguyên) ----
$sql = "SELECT e.*, c.name AS category_name,
               (SELECT COUNT(*) FROM registrations r
                 WHERE r.event_id = e.id AND r.status = 'registered') AS registered_count
        FROM events e
        JOIN event_categories c ON c.id = e.category_id
        $whereSql
        ORDER BY e.start_time DESC
        LIMIT ? OFFSET ?";
$st = db()->prepare($sql);
$i = 1;
foreach ($params as $v) {
    $st->bindValue($i++, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$st->bindValue($i++, $pg['perPage'], PDO::PARAM_INT);
$st->bindValue($i,   $pg['offset'],  PDO::PARAM_INT);
$st->execute();
$events = $st->fetchAll();

$categories = db()->query('SELECT id, name FROM event_categories ORDER BY name')->fetchAll();

/** Tạo nút thao tác (form POST nhỏ gửi tới event_action.php, có CSRF token). */
$actionBtn = function (int $id, string $action, string $label, string $cls, string $confirm = ''): string {
    $onsubmit = $confirm !== '' ? ' onsubmit="return confirm(\'' . e($confirm) . '\');"' : '';
    return '<form method="post" action="' . e(url('admin/event_action.php')) . '" class="d-inline"' . $onsubmit . '>'
         . csrf_field()
         . '<input type="hidden" name="id" value="' . $id . '">'
         . '<input type="hidden" name="action" value="' . e($action) . '">'
         . '<button type="submit" class="btn btn-sm ' . e($cls) . '">' . e($label) . '</button>'
         . '</form>';
};

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>
<div class="page-head">
    <h1 class="h4">Quản lý sự kiện</h1>
    <a class="btn btn-primary" href="<?= e(url('admin/event_form.php')) ?>">+ Thêm sự kiện</a>
</div>

<!-- Bộ lọc -->
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
                <option value="<?= (int)$c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-1" for="status">Trạng thái</label>
        <select class="form-select" id="status" name="status">
            <option value="">Tất cả</option>
            <?php foreach (['open', 'closed', 'finished', 'cancelled'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>>
                    <?= e(event_status_label($s)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1">Lọc</button>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/events.php')) ?>" title="Xóa bộ lọc">Xóa</a>
    </div>
</form>

<div class="small text-muted mb-2">Tìm thấy <strong><?= $pg['total'] ?></strong> sự kiện</div>

<div class="table-responsive rounded mb-3">
    <table class="table table-brand align-middle">
        <thead>
        <tr>
            <th>Sự kiện</th>
            <th>Thời gian</th>
            <th>Hạn đăng ký</th>
            <th style="min-width:130px">Đăng ký</th>
            <th>Trạng thái</th>
            <th class="text-end">Thao tác</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$events): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Không tìm thấy sự kiện phù hợp.</td></tr>
        <?php endif; ?>

        <?php foreach ($events as $ev):
            $id    = (int)$ev['id'];
            $eff   = event_effective_status($ev);
            $reg   = (int)$ev['registered_count'];
            $max   = (int)$ev['max_participants'];
            $pct   = $max > 0 ? min(100, (int)round($reg / $max * 100)) : 0;
            $canEdit   = !in_array($eff, ['finished', 'cancelled'], true);
            $canClose  = ($eff === 'open');
            $canReopen = ($ev['status'] === 'closed'
                          && strtotime($ev['registration_deadline']) > time()
                          && strtotime($ev['end_time']) > time());
            $canCancel = in_array($eff, ['open', 'closed'], true);
        ?>
            <tr>
                <td>
                    <div class="fw-semibold"><?= e($ev['title']) ?></div>
                    <span class="badge badge-cat"><?= e($ev['category_name']) ?></span>
                    <span class="small text-muted ms-1"><?= e($ev['location']) ?></span>
                </td>
                <td class="small">
                    <?= e(fmt_datetime($ev['start_time'])) ?><br>
                    <span class="text-muted">đến <?= e(fmt_datetime($ev['end_time'])) ?></span>
                </td>
                <td class="small"><?= e(fmt_datetime($ev['registration_deadline'])) ?></td>
                <td>
                    <div class="small mb-1"><strong><?= $reg ?></strong> / <?= $max ?></div>
                    <div class="progress"><div class="progress-bar" style="width: <?= $pct ?>%"></div></div>
                </td>
                <td><?= event_status_badge($eff) ?></td>
                <td class="text-end">
                    <div class="d-flex flex-wrap gap-1 justify-content-end">
                        <a class="btn btn-sm btn-outline-brand"
                           href="<?= e(url('admin/event_form.php?id=' . $id)) ?>"><?= $canEdit ? 'Sửa' : 'Xem' ?></a>
                        <a class="btn btn-sm btn-outline-secondary"
                           href="<?= e(url('admin/registrations.php?event_id=' . $id)) ?>">Đăng ký</a>
                        <?php if ($canClose):  echo $actionBtn($id, 'close',  'Đóng ĐK',  'btn-outline-secondary', 'Đóng đăng ký sự kiện này?'); endif; ?>
                        <?php if ($canReopen): echo $actionBtn($id, 'open',   'Mở lại',   'btn-outline-success'); endif; ?>
                        <?php if ($canCancel): echo $actionBtn($id, 'cancel', 'Hủy',      'btn-outline-warning', 'Hủy sự kiện này? Thành viên sẽ thấy sự kiện ở trạng thái đã hủy.'); endif; ?>
                        <?php echo $actionBtn($id, 'delete', 'Xóa', 'btn-outline-danger', 'Xóa sự kiện này? Nếu đã có người đăng ký, sự kiện sẽ chuyển sang trạng thái đã hủy thay vì bị xóa.'); ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= render_pagination($pg, ['q' => $q, 'category_id' => $categoryId, 'status' => $statusFilter]) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
