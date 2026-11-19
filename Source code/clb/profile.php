<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle = 'Hồ sơ cá nhân';
$userId    = (int)current_user()['id'];

$st = db()->prepare('SELECT id, email, full_name, student_code, class_name, phone, role FROM users WHERE id = ?');
$st->execute([$userId]);
$u = $st->fetch();
if (!$u) {
    redirect('logout.php');
}
$isMember = $u['role'] === 'member';

$errInfo = [];
$errPw   = [];
$form = [
    'full_name'  => $u['full_name'],
    'class_name' => (string)$u['class_name'],
    'phone'      => (string)$u['phone'],
    'email'      => $u['email'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $which = input('form');

    /* ---------- Cập nhật thông tin cá nhân ---------- */
    if ($which === 'info') {
        $form = [
            'full_name'  => input('full_name'),
            'class_name' => input('class_name'),
            'phone'      => input('phone'),
            'email'      => strtolower(input('email')),
        ];

        if (mb_strlen($form['full_name']) < 2 || mb_strlen($form['full_name']) > 100) {
            $errInfo['full_name'] = 'Họ và tên từ 2 đến 100 ký tự.';
        }
        if ($isMember && $form['class_name'] === '') {
            $errInfo['class_name'] = 'Lớp không được để trống.';
        } elseif (mb_strlen($form['class_name']) > 30) {
            $errInfo['class_name'] = 'Lớp tối đa 30 ký tự.';
        }
        if ($form['phone'] !== '' && !preg_match('/^0[0-9]{9,10}$/', $form['phone'])) {
            $errInfo['phone'] = 'Số điện thoại bắt đầu bằng 0 và có 10–11 chữ số.';
        }
        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 100) {
            $errInfo['email'] = 'Email không hợp lệ.';
        } else {
            $st = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $st->execute([$form['email'], $userId]);
            if ($st->fetch()) {
                $errInfo['email'] = 'Email này đã được sử dụng.';
            }
        }

        if (!$errInfo) {
            try {
                $st = db()->prepare('UPDATE users SET full_name = ?, class_name = ?, phone = ?, email = ? WHERE id = ?');
                $st->execute([
                    $form['full_name'],
                    $form['class_name'] !== '' ? $form['class_name'] : null,
                    $form['phone'] !== '' ? $form['phone'] : null,
                    $form['email'],
                    $userId,
                ]);
                $_SESSION['user']['full_name'] = $form['full_name'];   // cập nhật tên trên menu
                set_flash('success', 'Đã cập nhật thông tin cá nhân.');
                redirect('profile.php');
            } catch (PDOException $e) {
                error_log('Profile update error: ' . $e->getMessage());
                $errInfo['general'] = 'Không thể lưu thông tin lúc này. Vui lòng thử lại.';
            }
        }
    }

    /* ---------- Đổi mật khẩu ---------- */
    if ($which === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([$userId]);
        $hash = (string)$st->fetchColumn();

        if (!password_verify($current, $hash)) {
            $errPw['current_password'] = 'Mật khẩu hiện tại không đúng.';
        }
        if (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) {
            $errPw['new_password'] = 'Mật khẩu mới tối thiểu 8 ký tự, gồm cả chữ và số.';
        } elseif ($new === $current) {
            $errPw['new_password'] = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
        } elseif ($new !== $confirm) {
            $errPw['confirm_password'] = 'Mật khẩu nhập lại không khớp.';
        }

        if (!$errPw) {
            try {
                $st = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $st->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
                session_regenerate_id(true);
                set_flash('success', 'Đã đổi mật khẩu thành công.');
                redirect('profile.php');
            } catch (PDOException $e) {
                error_log('Password change error: ' . $e->getMessage());
                $errPw['general'] = 'Không thể đổi mật khẩu lúc này. Vui lòng thử lại.';
            }
        }
    }
}

$parts   = preg_split('/\s+/', trim($u['full_name']));
$initial = mb_strtoupper(mb_substr((string)end($parts), 0, 1));

require __DIR__ . '/includes/header.php';
?>
<h1 class="h3 section-title">Hồ sơ cá nhân</h1>

<div class="row g-4">
    <!-- Thông tin cá nhân -->
    <div class="col-lg-7">
        <div class="card"><div class="card-body p-4">
            <div class="d-flex align-items-center gap-3 mb-3">
                <span class="profile-avatar"><?= e($initial) ?></span>
                <div>
                    <div class="h5 mb-0"><?= e($u['full_name']) ?></div>
                    <div class="small text-muted"><?= $isMember ? 'Thành viên' : 'Quản trị viên' ?></div>
                </div>
            </div>

            <?php if (isset($errInfo['general'])): ?>
                <div class="alert alert-danger"><?= e($errInfo['general']) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="info">
                <h2 class="h6 form-section-title">Thông tin cá nhân</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="full_name">Họ và tên *</label>
                        <input type="text" class="form-control<?= invalid_class($errInfo, 'full_name') ?>"
                               id="full_name" name="full_name" maxlength="100" required value="<?= e($form['full_name']) ?>">
                        <?= field_error($errInfo, 'full_name') ?>
                    </div>
                    <?php if ($isMember): ?>
                        <div class="col-md-6">
                            <label class="form-label" for="student_code">Mã số sinh viên</label>
                            <input type="text" class="form-control" id="student_code" value="<?= e($u['student_code']) ?>" disabled>
                            <div class="form-text">Liên hệ Ban quản trị nếu cần đổi.</div>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-6">
                        <label class="form-label" for="class_name">Lớp<?= $isMember ? ' *' : '' ?></label>
                        <input type="text" class="form-control<?= invalid_class($errInfo, 'class_name') ?>"
                               id="class_name" name="class_name" maxlength="30" value="<?= e($form['class_name']) ?>">
                        <?= field_error($errInfo, 'class_name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email *</label>
                        <input type="email" class="form-control<?= invalid_class($errInfo, 'email') ?>"
                               id="email" name="email" maxlength="100" required value="<?= e($form['email']) ?>">
                        <?= field_error($errInfo, 'email') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Số điện thoại</label>
                        <input type="text" class="form-control<?= invalid_class($errInfo, 'phone') ?>"
                               id="phone" name="phone" maxlength="15" value="<?= e($form['phone']) ?>">
                        <?= field_error($errInfo, 'phone') ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-4">Lưu thông tin</button>
            </form>
        </div></div>
    </div>

    <!-- Đổi mật khẩu -->
    <div class="col-lg-5">
        <div class="card"><div class="card-body p-4">
            <h2 class="h6 form-section-title">Đổi mật khẩu</h2>

            <?php if (isset($errPw['general'])): ?>
                <div class="alert alert-danger"><?= e($errPw['general']) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="password">
                <div class="mb-3">
                    <label class="form-label" for="current_password">Mật khẩu hiện tại *</label>
                    <input type="password" class="form-control<?= invalid_class($errPw, 'current_password') ?>"
                           id="current_password" name="current_password" autocomplete="current-password" required>
                    <?= field_error($errPw, 'current_password') ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="new_password">Mật khẩu mới *</label>
                    <input type="password" class="form-control<?= invalid_class($errPw, 'new_password') ?>"
                           id="new_password" name="new_password" autocomplete="new-password" required>
                    <?= field_error($errPw, 'new_password') ?>
                    <div class="form-text">Tối thiểu 8 ký tự, gồm cả chữ và số.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="confirm_password">Nhập lại mật khẩu mới *</label>
                    <input type="password" class="form-control<?= invalid_class($errPw, 'confirm_password') ?>"
                           id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                    <?= field_error($errPw, 'confirm_password') ?>
                </div>
                <button type="submit" class="btn btn-outline-brand">Đổi mật khẩu</button>
            </form>
        </div></div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
