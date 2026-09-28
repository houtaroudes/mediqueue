<?php
// MediQueue landing page (Phase 1)
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/board.php';

// live queue status for the landing board (page must survive with the DB down)
$board = mq_board_status();

$page_title = 'Campus Clinic Appointments and Queueing';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="container hero-grid">
        <div>
            <h1>The clinic line, on your screen.</h1>
            <p class="hero-sub">
                Book a slot at the <?php echo e(CLINIC_NAME); ?> or walk in and take a number -
                then watch the board instead of the door.
            </p>
            <div class="hero-actions">
                <a href="<?php echo BASE_URL; ?>/user/book-appointment.php" class="btn btn-primary">Book an Appointment</a>
                <a href="<?php echo BASE_URL; ?>/user/queue.php" class="btn btn-outline">Join Walk-in Queue</a>
            </div>
            <p class="hero-note">New here? <a href="<?php echo BASE_URL; ?>/register.php">Create an account</a> - it takes a minute.</p>
        </div>

        <div class="board" data-board data-feed="<?php echo e(BASE_URL); ?>/queue-status.php"
             data-number="<?php echo e((string) $board['number']); ?>"
             data-status="<?php echo e((string) $board['status']); ?>"
             aria-label="Clinic queue status">
            <div class="board-head">
                <span class="board-dot"></span>
                <span>Now Serving</span>
                <span class="board-time"><?php echo e(date('g:i A')); ?></span>
            </div>
            <div class="board-main" role="status">
                <?php if ($board['number']): ?>
                    <div class="board-number"><?php echo e($board['number']); ?></div>
                    <p class="board-status">
                        <?php echo $board['status'] === 'in_consultation' ? 'In consultation' : 'Called to the consultation room'; ?>
                        <?php if ($board['waiting'] > 0): ?> &middot; <?php echo (int) $board['waiting']; ?> waiting<?php endif; ?>
                    </p>
                <?php else: ?>
                    <div class="board-number board-idle">--:--</div>
                    <p class="board-status">No one is being served right now.</p>
                <?php endif; ?>
            </div>
            <?php if ($board['next']): ?>
                <div class="board-next">
                    <span class="board-next-label">Next in line</span>
                    <span class="board-next-item"><?php echo e(implode('  ', $board['next'])); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="container features">
    <div class="card feature-step">
        <span class="step-num" aria-hidden="true">1</span>
        <h3>Book a slot</h3>
        <p>Pick a service, choose an available time, and get instant confirmation.</p>
    </div>
    <div class="card feature-step">
        <span class="step-num" aria-hidden="true">2</span>
        <h3>Take a number</h3>
        <p>Walk-ins get a queue ticket like A001 and watch their status live.</p>
    </div>
    <div class="card feature-step">
        <span class="step-num" aria-hidden="true">3</span>
        <h3>Step in when called</h3>
        <p>The board calls your number - no standing in line, no missing your turn.</p>
    </div>
</section>

<section class="container hours">
    <div class="card hours-card">
        <h3>Clinic Hours</h3>
        <table class="hours-table">
            <tr><td>Monday - Friday</td><td>8:00 AM - 5:00 PM</td></tr>
            <tr><td>Saturday</td><td>8:00 AM - 12:00 NN</td></tr>
            <tr><td>Sunday &amp; Holidays</td><td>Closed</td></tr>
        </table>
    </div>
    <div class="card facts-card">
        <h3>Good to know</h3>
        <ul class="fact-list">
            <li>Walk-in numbers are issued in order of arrival.</li>
            <li>Instructors get suggested slots that don't clash with classes.</li>
            <li>Your visit history stays private in your account.</li>
        </ul>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
