<?php
declare(strict_types=1);

/**
 * Kiểm tra dữ liệu hồ sơ thành viên (dùng cho form thêm/sửa của Admin).
 *
 * @param array    $in               full_name, student_code, class_name, phone, email, password
 * @param int|null $excludeId        id đang sửa (để khi kiểm tra trùng không tính chính nó), null khi thêm mới
 * @param bool     $passwordRequired true khi thêm mới; khi sửa, để trống nghĩa là giữ mật khẩu cũ
 * @return array   mảng lỗi [tên_trường => thông báo], rỗng nếu hợp lệ
 */
function validate_member_fields(array $in, ?int $excludeId, bool $passwordRequired): array
{
    $errors = [];

    if (mb_strlen($in['full_name']) < 2 || mb_strlen($in['full_name']) > 100) {
        $errors['full_name'] = 'Họ và tên từ 2 đến 100 ký tự.';
    }
    if (!preg_match('/^[A-Z0-9]{6,20}$/', $in['student_code'])) {
        $errors['student_code'] = 'Mã số sinh viên gồm 6–20 ký tự chữ hoặc số.';
    }
    if ($in['class_name'] === '' || mb_strlen($in['class_name']) > 30) {
        $errors['class_name'] = 'Lớp không được để trống và tối đa 30 ký tự.';
    }
    if ($in['phone'] !== '' && !preg_match('/^0[0-9]{9,10}$/', $in['phone'])) {
        $errors['phone'] = 'Số điện thoại bắt đầu bằng 0 và có 10–11 chữ số.';
    }
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($in['email']) > 100) {
        $errors['email'] = 'Email không hợp lệ.';
    }

    $pw = $in['password'];
    if ($pw !== '' || $passwordRequired) {
        if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/[0-9]/', $pw)) {
            $errors['password'] = 'Mật khẩu tối thiểu 8 ký tự, gồm cả chữ và số.';
        }
    }

    // Kiểm tra trùng email và MSSV (loại trừ chính thành viên đang sửa)
    $exclude = $excludeId ?? 0;
    if (!isset($errors['email'])) {
        $st = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
        $st->execute([$in['email'], $exclude]);
        if ($st->fetch()) {
            $errors['email'] = 'Email này đã được sử dụng.';
        }
    }
    if (!isset($errors['student_code'])) {
        $st = db()->prepare('SELECT id FROM users WHERE student_code = ? AND id <> ?');
        $st->execute([$in['student_code'], $exclude]);
        if ($st->fetch()) {
            $errors['student_code'] = 'Mã số sinh viên này đã tồn tại.';
        }
    }
    return $errors;
}
