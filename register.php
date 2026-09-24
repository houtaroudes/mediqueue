<?php
// Public registration (Phase 3)
require_once __DIR__ . '/includes/auth.php';

// already logged in? go to your dashboard
if (is_logged_in()) {
    redirect(dashboard_url(current_user()['role']));
}

$errors = array();
$old = array('first_name' => '', 'last_name' => '', 'email' => '', 'role' => 'student', 'id_number' => '', 'phone' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $v) {
        $old[$k] = trim($_POST[$k] ?? '');
    }
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // server-side validation
    if ($old['first_name'] === '' || mb_strlen($old['first_name']) > 50) $errors[] = 'First name is required (max 50 chars).';
    if ($old['last_name'] === '' || mb_strlen($old['last_name']) > 50)  $errors[] = 'Last name is required (max 50 chars).';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 120) $errors[] = 'A valid email is required.';
    if (!in_array($old['role'], array('student', 'staff', 'instructor'), true)) $errors[] = 'Choose a valid account type.';
    if (strlen($password) < 8)  $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $password2) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $db = get_db_connection();

        // email must be unique
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->bind_param('s', $old['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'That email is already registered.';
        }
        $stmt->close();

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (first_name, last_name, email, password, role, id_number, phone) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('sssssss', $old['first_name'], $old['last_name'], $old['email'], $hash, $old['role'], $old['id_number'], $old['phone']);
            $stmt->execute();
            $newUserId = $stmt->insert_id;
            $stmt->close();

            // students get a patient record so they can book right away
            if ($old['role'] === 'student') {
                $stmt = $db->prepare('INSERT INTO patients (user_id, first_name, last_name) VALUES (?, ?, ?)');
                $stmt->bind_param('iss', $newUserId, $old['first_name'], $old['last_name']);
                $stmt->execute();
                $stmt->close();
            }

            log_activity('user.register', $old['email'], $newUserId);
            flash_set('success', 'Account created! You can now log in.');
            redirect(BASE_URL . '/login.php');
        }
    }
}

$page_title = 'Create Account';
require __DIR__ . '/includes/header.php';
?>

<section class="container page narrow">
    <h1>Create your account</h1>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?php echo e($err); ?></div>
    <?php endforeach; ?>

    <form method="post" class="card form js-submit" novalidate>
        <div class="form-row">
            <label>First Name
                <input type="text" name="first_name" maxlength="50" required value="<?php echo e($old['first_name']); ?>">
            </label>
            <label>Last Name
                <input type="text" name="last_name" maxlength="50" required value="<?php echo e($old['last_name']); ?>">
            </label>
        </div>

        <label>Email
            <input type="email" name="email" maxlength="120" required value="<?php echo e($old['email']); ?>">
        </label>

        <label>Account Type
            <select name="role">
                <option value="student"    <?php echo $old['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                <option value="staff"      <?php echo $old['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                <option value="instructor" <?php echo $old['role'] === 'instructor' ? 'selected' : ''; ?>>Instructor</option>
            </select>
        </label>

        <div class="form-row">
            <label>ID Number <span class="muted">(student/employee no.)</span>
                <input type="text" name="id_number" maxlength="40" value="<?php echo e($old['id_number']); ?>">
            </label>
            <label>Phone
                <input type="tel" name="phone" maxlength="20" value="<?php echo e($old['phone']); ?>">
            </label>
        </div>

        <div class="form-row">
            <label>Password <span class="muted">(min 8 chars)</span>
                <input type="password" name="password" minlength="8" required>
            </label>
            <label>Confirm Password
                <input type="password" name="password2" minlength="8" required>
            </label>
        </div>

        <button type="submit" class="btn btn-primary btn-block js-btn">Register</button>
        <p class="muted">Already have an account? <a href="<?php echo BASE_URL; ?>/login.php">Log in</a></p>
    </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
