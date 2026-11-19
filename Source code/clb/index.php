<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Trang chủ';

// 6 sự kiện sắp diễn ra, kèm số người đã đăng ký hợp lệ để tính số chỗ còn lại
$events = db()->query(
    "SELECT e.id, e.title, e.location, e.start_time, e.max_participants,
            c.name AS category_name,
            (SELECT COUNT(*) FROM registrations r
              WHERE r.event_id = e.id AND r.status = 'registered') AS registered_count
     FROM events e
     JOIN event_categories c ON c.id = e.category_id
     WHERE e.status = 'open' AND e.start_time > NOW()
     ORDER BY e.start_time ASC
     LIMIT 6"
)->fetchAll();

// Số liệu tổng hợp công khai (không chứa thông tin cá nhân)
$summary = [
    'members' => (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'member' AND status = 'active'")->fetchColumn(),
    'events'  => (int)db()->query("SELECT COUNT(*) FROM events WHERE status = 'open' AND start_time > NOW()")->fetchColumn(),
    'regs'    => (int)db()->query("SELECT COUNT(*) FROM registrations WHERE status = 'registered'")->fetchColumn(),
];

require __DIR__ . '/includes/header.php';
?>

<!-- ===== Khối giới thiệu ===== -->
<section class="hero p-4 p-md-5 mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <span class="hero-pill mb-3"><?= e(APP_TAGLINE) ?></span>
            <h1 class="display-6 mb-3">Chào mừng đến với <span class="accent"><?= e(APP_NAME) ?></span></h1>
            <p class="lead mb-4 text-white-50">
                Kết nối thành viên, tham gia sự kiện và theo dõi lịch sử điểm danh của bạn
                chỉ trong vài thao tác.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <?php if (is_logged_in()): ?>
                    <a href="<?= e(url('events.php')) ?>" class="btn btn-primary btn-lg">Xem sự kiện sắp diễn ra</a>
                <?php else: ?>
                    <a href="<?= e(url('register.php')) ?>" class="btn btn-primary btn-lg">Trở thành thành viên</a>
                    <a href="<?= e(url('login.php')) ?>" class="btn btn-light-outline btn-lg">Đăng nhập</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-4 text-lg-end text-center">
            <div class="hero-logo-card">
                <img src="<?= e(url('assets/img/logo.png')) ?>" alt="Logo <?= e(APP_NAME) ?>" class="hero-logo">
            </div>
        </div>
    </div>
</section>

<!-- ===== Số liệu nhanh ===== -->
<section class="row g-3 mb-5">
    <div class="col-sm-4">
        <div class="stat-chip orange">
            <div class="num"><?= $summary['members'] ?></div>
            <div class="lbl">Thành viên đang hoạt động</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-chip blue">
            <div class="num"><?= $summary['events'] ?></div>
            <div class="lbl">Sự kiện sắp diễn ra</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-chip orange">
            <div class="num"><?= $summary['regs'] ?></div>
            <div class="lbl">Lượt đăng ký tham gia</div>
        </div>
    </div>
</section>

<!-- ===== Sự kiện sắp diễn ra ===== -->
<section id="su-kien" class="mb-5">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
        <h2 class="h4 section-title">Sự kiện sắp diễn ra</h2>
        <a class="fw-semibold mb-3" href="<?= e(url('events.php')) ?>">Xem tất cả sự kiện &rarr;</a>
    </div>

    <?php if (!$events): ?>
        <div class="alert alert-info">Hiện chưa có sự kiện nào đang mở đăng ký.</div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($events as $ev):
                $ts   = strtotime($ev['start_time']);
                $left = max(0, (int)$ev['max_participants'] - (int)$ev['registered_count']);
                if ($left === 0) {
                    $seatClass = 'badge-seat-full'; $seatText = 'Hết chỗ';
                } elseif ($left <= 3) {
                    $seatClass = 'badge-seat-low';  $seatText = 'Còn ' . $left . ' chỗ';
                } else {
                    $seatClass = 'badge-seat-ok';   $seatText = 'Còn ' . $left . ' chỗ';
                }
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card event-card h-100">
                        <div class="card-body d-flex gap-3">
                            <div class="date-block flex-shrink-0">
                                <div class="day"><?= e(date('d', $ts)) ?></div>
                                <div class="mon">Tháng <?= e(date('n', $ts)) ?></div>
                            </div>
                            <div class="flex-grow-1">
                                <span class="badge badge-cat mb-2"><?= e($ev['category_name']) ?></span>
                                <h3 class="h6 mb-2"><a class="text-decoration-none stretched-link" href="<?= e(url('event.php?id=' . (int)$ev['id'])) ?>"><?= e($ev['title']) ?></a></h3>
                                <div class="small text-muted"><?= e(date('H:i', $ts)) ?> &middot; <?= e($ev['location']) ?></div>
                                <div class="mt-2">
                                    <span class="badge <?= e($seatClass) ?>"><?= e($seatText) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- ===== Quy trình tham gia ===== -->
<section class="mb-2">
    <h2 class="h4 section-title">Tham gia như thế nào?</h2>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card step-card">
                <div><span class="step-no">1</span></div>
                <h3 class="h6">Tạo tài khoản</h3>
                <p class="small text-muted mb-0">Đăng ký bằng mã số sinh viên và chờ Ban quản trị duyệt.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card step-card">
                <div><span class="step-no">2</span></div>
                <h3 class="h6">Chọn sự kiện</h3>
                <p class="small text-muted mb-0">Xem lịch, địa điểm, số chỗ còn lại và đăng ký tham gia.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card step-card">
                <div><span class="step-no">3</span></div>
                <h3 class="h6">Tham dự và điểm danh</h3>
                <p class="small text-muted mb-0">Kết quả điểm danh được lưu lại trong lịch sử của bạn.</p>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
