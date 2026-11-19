<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$pageTitle = 'Đăng ký tài khoản';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $fullName    = input('full_name');
    $studentCode = strtoupper(input('student_code'));
    $className   = input('class_name');
    $phone       = input('phone');
    $email       = strtolower(input('email'));
    $password    = (string)($_POST['password'] ?? '');
    $confirm     = (string)($_POST['password_confirm'] ?? '');

    // ---- 1. Kiểm tra định dạng (phía máy chủ) ----
    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
        $errors['full_name'] = 'Họ và tên từ 2 đến 100 ký tự.';
    }
    if (!preg_match('/^[A-Z0-9]{6,20}$/', $studentCode)) {
        $errors['student_code'] = 'Mã số sinh viên gồm 6–20 ký tự chữ hoặc số.';
    }
    if ($className === '' || mb_strlen($className) > 30) {
        $errors['class_name'] = 'Lớp không được để trống và tối đa 30 ký tự.';
    }
    if ($phone !== '' && !preg_match('/^0[0-9]{9,10}$/', $phone)) {
        $errors['phone'] = 'Số điện thoại bắt đầu bằng 0 và có 10–11 chữ số.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
        $errors['email'] = 'Email không hợp lệ.';
    }
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Mật khẩu tối thiểu 8 ký tự, gồm cả chữ và số.';
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'Mật khẩu nhập lại không khớp.';
    }

    // ---- 2. Kiểm tra trùng (prepared statement) ----
    if (!isset($errors['email'])) {
        $st = db()->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) {
            $errors['email'] = 'Email này đã được sử dụng.';
        }
    }
    if (!isset($errors['student_code'])) {
        $st = db()->prepare('SELECT id FROM users WHERE student_code = ?');
        $st->execute([$studentCode]);
        if ($st->fetch()) {
            $errors['student_code'] = 'Mã số sinh viên này đã được đăng ký.';
        }
    }

    // ---- 3. Lưu tài khoản (trạng thái chờ duyệt) ----
    if (!$errors) {
        try {
            $st = db()->prepare(
                'INSERT INTO users (email, password_hash, full_name, student_code, class_name, phone, role, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $st->execute([
                $email,
                password_hash($password, PASSWORD_DEFAULT),   // băm mật khẩu, không lưu mật khẩu gốc
                $fullName,
                $studentCode,
                $className,
                $phone !== '' ? $phone : null,
                'member',
                'pending',
            ]);
            set_flash('success', 'Đăng ký thành công. Tài khoản đang chờ Ban quản trị duyệt, bạn có thể đăng nhập sau khi được duyệt.');
            redirect('login.php');
        } catch (PDOException $e) {
            error_log('Register error: ' . $e->getMessage());
            $errors['general'] = 'Không thể tạo tài khoản lúc này. Vui lòng thử lại.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="text-center mb-3">
    <img src="<?= e(url('assets/img/logo.png')) ?>" alt="Logo <?= e(APP_NAME) ?>" class="auth-logo">
</div>
<div class="card auth-card-wide">
    <div class="card-body p-4">
        <h1 class="h4 mb-3">Đăng ký tài khoản thành viên</h1>

        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-danger"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="full_name">Họ và tên *</label>
                    <input type="text" class="form-control<?= invalid_class($errors, 'full_name') ?>"
                           id="full_name" name="full_name" value="<?= old('full_name') ?>" maxlength="100" required>
                    <?= field_error($errors, 'full_name') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="student_code">Mã số sinh viên *</label>
                    <input type="text" class="form-control<?= invalid_class($errors, 'student_code') ?>"
                           id="student_code" name="student_code" value="<?= old('student_code') ?>" maxlength="20" required>
                    <?= field_error($errors, 'student_code') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="class_name">Lớp *</label>
                    <input type="text" class="form-control<?= invalid_class($errors, 'class_name') ?>"
                           id="class_name" name="class_name" value="<?= old('class_name') ?>" maxlength="30" required>
                    <?= field_error($errors, 'class_name') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Email *</label>
                    <input type="email" class="form-control<?= invalid_class($errors, 'email') ?>"
                           id="email" name="email" value="<?= old('email') ?>" maxlength="100" required>
                    <?= field_error($errors, 'email') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="phone">Số điện thoại</label>
                    <input type="text" class="form-control<?= invalid_class($errors, 'phone') ?>"
                           id="phone" name="phone" value="<?= old('phone') ?>" maxlength="15">
                    <?= field_error($errors, 'phone') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password">Mật khẩu *</label>
                    <input type="password" class="form-control<?= invalid_class($errors, 'password') ?>"
                           id="password" name="password" autocomplete="new-password" required>
                    <?= field_error($errors, 'password') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password_confirm">Nhập lại mật khẩu *</label>
                    <input type="password" class="form-control<?= invalid_class($errors, 'password_confirm') ?>"
                           id="password_confirm" name="password_confirm" autocomplete="new-password" required>
                    <?= field_error($errors, 'password_confirm') ?>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <a href="<?= e(url('login.php')) ?>">Đã có tài khoản? Đăng nhập</a>
                <button type="submit" class="btn btn-primary">Đăng ký</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
