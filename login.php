<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect_for_role(current_user() ?? []);
}

$email = '';
$errors = [];
$nextPath = clean_next_path((string) ($_GET['next'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('login.php');

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $nextPath = clean_next_path((string) ($_POST['next'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if ($errors === [] && login_rate_limited()) {
        $errors[] = 'Too many failed attempts. Please wait 10 minutes and try again.';
    }

    if ($errors === []) {
        $stmt = db()->prepare('SELECT id, role, name, email, password_hash FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user === false || !password_verify($password, $user['password_hash'])) {
            register_failed_login_attempt();
            $errors[] = 'Invalid credentials. Please try again.';
        } else {
            session_regenerate_id(true);
            clear_failed_login_attempts();

            $_SESSION['auth_user'] = [
                'id' => (int) $user['id'],
                'role' => $user['role'],
                'name' => $user['name'],
                'email' => $user['email'],
            ];

            flash('success', 'Welcome back, ' . $user['name'] . '.');

            if ($nextPath !== null) {
                redirect($nextPath);
            }

            redirect_for_role($_SESSION['auth_user']);
        }
    }
}

$pageTitle = 'Login';
$metaDescription = 'Sign in to customer or agency account with secure session controls.';
require_once __DIR__ . '/includes/header.php';
?>
<section class="auth-panel">
    <div class="hero-spotlight mb-4 reveal">
        <div class="hero-copy">
            <span class="eyebrow">Secure Access</span>
            <h1 class="hero-title">Login</h1>
            <p class="hero-subtitle mb-0">Use your customer or agency account to continue.</p>
        </div>
    </div>

    <div class="card card-soft p-4 reveal">
        <?php if ($errors !== []): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>

            <?php if ($nextPath !== null): ?>
                <input type="hidden" name="next" value="<?= e($nextPath) ?>">
            <?php endif; ?>

            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input id="email" name="email" type="email" class="form-control" value="<?= e($email) ?>" autocomplete="username" maxlength="190" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input id="password" name="password" type="password" class="form-control" autocomplete="current-password" required>
            </div>
            <button class="btn btn-brand w-100" type="submit">Sign In</button>
        </form>

        <hr>

        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-between">
            <a href="<?= e(url('register_customer.php')) ?>">Create customer account</a>
            <a href="<?= e(url('register_agency.php')) ?>">Create agency account</a>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
