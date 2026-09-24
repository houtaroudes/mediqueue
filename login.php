<?php
// Public login (Phase 3)
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(dashboard_url(current_user()['role']));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $db = get_db_connection();
        $stmt = $db->prepare('SELECT id, first_name, last_name, email, password, role, status FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = 'This account is deactivated. Please contact the clinic admin.';
            } else {
                // keep only safe fields in the session
                $_SESSION['user'] = array(
                    'id'         => (int) $user['id'],
                    'first_name' => $user['first_name'],
                    'last_name'  => $user['last_name'],
                    'email'      => $user['email'],
                    'role'       => $user['role'],
                );
                session_regenerate_id(true); // stop session fixation

                log_activity('auth.login', 'role=' . $user['role']);
                flash_set('success', 'Welcome back, ' . $user['first_name'] . '! You are now logged in.');
                redirect(dashboard_url($user['role']));
            }
        } else {
            $error = 'Invalid email or password.'; // same msg for both = no hints
        }
    }
}

$page_title = 'Login';
require __DIR__ . '/includes/header.php';

$f = flash_get();
if ($f): ?>
    <div class="alert alert-<?php echo e($f['type']); ?>"><?php echo e($f['message']); ?></div>
<?php endif; ?>

<section class="container page narrow">
    <h1>Welcome back</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="post" class="card form js-submit">
        <label>Email
            <input type="email" name="email" required value="<?php echo e($email); ?>" autofocus>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn btn-primary btn-block js-btn">Login</button>
        <p class="muted">No account yet? <a href="<?php echo BASE_URL; ?>/register.php">Register here</a></p>
    </form>

    </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
