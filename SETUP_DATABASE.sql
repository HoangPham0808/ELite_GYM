-- ============================================================
-- GYM MANAGEMENT DATABASE - FULL SCHEMA (ENGLISH)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ========================
-- DROP TABLES
-- ========================

DROP TABLE IF EXISTS Notification;
DROP TABLE IF EXISTS StatisticsReport;
DROP TABLE IF EXISTS CheckInHistory;
DROP TABLE IF EXISTS EquipmentMaintenance;
DROP TABLE IF EXISTS Equipment;
DROP TABLE IF EXISTS EquipmentType;
DROP TABLE IF EXISTS GymRoom;
DROP TABLE IF EXISTS ClassRegistration;
DROP TABLE IF EXISTS TrainingClass;
DROP TABLE IF EXISTS MembershipRegistration;
DROP TABLE IF EXISTS Review;
DROP TABLE IF EXISTS Payroll;
DROP TABLE IF EXISTS Attendance;
DROP TABLE IF EXISTS InvoiceDetail;
DROP TABLE IF EXISTS Invoice;
DROP TABLE IF EXISTS Promotion;
DROP TABLE IF EXISTS MembershipPlan;
DROP TABLE IF EXISTS Customer;
DROP TABLE IF EXISTS Employee;
DROP TABLE IF EXISTS LoginHistory;
DROP TABLE IF EXISTS Account;

-- ========================
-- ACCOUNT
-- ========================

CREATE TABLE Account (
    account_id          INT AUTO_INCREMENT PRIMARY KEY,
    username            VARCHAR(100)  NOT NULL UNIQUE,
    password            VARCHAR(255)  NOT NULL,
    role                ENUM('Admin','Employee','Customer') NOT NULL,
    is_active           BOOLEAN       DEFAULT TRUE,
    created_at          DATETIME      DEFAULT CURRENT_TIMESTAMP,
    last_login          DATETIME      NULL COMMENT 'Last login timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



CREATE TABLE IF NOT EXISTS GymCheckIn (
    checkin_id    INT AUTO_INCREMENT PRIMARY KEY,
    customer_id   INT NOT NULL,
    type          ENUM('checkin', 'checkout') NOT NULL DEFAULT 'checkin',
    check_time    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    recorded_by   INT DEFAULT NULL,         -- account_id của Staff/Admin thực hiện
    note          VARCHAR(255) DEFAULT NULL,

    INDEX idx_customer (customer_id),
    INDEX idx_date     (check_time),
    INDEX idx_type     (type),

    CONSTRAINT fk_checkin_customer
        FOREIGN KEY (customer_id) REFERENCES Customer(customer_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE Employee (
    employee_id   INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(150) NOT NULL,
    position      ENUM('Receptionist','Personal Trainer') DEFAULT NULL COMMENT 'Employee position',
    date_of_birth DATE,
    gender        ENUM('Male','Female','Other'),
    phone         VARCHAR(20),
    email         VARCHAR(150),
    address       VARCHAR(255),
    hire_date     DATE,
    hourly_wage   DECIMAL(12,2) DEFAULT 0 COMMENT 'Wage per hour (currency)',
    account_id    INT UNIQUE,
    FOREIGN KEY (account_id) REFERENCES Account(account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================
-- CUSTOMER
-- ========================

CREATE TABLE Customer (
    customer_id   INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(150) NOT NULL,
    date_of_birth DATE,
    gender        ENUM('Male','Female','Other'),
    phone         VARCHAR(20),
    email         VARCHAR(150),
    address       VARCHAR(255),
    registered_at DATE,
    account_id    INT UNIQUE,
    CONSTRAINT fk_customer_account
        FOREIGN KEY (account_id) REFERENCES Account(account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================
-- MEMBERSHIP PLAN
-- ========================

CREATE TABLE MembershipPlan (
    plan_id       INT AUTO_INCREMENT PRIMARY KEY,
    plan_name     VARCHAR(150) NOT NULL,
    duration_months INT        NOT NULL,
    price         DECIMAL(12,2) NOT NULL,
    description   VARCHAR(500)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================
-- PROMOTION
-- ========================

CREATE TABLE Promotion (
    promotion_id          INT AUTO_INCREMENT PRIMARY KEY,
    promotion_name        VARCHAR(200),
    discount_percent      DECIMAL(5,2),
    start_date            DATE,
    end_date              DATE,
    max_usage             INT           DEFAULT NULL  COMMENT 'Maximum number of uses (NULL = unlimited)',
    usage_count           INT           DEFAULT 0     COMMENT 'Number of times used',
    min_order_value       DECIMAL(12,2) DEFAULT 0     COMMENT 'Minimum order value to apply',
    max_discount_amount   DECIMAL(12,2) DEFAULT NULL  COMMENT 'Maximum discount amount (NULL = unlimited)',
    status                ENUM('Active','Expired','Paused') DEFAULT 'Active',
    description           VARCHAR(500)  DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================
-- INVOICE
-- ========================

CREATE TABLE Invoice (
    invoice_id          INT AUTO_INCREMENT PRIMARY KEY,
    customer_id         INT           NOT NULL,
    invoice_date        DATE,
    promotion_id        INT           NULL,
    original_amount     DECIMAL(12,2) DEFAULT 0,
    discount_amount     DECIMAL(12,2) DEFAULT 0,
    final_amount        DECIMAL(12,2) DEFAULT 0,
    note                TEXT          NULL,
    status              ENUM('Paid','Pending','Cancelled') DEFAULT 'Paid',
    FOREIGN KEY (customer_id) REFERENCES Customer(customer_id),
    CONSTRAINT fk_invoice_promotion
        FOREIGN KEY (promotion_id) REFERENCES Promotion(promotion_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE Invoice
    ADD COLUMN created_by VARCHAR(100) NULL DEFAULT NULL
        COMMENT 'Username người tạo hóa đơn'
    AFTER status;

-- (Tùy chọn) Đặt giá trị mặc định cho hóa đơn cũ
UPDATE Invoice SET created_by = 'admin' WHERE created_by IS NULL;
CREATE TABLE InvoiceDetail (
    detail_id    INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id   INT,
    plan_id      INT,
    quantity     INT,
    unit_price   DECIMAL(12,2),
    subtotal     DECIMAL(12,2) DEFAULT 0,
    FOREIGN KEY (invoice_id) REFERENCES Invoice(invoice_id),
    FOREIGN KEY (plan_id)    REFERENCES MembershipPlan(plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================
-- MEMBERSHIP REGISTRATION
-- ========================

CREATE TABLE MembershipRegistration (
    registration_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT NOT NULL,
    plan_id         INT NOT NULL,
    start_date      DATE,
    end_date        DATE,
    FOREIGN KEY (customer_id) REFERENCES Customer(customer_id),
    FOREIGN KEY (plan_id)     REFERENCES MembershipPlan(plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================
-- REVIEW
-- ========================

CREATE TABLE Review (
    review_id   INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    content     VARCHAR(500),
    rating      INT CHECK (rating BETWEEN 1 AND 5),
    review_date DATE,
    FOREIGN KEY (customer_id) REFERENCES Customer(customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================
-- ATTENDANCE
-- ========================

CREATE TABLE Attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id   INT  NOT NULL,
    work_date     DATE NOT NULL,
    check_in      TIME,
    check_out     TIME,
    status        ENUM('Present','Absent','On Leave','Late') DEFAULT 'Present',
    note          TEXT NULL,
    FOREIGN KEY (employee_id) REFERENCES Employee(employee_id),
    UNIQUE KEY uq_employee_date (employee_id, work_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================
-- PAYROLL
-- ========================

CREATE TABLE Payroll (
    payroll_id    INT AUTO_INCREMENT PRIMARY KEY,
    employee_id   INT           NOT NULL,
    month         INT           NOT NULL CHECK (month BETWEEN 1 AND 12),
    year          INT           NOT NULL CHECK (year >= 2000),
    base_salary   DECIMAL(12,2) NOT NULL,
    allowance     DECIMAL(12,2) DEFAULT 0,
    bonus         DECIMAL(12,2) DEFAULT 0,
    deduction     DECIMAL(12,2) DEFAULT 0,
    net_salary    DECIMAL(12,2) NOT NULL,
    total_hours   DECIMAL(10,2) DEFAULT 0,
    UNIQUE KEY uq_employee_month_year (employee_id, month, year),
    FOREIGN KEY (employee_id) REFERENCES Employee(employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================
-- TRAINING CLASS
-- ========================

CREATE TABLE TrainingClass (
    class_id    INT AUTO_INCREMENT PRIMARY KEY,
    class_name  VARCHAR(150),
    trainer_id  INT,
    class_time  DATETIME,
    FOREIGN KEY (trainer_id) REFERENCES Employee(employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ClassRegistration (
    class_registration_id INT AUTO_INCREMENT PRIMARY KEY,
    class_id              INT,
    customer_id           INT,
    FOREIGN KEY (class_id)    REFERENCES TrainingClass(class_id),
    FOREIGN KEY (customer_id) REFERENCES Customer(customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================
-- GYM ROOM
-- ========================

CREATE TABLE GymRoom (
    room_id    INT AUTO_INCREMENT PRIMARY KEY,
    room_name  VARCHAR(200)  NOT NULL,
    room_type  VARCHAR(100),
    status     VARCHAR(50)   DEFAULT 'Active',
    capacity   INT,
    area       DECIMAL(10,2),
    floor      INT,
    open_time  TIME,
    description TEXT,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================
-- EQUIPMENT TYPE
-- ========================

CREATE TABLE EquipmentType (
    type_id              INT AUTO_INCREMENT PRIMARY KEY,
    type_name            VARCHAR(150) NOT NULL,
    description          TEXT         NULL,
    maintenance_interval INT          DEFAULT 180 COMMENT 'Max days between maintenance'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO EquipmentType (type_name, description, maintenance_interval) VALUES
('Treadmill',        'Electric treadmill machines',             90),
('Weight Machine',   'Dumbbells, barbells, weight racks',       180),
('Cardio Machine',   'Exercise bikes, stair climbers',          90),
('Group Equipment',  'Yoga mats, resistance bands',             365),
('Electrical System','Electricity, air conditioning, lighting', 180),
('Other',            NULL,                                       365);

-- ========================
-- EQUIPMENT
-- ========================

CREATE TABLE Equipment (
    equipment_id         INT AUTO_INCREMENT PRIMARY KEY,
    equipment_name       VARCHAR(200),
    condition_status     VARCHAR(200),
    room_id              INT           NULL,
    type_id              INT           NULL,
    purchase_price       DECIMAL(15,2) NULL    COMMENT 'Purchase price',
    purchase_date        DATE          NULL    COMMENT 'Date of purchase',
    last_maintenance_date DATE          NULL    COMMENT 'Most recent maintenance date',
    description          TEXT          NULL,
    CONSTRAINT fk_equipment_room
        FOREIGN KEY (room_id) REFERENCES GymRoom(room_id) ON DELETE SET NULL,
    CONSTRAINT fk_equipment_type
        FOREIGN KEY (type_id) REFERENCES EquipmentType(type_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================
-- EQUIPMENT MAINTENANCE
-- ========================

CREATE TABLE EquipmentMaintenance (
    maintenance_id    INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id      INT,
    maintenance_date  DATE,
    description       VARCHAR(500),
    cost              DECIMAL(12,2) DEFAULT 0    COMMENT 'Maintenance cost',
    performed_by      VARCHAR(150)  NULL          COMMENT 'Person or company performing maintenance',
    status            ENUM('Completed','In Progress','Scheduled') DEFAULT 'Completed',
    FOREIGN KEY (equipment_id) REFERENCES Equipment(equipment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================
-- CHECK-IN HISTORY
-- ========================

CREATE TABLE CheckInHistory (
    checkin_id   INT AUTO_INCREMENT PRIMARY KEY,
    customer_id  INT,
    check_in     DATETIME,
    check_out    DATETIME,
    FOREIGN KEY (customer_id) REFERENCES Customer(customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================
-- NOTIFICATION
-- ========================

CREATE TABLE Notification (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(200),
    content         VARCHAR(1000),
    sent_date       DATE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================
-- STATISTICS REPORT
-- ========================

CREATE TABLE StatisticsReport (
    report_id   INT AUTO_INCREMENT PRIMARY KEY,
    report_name VARCHAR(200),
    created_at  DATE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ========================
-- TRIGGER
-- ========================

DROP TRIGGER IF EXISTS trg_update_last_maintenance;
CREATE TRIGGER trg_update_last_maintenance
AFTER INSERT ON EquipmentMaintenance
FOR EACH ROW
    UPDATE Equipment
    SET last_maintenance_date = NEW.maintenance_date
    WHERE equipment_id = NEW.equipment_id;

-- ========================
-- INDEXES
-- ========================

CREATE INDEX idx_account_is_active            ON Account(is_active);
CREATE INDEX idx_customer_registered_at       ON Customer(registered_at);
CREATE INDEX idx_promotion_dates              ON Promotion(start_date, end_date);
CREATE INDEX idx_promotion_status             ON Promotion(status);
CREATE INDEX idx_invoice_customer             ON Invoice(customer_id);
CREATE INDEX idx_invoice_date                 ON Invoice(invoice_date);
CREATE INDEX idx_invoice_status               ON Invoice(status);
CREATE INDEX idx_invoicedetail_invoice        ON InvoiceDetail(invoice_id);
CREATE INDEX idx_invoicedetail_plan           ON InvoiceDetail(plan_id);
CREATE INDEX idx_membershipreg_customer       ON MembershipRegistration(customer_id);
CREATE INDEX idx_membershipreg_end_date       ON MembershipRegistration(end_date);
CREATE INDEX idx_trainingclass_trainer        ON TrainingClass(trainer_id);
CREATE INDEX idx_checkin_time                 ON CheckInHistory(check_in);
CREATE INDEX idx_checkin_customer             ON CheckInHistory(customer_id);
CREATE INDEX idx_gymroom_status               ON GymRoom(status);
CREATE INDEX idx_equipment_room               ON Equipment(room_id);
CREATE INDEX idx_equipment_type               ON Equipment(type_id);
CREATE INDEX idx_equipment_last_maintenance   ON Equipment(last_maintenance_date);
CREATE INDEX idx_maintenance_date             ON EquipmentMaintenance(maintenance_date);
CREATE INDEX idx_maintenance_status           ON EquipmentMaintenance(status);
-- ════════════════════════════════════════════════════════════════
--  File    : landing_images.sql
--  Database: datn  (WAMP64 / phpMyAdmin)
--
--  CÁCH CHẠY:
--    1. Mở trình duyệt → http://localhost/phpmyadmin
--    2. Chọn database "datn" ở cột trái
--    3. Nhấn tab "SQL" phía trên
--    4. Paste toàn bộ nội dung file này → nhấn "Go"
--
--  Vị trí lưu file:
--    C:\wamp64\www\PHP\ELite_GYM\Database\landing_images.sql
-- ════════════════════════════════════════════════════════════════

USE `datn`;

-- ── Xóa bảng cũ nếu muốn tạo lại (bỏ comment -- nếu cần reset) ──
-- DROP TABLE IF EXISTS `landing_images`;

-- ── Tạo bảng ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `landing_images` (

    `image_id`    INT          NOT NULL AUTO_INCREMENT
                               COMMENT 'Khóa chính tự tăng',

    `image_name`  VARCHAR(255) NOT NULL
                               COMMENT 'Tên hiển thị / caption dưới ảnh trong slideshow',

    `file_name`   VARCHAR(255) NOT NULL
                               COMMENT 'Tên file thực tế, vd: gym_hall_6abc123.jpg',

    `file_path`   VARCHAR(500) NOT NULL
                               COMMENT 'Đường dẫn vật lý đầy đủ trên server, vd: C:/wamp64/www/PHP/ELite_GYM/upload/image_panel/gym_hall.jpg',

    `file_url`    VARCHAR(500) NOT NULL
                               COMMENT 'URL tương đối để trình duyệt hiển thị, vd: /PHP/ELite_GYM/upload/image_panel/gym_hall.jpg',

    `file_size`   INT          NOT NULL DEFAULT 0
                               COMMENT 'Kích thước file tính bằng byte',

    `file_ext`    VARCHAR(10)  NOT NULL DEFAULT ''
                               COMMENT 'Phần mở rộng không có dấu chấm: jpg | png | webp | gif',

    `sort_order`  INT          NOT NULL DEFAULT 0
                               COMMENT 'Thứ tự hiển thị trong slideshow, sắp xếp tăng dần (ASC)',

    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1
                               COMMENT '1 = đang hiển thị  |  0 = đang ẩn',

    `uploaded_by` INT              NULL DEFAULT NULL
                               COMMENT 'account_id của Admin thực hiện upload (tham chiếu bảng Account)',

    `uploaded_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                               COMMENT 'Thời điểm upload ảnh lên server',

    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP
                               COMMENT 'Thời điểm cập nhật bản ghi gần nhất',

    -- Khóa chính
    PRIMARY KEY (`image_id`),

    -- Index tra cứu nhanh khi query slideshow (lọc is_active + sort_order)
    KEY `idx_active_order` (`is_active`, `sort_order`),

    -- Index theo người upload
    KEY `idx_uploaded_by`  (`uploaded_by`)

) ENGINE  = InnoDB
  DEFAULT CHARSET  = utf8mb4
  COLLATE          = utf8mb4_unicode_ci
  AUTO_INCREMENT   = 1
  COMMENT          = 'Ảnh slideshow hiển thị trang chủ Landing — Elite Gym';


-- ════════════════════════════════════════════════════════════════
--  Kiểm tra bảng vừa tạo
--  (Chạy riêng dòng này để xem cấu trúc)
-- ════════════════════════════════════════════════════════════════
-- DESCRIBE `landing_images`;
-- SELECT * FROM `landing_images`;
-- ════════════════════════════════════════════════════════════════
--  Thêm cột image_url vào bảng MembershipPlan
--  Chạy trong phpMyAdmin → database "datn" → tab SQL
-- ════════════════════════════════════════════════════════════════

ALTER TABLE `MembershipPlan`
    ADD COLUMN `image_url` VARCHAR(500) NULL DEFAULT NULL
        COMMENT 'URL ảnh đại diện gói tập (lưu trong /upload/package/)'
    AFTER `description`;

-- Tạo thư mục upload nếu chưa có (thực hiện thủ công trên server):
-- C:\wamp64\www\PHP\ELite_GYM\upload\package\
CREATE TABLE IF NOT EXISTS `gym_settings` (
    `setting_key`   VARCHAR(100)  NOT NULL,
    `setting_value` TEXT          NULL,
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by`    INT           NULL,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
-- Dữ liệu mặc định
INSERT INTO `gym_settings` (`setting_key`, `setting_value`) VALUES
    ('gym_lat',            '21.0285'),
    ('gym_lng',            '105.8542'),
    ('gym_radius_m',       '100'),
    ('gym_location_name',  'Elite Gym — Chưa cài đặt'),
    ('location_check',     '1')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
ALTER TABLE `Attendance`
    ADD COLUMN `checkin_lat` DOUBLE NULL DEFAULT NULL
    COMMENT 'Vĩ độ GPS lúc check in'
    AFTER `check_out`;
ALTER TABLE `Attendance`
    ADD COLUMN `checkin_lng` DOUBLE NULL DEFAULT NULL
    COMMENT 'Kinh độ GPS lúc check in'
    AFTER `checkin_lat`;
ALTER TABLE `Attendance`
    ADD COLUMN `checkin_distance` INT NULL DEFAULT NULL
    COMMENT 'Khoảng cách (mét) tới phòng tập lúc check in'
    AFTER `checkin_lng`;

CREATE TABLE IF NOT EXISTS `PackageType` (
    `type_id`     INT          NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
    `type_name`   VARCHAR(100) NOT NULL                COMMENT 'Package type name: Basic / Standard / Premium / VIP / Student',
    `description` VARCHAR(500) NULL                    COMMENT 'Package type description',
    `color_code`  VARCHAR(10)  NULL                    COMMENT 'UI color hex code, e.g. #d4a017',
    `sort_order`  INT          NOT NULL DEFAULT 0       COMMENT 'Display order ascending',
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1       COMMENT '1 = active | 0 = hidden',
    PRIMARY KEY (`type_id`),
    UNIQUE KEY `uq_package_type_name` (`type_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Package type categories for membership plans and gym rooms';

INSERT INTO `PackageType` (`type_name`, `description`, `color_code`, `sort_order`) VALUES
    ('Basic',    'Standard access to main gym area only',              '#6b7280', 1),
    ('Standard', 'Access to main gym area and group classes',          '#3b82f6', 2),
    ('Premium',  'Full access including all rooms and equipment',      '#d4a017', 3),
    ('VIP',      'All-access with personal trainer sessions included', '#a855f7', 4),
    ('Student',  'Discounted plan for students with valid ID',         '#22c55e', 5)
ON DUPLICATE KEY UPDATE `type_name` = `type_name`;

-- ========================
-- MEMBERSHIPPLAN
-- ========================

ALTER TABLE `MembershipPlan`
    ADD COLUMN `package_type_id` INT NULL DEFAULT NULL
        COMMENT 'Package type of this plan (FK → PackageType)'
    AFTER `plan_id`,
    ADD CONSTRAINT `fk_plan_package_type`
        FOREIGN KEY (`package_type_id`) REFERENCES `PackageType`(`type_id`)
        ON UPDATE CASCADE ON DELETE SET NULL;

-- ========================
-- GYMROOM
-- ========================

ALTER TABLE `GymRoom`
    ADD COLUMN `package_type_id` INT NULL DEFAULT NULL
        COMMENT 'Minimum package type required to access this room (FK → PackageType)'
    AFTER `description`,
    ADD CONSTRAINT `fk_room_package_type`
        FOREIGN KEY (`package_type_id`) REFERENCES `PackageType`(`type_id`)
        ON UPDATE CASCADE ON DELETE SET NULL;
        -- ============================================================
-- CẬP NHẬT: Lương tháng + Giờ đóng cửa phòng tập
-- Chạy trong phpMyAdmin → database "datn" → tab SQL
-- ============================================================

-- ========================
-- 1. EMPLOYEE: đổi lương giờ → lương tháng
-- ========================
ALTER TABLE `Employee`
    CHANGE COLUMN `hourly_wage` `monthly_salary` DECIMAL(12,2) NOT NULL DEFAULT 0
        COMMENT 'Lương cơ bản cố định theo tháng (VNĐ)';

-- ========================
-- 2. GYMROOM: thêm giờ đóng cửa
--    (open_time đã có sẵn, thêm close_time ngay sau)
-- ========================
ALTER TABLE `GymRoom`
    ADD COLUMN `close_time` TIME NULL DEFAULT NULL
        COMMENT 'Giờ đóng cửa phòng tập'
    AFTER `open_time`;

-- ========================
-- 3. PAYROLL: xóa total_hours (không tính theo giờ nữa)
-- ========================
ALTER TABLE `Payroll`
    DROP COLUMN `total_hours`;

-- Kiểm tra kết quả:
-- DESCRIBE Employee;
-- DESCRIBE GymRoom;
-- DESCRIBE Payroll;