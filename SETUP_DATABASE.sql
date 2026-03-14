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

-- ========================
-- LOGIN HISTORY
-- ========================

CREATE TABLE LoginHistory (
    login_history_id INT AUTO_INCREMENT PRIMARY KEY,
    account_id       INT          NOT NULL,
    login_time       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    ip_address       VARCHAR(45)  NULL,
    user_agent       VARCHAR(300) NULL,
    result           ENUM('Success','Failed') DEFAULT 'Success',
    note             VARCHAR(200) NULL,
    INDEX idx_lh_account   (account_id),
    INDEX idx_lh_logintime (login_time),
    INDEX idx_lh_result    (result),
    FOREIGN KEY (account_id) REFERENCES Account(account_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================
-- EMPLOYEE
-- ========================

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