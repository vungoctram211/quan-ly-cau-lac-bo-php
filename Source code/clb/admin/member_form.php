<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

// Một form dùng chung: không có ?id => thêm mới; có ?id => sửa
$id     = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$isEdit = $id > 0;
$member = null;

if ($isEdit) {
    $st = db()->prepare(
        "SELECT id, email, full_name, student_code, class_name, phone, status
         FROM users WHERE id = ? AND role = 'member'"
    );
    $st->execute([$id]);
    $member = $st->fetch();
    if (!$member) {
        set_flash('danger', 'Không tìm thấy thành viên.');
        redirect('admin/members.php');
    }
}

$pageTitle = $isEdit ? 'Sửa thành viên' : 'Thêm thành viên';
$errors    = [];
$form = [
    'full_name' => $member['full_name'] ?? '',
    'student_code' => $member['student_code'] ?? '',
    'class_name' => $member['class_name'] ?? '',
    'phone' => (string)($member['phone'] ?? ''),
    'email' => $member['email'] ?? '',
    'password' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $form = [
        'full_name'    => input('full_name'),
        'student_code' => strtoupper(input('student_code')),
        'class_name'   => input('class_name'),
        'phone'        => input('phone'),
        'email'        => strtolower(input('email')),
        'password'     => (string)($_POST['password'] ?? ''),
    ];

    // Khi sửa: để trống mật khẩu nghĩa là giữ nguyên mật khẩu cũ
    $errors = validate_member_fields($form, $isEdit ? $id : null, !$isEdit);

    if (!$errors) {
        try {
            $phone = $form['phone'] !== '' ? $form['phone'] : null;

            if ($isEdit) {
                if ($form['password'] !== '') {
                    $st = db()->prepare(
                        "UPDATE users SET email = ?, full_name = ?, student_code = ?, class_name = ?, phone = ?, password_hash = ?
                         WHERE id = ? AND role = 'member'"
                    );
                    $st->execute([$form['email'], $form['full_name'], $form['student_code'], $form['class_name'],
                                  $phone, password_hash($form['password'], PASSWORD_DEFAULT), $id]);
                } else {
                    $st = db()->prepare(
                        "UPDATE users SET email = ?, full_name = ?, student_code = ?, class_name = ?, phone = ?
                         WHERE id = ? AND role = 'member'"
                    );
                    $st->execute([$form['email'], $form['full_name'], $form['student_code'], $form['class_name'], $phone, $id]);
                }
                set_flash('success', 'Cập nhật thông tin thành viên thành công.');
            } else {
                // Thành viên do Admin thêm được kích hoạt ngay (không cần chờ duyệt)
                $st = db()->prepare(
                    "INSERT INTO users (email, password_hash, full_name, student_code, class_name, phone, role, status)
                     VALUES (?, ?, ?, ?, ?, ?, 'member', 'active')"
                );
                $st->execute([$form['email'], password_hash($form['password'], PASSWORD_DEFAULT),
                              $form['full_name'], $form['student_code'], $form['class_name'], $phone]);
                set_flash('success', 'Đã thêm thành viên ' . $form['full_name'] . '. Hãy gửi mật khẩu khởi tạo cho thành viên.');
            }
            redirect('admin/members.php');
        } catch (PDOException $e) {
            error_log('Member save error: ' . $e->getMessage());
            $errors['general'] = 'Không thể lưu thông tin lúc này. Vui lòng thử lại.';
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
            <?= user_status_badge($member['status']) ?>
            <a class="btn btn-sm btn-outline-brand" href="<?= e(url('admin/member_view.php?id=' . $id)) ?>">Xem hồ sơ</a>
        </div>
    <?php endif; ?>
</div>

<?php if (isset($errors['general'])): ?>
    <div class="alert alert-danger"><?= e($errors['general']) ?></div>
<?php endif; ?>

<div class="card auth-card-wide mx-0" style="max-width: 760px;">
    <div class="card-body p-4">
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">

            <h2 class="h6 form-section-title">Thông tin thành viên</h2>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="full_name">Họ và tên *</label>
                    <input type="text" class="form-control<?= invalid_class($errors, 'full_name') ?>"
                           id="full_name" name="full_name" maxlength="100" required value="<?= e($form['full_name']) ?>">
                    <?= field_error($errors, 'full_name') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="student_code">Mã số sinh viên *</label>
                    <input type="text" class="form-control<?= invalid_class($errors, 'student_code') ?>"
                           id="student_code" name="student_code" maxlength="20" required value="<?= e($form['student_code']) ?>">
                    <?= field_error($errors, 'student_code') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="class_name">Lớp *</label>
                    <input type="text" class="form-control<?= invalid_class($errors, 'class_name') ?>"
                           id="class_name" name="class_name" maxlength="30" required value="<?= e($form['class_name']) ?>">
                    <?= field_error($errors, 'class_name') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Email *</label>
                    <input type="email" class="form-control<?= invalid_class($errors, 'email') ?>"
                           id="email" name="email" maxlength="100" required value="<?= e($form['email']) ?>">
                    <?= field_error($errors, 'email') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="phone">Số điện thoại</label>
                    <input type="text" class="form-control<?= invalid_class($errors, 'phone') ?>"
                           id="phone" name="phone" maxlength="15" value="<?= e($form['phone']) ?>">
                    <?= field_error($errors, 'phone') ?>
                </div>
            </div>

            <h2 class="h6 form-section-title mt-4">Mật khẩu</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="password">
                        <?= $isEdit ? 'Mật khẩu mới' : 'Mật khẩu khởi tạo *' ?>
                    </label>
                    <input type="password" class="form-control<?= invalid_class($errors, 'password') ?>"
                           id="password" name="password" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                    <?= field_error($errors, 'password') ?>
                    <?php if ($isEdit): ?>
                        <div class="form-text">Để trống nếu giữ nguyên mật khẩu hiện tại.</div>
                    <?php else: ?>
                        <div class="form-text">Tối thiểu 8 ký tự, gồm cả chữ và số.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Lưu thay đổi' : 'Thêm thành viên' ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/members.php')) ?>">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
