<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$pageTitle = 'Đăng nhập';
$error = '';
$identity = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $identity = input('identity');                 // email hoặc mã số sinh viên
    $password = (string)($_POST['password'] ?? '');

    // Giới hạn đăng nhập sai (chống dò mật khẩu): quá 5 lần trong 5 phút thì tạm khóa
    $fail = $_SESSION['login_fail'] ?? ['count' => 0, 'first' => time()];
    if (time() - $fail['first'] > 300) {
        $fail = ['count' => 0, 'first' => time()];
    }

    if ($fail['count'] >= 5) {
        $error = 'Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau vài phút.';
    } elseif ($identity === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ tài khoản và mật khẩu.';
    } else {
        $st = db()->prepare(
            'SELECT id, password_hash, full_name, role, status
             FROM users
             WHERE email = ? OR student_code = ?
             LIMIT 1'
        );
        $st->execute([strtolower($identity), $identity]);
        $u = $st->fetch();

        if (!$u || !password_verify($password, $u['password_hash'])) {
            // Báo chung, không cho biết tài khoản có tồn tại hay không
            $error = 'Sai tài khoản hoặc mật khẩu.';
            $fail['count']++;
            $_SESSION['login_fail'] = $fail;
        } elseif ($u['status'] === 'pending') {
            $error = 'Tài khoản đang chờ Ban quản trị duyệt.';
        } elseif ($u['status'] === 'rejected') {
            $error = 'Đăng ký tài khoản của bạn đã bị từ chối.';
        } elseif ($u['status'] === 'inactive') {
            $error = 'Tài khoản đã ngừng hoạt động.';
        } else {
            unset($_SESSION['login_fail']);
            login_user($u);
            set_flash('success', 'Đăng nhập thành công.');

            if ($u['role'] === 'admin') {
                redirect('admin/index.php');
            }

            // Quay lại trang đang xem dở (chỉ chấp nhận đường dẫn nội bộ của website)
            $target = $_SESSION['redirect_after_login'] ?? '';
            unset($_SESSION['redirect_after_login']);
            if ($target !== ''
                && str_starts_with($target, BASE_URL . '/')
                && !str_contains($target, '://')
                && !str_starts_with($target, '//')) {
                header('Location: ' . $target);
                exit;
            }
            redirect('index.php');
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="text-center mb-3">
    <img src="<?= e(url('assets/img/logo.png')) ?>" alt="Logo <?= e(APP_NAME) ?>" class="auth-logo">
</div>
<div class="card auth-card">
    <div class="card-body p-4">
        <h1 class="h4 mb-3">Đăng nhập</h1>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="identity">Email hoặc mã số sinh viên</label>
                <input type="text" class="form-control" id="identity" name="identity"
                       value="<?= e($identity) ?>" autocomplete="username" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Mật khẩu</label>
                <input type="password" class="form-control" id="password" name="password"
                       autocomplete="current-password" required>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <a href="<?= e(url('register.php')) ?>">Chưa có tài khoản? Đăng ký</a>
                <button type="submit" class="btn btn-primary">Đăng nhập</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
