<?php
require_once __DIR__ . '/includes/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);

$st = db()->prepare(
    "SELECT e.*, c.name AS category_name,
            (SELECT COUNT(*) FROM registrations r
              WHERE r.event_id = e.id AND r.status = 'registered') AS registered_count
     FROM events e
     JOIN event_categories c ON c.id = e.category_id
     WHERE e.id = ?"
);
$st->execute([$id]);
$ev = $st->fetch();

if (!$ev) {                                        // mã không hợp lệ hoặc sự kiện không tồn tại
    set_flash('danger', 'Không tìm thấy sự kiện.');
    redirect('events.php');
}

$eff   = event_effective_status($ev);
$max   = (int)$ev['max_participants'];
$reg   = (int)$ev['registered_count'];
$left  = max(0, $max - $reg);
$pct   = $max > 0 ? min(100, (int)round($reg / $max * 100)) : 0;
$ts    = strtotime($ev['start_time']);

$isMember = is_logged_in() && (current_user()['role'] ?? '') === 'member';
$my = null;                                        // đăng ký của người đang xem (nếu có)
if ($isMember) {
    $st = db()->prepare('SELECT status, attendance FROM registrations WHERE event_id = ? AND user_id = ?');
    $st->execute([$id, (int)current_user()['id']]);
    $my = $st->fetch() ?: null;
}
$isRegistered   = $my && $my['status'] === 'registered';
$beforeDeadline = strtotime($ev['registration_deadline']) > time();
$canCancel      = $isRegistered && $my['attendance'] === null && $beforeDeadline
                  && in_array($eff, ['open', 'closed'], true);

// Khách bấm "Đăng nhập để đăng ký": sau khi đăng nhập quay lại đúng trang này
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
}

$pageTitle = $ev['title'];
require __DIR__ . '/includes/header.php';
?>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= e(url('events.php')) ?>">Sự kiện</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($ev['category_name']) ?></li>
    </ol>
</nav>

<section class="event-banner mb-4">
    <div class="d-flex flex-wrap gap-2 mb-2">
        <span class="hero-pill"><?= e($ev['category_name']) ?></span>
        <?= event_status_badge($eff) ?>
    </div>
    <h1 class="h2 mb-2"><?= e($ev['title']) ?></h1>
    <div class="opacity-75"><?= e(fmt_datetime($ev['start_time'])) ?> &middot; <?= e($ev['location']) ?></div>
</section>

<div class="row g-4">
    <!-- Thông tin sự kiện -->
    <div class="col-lg-8">
        <div class="card"><div class="card-body p-4">
            <h2 class="h6 form-section-title">Thông tin sự kiện</h2>
            <dl class="row detail-list mb-4">
                <dt class="col-sm-4">Thời gian</dt>
                <dd class="col-sm-8"><?= e(fmt_datetime($ev['start_time'])) ?> – <?= e(fmt_datetime($ev['end_time'])) ?></dd>
                <dt class="col-sm-4">Địa điểm</dt>
                <dd class="col-sm-8"><?= e($ev['location']) ?></dd>
                <dt class="col-sm-4">Hạn đăng ký</dt>
                <dd class="col-sm-8"><?= e(fmt_datetime($ev['registration_deadline'])) ?></dd>
                <dt class="col-sm-4">Loại sự kiện</dt>
                <dd class="col-sm-8"><?= e($ev['category_name']) ?></dd>
                <dt class="col-sm-4">Số chỗ</dt>
                <dd class="col-sm-8">
                    <strong><?= $reg ?></strong> / <?= $max ?> đã đăng ký
                    <?php if ($eff === 'open'): ?>
                        <span class="text-muted">(<?= $left > 0 ? 'còn ' . $left . ' chỗ' : 'đã hết chỗ' ?>)</span>
                    <?php endif; ?>
                    <div class="progress mt-2" style="max-width: 260px;"><div class="progress-bar" style="width: <?= $pct ?>%"></div></div>
                </dd>
            </dl>

            <h2 class="h6 form-section-title">Nội dung</h2>
            <?php if (trim((string)$ev['description']) !== ''): ?>
                <p class="mb-0"><?= nl2br(e($ev['description'])) ?></p>
            <?php else: ?>
                <p class="text-muted mb-0">Chưa có mô tả cho sự kiện này.</p>
            <?php endif; ?>
        </div></div>
    </div>

    <!-- Khung đăng ký -->
    <div class="col-lg-4">
        <div class="card reg-panel"><div class="card-body p-4">
            <h2 class="h6 form-section-title">Đăng ký tham gia</h2>

            <?php if ($eff === 'cancelled'): ?>
                <div class="alert alert-danger mb-0">Sự kiện này đã bị hủy.</div>

            <?php elseif ($eff === 'finished'): ?>
                <div class="alert alert-secondary">Sự kiện đã kết thúc.</div>
                <?php if ($isRegistered): ?>
                    <div class="small">Kết quả điểm danh của bạn: <?= attendance_badge($my['attendance'], 'registered') ?></div>
                <?php endif; ?>

            <?php elseif (!is_logged_in()): ?>
                <?php if ($eff === 'open'): ?>
                    <p class="small text-muted">Bạn cần đăng nhập bằng tài khoản thành viên để đăng ký.</p>
                    <a class="btn btn-primary w-100" href="<?= e(url('login.php')) ?>">Đăng nhập để đăng ký</a>
                    <div class="text-center mt-2 small">Chưa có tài khoản? <a href="<?= e(url('register.php')) ?>">Đăng ký</a></div>
                <?php else: ?>
                    <button class="btn btn-secondary w-100" disabled>Đã đóng đăng ký</button>
                <?php endif; ?>

            <?php elseif (!$isMember): ?>
                <div class="alert alert-info mb-2">Bạn đang đăng nhập bằng tài khoản quản trị.</div>
                <a class="btn btn-outline-brand btn-sm" href="<?= e(url('admin/event_form.php?id=' . $id)) ?>">Quản lý sự kiện này</a>

            <?php elseif ($isRegistered): ?>
                <div class="alert alert-success">Bạn đã đăng ký sự kiện này.</div>
                <?php if ($my['attendance'] !== null): ?>
                    <div class="small mb-2">Điểm danh: <?= attendance_badge($my['attendance'], 'registered') ?></div>
                <?php endif; ?>
                <?php if ($canCancel): ?>
                    <form method="post" action="<?= e(url('event_register.php')) ?>"
                          onsubmit="return confirm('Hủy đăng ký sự kiện này?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="event_id" value="<?= $id ?>">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn-outline-danger w-100">Hủy đăng ký</button>
                    </form>
                    <div class="small text-muted mt-2">Có thể hủy đến trước <?= e(fmt_datetime($ev['registration_deadline'])) ?>.</div>
                <?php elseif ($my['attendance'] === null): ?>
                    <div class="small text-muted">Đã quá hạn đăng ký nên không thể hủy.</div>
                <?php endif; ?>

            <?php elseif ($eff === 'open'): ?>
                <?php if ($my && $my['status'] === 'cancelled'): ?>
                    <div class="alert alert-warning small">Bạn đã hủy đăng ký trước đó. Bạn có thể đăng ký lại.</div>
                <?php endif; ?>
                <?php if ($left > 0): ?>
                    <form method="post" action="<?= e(url('event_register.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="event_id" value="<?= $id ?>">
                        <input type="hidden" name="action" value="register">
                        <button type="submit" class="btn btn-primary w-100 btn-lg">Đăng ký tham gia</button>
                    </form>
                    <div class="small text-muted mt-2">Còn <?= $left ?> chỗ. Hạn đăng ký: <?= e(fmt_datetime($ev['registration_deadline'])) ?>.</div>
                <?php else: ?>
                    <button class="btn btn-secondary w-100" disabled>Sự kiện đã đủ số lượng</button>
                <?php endif; ?>

            <?php else: ?>
                <button class="btn btn-secondary w-100" disabled>Đã đóng đăng ký</button>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
