# MediQueue - Campus Wellness Clinic Appointment and Queueing System

Full-stack clinic appointment booking + walk-in queue management. PHP 8 / MySQL / vanilla JS, no frameworks. Built in 15 Modified-Waterfall phases.

## Run locally

1. XAMPP is installed at `C:\xampp` with Apache + MySQL running as auto-start Windows services - just open the site
2. **Site:** http://localhost/mediqueue/
3. **Health check:** http://localhost/mediqueue/health.php
4. **Database tool:** http://localhost/phpmyadmin (root, no password - local dev only)
5. If the DB was wiped: re-import `database/mediqueue.sql` via phpMyAdmin

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
- **Queue:** walk-in numbers (A001...), reset daily, staff flow Call Next → Start → Complete / Skip / Cancel with atomic status guards so two staff can't process the same patient
- **Instructor tools:** weekly teaching schedule CRUD + recommended clinic slots that don't overlap classes (never auto-books)
- **Records:** staff-only visit records; patients see their own history
- **Notifications:** internal inbox with unread badge; every booking/call/status change notifies the user
- **Admin:** users, services, duty schedules, appointments browser, reports (per-day, per-service, CSV export), clinic settings (hours, slot length, cancel window, queue format - all DB-driven, nothing hardcoded), activity logs
- **Security:** prepared statements everywhere, password_hash, CSRF token auto-injected into every POST form and verified, security headers, .htaccess blocks on config/includes/database/uploads, friendly errors (never SQL)

## Project layout

```
mediqueue/
├── admin/        dashboard, users, services, schedules, appointments, reports, settings, activity-logs
├── staff/        dashboard, queue-management, today-appointments, patient, visit-record, staff-schedule
├── user/         dashboard, book-appointment, appointments, queue, history, notifications, teaching-schedule, recommended-slots
├── assets/       css / js / images
├── config/       config.php (DB credentials + app settings)
├── includes/     db, auth (sessions/CSRF/guards), functions, icons (Reicon SVG), header, footer
├── database/     mediqueue.sql (schema + seed), smoke-test.sh (38 automated checks)
├── uploads/      (future file uploads; PHP execution blocked)
├── index.php     landing
├── about.php  services.php  contact.php  login.php  register.php  logout.php
└── health.php    system check page
```

## Run the smoke tests yourself

```bash
bash database/smoke-test.sh
```

## Before any real deployment

- Set `APP_DEBUG` to `false` in `config/config.php`
- Give MySQL `root` a password and update config
- Serve over HTTPS (session cookie already flips to secure mode automatically)
- Replace placeholder clinic name, hours, contact details
