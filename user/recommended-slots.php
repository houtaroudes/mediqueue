<?php
// Recommended slots for instructors (Phase 9)
// Simple, readable matching: clinic slot is recommended if it does NOT overlap a class that day.
require_once __DIR__ . '/../includes/auth.php';
require_role(array('instructor'));

$db = get_db_connection();
$userId = current_user()['id'];

// clinic rules from settings (configurable)
$openTime = get_setting('clinic_open_time', '08:00');
$closeTime = get_setting('clinic_close_time', '17:00');
$interval = (int) get_setting('slot_interval_min', 30);
$horizon = (int) get_setting('booking_advance_days', 14);

// my classes by weekday
$stmt = $db->prepare('SELECT day_of_week, start_time, end_time, class_name FROM teaching_schedules WHERE instructor_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$allClasses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// does [slotStart, slotEnd) overlap [classStart, classEnd)?
function overlaps($slotStart, $slotEnd, $classStart, $classEnd) {
    return $slotStart < $classEnd && $classStart < $slotEnd;
}

// build recommendations for the next 7 calendar days
$recommendations = array();
$today = new DateTime('today');
for ($i = 0; $i < 7; $i++) {
    $date = (clone $today)->modify("+$i days");
    $dow = (int) $date->format('N');
    if ($dow === 7) continue; // clinic closed Sundays

    $dayClasses = array_filter($allClasses, function ($c) use ($dow) {
        return (int) $c['day_of_week'] === $dow;
    });

    $slots = array();
    for ($t = strtotime($openTime); $t < strtotime($closeTime); $t += $interval * 60) {
        $slotStart = date('H:i', $t);
        $slotEnd = date('H:i', $t + $interval * 60);

        $conflict = null;
        foreach ($dayClasses as $c) {
            if (overlaps($slotStart, $slotEnd, $c['start_time'], $c['end_time'])) {
                $conflict = $c['class_name'];
                break;
            }
        }
        $slots[] = array(
            'start' => $slotStart,
            'end' => $slotEnd,
            'free' => $conflict === null,
            'conflict' => $conflict,
        );
    }
    $recommendations[$date->format('Y-m-d')] = $slots;
}

// count already-booked slots to mark availability (passing date safely below)
$page_title = 'Recommended Clinic Slots';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page">
    <h1>Recommended clinic slots</h1>
    <p class="muted">
        Clinic hours <?php echo e($openTime); ?>–<?php echo e($closeTime); ?>, <?php echo (int) $interval; ?>-minute slots.
        Slots that overlap your classes are marked with the conflicting class. Booking still needs your confirmation —
        nothing is ever auto-booked.
    </p>

    <?php foreach ($recommendations as $date => $slots): ?>
        <div class="card">
            <h3><?php echo e(date('l, M j, Y', strtotime($date))); ?></h3>
            <div class="slot-grid">
                <?php foreach ($slots as $s): ?>
                    <?php if ($s['free']): ?>
                        <a class="slot slot-free" href="<?php echo BASE_URL; ?>/user/book-appointment.php?date=<?php echo e($date); ?>&slot=<?php echo e($s['start']); ?>">
                            <?php echo e(date('g:i A', strtotime($s['start']))); ?>
                        </a>
                    <?php else: ?>
                        <span class="slot slot-busy" title="Conflicts with <?php echo e($s['conflict']); ?>">
                            <?php echo e(date('g:i A', strtotime($s['start']))); ?> ✕
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
