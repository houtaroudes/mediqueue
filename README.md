# MediQueue - Campus Wellness Clinic Appointment and Queueing System

Full-stack clinic appointment booking + walk-in queue management. PHP 8 / MySQL / vanilla JS, no frameworks. Built in 15 Modified-Waterfall phases.

## Run locally

1. XAMPP is installed at `C:\xampp`. Apache + MySQL are set to start manually to save RAM: open the XAMPP Control Panel and press Start on Apache and MySQL (services exist but StartType is Manual, nothing runs until you start it)
2. **Site:** http://localhost/mediqueue/ (works only while Apache + MySQL are running)
3. **Health check:** http://localhost/mediqueue/health.php
4. **Database tool:** http://localhost/phpmyadmin (root, no password - local dev only)
5. If the DB was wiped: re-import `database/mediqueue.sql` via phpMyAdmin
6. **Want it to look alive?** Run `C:\xampp\mysql\bin\mysql.exe -u root mediqueue < database/seed-demo-today.sql`. The queue board, the staff queue page, and today's appointments all filter on today's date, so without this they are empty on any day after the sample data was written.

## Demo accounts (seed data)

| Role | Email | Password |
|---|---|---|
| Admin | admin@campus.edu | Admin@123 |
| Clinic staff | a.reyes@campus.edu | Doctor@123 |
| Clinic staff | b.santos@campus.edu | Doctor@123 |
| Instructor | c.lopez@campus.edu | Instructor@123 |
| Student | d.cruz@student.edu | Student@123 |
| Student | e.ramos@student.edu | Student@123 |

## What's inside (all 15 phases)

- **Auth:** register (student/staff/instructor), login, logout, role-based guards, hardened session cookies
- **Booking:** service → date → time slot, availability checks, anti-double-booking (DB unique key + overlap check), configurable daily caps, cancellation window
- **Queue:** walk-in numbers (A001...), reset daily, staff flow Call Next → Start → Complete / Skip / Cancel with atomic status guards so two staff can't process the same patient; QR walk-in registration at `public/join.php` (no login, receipt link returns the visitor to their ticket), a learned wait estimate on the board, and a "you are next" notification for the waiting account holder
- **Instructor tools:** weekly teaching schedule CRUD + recommended clinic slots that don't overlap classes (never auto-books)
- **Records:** staff-only visit records; patients see their own history
- **Notifications:** internal inbox with unread badge; every booking/call/status change notifies the user
- **Admin:** users, services, duty schedules, appointments browser, reports (per-day, per-service, CSV export), clinic settings (opening hours, Saturday hours, closed days, slot length, cancel window, queue format - all DB-driven, and the public hours table is rendered from the same values, so the two cannot disagree), activity logs
- **Security:** prepared statements everywhere, password_hash, CSRF token auto-injected into every POST form and verified, security headers, .htaccess blocks on config/includes/database/uploads, friendly errors (never SQL)

## Project layout

```
mediqueue/
├── admin/        dashboard, users, services, schedules, appointments, reports, settings, activity-logs
├── staff/        dashboard, queue-management, today-appointments, patient, visit-record, staff-schedule
├── user/         dashboard, book-appointment, appointments, queue, history, notifications, teaching-schedule, recommended-slots
├── public/       join.php (QR walk-in registration, no login) + .htaccess
├── assets/       css / js / images
├── config/       config.php (DB credentials + app settings)
├── includes/     db, auth (sessions/CSRF/guards), functions (clinic rules), icons (Reicon SVG),
│                 board (shared queue-board status), header, footer
├── database/     mediqueue.sql (schema + seed), seed-demo-today.sql (today's demo queue),
│                 smoke-test.sh (58 automated checks)
├── uploads/      (future file uploads; PHP execution blocked)
├── index.php     landing
├── about.php  services.php  contact.php  login.php  register.php  logout.php
└── health.php    system check page
```

## Run the smoke tests yourself

```bash
bash database/smoke-test.sh
```

It resets today's queue and appointments first, so run `seed-demo-today.sql`
afterwards if you want the demo state back.

## Upgrading an existing database

Re-importing `database/mediqueue.sql` drops your data. If you already have the
database from an earlier version, apply this once instead:

```sql
ALTER TABLE queue_entries
  ADD COLUMN enqueued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER created_at,
  ADD COLUMN join_token CHAR(32) NULL AFTER enqueued_at,
  ADD UNIQUE KEY uq_join_token (join_token),
  ADD INDEX idx_queue_order (queue_date, status, enqueued_at);
UPDATE queue_entries SET enqueued_at = created_at;
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('saturday_open_time','08:00'), ('saturday_close_time','12:00'), ('closed_days','7'),
  ('avg_service_min','15');
INSERT IGNORE INTO services (name, description, duration_minutes) VALUES
  ('General Checkup','Routine consultation with the clinic nurse',30),
  ('Follow-up Checkup','Progress review after a previous visit',30),
  ('Medical Clearance','Clearance exam for sports, work, or enrollment',20);
```

## Before any real deployment

- Set `APP_DEBUG` to `false` in `config/config.php`
- Give MySQL `root` a password and update config
- Serve over HTTPS (session cookie already flips to secure mode automatically)
- Replace placeholder clinic name, hours, contact details
