<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$eventId = (int)($_GET['event_id'] ?? $_POST['event_id'] ?? 0);

$st = db()->prepare(
    "SELECT e.*, c.name AS category_name
     FROM events e JOIN event_categories c ON c.id = e.category_id
     WHERE e.id = ?"
);
$st->execute([$eventId]);
$ev = $st->fetch();
if (!$ev) {
    set_flash('danger', 'Không tìm thấy sự kiện.');
    redirect('admin/registrations.php');
}

$eff = event_effective_status($ev);

// Điều kiện điểm danh (QT5): sự kiện không bị hủy và đã đến giờ bắt đầu
if ($ev['status'] === 'cancelled') {
    set_flash('danger', 'Sự kiện đã bị hủy, không thể điểm danh.');
    redirect('admin/registrations.php?event_id=' . $eventId);
}
if (strtotime($ev['start_time']) > time()) {
    set_flash('danger', 'Sự kiện chưa đến giờ bắt đầu (' . fmt_datetime($ev['start_time']) . '), chưa thể điểm danh.');
    redirect('admin/registrations.php?event_id=' . $eventId);
}

// Chỉ những đăng ký còn hiệu lực mới được điểm danh
$st = db()->prepare(
    "SELECT r.id, r.attendance, r.checked_at, u.full_name, u.student_code, u.class_name
     FROM registrations r
     JOIN users u ON u.id = r.user_id
     WHERE r.event_id = ? AND r.status = 'registered'
     ORDER BY u.full_name ASC, r.id ASC"
);
$st->execute([$eventId]);
$rows = $st->fetchAll();

/* ============================ LƯU ĐIỂM DANH ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $posted = $_POST['att'] ?? [];
    if (!is_array($posted)) {
        $posted = [];
    }

    // Kết quả hiện tại, dùng để chỉ cập nhật những dòng thật sự thay đổi
    $current = [];
    foreach ($rows as $r) {
        $current[(int)$r['id']] = $r['attendance'];
    }

    $pdo     = db();
    $changed = 0;
    $ignored = 0;
    $nowStr  = date('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();
        $upd = $pdo->prepare(
            "UPDATE registrations SET attendance = ?, checked_at = ?
             WHERE id = ? AND event_id = ? AND status = 'registered'"
        );
        foreach ($posted as $rid => $val) {
            $rid = (int)$rid;
            // Bỏ qua mã đăng ký không thuộc sự kiện này hoặc giá trị lạ (do sửa form)
            if (!array_key_exists($rid, $current) || !is_string($val)
                || !in_array($val, ['present', 'absent', ''], true)) {
                $ignored++;
                continue;
            }
            $new = ($val === '') ? null : $val;
            if ($new === $current[$rid]) {
                continue;                                  // không đổi
            }
            $upd->execute([$new, $new === null ? null : $nowStr, $rid, $eventId]);
            $changed++;
        }
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Attendance save error: ' . $e->getMessage());
        set_flash('danger', 'Không thể lưu điểm danh lúc này. Dữ liệu cũ được giữ nguyên.');
        redirect('admin/attendance.php?event_id=' . $eventId);
    }

    if ($ignored > 0) {
        error_log('Attendance: bỏ qua ' . $ignored . ' mục không hợp lệ ở sự kiện ' . $eventId);
    }
    set_flash('success', $changed > 0
        ? 'Đã lưu điểm danh (' . $changed . ' thay đổi).'
        : 'Không có thay đổi nào để lưu.');
    redirect('admin/attendance.php?event_id=' . $eventId);
}

// ---- Tổng hợp để hiển thị ----
$total   = count($rows);
$present = 0;
$absent  = 0;
foreach ($rows as $r) {
    if ($r['attendance'] === 'present') { $present++; }
    elseif ($r['attendance'] === 'absent') { $absent++; }
}
$notYet = $total - $present - $absent;
$rate   = ($present + $absent) > 0 ? (int)round($present / ($present + $absent) * 100) : null;

$pageTitle = 'Điểm danh';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>
<div class="page-head">
    <div>
        <h1 class="h4 mb-1">Điểm danh: <?= e($ev['title']) ?></h1>
        <div class="small text-muted">
            <?= e(fmt_datetime($ev['start_time'])) ?> &middot; <?= e($ev['location']) ?> &middot;
            <?= event_status_badge($eff) ?>
        </div>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('admin/registrations.php?event_id=' . $eventId)) ?>">Xem danh sách đăng ký</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-chip orange"><div class="num"><?= $total ?></div><div class="lbl">Đăng ký hợp lệ</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-chip blue"><div class="num"><?= $present ?></div><div class="lbl">Có mặt</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-chip orange"><div class="num"><?= $absent ?></div><div class="lbl">Vắng mặt</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-chip blue"><div class="num"><?= $rate === null ? '—' : $rate . '%' ?></div><div class="lbl">Tỷ lệ có mặt (<?= $notYet ?> chưa điểm danh)</div></div></div>
</div>

<?php if ($total === 0): ?>
    <div class="alert alert-info">Chưa có thành viên nào đăng ký sự kiện này nên chưa có ai để điểm danh.</div>
<?php else: ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="event_id" value="<?= $eventId ?>">

        <div class="d-flex flex-wrap gap-2 mb-3">
            <button type="button" class="btn btn-sm btn-outline-success" data-set-all="present">Chọn tất cả có mặt</button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-set-all="absent">Chọn tất cả vắng mặt</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-set-all="none">Bỏ chọn tất cả</button>
        </div>

        <div class="table-responsive rounded mb-3">
            <table class="table table-brand align-middle">
                <thead>
                <tr>
                    <th style="width:50px">STT</th>
                    <th>Thành viên</th>
                    <th>MSSV</th>
                    <th>Lớp</th>
                    <th>Kết quả điểm danh</th>
                    <th>Ghi nhận lúc</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $n => $r): $rid = (int)$r['id']; ?>
                    <tr>
                        <td><?= $n + 1 ?></td>
                        <td class="fw-semibold"><?= e($r['full_name']) ?></td>
                        <td><?= e($r['student_code']) ?></td>
                        <td><?= e($r['class_name']) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Điểm danh <?= e($r['full_name']) ?>">
                                <input type="radio" class="btn-check" name="att[<?= $rid ?>]" id="p-<?= $rid ?>"
                                       value="present" data-att="present" autocomplete="off"
                                       <?= $r['attendance'] === 'present' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-success" for="p-<?= $rid ?>">Có mặt</label>

                                <input type="radio" class="btn-check" name="att[<?= $rid ?>]" id="a-<?= $rid ?>"
                                       value="absent" data-att="absent" autocomplete="off"
                                       <?= $r['attendance'] === 'absent' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-danger" for="a-<?= $rid ?>">Vắng mặt</label>

                                <input type="radio" class="btn-check" name="att[<?= $rid ?>]" id="n-<?= $rid ?>"
                                       value="" data-att="none" autocomplete="off"
                                       <?= $r['attendance'] === null ? 'checked' : '' ?>>
                                <label class="btn btn-outline-secondary" for="n-<?= $rid ?>">Chưa</label>
                            </div>
                        </td>
                        <td class="small text-muted"><?= e(fmt_datetime($r['checked_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-lg">Lưu điểm danh</button>
            <a class="btn btn-outline-secondary btn-lg" href="<?= e(url('admin/registrations.php')) ?>">Quay lại</a>
        </div>
    </form>

    <script>
    // Các nút chọn nhanh: đặt cùng một kết quả cho tất cả thành viên trong danh sách
    document.querySelectorAll('[data-set-all]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var v = btn.getAttribute('data-set-all');
            document.querySelectorAll('input[type=radio][data-att="' + v + '"]').forEach(function (r) {
                r.checked = true;
            });
        });
    });
    </script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
