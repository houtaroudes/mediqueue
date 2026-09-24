<?php
// Book appointment (Phase 5)
require_once __DIR__ . '/../includes/auth.php';
require_role(array('student', 'staff', 'instructor'));

$db = get_db_connection();
$userId = current_user()['id'];

// patient record required to book
$stmt = $db->prepare('SELECT id FROM patients WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    flash_set('error', 'No patient record is linked to your account. Please contact the clinic.');
    redirect(BASE_URL . '/user/dashboard.php');
}
$patientId = (int) $patient['id'];

$errors = array();
$serviceId = isset($_POST['service_id']) ? (int) $_POST['service_id'] : 0;
$date      = $_POST['date'] ?? '';
$slot      = $_POST['slot'] ?? '';

// settings-driven clinic rules (configurable, not hardcoded)
$openTime  = get_setting('clinic_open_time', '08:00');
$closeTime = get_setting('clinic_close_time', '17:00');
$interval  = (int) get_setting('slot_interval_min', 30);
$maxAdv    = (int) get_setting('booking_advance_days', 14);
$maxDaily  = (int) get_setting('max_daily_bookings', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    // validate date
    $d = DateTime::createFromFormat('Y-m-d', $date);
    $today = new DateTime('today');
    if (!$d || $d->format('Y-m-d') !== $date) {
        $errors[] = 'Invalid date.';
    } elseif ($d < $today) {
        $errors[] = 'You cannot book a date in the past.';
    } elseif ($d > (clone $today)->modify("+$maxAdv days")) {
        $errors[] = "Bookings are only allowed within $maxAdv days.";
    } elseif (in_array((int) $d->format('N'), array(7), true)) { // Sunday closed
        $errors[] = 'The clinic is closed on Sundays.';
    }

    // validate slot
    if (!preg_match('/^\d{2}:\d{2}$/', $slot)) {
        $errors[] = 'Invalid time slot.';
    } else {
        $slotTime = strtotime($slot);
        if ($slotTime < strtotime($openTime) || $slotTime >= strtotime($closeTime)) {
            $errors[] = "Slot must be between $openTime and $closeTime.";
        }
    }

    // validate service
    $stmt = $db->prepare('SELECT * FROM services WHERE id = ? AND is_active = 1');
    $stmt->bind_param('i', $serviceId);
    $stmt->execute();
    $service = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$service) $errors[] = 'Please choose a valid service.';

    // daily booking cap
    if (empty($errors)) {
        $stmt = $db->prepare('SELECT COUNT(*) AS c FROM appointments
            WHERE patient_id = ? AND appointment_date = ? AND status IN ("pending","confirmed")');
        $stmt->bind_param('is', $patientId, $date);
        $stmt->execute();
        $c = (int) $stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
        if ($c >= $maxDaily) $errors[] = "You already have $maxDaily appointment(s) that day.";
    }

    // double-booking: slot taken by anyone
    if (empty($errors)) {
        $end = date('H:i:s', strtotime($slot) + $service['duration_minutes'] * 60);
        $slotEnd = $slot . ':00'; // bind_param needs a variable, not an expression
        $stmt = $db->prepare('SELECT id FROM appointments
            WHERE appointment_date = ? AND status IN ("pending","confirmed")
            AND (start_time < ? AND end_time > ?)');
        $stmt->bind_param('sss', $date, $end, $slotEnd);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'That slot was just taken. Please pick another.';
        }
        $stmt->close();
    }

    // insert
    if (empty($errors)) {
        $end = date('H:i:s', strtotime($slot) + $service['duration_minutes'] * 60);
        $stmt = $db->prepare('INSERT INTO appointments (patient_id, service_id, appointment_date, start_time, end_time, status, notes)
            VALUES (?, ?, ?, ?, ?, "pending", ?)');
        $notes = trim($_POST['notes'] ?? '');
        $stmt->bind_param('iissss', $patientId, $serviceId, $date, $slot, $end, $notes);
        $stmt->execute();
        $apptId = $stmt->insert_id;
        $stmt->close();

        notify_user($userId, 'appointment', 'Appointment booked for ' . $date . ' at ' . date('g:i A', strtotime($slot)) . '.');
        log_activity('appointment.book', 'appt=' . $apptId . ' date=' . $date . ' slot=' . $slot);
        flash_set('success', 'Appointment booked! Status is pending until clinic staff confirm it.');
        redirect(BASE_URL . '/user/appointments.php');
    }
}

// services list for the form
$services = $db->query('SELECT * FROM services WHERE is_active = 1 ORDER BY name')->fetch_all(MYSQLI_ASSOC);

$page_title = 'Book Appointment';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page narrow">
    <h1>Book an appointment</h1>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?php echo e($err); ?></div>
    <?php endforeach; ?>

    <form method="post" class="card form">
        <label>Clinic Service
            <select name="service_id" required>
                <option value="">-- choose a service --</option>
                <?php foreach ($services as $s): ?>
                    <option value="<?php echo (int) $s['id']; ?>" <?php echo $serviceId === (int) $s['id'] ? 'selected' : ''; ?>>
                        <?php echo e($s['name']); ?> (<?php echo (int) $s['duration_minutes']; ?> min)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Date
            <input type="date" name="date" required min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime("+$maxAdv days")); ?>" value="<?php echo e($date); ?>">
        </label>

        <label>Time Slot
            <select name="slot" required>
                <option value="">-- choose a time --</option>
                <?php
                // build slots between open and close; skip past times today
                for ($t = strtotime($openTime); $t < strtotime($closeTime); $t += $interval * 60) {
                    $slotVal = date('H:i', $t);
                    if ($date === date('Y-m-d') && $t < time()) continue;
                    if (in_array((int) date('N', strtotime($date ?: 'next monday')), array(6), true) && $t >= strtotime('12:00')) {
                        continue; // Saturday half-day
                    }
                    echo '<option value="' . $slotVal . '" ' . ($slot === $slotVal ? 'selected' : '') . '>'
                        . date('g:i A', $t) . '</option>';
                }
                ?>
            </select>
        </label>

        <label>Notes <span class="muted">(optional, max 255)</span>
            <input type="text" name="notes" maxlength="255">
        </label>

        <input type="hidden" name="confirm" value="1">
        <button type="submit" class="btn btn-primary btn-block">Confirm Booking</button>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
