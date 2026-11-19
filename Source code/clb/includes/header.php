<?php
/** Biến cần có trước khi include: $pageTitle (string) */
$user = current_user();
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$inAdmin = str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/admin/');
$isMember = $user && ($user['role'] ?? '') === 'member';

// Chữ cái đầu của tên (hiển thị trong vòng tròn avatar)
$initial = '';
if ($user) {
    $parts = preg_split('/\s+/', trim($user['full_name']));
    $initial = mb_strtoupper(mb_substr((string)end($parts), 0, 1));
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A0C8C">
    <title><?= e(($pageTitle ?? 'Trang chủ') . ' - ' . APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= e(url('assets/img/favicon.png')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-light bg-white site-nav sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(url('index.php')) ?>">
            <img src="<?= e(url('assets/img/logo.png')) ?>" alt="Logo <?= e(APP_NAME) ?>" class="brand-logo">
            <span class="lh-1">
                <span class="brand-name"><?= e(APP_NAME) ?></span>
                <small class="brand-tag d-block"><?= e(APP_TAGLINE) ?></small>
            </span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Mở menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto ms-lg-3">
                <li class="nav-item">
                    <a class="nav-link<?= (!$inAdmin && $currentPage === 'index.php') ? ' active' : '' ?>"
                       href="<?= e(url('index.php')) ?>">Trang chủ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= (!$inAdmin && in_array($currentPage, ['events.php', 'event.php'], true)) ? ' active' : '' ?>"
                       href="<?= e(url('events.php')) ?>">Sự kiện</a>
                </li>
                <?php if ($isMember): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $currentPage === 'my_registrations.php' ? ' active' : '' ?>"
                           href="<?= e(url('my_registrations.php')) ?>">Lịch sử tham gia</a>
                    </li>
                <?php endif; ?>
                <?php if (is_admin()): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $inAdmin ? ' active' : '' ?>"
                           href="<?= e(url('admin/index.php')) ?>">Quản trị</a>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                <?php if ($user): ?>
                    <a href="<?= e(url('profile.php')) ?>" class="d-flex align-items-center gap-2 text-decoration-none me-2"
                       title="Hồ sơ cá nhân">
                        <span class="user-avatar" aria-hidden="true"><?= e($initial) ?></span>
                        <span class="small fw-semibold text-dark"><?= e($user['full_name']) ?></span>
                    </a>
                    <form method="post" action="<?= e(url('logout.php')) ?>" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-brand btn-sm">Đăng xuất</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-outline-brand btn-sm" href="<?= e(url('login.php')) ?>">Đăng nhập</a>
                    <a class="btn btn-primary btn-sm" href="<?= e(url('register.php')) ?>">Đăng ký</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main class="container py-4 flex-grow-1">
<?php foreach (get_flashes() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($f['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
    </div>
<?php endforeach; ?>
