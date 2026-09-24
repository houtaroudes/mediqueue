-- =============================================
-- MediQueue - Phase 2: full schema + seed data
-- Import via phpMyAdmin or:
--   C:\xampp\mysql\bin\mysql.exe -u root < database/mediqueue.sql
-- =============================================

CREATE DATABASE IF NOT EXISTS mediqueue
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE mediqueue;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notifications, health_records, teaching_schedules, staff_schedules,
    queue_entries, appointments, services, patients, users, settings, activity_logs;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------- users ----------
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(50)  NOT NULL,
    last_name       VARCHAR(50)  NOT NULL,
    email           VARCHAR(120) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    role            ENUM('student','staff','instructor','clinic_staff','admin') NOT NULL DEFAULT 'student',
    id_number       VARCHAR(40)  NULL,               -- student / employee number
    phone           VARCHAR(20)  NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_status (status)
) ENGINE=InnoDB;

-- ---------- patients ----------
CREATE TABLE patients (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NULL,
    first_name      VARCHAR(50)  NOT NULL,
    last_name       VARCHAR(50)  NOT NULL,
    date_of_birth   DATE NULL,
    sex             ENUM('male','female','other') NULL,
    address         VARCHAR(200) NULL,
    emergency_name  VARCHAR(100) NULL,
    emergency_phone VARCHAR(20)  NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_patients_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_patients_user (user_id)
) ENGINE=InnoDB;

-- ---------- services (clinic services) ----------
CREATE TABLE services (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    description     VARCHAR(255) NULL,
    duration_minutes INT NOT NULL DEFAULT 30,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- appointments ----------
CREATE TABLE appointments (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id       INT UNSIGNED NOT NULL,
    staff_id         INT UNSIGNED NULL,          -- clinic staff assigned
    service_id       INT UNSIGNED NOT NULL,
    appointment_date DATE NOT NULL,
    start_time       TIME NOT NULL,
    end_time         TIME NOT NULL,
    status           ENUM('pending','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
    notes            VARCHAR(255) NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_appt_patient  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_appt_staff    FOREIGN KEY (staff_id)   REFERENCES users(id)     ON DELETE SET NULL,
    CONSTRAINT fk_appt_service  FOREIGN KEY (service_id) REFERENCES services(id),
    -- one appointment per slot per patient
    UNIQUE KEY uq_appt_slot (appointment_date, start_time, patient_id),
    INDEX idx_appt_date_status (appointment_date, status),
    INDEX idx_appt_staff (staff_id)
) ENGINE=InnoDB;

-- ---------- queue_entries ----------
CREATE TABLE queue_entries (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT UNSIGNED NOT NULL,
    appointment_id  INT UNSIGNED NULL,
    queue_date      DATE NOT NULL,
    queue_number    VARCHAR(10) NOT NULL,        -- A001, A002 ...
    status          ENUM('waiting','called','in_consultation','completed','cancelled') NOT NULL DEFAULT 'waiting',
    called_at       DATETIME NULL,
    completed_at    DATETIME NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_queue_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_queue_appt    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    UNIQUE KEY uq_queue_number (queue_date, queue_number),
    INDEX idx_queue_date_status (queue_date, status)
) ENGINE=InnoDB;

-- ---------- staff_schedules (clinic duty) ----------
CREATE TABLE staff_schedules (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id            INT UNSIGNED NOT NULL,
    schedule_date       DATE NOT NULL,
    start_time          TIME NOT NULL,
    end_time            TIME NOT NULL,
    availability_status ENUM('available','off_duty') NOT NULL DEFAULT 'available',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sched_staff FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_sched (staff_id, schedule_date, start_time),
    INDEX idx_sched_date (schedule_date)
) ENGINE=InnoDB;

-- ---------- teaching_schedules (instructor classes) ----------
CREATE TABLE teaching_schedules (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instructor_id  INT UNSIGNED NOT NULL,
    day_of_week    TINYINT NOT NULL,             -- 1=Mon ... 7=Sun
    start_time     TIME NOT NULL,
    end_time       TIME NOT NULL,
    class_name     VARCHAR(100) NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_teach_instructor FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_teach_instructor (instructor_id, day_of_week)
) ENGINE=InnoDB;

-- ---------- health_records ----------
CREATE TABLE health_records (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id     INT UNSIGNED NOT NULL,
    appointment_id INT UNSIGNED NULL,
    staff_id       INT UNSIGNED NOT NULL,        -- who recorded the visit
    visit_date     DATE NOT NULL,
    visit_notes    TEXT NULL,
    treatment      VARCHAR(255) NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hrec_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_hrec_appt    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    CONSTRAINT fk_hrec_staff   FOREIGN KEY (staff_id) REFERENCES users(id),
    INDEX idx_hrec_patient (patient_id, visit_date)
) ENGINE=InnoDB;

-- ---------- notifications ----------
CREATE TABLE notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    type       ENUM('appointment','queue','status','general') NOT NULL DEFAULT 'general',
    message    VARCHAR(255) NOT NULL,
    status     ENUM('unread','read') NOT NULL DEFAULT 'unread',
    sent_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user (user_id, status)
) ENGINE=InnoDB;

-- ---------- settings (configurable clinic rules) ----------
CREATE TABLE settings (
    setting_key   VARCHAR(60) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- ---------- activity_logs ----------
CREATE TABLE activity_logs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    action     VARCHAR(120) NOT NULL,
    detail     VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_log_time (created_at)
) ENGINE=InnoDB;

-- =============================================================
-- SEED DATA (demo content - safe, no real personal info)
-- =============================================================

INSERT INTO users (first_name, last_name, email, password, role, id_number, phone) VALUES
('Admin',  'User',   'admin@campus.edu',      '$2y$10$Z1ZO2.8JjDUCqckZvgXPaufbeoTZRZXi/PZ4IRu.TtQlGDKmUjjKq', 'admin',       'EMP-0001', '09170000001'),
('Dr. Ana', 'Reyes',   'a.reyes@campus.edu',    '$2y$10$jAunVa7A3yY74vuceIbus.TcR7BKSNem1gMjLvl9dRV3N2SqWngW.', 'clinic_staff','EMP-0002', '09170000002'),
('Nurse Ben','Santos', 'b.santos@campus.edu',   '$2y$10$jAunVa7A3yY74vuceIbus.TcR7BKSNem1gMjLvl9dRV3N2SqWngW.', 'clinic_staff','EMP-0003', '09170000003'),
('Prof. Cara','Lopez', 'c.lopez@campus.edu',    '$2y$10$ZD9EnFnbqYx2QMGbt7dY5OXVHgzt458JK7Q09vvOMg.1xedMKdTF2', 'instructor',  'EMP-0004', '09170000004'),
('Dino',    'Cruz',    'd.cruz@student.edu',    '$2y$10$qtSo7cwExaJTKanhAdLmSOgFG8fLzCvQo6Opjpz/ONiey5JGBiSf.', 'student',     'STU-2026-001', '09180000001'),
('Ella',    'Ramos',   'e.ramos@student.edu',   '$2y$10$qtSo7cwExaJTKanhAdLmSOgFG8fLzCvQo6Opjpz/ONiey5JGBiSf.', 'student',     'STU-2026-002', '09180000002');

INSERT INTO patients (user_id, first_name, last_name, date_of_birth, sex, emergency_name, emergency_phone) VALUES
(4, 'Prof. Cara', 'Lopez', '1985-06-10', 'female', 'Marco Lopez', '09183333333'),
(5, 'Dino', 'Cruz',  '2004-03-15', 'male',   'Maria Cruz',   '09181111111'),
(6, 'Ella', 'Ramos', '2005-08-22', 'female', 'Pablo Ramos',  '09182222222');

INSERT INTO services (name, description, duration_minutes) VALUES
('General Consultation', 'Check-up and basic medical advice', 30),
('Dental Check-up',      'Tooth cleaning and oral exam',      45),
('First Aid / Injury',   'Treatment for minor injuries',      20),
('Health Certificate',   'Medical certificate issuance',      15);

INSERT INTO staff_schedules (staff_id, schedule_date, start_time, end_time) VALUES
(2, CURDATE() + INTERVAL 0 DAY, '08:00:00', '17:00:00'),
(2, CURDATE() + INTERVAL 1 DAY, '08:00:00', '17:00:00'),
(2, CURDATE() + INTERVAL 2 DAY, '08:00:00', '12:00:00'),
(3, CURDATE() + INTERVAL 0 DAY, '08:00:00', '17:00:00'),
(3, CURDATE() + INTERVAL 1 DAY, '10:00:00', '18:00:00');

INSERT INTO teaching_schedules (instructor_id, day_of_week, start_time, end_time, class_name) VALUES
(4, 1, '08:00:00', '09:30:00', 'IT 101 - A'),
(4, 1, '10:00:00', '11:30:00', 'IT 205 - B'),
(4, 1, '13:00:00', '14:30:00', 'IS 110 - C'),
(4, 2, '08:00:00', '09:30:00', 'IT 101 - A'),
(4, 3, '10:00:00', '11:30:00', 'IT 205 - B');

INSERT INTO settings (setting_key, setting_value) VALUES
('clinic_open_time',   '08:00'),
('clinic_close_time',  '17:00'),
('slot_interval_min',  '30'),
('booking_advance_days','14'),
('cancel_min_hours',   '2'),
('queue_prefix',       'A'),
('queue_pad_len',      '3'),
('priority_instructor','1'),
('priority_staff',     '2'),
('priority_student',   '3'),
('max_daily_bookings', '1');
