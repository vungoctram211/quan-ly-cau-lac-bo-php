<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$pageTitle = 'Quản lý thành viên';

// ---- Bộ lọc từ URL ----
$q            = trim((string)($_GET['q'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? '');
if (!in_array($statusFilter, ['pending', 'active', 'inactive', 'rejected'], true)) {
    $statusFilter = '';
}
$page = (int)($_GET['page'] ?? 1);

// ---- Điều kiện WHERE (chỉ liệt kê tài khoản thành viên, không liệt kê Admin) ----
$where  = ["u.role = 'member'"];
$params = [];
if ($q !== '') {
    $like     = '%' . addcslashes($q, '%_\\') . '%';
    $where[]  = '(u.full_name LIKE ? OR u.student_code LIKE ? OR u.class_name LIKE ?)';
    array_push($params, $like, $like, $like);
}
if ($statusFilter !== '') {
    $where[]  = 'u.status = ?';
    $params[] = $statusFilter;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$st = db()->prepare("SELECT COUNT(*) FROM users u $whereSql");
$st->execute($params);
$pg = paginate((int)$st->fetchColumn(), $page);

// Tài khoản chờ duyệt được đưa lên đầu danh sách
$sql = "SELECT u.id, u.email, u.full_name, u.student_code, u.class_name, u.phone, u.status,
               (SELECT COUNT(*) FROM registrations r
                 WHERE r.user_id = u.id AND r.status = 'registered') AS reg_count
        FROM users u
        $whereSql
        ORDER BY (u.status = 'pending') DESC, u.full_name ASC
        LIMIT ? OFFSET ?";
$st = db()->prepare($sql);
$i = 1;
foreach ($params as $v) {
    $st->bindValue($i++, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$st->bindValue($i++, $pg['perPage'], PDO::PARAM_INT);
$st->bindValue($i,   $pg['offset'],  PDO::PARAM_INT);
$st->execute();
$members = $st->fetchAll();

// ---- Số lượng theo từng trạng thái (cho các nút lọc nhanh) ----
$counts = ['pending' => 0, 'active' => 0, 'inactive' => 0, 'rejected' => 0];
foreach (db()->query("SELECT status, COUNT(*) AS n FROM users WHERE role = 'member' GROUP BY status")->fetchAll() as $r) {
    $counts[$r['status']] = (int)$r['n'];
}
$totalMembers = array_sum($counts);

$back = (string)($_SERVER['QUERY_STRING'] ?? '');   // để quay lại đúng trang lọc sau khi thao tác

/** Nút thao tác: form POST nhỏ gửi tới member_action.php (có CSRF token). */
$actionBtn = function (int $id, string $action, string $label, string $cls, string $confirm = '') use ($back): string {
    $onsubmit = $confirm !== '' ? ' onsubmit="return confirm(\'' . e($confirm) . '\');"' : '';
    return '<form method="post" action="' . e(url('admin/member_action.php')) . '" class="d-inline"' . $onsubmit . '>'
         . csrf_field()
         . '<input type="hidden" name="id" value="' . $id . '">'
         . '<input type="hidden" name="action" value="' . e($action) . '">'
         . '<input type="hidden" name="back" value="' . e($back) . '">'
         . '<button type="submit" class="btn btn-sm ' . e($cls) . '">' . e($label) . '</button>'
         . '</form>';
};

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>
<div class="page-head">
    <h1 class="h4">Quản lý thành viên</h1>
    <a class="btn btn-primary" href="<?= e(url('admin/member_form.php')) ?>">+ Thêm thành viên</a>
</div>

<!-- Lọc nhanh theo trạng thái -->
<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="status-pill<?= $statusFilter === '' ? ' active' : '' ?>"
       href="<?= e(url('admin/members.php')) ?>">Tất cả (<?= $totalMembers ?>)</a>
    <?php foreach (['pending', 'active', 'inactive', 'rejected'] as $s): ?>
        <a class="status-pill<?= $statusFilter === $s ? ' active' : '' ?>"
           href="<?= e(url('admin/members.php?status=' . $s)) ?>">
            <?= e(user_status_label($s)) ?> (<?= $counts[$s] ?>)
        </a>
    <?php endforeach; ?>
</div>

<!-- Tìm kiếm -->
<form method="get" class="filter-bar row g-2 align-items-end mb-3">
    <div class="col-md-7">
        <label class="form-label small mb-1" for="q">Tìm theo họ tên, mã số sinh viên hoặc lớp</label>
        <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Ví dụ: Nguyễn, SV2024001, CNTT-K20A">
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-1" for="status">Trạng thái</label>
        <select class="form-select" id="status" name="status">
            <option value="">Tất cả</option>
            <?php foreach (['pending', 'active', 'inactive', 'rejected'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(user_status_label($s)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1">Tìm</button>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/members.php')) ?>" title="Xóa bộ lọc">Xóa</a>
    </div>
</form>

<div class="small text-muted mb-2">Tìm thấy <strong><?= $pg['total'] ?></strong> thành viên</div>

<div class="table-responsive rounded mb-3">
    <table class="table table-brand align-middle">
        <thead>
        <tr>
            <th>Thành viên</th>
            <th>MSSV</th>
            <th>Lớp</th>
            <th>Điện thoại</th>
            <th class="text-center">Lượt ĐK</th>
            <th>Trạng thái</th>
            <th class="text-end">Thao tác</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$members): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Không tìm thấy thành viên phù hợp.</td></tr>
        <?php endif; ?>

        <?php foreach ($members as $m): $id = (int)$m['id']; ?>
            <tr>
                <td>
                    <div class="fw-semibold"><?= e($m['full_name']) ?></div>
                    <div class="small text-muted"><?= e($m['email']) ?></div>
                </td>
                <td><?= e($m['student_code']) ?></td>
                <td><?= e($m['class_name']) ?></td>
                <td><?= e($m['phone'] ?: '—') ?></td>
                <td class="text-center"><?= (int)$m['reg_count'] ?></td>
                <td><?= user_status_badge($m['status']) ?></td>
                <td class="text-end">
                    <div class="d-flex flex-wrap gap-1 justify-content-end">
                        <?php if ($m['status'] === 'pending'): ?>
                            <?= $actionBtn($id, 'approve', 'Duyệt', 'btn-success') ?>
                            <?= $actionBtn($id, 'reject', 'Từ chối', 'btn-outline-danger', 'Từ chối tài khoản này?') ?>
                        <?php elseif ($m['status'] === 'rejected'): ?>
                            <?= $actionBtn($id, 'approve', 'Duyệt lại', 'btn-outline-success') ?>
                        <?php elseif ($m['status'] === 'active'): ?>
                            <?= $actionBtn($id, 'deactivate', 'Ngừng HĐ', 'btn-outline-secondary', 'Ngừng hoạt động tài khoản này? Thành viên sẽ không đăng nhập được.') ?>
                        <?php elseif ($m['status'] === 'inactive'): ?>
                            <?= $actionBtn($id, 'activate', 'Kích hoạt', 'btn-outline-success') ?>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-brand" href="<?= e(url('admin/member_view.php?id=' . $id)) ?>">Xem</a>
                        <a class="btn btn-sm btn-outline-brand" href="<?= e(url('admin/member_form.php?id=' . $id)) ?>">Sửa</a>
                        <?= $actionBtn($id, 'delete', 'Xóa', 'btn-outline-danger', 'Xóa thành viên này? Nếu đã có lịch sử đăng ký, tài khoản sẽ chuyển sang ngừng hoạt động thay vì bị xóa.') ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= render_pagination($pg, ['q' => $q, 'status' => $statusFilter]) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
