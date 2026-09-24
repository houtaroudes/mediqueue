<?php
// Create visit record (staff, Phase 8)
require_once __DIR__ . '/../includes/auth.php';
require_role(array('clinic_staff', 'admin'));

$db = get_db_connection();
$staffId = current_user()['id'];

$patientId = (int) ($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
$errors = array();

// verify patient exists
$stmt = $db->prepare('SELECT first_name, last_name FROM patients WHERE id = ?');
$stmt->bind_param('i', $patientId);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    flash_set('error', 'Patient not found.');
    redirect(BASE_URL . '/staff/patient.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitDate = $_POST['visit_date'] ?? '';
    $notes     = trim($_POST['visit_notes'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $apptId    = (int) ($_POST['appointment_id'] ?? 0) ?: null;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $visitDate)) {
        $errors[] = 'Valid visit date required.';
    }
    if ($notes === '' && $treatment === '') {
        $errors[] = 'Add visit notes or treatment (at least one).';
    }

    if (empty($errors)) {
        $stmt = $db->prepare('INSERT INTO health_records (patient_id, appointment_id, staff_id, visit_date, visit_notes, treatment)
            VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iiisss', $patientId, $apptId, $staffId, $visitDate, $notes, $treatment);
        $stmt->execute();
        $stmt->close();

        // link back to the patient's account notification if any
        $stmt = $db->prepare('SELECT user_id FROM patients WHERE id = ?');
        $stmt->bind_param('i', $patientId);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($p && $p['user_id']) {
            notify_user((int) $p['user_id'], 'general', 'A new visit record was added to your history (' . $visitDate . ').');
        }

        log_activity('record.create', 'patient=' . $patientId . ' date=' . $visitDate);
        flash_set('success', 'Visit record saved.');
        redirect(BASE_URL . '/staff/patient.php?id=' . $patientId);
    }
}

// optional: today's completed appointments for this patient to link the record
$stmt = $db->prepare('SELECT a.id, a.appointment_date, s.name AS service_name FROM appointments a
    JOIN services s ON s.id = a.service_id
    WHERE a.patient_id = ? AND a.status = "completed"
    ORDER BY a.appointment_date DESC LIMIT 10');
$stmt->bind_param('i', $patientId);
$stmt->execute();
$recentAppts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'New Visit Record';
require __DIR__ . '/../includes/header.php';
?>

<section class="container page narrow">
    <h1>New visit record — <?php echo e($patient['first_name'] . ' ' . $patient['last_name']); ?></h1>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?php echo e($err); ?></div>
    <?php endforeach; ?>

    <form method="post" class="card form">
        <input type="hidden" name="patient_id" value="<?php echo (int) $patientId; ?>">

        <label>Visit Date
            <input type="date" name="visit_date" required value="<?php echo date('Y-m-d'); ?>">
        </label>

        <label>Link to appointment <span class="muted">(optional)</span>
            <select name="appointment_id">
                <option value="">— none / walk-in —</option>
                <?php foreach ($recentAppts as $a): ?>
                    <option value="<?php echo (int) $a['id']; ?>"><?php echo e($a['appointment_date'] . ' - ' . $a['service_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Visit Notes
            <textarea name="visit_notes" rows="4" maxlength="2000" placeholder="Symptoms, findings..."></textarea>
        </label>

        <label>Treatment / Remarks
            <input type="text" name="treatment" maxlength="255" placeholder="e.g. Paracetamol 500mg, rest advised">
        </label>

        <button type="submit" class="btn btn-primary btn-block">Save Record</button>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
