<?php
// Public about page (Phase 15)
require_once __DIR__ . '/includes/functions.php';
$page_title = 'About';
require __DIR__ . '/includes/header.php';
?>

<section class="container page narrow">
    <h1>About <?php echo e(APP_NAME); ?></h1>

    <div class="card">
        <p><?php echo e(CLINIC_NAME); ?> provides basic health services to students, faculty, and staff
            of the campus. To make visits faster and fairer, the clinic uses <strong>MediQueue</strong> -
            a simple online system for appointments and walk-in queuing.</p>
    </div>

    <div class="card">
        <h3>Why MediQueue?</h3>
        <p>No more long physical lines. Book a slot online, or grab a queue number and watch your
            status live. Clinic staff process patients in order, and your visit history stays
            available to you and the clinic team only.</p>
    </div>

    <div class="card">
        <h3>Clinic hours</h3>
        <table class="hours-table">
            <tr><td>Monday - Friday</td><td>8:00 AM - 5:00 PM</td></tr>
            <tr><td>Saturday</td><td>8:00 AM - 12:00 NN</td></tr>
            <tr><td>Sunday &amp; Holidays</td><td>Closed</td></tr>
        </table>
    </div>

    <div class="card">
        <h3>Location</h3>
        <p class="muted">Campus Wellness Clinic, Admin Building Ground Floor (update this with your campus details).</p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
