<?php
/** Thanh điều hướng con của khu vực quản trị. Include sau header.php. */
$cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
$groups = [
    'members.php'       => ['members.php', 'member_form.php', 'member_view.php'],
    'events.php'        => ['events.php', 'event_form.php'],
    'registrations.php' => ['registrations.php', 'attendance.php'],
];
$activeKey = $cur;
foreach ($groups as $tab => $files) {
    if (in_array($cur, $files, true)) {
        $activeKey = $tab;
    }
}
$adminTabs = [
    'index.php'         => 'Tổng quan',
    'members.php'       => 'Thành viên',
    'categories.php'    => 'Loại sự kiện',
    'events.php'        => 'Sự kiện',
    'registrations.php' => 'Đăng ký & điểm danh',
];
?>
<ul class="nav nav-pills admin-tabs mb-4">
    <?php foreach ($adminTabs as $file => $label): ?>
        <li class="nav-item">
            <a class="nav-link<?= $activeKey === $file ? ' active' : '' ?>"
               href="<?= e(url('admin/' . $file)) ?>"><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>
