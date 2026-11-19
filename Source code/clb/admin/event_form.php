<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

// Một form dùng chung: không có ?id => thêm mới; có ?id => sửa
$id     = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$isEdit = $id > 0;
$event  = null;

if ($isEdit) {
    $st = db()->prepare(
        "SELECT e.*,
                (SELECT COUNT(*) FROM registrations r
                  WHERE r.event_id = e.id AND r.status = 'registered') AS registered_count
         FROM events e WHERE e.id = ?"
    );
    $st->execute([$id]);
    $event = $st->fetch();
    if (!$event) {
        set_flash('danger', 'Không tìm thấy sự kiện.');
        redirect('admin/events.php');
    }
}

$categories = db()->query('SELECT id, name FROM event_categories ORDER BY name')->fetchAll();
if (!$categories) {
    set_flash('warning', 'Hãy tạo ít nhất một loại sự kiện trước khi thêm sự kiện.');
    redirect('admin/categories.php');
}

$eff        = $event ? event_effective_status($event) : 'open';
$readonly   = $isEdit && in_array($eff, ['finished', 'cancelled'], true);   // chỉ xem
$registered = $event ? (int)$event['registered_count'] : 0;
$pageTitle  = $isEdit ? ($readonly ? 'Xem sự kiện' : 'Sửa sự kiện') : 'Thêm sự kiện';
$errors     = [];

// Giá trị ban đầu của form
$form = [
    'category_id' => '', 'title' => '', 'description' => '', 'location' => '',
    'start_time' => '', 'end_time' => '', 'registration_deadline' => '', 'max_participants' => '',
];
if ($event) {
    $form = [
        'category_id'           => (string)$event['category_id'],
        'title'                 => $event['title'],
        'description'           => (string)$event['description'],
        'location'              => $event['location'],
        'start_time'            => to_input_datetime($event['start_time']),
        'end_time'              => to_input_datetime($event['end_time']),
        'registration_deadline' => to_input_datetime($event['registration_deadline']),
        'max_participants'      => (string)$event['max_participants'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($readonly) {
        set_flash('danger', 'Sự kiện đã kết thúc hoặc đã hủy chỉ được xem, không thể sửa.');
        redirect('admin/events.php');
    }
    require_csrf();

    $form = [
        'category_id'           => input('category_id'),
        'title'                 => input('title'),
        'description'           => input('description'),
        'location'              => input('location'),
        'start_time'            => input('start_time'),
        'end_time'              => input('end_time'),
        'registration_deadline' => input('registration_deadline'),
        'max_participants'      => input('max_participants'),
    ];

    // ---- Kiểm tra dữ liệu phía máy chủ ----
    $catId = ctype_digit($form['category_id']) ? (int)$form['category_id'] : 0;
    if ($catId <= 0) {
        $errors['category_id'] = 'Vui lòng chọn loại sự kiện.';
    } else {
        $st = db()->prepare('SELECT id FROM event_categories WHERE id = ?');
        $st->execute([$catId]);
        if (!$st->fetch()) {
            $errors['category_id'] = 'Loại sự kiện không tồn tại.';
        }
    }
    if (mb_strlen($form['title']) < 3 || mb_strlen($form['title']) > 200) {
        $errors['title'] = 'Tên sự kiện từ 3 đến 200 ký tự.';
    }
    if ($form['location'] === '' || mb_strlen($form['location']) > 200) {
        $errors['location'] = 'Địa điểm không được để trống và tối đa 200 ký tự.';
    }
    if (mb_strlen($form['description']) > 5000) {
        $errors['description'] = 'Nội dung tối đa 5000 ký tự.';
    }

    $start    = parse_input_datetime($form['start_time']);
    $end      = parse_input_datetime($form['end_time']);
    $deadline = parse_input_datetime($form['registration_deadline']);
    if ($start === null)    { $errors['start_time'] = 'Thời gian bắt đầu không hợp lệ.'; }
    if ($end === null)      { $errors['end_time'] = 'Thời gian kết thúc không hợp lệ.'; }
    if ($deadline === null) { $errors['registration_deadline'] = 'Hạn đăng ký không hợp lệ.'; }

    if ($start !== null && $end !== null && strtotime($end) <= strtotime($start)) {
        $errors['end_time'] = 'Thời gian kết thúc phải sau thời gian bắt đầu.';
    }
    if ($start !== null && $deadline !== null && strtotime($deadline) > strtotime($start)) {
        $errors['registration_deadline'] = 'Hạn đăng ký không được muộn hơn thời gian bắt đầu.';
    }
    // Thời gian bắt đầu phải ở tương lai (khi sửa, chỉ kiểm tra nếu Admin đổi giờ bắt đầu)
    $startChanged = !$isEdit || $form['start_time'] !== to_input_datetime($event['start_time']);
    if ($start !== null && $startChanged && strtotime($start) <= time() && !isset($errors['start_time'])) {
        $errors['start_time'] = 'Thời gian bắt đầu phải ở tương lai.';
    }

    $max = 0;
    if (!ctype_digit($form['max_participants']) || (int)$form['max_participants'] < 1
        || (int)$form['max_participants'] > 10000) {
        $errors['max_participants'] = 'Số chỗ tối đa là số nguyên từ 1 đến 10000.';
    } else {
        $max = (int)$form['max_participants'];
        if ($isEdit && $max < $registered) {
            $errors['max_participants'] = 'Số chỗ không được nhỏ hơn số thành viên đã đăng ký (' . $registered . ').';
        }
    }

    // ---- Lưu ----
    if (!$errors) {
        try {
            $desc = $form['description'] !== '' ? $form['description'] : null;

            if ($isEdit) {
                $st = db()->prepare(
                    'UPDATE events
                     SET category_id = ?, title = ?, description = ?, location = ?,
                         start_time = ?, end_time = ?, registration_deadline = ?, max_participants = ?
                     WHERE id = ?'
                );
                $st->execute([$catId, $form['title'], $desc, $form['location'],
                              $start, $end, $deadline, $max, $id]);
                set_flash('success', 'Cập nhật sự kiện thành công.');

                $changed = $form['location'] !== $event['location']
                        || $form['start_time'] !== to_input_datetime($event['start_time'])
                        || $form['end_time'] !== to_input_datetime($event['end_time']);
                if ($registered > 0 && $changed) {
                    set_flash('warning', 'Sự kiện đã có ' . $registered . ' người đăng ký. Hãy thông báo cho họ biết thời gian hoặc địa điểm mới.');
                }
            } else {
                $st = db()->prepare(
                    'INSERT INTO events
                        (category_id, title, description, location, start_time, end_time, registration_deadline, max_participants, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $st->execute([$catId, $form['title'], $desc, $form['location'],
                              $start, $end, $deadline, $max, 'open']);
                set_flash('success', 'Thêm sự kiện thành công.');
            }
            redirect('admin/events.php');
        } catch (PDOException $e) {
            error_log('Event save error: ' . $e->getMessage());
            $errors['general'] = 'Không thể lưu sự kiện lúc này. Vui lòng thử lại.';
        }
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>
<div class="page-head">
    <h1 class="h4"><?= e($pageTitle) ?></h1>
    <?php if ($isEdit): ?>
        <div class="d-flex align-items-center gap-2">
            <?= event_status_badge($eff) ?>
            <span class="small text-muted">Đã đăng ký: <strong><?= $registered ?></strong> / <?= (int)$event['max_participants'] ?></span>
        </div>
    <?php endif; ?>
</div>

<?php if ($readonly): ?>
    <div class="alert alert-info">Sự kiện đã kết thúc hoặc đã hủy chỉ được xem, không thể sửa.</div>
<?php endif; ?>
<?php if (isset($errors['general'])): ?>
    <div class="alert alert-danger"><?= e($errors['general']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-4">
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">

            <fieldset <?= $readonly ? 'disabled' : '' ?>>
                <h2 class="h6 form-section-title">Thông tin chung</h2>
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <label class="form-label" for="title">Tên sự kiện *</label>
                        <input type="text" class="form-control<?= invalid_class($errors, 'title') ?>"
                               id="title" name="title" maxlength="200" required value="<?= e($form['title']) ?>">
                        <?= field_error($errors, 'title') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="category_id">Loại sự kiện *</label>
                        <select class="form-select<?= invalid_class($errors, 'category_id') ?>"
                                id="category_id" name="category_id" required>
                            <option value="">-- Chọn loại --</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"
                                    <?= $form['category_id'] === (string)$c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error($errors, 'category_id') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="location">Địa điểm *</label>
                        <input type="text" class="form-control<?= invalid_class($errors, 'location') ?>"
                               id="location" name="location" maxlength="200" required value="<?= e($form['location']) ?>">
                        <?= field_error($errors, 'location') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Nội dung, mô tả</label>
                        <textarea class="form-control<?= invalid_class($errors, 'description') ?>"
                                  id="description" name="description" rows="4" maxlength="5000"><?= e($form['description']) ?></textarea>
                        <?= field_error($errors, 'description') ?>
                    </div>
                </div>

                <h2 class="h6 form-section-title">Thời gian và số chỗ</h2>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="start_time">Bắt đầu *</label>
                        <input type="datetime-local" class="form-control<?= invalid_class($errors, 'start_time') ?>"
                               id="start_time" name="start_time" required value="<?= e($form['start_time']) ?>">
                        <?= field_error($errors, 'start_time') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="end_time">Kết thúc *</label>
                        <input type="datetime-local" class="form-control<?= invalid_class($errors, 'end_time') ?>"
                               id="end_time" name="end_time" required value="<?= e($form['end_time']) ?>">
                        <?= field_error($errors, 'end_time') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="registration_deadline">Hạn đăng ký *</label>
                        <input type="datetime-local" class="form-control<?= invalid_class($errors, 'registration_deadline') ?>"
                               id="registration_deadline" name="registration_deadline" required
                               value="<?= e($form['registration_deadline']) ?>">
                        <?= field_error($errors, 'registration_deadline') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="max_participants">Số chỗ tối đa *</label>
                        <input type="number" class="form-control<?= invalid_class($errors, 'max_participants') ?>"
                               id="max_participants" name="max_participants" min="1" max="10000" required
                               value="<?= e($form['max_participants']) ?>">
                        <?= field_error($errors, 'max_participants') ?>
                    </div>
                </div>
            </fieldset>

            <div class="d-flex gap-2 mt-4">
                <?php if (!$readonly): ?>
                    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Lưu thay đổi' : 'Thêm sự kiện' ?></button>
                <?php endif; ?>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/events.php')) ?>">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
