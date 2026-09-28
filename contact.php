<?php
// Public contact page (Phase 15)
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Contact';
require __DIR__ . '/includes/header.php';
?>

<section class="container page narrow">
    <h1>Contact the clinic</h1>

    <div class="card">
        <h3><?php echo e(CLINIC_NAME); ?></h3>
        <p><strong>Phone:</strong> (046) 000-0000 <span class="muted">(placeholder - update with real number)</span></p>
        <p><strong>Email:</strong> clinic@campus.edu <span class="muted">(placeholder)</span></p>
        <p><strong>Location:</strong> Admin Building Ground Floor</p>
    </div>

    <div class="card">
        <h3>Emergencies</h3>
        <p>For life-threatening emergencies, call campus security or 911 immediately.
            The clinic walk-in queue is for non-emergency, same-day concerns.</p>
    </div>

    <div class="card">
        <h3>Account help</h3>
        <p>Forgot your password or can't log in? Visit the clinic front desk with your student
            or employee ID - staff can verify and reset your access.</p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
