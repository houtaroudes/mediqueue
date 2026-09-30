-- =============================================================
-- MediQueue demo data for TODAY
--
-- Run this before a walkthrough, a screenshot, or an interview demo:
--   C:\xampp\mysql\bin\mysql.exe -u root mediqueue < database/seed-demo-today.sql
--
-- Why it exists: the queue board, the staff queue page, and today's
-- appointments all filter on CURDATE(). The sample data in mediqueue.sql is
-- fixed to the day it was written, so on any other day every one of those
-- screens looks empty, which is the worst possible first impression for the
-- feature the project is built around.
--
-- The two completed entries are deliberate: the wait estimate on the board
-- learns from today's own enqueue-to-done times, and without them it would
-- sit on the configured average instead of showing what it learned.
--
-- WARNING: this deletes today's appointments and queue entries first, so it
-- is local demo data only. Never run it against a live clinic.
-- =============================================================

USE mediqueue;

DELETE FROM queue_entries WHERE queue_date = CURDATE();
DELETE FROM appointments  WHERE appointment_date = CURDATE();

-- Walk-ins with no account at all. patients.user_id is nullable on purpose:
-- people can take a number at the desk or by QR without ever registering.
-- join.php shares one anonymous record per clinic ('Walk-in Visitor').
DELETE FROM patients
 WHERE user_id IS NULL AND first_name = 'Walk-in' AND last_name IN ('Patient', 'Visitor');

INSERT INTO patients (user_id, first_name, last_name, date_of_birth, sex)
VALUES (NULL, 'Walk-in', 'Visitor', '2003-01-01', 'other');
SET @walkin = LAST_INSERT_ID();

-- Two appointments on the books today (service 1 = General Consultation, 30 min)
INSERT INTO appointments (patient_id, service_id, appointment_date, start_time, end_time, status, notes) VALUES
(2, 1, CURDATE(), '09:00:00', '09:30:00', 'confirmed', 'demo data'),
(3, 1, CURDATE(), '09:30:00', '10:00:00', 'pending',   'demo data');

-- The day so far, in arrival order: A001 in the room, A002 called, A003 and
-- A005 waiting, A004 and A006 already done (15 minutes each, which is what
-- the board's wait estimate reports). enqueued_at is set explicitly so the
-- demo shows a real order rather than rows that all landed in one second.
--   A001 (patient 2, appointment) in_consultation
INSERT INTO queue_entries (patient_id, appointment_id, queue_date, queue_number, status, called_at, enqueued_at)
SELECT 2, a.id, CURDATE(), 'A001', 'in_consultation', NOW() - INTERVAL 35 MINUTE, NOW() - INTERVAL 95 MINUTE
  FROM appointments a WHERE a.patient_id = 2 AND a.appointment_date = CURDATE() LIMIT 1;

--   A002 (patient 3, appointment) called, about to enter the room
INSERT INTO queue_entries (patient_id, appointment_id, queue_date, queue_number, status, called_at, enqueued_at)
SELECT 3, a.id, CURDATE(), 'A002', 'called', NOW() - INTERVAL 3 MINUTE, NOW() - INTERVAL 80 MINUTE
  FROM appointments a WHERE a.patient_id = 3 AND a.appointment_date = CURDATE() LIMIT 1;

--   A003 (patient 1) waiting, A005 (anonymous QR walk-in) waiting with the
--   receipt token from the join page, A004 and A006 completed
INSERT INTO queue_entries (patient_id, queue_date, queue_number, status, enqueued_at) VALUES
(1,       CURDATE(), 'A003', 'waiting', NOW() - INTERVAL 45 MINUTE);

INSERT INTO queue_entries (patient_id, queue_date, queue_number, status, called_at, completed_at, enqueued_at) VALUES
(@walkin, CURDATE(), 'A004', 'completed', NOW() - INTERVAL 38 MINUTE, NOW() - INTERVAL 25 MINUTE, NOW() - INTERVAL 40 MINUTE);

INSERT INTO queue_entries (patient_id, queue_date, queue_number, status, enqueued_at, join_token) VALUES
(@walkin, CURDATE(), 'A005', 'waiting', NOW() - INTERVAL 20 MINUTE, 'a005a005a005a005a005a005a005a005');

INSERT INTO queue_entries (patient_id, queue_date, queue_number, status, called_at, completed_at, enqueued_at) VALUES
(@walkin, CURDATE(), 'A006', 'completed', NOW() - INTERVAL 16 MINUTE, NOW() - INTERVAL 3 MINUTE, NOW() - INTERVAL 18 MINUTE);

-- the next number the system will hand out today is A007
