<?php
// Admin: clinic settings (Phase 11) - all the "configurable rules" live here
require_once __DIR__ . '/../includes/auth.php';
require_role(array('admin'));

$db = get_db_connection();

// keys the admin may edit, with labels (anything else in settings stays untouched)
$editable = array(
    'clinic_open_time'     => 'Clinic opens (HH:MM)',
    'clinic_close_time'    => 'Clinic closes (HH:MM)',
    'slot_interval_min'    => 'Slot interval (minutes)',
    'booking_advance_days' => 'Book up to N days ahead',
    'cancel_min_hours'     => 'Cancel at least N hours before',
    'queue_prefix'         => 'Queue number prefix',
    'queue_pad_len'        => 'Queue number digits (e.g. 3 = A001)',
    'max_daily_bookings'   => 'Max bookings per student per day',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($editable as $key => $label) {
        if (!isset($_POST[$key])) continue;
        $val = trim($_POST[$key]);
        // light validation per known key
        if (in_array($key, array('slot_interval_min', 'booking_advance_days', 'cancel_min_hours', 'queue_pad_len', 'max_daily_bookings'), true)) {
            if (!ctype_digit($val) || (int) $val < 1 || (int) $val > 365) continue;
        }
        if ($key === 'queue_prefix' && !preg_match('/^[A-Z]{1,3}$/i', $val)) continue;
        if (in_array($key, array('clinic_open_time', 'clinic_close_time'), true) && !preg_match('/^\d{2}:\d{2}$/', $val)) continue;

        $stmt = $db->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
        $stmt->bind_param('ss', $val, $key);
        $stmt->execute();
        $stmt->close();
    }
    log_activity('admin.settings', 'clinic settings updated');
    flash_set('success', 'Settings saved.');
    redirect(BASE_URL . '/admin/settings.php');
}

// load current values
$current = array();
$res = $db->query('SELECT setting_key, setting_value FROM settings');
while ($row = $res->fetch_assoc()) {
    $current[$row['setting_key']] = $row['setting_value'];
}

$page_title = 'Clinic Settings';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page narrow">
    <h1>Clinic settings</h1>
    <p class="muted">These rules drive booking, cancellation, and queue behavior everywhere in the system — nothing is hardcoded.</p>

    <form method="post" class="card form">
        <?php foreach ($editable as $key => $label): ?>
            <label><?php echo e($label); ?>
                <input type="text" name="<?php echo e($key); ?>" value="<?php echo e($current[$key] ?? ''); ?>">
            </label>
        <?php endforeach; ?>
        <button class="btn btn-primary btn-block">Save Settings</button>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
