#!/bin/bash
# MediQueue Phase 14 smoke tests (curl-driven)
B="http://localhost/mediqueue"
T="/c/Users/brysa/AppData/Local/Temp/mqtest"
MYSQL="/c/xampp/mysql/bin/mysql.exe"
mkdir -p "$T"
PASS=0; FAIL=0

ok()  { PASS=$((PASS+1)); echo "PASS: $1"; }
bad() { FAIL=$((FAIL+1)); echo "FAIL: $1"; }

check() {
  if grep -q "$3" "$T/last.html" 2>/dev/null; then ok "$1"; else bad "$1 (missing: $3)"; fi
}

# login as a role into its own cookie jar
login() {
  local jar="$1" email="$2" pass="$3"
  TOKEN=$(curl -s -c "$jar" "$B/login.php" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -b "$jar" -c "$jar" -X POST "$B/login.php" \
    --data-urlencode "csrf_token=$TOKEN" \
    --data-urlencode "email=$email" \
    --data-urlencode "password=$pass" \
    -o /dev/null
}

# POST with fresh CSRF token, following redirects so flash messages are visible.
# NOTE: no -X POST here! With -X POST + -L, curl re-POSTs on every redirect leg
# (which trips CSRF). --data implies POST for leg 1, then curl follows with GET.
post() {
  local jar="$1" url="$2"; shift 2
  TOKEN=$(curl -s -b "$jar" -c "$jar" "$url" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  local args=()
  for kv in "$@"; do args+=(--data-urlencode "$kv"); done
  curl -s -L -b "$jar" -c "$jar" "$url" --data-urlencode "csrf_token=$TOKEN" "${args[@]}" -o "$T/last.html" -w "%{http_code}" > "$T/code.txt"
}

get() {
  local jar="$1" url="$2"
  curl -s -b "$jar" "$url" -o "$T/last.html" -w "%{http_code}" > "$T/code.txt"
}

J=/tmp/mq_jar; A=/tmp/adm_jar; S=/tmp/stf_jar; I=/tmp/ins_jar
rm -f "$J" "$A" "$S" "$I" /tmp/mq_cookies

echo "== Reset test state (clean slate for deterministic numbers) =="
$MYSQL -u root mediqueue -e "
  DELETE FROM health_records WHERE visit_notes = 'smoke visit';
  DELETE FROM queue_entries WHERE queue_date = CURDATE();
  DELETE FROM appointments WHERE notes = 'smoke test';
  DELETE FROM notifications WHERE message LIKE '%smoke%' OR message LIKE '%A00%';
  DELETE FROM services WHERE name = 'Smoke Test Service';
  DELETE FROM teaching_schedules WHERE class_name = 'TEST 999';
  DELETE FROM staff_schedules WHERE schedule_date = CURDATE() + INTERVAL 3 DAY AND start_time = '09:00:00';
  DELETE l FROM activity_logs l WHERE l.detail LIKE '%smoke%' OR l.detail LIKE '%TEST 999%' OR l.detail LIKE 'number=A00%';
" && echo "state reset OK"

echo "== Public pages =="
curl -s "$B/" -o "$T/last.html"; check "landing renders" "$T/last.html" "clinic line, on your screen"
curl -s "$B/health.php" -o "$T/last.html"; check "health DB ok" "$T/last.html" "Connected to MySQL"

echo "== Auth: wrong password =="
curl -s -c /tmp/mq_cookies "$B/login.php" -o "$T/last.html"
TK=$(grep -o 'name="csrf_token" value="[^"]*"' "$T/last.html" | head -1 | sed 's/.*value="//;s/"//')
curl -s -b /tmp/mq_cookies -X POST "$B/login.php" --data-urlencode "csrf_token=$TK" \
  --data-urlencode "email=d.cruz@student.edu" --data-urlencode "password=WRONG" -o "$T/last.html"
check "wrong password rejected" "$T/last.html" "Invalid email or password"

echo "== CSRF protection =="
curl -s -b /tmp/mq_cookies -X POST "$B/login.php" \
  --data-urlencode "email=d.cruz@student.edu" --data-urlencode "password=Student@123" -o /dev/null -w "%{http_code}" > "$T/code.txt"
if [ "$(cat $T/code.txt)" = "403" ]; then ok "POST without CSRF blocked (403)"; else bad "CSRF check (got $(cat $T/code.txt))"; fi

echo "== Student flow =="
login "$J" "d.cruz@student.edu" "Student@123"
get "$J" "$B/user/dashboard.php"; check "student dashboard loads" "$T/last.html" "Upcoming appointments"
get "$J" "$B/user/book-appointment.php"; check "booking form loads" "$T/last.html" "Confirm Booking"
check "brief services offered (Follow-up Checkup)" "$T/last.html" "Follow-up Checkup"
SVC_ID=$(grep -o 'option value="[0-9][0-9]*"' "$T/last.html" | head -1 | sed 's/option value="//;s/"//')
BOOKDATE=$(date -d '+2 days' +%Y-%m-%d)
post "$J" "$B/user/book-appointment.php" "service_id=$SVC_ID" "date=$BOOKDATE" "slot=10:00" "notes=smoke test" "confirm=1"
check "booking created" "$T/last.html" "Appointment booked"
# a hand-made POST must not get a slot the form never offers
post "$J" "$B/user/book-appointment.php" "service_id=$SVC_ID" "date=$(date -d 'next monday' +%Y-%m-%d)" "slot=09:07" "notes=off grid" "confirm=1"
check "off-grid slot rejected" "$T/last.html" "not one of the offered slots"
# closed days come from settings (default 7 = Sunday)
post "$J" "$B/user/book-appointment.php" "service_id=$SVC_ID" "date=$(date -d 'next sunday' +%Y-%m-%d)" "slot=10:00" "notes=closed day" "confirm=1"
check "closed day rejected" "$T/last.html" "The clinic is closed on"
get "$J" "$B/user/appointments.php"; check "appointment listed" "$T/last.html" "$BOOKDATE"
post "$J" "$B/user/queue.php" "action=join"
check "queue joined (A001)" "$T/last.html" "A001"
get "$J" "$B/user/queue.php"; check "queue status shows number" "$T/last.html" "Your number today"
post "$J" "$B/user/notifications.php" "action=mark_read"
check "notifications page works" "$T/last.html" "Notifications"

echo "== Role guard: student blocked from admin =="
get "$J" "$B/admin/users.php"
CODE=$(cat "$T/code.txt")
if [ "$CODE" = "403" ] || grep -q "Access Denied" "$T/last.html"; then ok "student blocked from admin (403)"; else bad "admin guard (got $CODE)"; fi

echo "== Instructor flow =="
login "$I" "c.lopez@campus.edu" "Instructor@123"
get "$I" "$B/user/teaching-schedule.php"; check "teaching schedule loads" "$T/last.html" "Add Class"
post "$I" "$B/user/teaching-schedule.php" "action=add" "day_of_week=5" "start_time=09:00" "end_time=10:30" "class_name=TEST 999"
get "$I" "$B/user/teaching-schedule.php"; check "class added" "$T/last.html" "TEST 999"
get "$I" "$B/user/recommended-slots.php"; check "recommended slots load" "$T/last.html" "Recommended clinic slots"
post "$I" "$B/user/queue.php" "action=join"
check "instructor joined queue (A002)" "$T/last.html" "A002"

echo "== Staff flow: queue =="
login "$S" "a.reyes@campus.edu" "Doctor@123"
get "$S" "$B/staff/dashboard.php"; check "staff dashboard loads" "$T/last.html" "Clinic Staff Dashboard"
post "$S" "$B/staff/queue-management.php" "action=call_next"
check "call next -> A001 called" "$T/last.html" "Called A001"
ENTRY_ID=$(grep -o 'name="entry_id" value="[0-9]*"' "$T/last.html" | head -1 | sed 's/.*value="//;s/"//')
post "$S" "$B/staff/queue-management.php" "action=start_consult" "entry_id=$ENTRY_ID"
check "start consultation" "$T/last.html" "in consultation"
post "$S" "$B/staff/queue-management.php" "action=complete" "entry_id=$ENTRY_ID"
check "complete consultation" "$T/last.html" "completed"
post "$S" "$B/staff/queue-management.php" "action=call_next"
check "call next -> A002" "$T/last.html" "Called A002"
ENTRY_ID=$(grep -o 'name="entry_id" value="[0-9]*"' "$T/last.html" | head -1 | sed 's/.*value="//;s/"//')
post "$S" "$B/staff/queue-management.php" "action=start_consult" "entry_id=$ENTRY_ID"
post "$S" "$B/staff/queue-management.php" "action=complete" "entry_id=$ENTRY_ID"
get "$S" "$B/staff/patient.php?q=Cruz"; check "patient search finds Cruz" "$T/last.html" "Dino Cruz"
get "$S" "$B/staff/visit-record.php?patient_id=1"; check "visit record form loads" "$T/last.html" "New visit record"
# token must come from a page that has a POST form; visit-record with patient_id shows it
post "$S" "$B/staff/visit-record.php?patient_id=1" "patient_id=1" "visit_date=$(date +%Y-%m-%d)" "visit_notes=smoke visit" "treatment=rest and water"
check "visit record saved" "$T/last.html" "Visit record saved"
get "$S" "$B/staff/today-appointments.php"; check "today appointments loads" "$T/last.html" "Today"
get "$S" "$B/staff/staff-schedule.php"; check "staff schedule loads" "$T/last.html" "duty schedule"

echo "== Admin flow =="
login "$A" "admin@campus.edu" "Admin@123"
get "$A" "$B/admin/dashboard.php"; check "admin dashboard loads" "$T/last.html" "Admin Dashboard"
get "$A" "$B/admin/users.php"; check "users page loads" "$T/last.html" "Search"
get "$A" "$B/admin/services.php"; check "services page loads" "$T/last.html" "Add Service"
post "$A" "$B/admin/services.php" "op=add" "name=Smoke Test Service" "duration_minutes=25" "description=temp"
check "service added" "$T/last.html" "Service added"
get "$A" "$B/admin/schedules.php"; check "schedules page loads" "$T/last.html" "duty schedules"
post "$A" "$B/admin/schedules.php" "op=add" "staff_id=3" "schedule_date=$(date -d '+3 days' +%Y-%m-%d)" "start_time=09:00" "end_time=13:00"
check "schedule added" "$T/last.html" "Schedule added"
get "$A" "$B/admin/appointments.php"; check "appointments page loads" "$T/last.html" "All appointments"
get "$A" "$B/admin/reports.php"; check "reports page loads" "$T/last.html" "Per-service load"
get "$A" "$B/admin/settings.php"; check "settings page loads" "$T/last.html" "Clinic opens"
post "$A" "$B/admin/settings.php" "clinic_open_time=08:00" "clinic_close_time=17:00" "saturday_open_time=08:00" "saturday_close_time=12:00" "closed_days=7" "slot_interval_min=30" "booking_advance_days=14" "cancel_min_hours=2" "queue_prefix=A" "queue_pad_len=3" "max_daily_bookings=1"
check "settings saved" "$T/last.html" "Settings saved"

# the public hours table must follow the settings, not a literal in the page
post "$A" "$B/admin/settings.php" "clinic_open_time=07:30" "clinic_close_time=16:30"
curl -s "$B/" -o "$T/last.html"; check "landing hours follow settings" "$T/last.html" "7:30 AM - 4:30 PM"
post "$A" "$B/admin/settings.php" "clinic_open_time=08:00" "clinic_close_time=17:00"
curl -s "$B/" -o "$T/last.html"; check "landing hours restored" "$T/last.html" "8:00 AM - 5:00 PM"
get "$A" "$B/admin/activity-logs.php"; check "activity logs load" "$T/last.html" "Activity logs"

echo "== Queue order: skip must send the patient to the back =="
# the student joins first (A003), the instructor second (A004)
# assert on the join confirmation, not the bare number: the number also shows
# up in the page counters, so a bare "A004" check passes even when the join is
# refused and the queue is one shorter than the test believes
post "$J" "$B/user/queue.php" "action=join"
check "student rejoined queue (A003)" "$T/last.html" "Your number is A003"
post "$I" "$B/user/queue.php" "action=join"
check "instructor rejoined queue (A004)" "$T/last.html" "Your number is A004"
post "$S" "$B/staff/queue-management.php" "action=call_next"
check "call next -> A003" "$T/last.html" "Called A003"
ENTRY_ID=$(grep -o 'name="entry_id" value="[0-9]*"' "$T/last.html" | head -1 | sed 's/.*value="//;s/"//')
post "$S" "$B/staff/queue-management.php" "action=skip" "entry_id=$ENTRY_ID"
# the flash is escaped on output, so the arrow arrives as &gt;
check "skip returned A003 to waiting" "$T/last.html" "Entry A003 -&gt; waiting"
post "$S" "$B/staff/queue-management.php" "action=call_next"
# the regression this guards: with id ordering this said "Called A003" again
check "call next after skip -> A004" "$T/last.html" "Called A004"
# every Call Next also nudges the waiting account holder behind the call;
# the instructor was behind A001 earlier in this script, so the nudge exists
get "$I" "$B/user/notifications.php"; check "next-in-line nudge reached the waiting account" "$T/last.html" "You are next in line"

echo "== QR walk-in: an anonymous visitor takes a number =="
rm -f /tmp/mq_guest_jar
post /tmp/mq_guest_jar "$B/public/join.php" "action=join"
check "guest takes a number (A005)" "$T/last.html" "Your number is A005"
check "guest ticket shows its position" "$T/last.html" "ahead of you"
# mysql.exe prints CRLF on Windows; the \r would break the 32-hex token check
GTOK=$($MYSQL -u root mediqueue -N -s -e "SELECT join_token FROM queue_entries WHERE queue_date=CURDATE() AND queue_number='A005'" | tr -d '\r')
# harvest the CSRF token from the BARE page: join.php?t=... shows the ticket
# view, which has no form, so post()'s own harvest would come back empty
GTOKEN=$(curl -s -b /tmp/mq_guest_jar "$B/public/join.php" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -L -b /tmp/mq_guest_jar -c /tmp/mq_guest_jar "$B/public/join.php?t=$GTOK" \
  --data-urlencode "csrf_token=$GTOKEN" --data-urlencode "action=join" -o "$T/last.html"
check "same receipt returns the same ticket" "$T/last.html" "already in the queue today"
curl -s "$B/" -o "$T/last.html"
check "landing shows the wait estimate line" "$T/last.html" "board-wait"
curl -s "$B/queue-status.php" -o "$T/last.html"
check "board feed carries the estimate" "$T/last.html" "wait_min"
get "$S" "$B/staff/queue-management.php"; check "staff page shows the walk-in QR" "$T/last.html" "Walk-in QR code"

 echo "== Login guard explains itself and returns you back =="
rm -f /tmp/mq_guard_jar
curl -s -L -c /tmp/mq_guard_jar -b /tmp/mq_guard_jar "$B/user/queue.php" -o "$T/last.html"
check "login page says why" "$T/last.html" "Please log in to continue"
GTOKEN=$(grep -o 'name="csrf_token" value="[^"]*"' "$T/last.html" | head -1 | sed 's/.*value="//;s/"//')
curl -s -L -c /tmp/mq_guard_jar -b /tmp/mq_guard_jar "$B/login.php" \
  --data-urlencode "csrf_token=$GTOKEN" \
  --data-urlencode "email=d.cruz@student.edu" \
  --data-urlencode "password=Student@123" -o "$T/last.html"
check "login returns to the interrupted page" "$T/last.html" "Your number today"
# but a role that cannot open that page must not be sent into a 403
rm -f /tmp/mq_guard2
curl -s -L -c /tmp/mq_guard2 -b /tmp/mq_guard2 "$B/user/queue.php" -o "$T/last.html"
GTOKEN=$(grep -o 'name="csrf_token" value="[^"]*"' "$T/last.html" | head -1 | sed 's/.*value="//;s/"//')
curl -s -L -c /tmp/mq_guard2 -b /tmp/mq_guard2 "$B/login.php" \
  --data-urlencode "csrf_token=$GTOKEN" \
  --data-urlencode "email=a.reyes@campus.edu" \
  --data-urlencode "password=Doctor@123" -o "$T/last.html"
check "staff falls back to its dashboard, not a 403" "$T/last.html" "Clinic Staff Dashboard"

echo "== Logout =="
get "$J" "$B/logout.php"
get "$J" "$B/user/dashboard.php"
CODE=$(cat "$T/code.txt")
if [ "$CODE" = "302" ]; then ok "logged-out session redirected"; else bad "logout guard (got $CODE)"; fi

echo ""
echo "================================"
echo "RESULTS: $PASS passed, $FAIL failed"
echo "================================"
