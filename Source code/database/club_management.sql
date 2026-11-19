-- =====================================================================
-- HỆ THỐNG QUẢN LÝ CÂU LẠC BỘ SINH VIÊN
-- Cấu trúc 4 bảng + dữ liệu mẫu | MySQL 8 / MariaDB 10.4+, InnoDB, utf8mb4
-- Cách nạp: phpMyAdmin -> tab Import -> chọn file này -> Import
-- =====================================================================

CREATE DATABASE IF NOT EXISTS club_management
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE club_management;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS event_categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. users: tài khoản + hồ sơ thành viên (admin và sinh viên)
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(100) NOT NULL,
  student_code  VARCHAR(20)  NULL,
  class_name    VARCHAR(30)  NULL,
  phone         VARCHAR(15)  NULL,
  role          ENUM('admin','member') NOT NULL DEFAULT 'member',
  status        ENUM('pending','active','inactive','rejected') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_student_code (student_code),
  KEY idx_users_full_name (full_name),
  KEY idx_users_class (class_name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. event_categories: loại sự kiện
-- ---------------------------------------------------------------------
CREATE TABLE event_categories (
  id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_event_categories_name (name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. events: sự kiện của CLB
-- ---------------------------------------------------------------------
CREATE TABLE events (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id           INT UNSIGNED NOT NULL,
  title                 VARCHAR(200) NOT NULL,
  description           TEXT         NULL,
  location              VARCHAR(200) NOT NULL,
  start_time            DATETIME     NOT NULL,
  end_time              DATETIME     NOT NULL,
  registration_deadline DATETIME     NOT NULL,
  max_participants      INT UNSIGNED NOT NULL,
  status                ENUM('open','closed','finished','cancelled') NOT NULL DEFAULT 'open',
  PRIMARY KEY (id),
  KEY idx_events_start_time (start_time),
  KEY idx_events_status (status),
  KEY idx_events_category (category_id),
  CONSTRAINT chk_events_max CHECK (max_participants > 0),
  CONSTRAINT chk_events_time CHECK (end_time > start_time),
  CONSTRAINT chk_events_deadline CHECK (registration_deadline <= start_time),
  CONSTRAINT fk_events_category
    FOREIGN KEY (category_id) REFERENCES event_categories(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. registrations: đăng ký tham gia (n-n giữa users và events) + điểm danh
--      attendance = NULL      -> chưa điểm danh
--      attendance = 'present' -> có mặt
--      attendance = 'absent'  -> vắng mặt
-- ---------------------------------------------------------------------
CREATE TABLE registrations (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id      INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  status        ENUM('registered','cancelled') NOT NULL DEFAULT 'registered',
  registered_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  attendance    ENUM('present','absent') NULL DEFAULT NULL,
  checked_at    DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_registrations_event_user (event_id, user_id),
  KEY idx_registrations_user (user_id),
  KEY idx_registrations_event_status (event_id, status),
  CONSTRAINT fk_registrations_event
    FOREIGN KEY (event_id) REFERENCES events(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_registrations_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- DỮ LIỆU MẪU
-- Mật khẩu demo:  admin@clb.local  -> Admin@123
--                 mọi thành viên   -> Member@123
-- (cột password_hash lưu chuỗi băm bcrypt, không phải mật khẩu thô)
-- Thời gian sự kiện tính theo NOW() nên dữ liệu luôn "sắp diễn ra"/"đã qua" đúng.
-- =====================================================================

INSERT INTO event_categories (id, name) VALUES
  (1, 'Sinh hoạt định kỳ'),
  (2, 'Workshop'),
  (3, 'Hội thảo'),
  (4, 'Thiện nguyện'),
  (5, 'Giao lưu');

INSERT INTO users (id, email, password_hash, full_name, student_code, class_name, phone, role, status) VALUES
  (1, 'admin@clb.local',     '$2y$10$gJiK.SvI2Ria8muIbPahh.l8tYF5E7ZNQK2RcrhkTCdmAhzB3WT9a',  'Quản trị viên CLB',  NULL,        NULL,        NULL,         'admin',  'active'),
  (2, 'an.nv@student.local',   '$2y$10$NIyupEV4F.D02ReylLUFruuIJdOpgniHoCJ.BRhd7R.aP9cZ9gDPK', 'Nguyễn Văn An',      'SV2024001', 'CNTT-K20A', '0901234567', 'member', 'active'),
  (3, 'binh.tt@student.local', '$2y$10$NIyupEV4F.D02ReylLUFruuIJdOpgniHoCJ.BRhd7R.aP9cZ9gDPK', 'Trần Thị Bình',      'SV2024002', 'CNTT-K20A', '0912345678', 'member', 'active'),
  (4, 'cuong.lh@student.local','$2y$10$NIyupEV4F.D02ReylLUFruuIJdOpgniHoCJ.BRhd7R.aP9cZ9gDPK', 'Lê Hoàng Cường',     'SV2024003', 'CNTT-K20B', '0923456789', 'member', 'active'),
  (5, 'dung.pt@student.local', '$2y$10$NIyupEV4F.D02ReylLUFruuIJdOpgniHoCJ.BRhd7R.aP9cZ9gDPK', 'Phạm Thu Dung',      'SV2024004', 'KTPM-K21A', '0934567890', 'member', 'active'),
  (6, 'em.vm@student.local',   '$2y$10$NIyupEV4F.D02ReylLUFruuIJdOpgniHoCJ.BRhd7R.aP9cZ9gDPK', 'Võ Minh Em',         'SV2024005', 'KTPM-K21A', '0945678901', 'member', 'active'),
  (7, 'phong.dq@student.local','$2y$10$NIyupEV4F.D02ReylLUFruuIJdOpgniHoCJ.BRhd7R.aP9cZ9gDPK', 'Đặng Quốc Phong',    'SV2024006', 'HTTT-K20A', '0956789012', 'member', 'active'),
  (8, 'giang.bn@student.local','$2y$10$NIyupEV4F.D02ReylLUFruuIJdOpgniHoCJ.BRhd7R.aP9cZ9gDPK', 'Bùi Ngọc Giang',     'SV2024007', 'HTTT-K21B', '0967890123', 'member', 'pending'),
  (9, 'huy.ha@student.local',  '$2y$10$NIyupEV4F.D02ReylLUFruuIJdOpgniHoCJ.BRhd7R.aP9cZ9gDPK', 'Hoàng Anh Huy',      'SV2024008', 'CNTT-K21A', '0978901234', 'member', 'inactive');

INSERT INTO events (id, category_id, title, description, location, start_time, end_time, registration_deadline, max_participants, status) VALUES
  (1, 1, 'Sinh hoạt định kỳ tháng này',
     'Tổng kết hoạt động, phân công nhiệm vụ cho các sự kiện sắp tới.',
     'Phòng A101',
     DATE_ADD(NOW(), INTERVAL 168 HOUR), DATE_ADD(NOW(), INTERVAL 170 HOUR), DATE_ADD(NOW(), INTERVAL 144 HOUR), 40, 'open'),
  (2, 2, 'Workshop Git và GitHub cho người mới',
     'Thực hành tạo repository, commit, branch và làm việc nhóm với GitHub.',
     'Phòng máy B203',
     DATE_ADD(NOW(), INTERVAL 240 HOUR), DATE_ADD(NOW(), INTERVAL 244 HOUR), DATE_ADD(NOW(), INTERVAL 216 HOUR), 30, 'open'),
  (3, 3, 'Hội thảo định hướng nghề nghiệp CNTT',
     'Khách mời chia sẻ lộ trình học tập và tìm việc cho sinh viên CNTT.',
     'Hội trường lớn',
     DATE_ADD(NOW(), INTERVAL 480 HOUR), DATE_ADD(NOW(), INTERVAL 486 HOUR), DATE_ADD(NOW(), INTERVAL 456 HOUR), 100, 'open'),
  (4, 4, 'Chiến dịch dọn vệ sinh công viên',
     'Hoạt động thiện nguyện cuối tuần cùng cộng đồng. Số chỗ có hạn.',
     'Công viên trung tâm',
     DATE_ADD(NOW(), INTERVAL 72 HOUR), DATE_ADD(NOW(), INTERVAL 80 HOUR), DATE_ADD(NOW(), INTERVAL 48 HOUR), 5, 'open'),
  (5, 5, 'Giao lưu bóng đá giữa các CLB',
     'Giải giao hữu thân thiện.',
     'Sân bóng trường',
     DATE_SUB(NOW(), INTERVAL 240 HOUR), DATE_SUB(NOW(), INTERVAL 236 HOUR), DATE_SUB(NOW(), INTERVAL 264 HOUR), 20, 'finished'),
  (6, 1, 'Sinh hoạt định kỳ tháng trước',
     'Buổi sinh hoạt đã diễn ra.',
     'Phòng A101',
     DATE_SUB(NOW(), INTERVAL 720 HOUR), DATE_SUB(NOW(), INTERVAL 718 HOUR), DATE_SUB(NOW(), INTERVAL 744 HOUR), 40, 'finished'),
  (7, 5, 'Team building mùa thu',
     'Sự kiện đã bị hủy do thời tiết.',
     'Khu du lịch sinh thái',
     DATE_ADD(NOW(), INTERVAL 360 HOUR), DATE_ADD(NOW(), INTERVAL 368 HOUR), DATE_ADD(NOW(), INTERVAL 336 HOUR), 40, 'cancelled'),
  (8, 3, 'Talkshow khởi nghiệp sinh viên',
     'Ban tổ chức đã đóng đăng ký sớm.',
     'Phòng hội thảo C301',
     DATE_ADD(NOW(), INTERVAL 96 HOUR), DATE_ADD(NOW(), INTERVAL 100 HOUR), DATE_ADD(NOW(), INTERVAL 24 HOUR), 50, 'closed');

-- Sự kiện 4 (tối đa 5 chỗ) đã đủ 5 đăng ký -> dùng để thử trường hợp "hết chỗ"
INSERT INTO registrations (event_id, user_id, status, registered_at, attendance, checked_at) VALUES
  (1, 2, 'registered', DATE_SUB(NOW(), INTERVAL 3 DAY), NULL, NULL),
  (1, 3, 'registered', DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, NULL),
  (1, 4, 'registered', DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, NULL),
  (1, 6, 'cancelled',  DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, NULL),
  (2, 2, 'registered', DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, NULL),
  (2, 5, 'registered', DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, NULL),
  (3, 7, 'registered', DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, NULL),
  (4, 2, 'registered', DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, NULL),
  (4, 3, 'registered', DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, NULL),
  (4, 4, 'registered', DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, NULL),
  (4, 5, 'registered', DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, NULL),
  (4, 6, 'registered', DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, NULL),
  (5, 2, 'registered', DATE_SUB(NOW(), INTERVAL 15 DAY), 'present', DATE_SUB(NOW(), INTERVAL 238 HOUR)),
  (5, 3, 'registered', DATE_SUB(NOW(), INTERVAL 15 DAY), 'present', DATE_SUB(NOW(), INTERVAL 238 HOUR)),
  (5, 4, 'registered', DATE_SUB(NOW(), INTERVAL 15 DAY), 'present', DATE_SUB(NOW(), INTERVAL 238 HOUR)),
  (5, 5, 'registered', DATE_SUB(NOW(), INTERVAL 15 DAY), 'absent',  DATE_SUB(NOW(), INTERVAL 238 HOUR)),
  (6, 2, 'registered', DATE_SUB(NOW(), INTERVAL 40 DAY), 'present', DATE_SUB(NOW(), INTERVAL 719 HOUR)),
  (6, 3, 'registered', DATE_SUB(NOW(), INTERVAL 40 DAY), 'present', DATE_SUB(NOW(), INTERVAL 719 HOUR)),
  (6, 4, 'cancelled',  DATE_SUB(NOW(), INTERVAL 40 DAY), NULL, NULL);
