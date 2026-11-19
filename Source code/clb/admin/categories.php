<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$pageTitle = 'Quản lý loại sự kiện';
$errors    = [];
$editId    = 0;
$nameValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = input('action');
    $id     = (int)input('id');
    $name   = input('name');

    try {
        if ($action === 'create' || $action === 'update') {
            // ---- Kiểm tra dữ liệu ----
            if ($name === '' || mb_strlen($name) > 100) {
                $errors['name'] = 'Tên loại sự kiện từ 1 đến 100 ký tự.';
            } else {
                if ($action === 'update') {
                    $st = db()->prepare('SELECT id FROM event_categories WHERE id = ?');
                    $st->execute([$id]);
                    if (!$st->fetch()) {
                        set_flash('danger', 'Không tìm thấy loại sự kiện cần sửa.');
                        redirect('admin/categories.php');
                    }
                }
                // Kiểm tra trùng tên (loại trừ chính nó khi sửa)
                $st = db()->prepare('SELECT id FROM event_categories WHERE name = ? AND id <> ?');
                $st->execute([$name, $action === 'update' ? $id : 0]);
                if ($st->fetch()) {
                    $errors['name'] = 'Tên loại sự kiện này đã tồn tại.';
                }
            }

            if (!$errors) {
                if ($action === 'create') {
                    $st = db()->prepare('INSERT INTO event_categories (name) VALUES (?)');
                    $st->execute([$name]);
                    set_flash('success', 'Đã thêm loại sự kiện "' . $name . '".');
                } else {
                    $st = db()->prepare('UPDATE event_categories SET name = ? WHERE id = ?');
                    $st->execute([$name, $id]);
                    set_flash('success', 'Đã cập nhật loại sự kiện.');
                }
                redirect('admin/categories.php');
            }

            // Có lỗi: hiển thị lại form với dữ liệu vừa nhập
            $editId    = ($action === 'update') ? $id : 0;
            $nameValue = $name;

        } elseif ($action === 'delete') {
            // Không cho xóa loại đang có sự kiện (đồng thời CSDL cũng chặn bằng khóa ngoại RESTRICT)
            $st = db()->prepare('SELECT COUNT(*) FROM events WHERE category_id = ?');
            $st->execute([$id]);
            $n = (int)$st->fetchColumn();

            if ($n > 0) {
                set_flash('danger', 'Không thể xóa: loại này đang có ' . $n . ' sự kiện.');
            } else {
                $st = db()->prepare('DELETE FROM event_categories WHERE id = ?');
                $st->execute([$id]);
                set_flash($st->rowCount() > 0 ? 'success' : 'warning',
                          $st->rowCount() > 0 ? 'Đã xóa loại sự kiện.' : 'Không tìm thấy loại sự kiện cần xóa.');
            }
            redirect('admin/categories.php');
        }
    } catch (PDOException $e) {
        error_log('Categories error: ' . $e->getMessage());
        set_flash('danger', 'Không thể thực hiện thao tác lúc này. Vui lòng thử lại.');
        redirect('admin/categories.php');
    }
}

// Mở form sửa khi có ?edit=ID (và chưa ở trạng thái báo lỗi)
if (!$errors && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $wantEdit = (int)($_GET['edit'] ?? 0);
    if ($wantEdit > 0) {
        $st = db()->prepare('SELECT id, name FROM event_categories WHERE id = ?');
        $st->execute([$wantEdit]);
        if ($row = $st->fetch()) {
            $editId    = (int)$row['id'];
            $nameValue = $row['name'];
        }
    }
}

$categories = db()->query(
    'SELECT c.id, c.name, COUNT(e.id) AS event_count
     FROM event_categories c
     LEFT JOIN events e ON e.category_id = c.id
     GROUP BY c.id, c.name
     ORDER BY c.name'
)->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>
<div class="page-head">
    <h1 class="h4">Quản lý loại sự kiện</h1>
    <span class="text-muted small"><?= count($categories) ?> loại</span>
</div>

<div class="row g-4">
    <!-- Danh sách -->
    <div class="col-lg-7">
        <div class="table-responsive rounded">
            <table class="table table-brand align-middle">
                <thead>
                <tr>
                    <th style="width:60px">STT</th>
                    <th>Tên loại</th>
                    <th class="text-center">Số sự kiện</th>
                    <th class="text-end">Thao tác</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$categories): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">Chưa có loại sự kiện nào.</td></tr>
                <?php endif; ?>
                <?php foreach ($categories as $i => $c): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="fw-semibold"><?= e($c['name']) ?></td>
                        <td class="text-center"><span class="badge badge-cat"><?= (int)$c['event_count'] ?></span></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-brand"
                               href="<?= e(url('admin/categories.php?edit=' . (int)$c['id'])) ?>">Sửa</a>
                            <form method="post" class="d-inline"
                                  onsubmit="return confirm('Xóa loại sự kiện này?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    <?= (int)$c['event_count'] > 0 ? 'disabled title="Loại đang có sự kiện, không thể xóa"' : '' ?>>
                                    Xóa
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Form thêm / sửa -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <h2 class="h6 form-section-title">
                    <?= $editId > 0 ? 'Sửa loại sự kiện' : 'Thêm loại sự kiện' ?>
                </h2>
                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editId > 0 ? 'update' : 'create' ?>">
                    <input type="hidden" name="id" value="<?= $editId ?>">
                    <div class="mb-3">
                        <label class="form-label" for="name">Tên loại *</label>
                        <input type="text" class="form-control<?= invalid_class($errors, 'name') ?>"
                               id="name" name="name" maxlength="100" required
                               value="<?= e($nameValue) ?>" placeholder="Ví dụ: Workshop">
                        <?= field_error($errors, 'name') ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <?= $editId > 0 ? 'Cập nhật' : 'Thêm loại' ?>
                        </button>
                        <?php if ($editId > 0): ?>
                            <a class="btn btn-outline-secondary" href="<?= e(url('admin/categories.php')) ?>">Hủy</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
