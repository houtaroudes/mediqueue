</main>

<footer class="site-footer">
    <div class="container">
        <p><?php echo e(CLINIC_NAME); ?> &middot; <?php echo e(APP_NAME); ?> &copy; <?php echo date('Y'); ?></p>
        <p>Serving students, staff, and instructors of the campus.</p>
    </div>
</footer>

<script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
<?php if (!empty($GLOBALS['mq_qr_frame'])): ?>
<script src="<?php echo BASE_URL; ?>/assets/js/qrcode.min.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/qr.js"></script>
<?php endif; ?>
</body>
</html>
