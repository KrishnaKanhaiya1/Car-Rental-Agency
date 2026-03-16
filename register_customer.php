<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect_for_role(current_user() ?? []);
}

$form = [
    'name' => '',
    'email' => '',
    'phone' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('register_customer.php');

    $form['name'] = trim((string) ($_POST['name'] ?? ''));
    $form['email'] = trim((string) ($_POST['email'] ?? ''));
    $form['phone'] = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($form['name'] === '') {
        $errors[] = 'Full name is required.';
    }

    if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if ($form['phone'] === '' || !validate_phone($form['phone'])) {
        $errors[] = 'Please enter a valid phone number.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password must include at least one letter and one number.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password and confirm password do not match.';
    }

    if ($errors === []) {
        $checkEmailStmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $checkEmailStmt->execute([$form['email']]);

        if ($checkEmailStmt->fetch() !== false) {
            $errors[] = 'This email is already registered. Try logging in instead.';
        }
    }

    if ($errors === []) {
        $insertStmt = db()->prepare('INSERT INTO users (role, name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)');
        $insertStmt->execute([
            'customer',
            $form['name'],
            $form['email'],
            $form['phone'],
            password_hash($password, PASSWORD_DEFAULT),
        ]);

        flash('success', 'Customer account created successfully. Please log in.');
        redirect('login.php');
    }
}

$pageTitle = 'Customer Registration';
$metaDescription = 'Register as a customer to browse and rent available cars with secure booking flow.';
require_once __DIR__ . '/includes/header.php';
?>
<section class="auth-panel">
    <div class="hero-spotlight mb-4 reveal">
        <div class="hero-copy">
            <span class="eyebrow">Customer Onboarding</span>
            <h1 class="hero-title">Create Customer Account</h1>
            <p class="hero-subtitle mb-0">Set up your profile and start booking verified cars in just a few clicks.</p>
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

            <div class="mb-3">
                <label for="name" class="form-label">Full Name</label>
                <input id="name" name="name" type="text" class="form-control" value="<?= e($form['name']) ?>" autocomplete="name" maxlength="120" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input id="email" name="email" type="email" class="form-control" value="<?= e($form['email']) ?>" autocomplete="email" maxlength="190" required>
            </div>
            <div class="mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <input id="phone" name="phone" type="tel" class="form-control" value="<?= e($form['phone']) ?>" pattern="[0-9+\-()\s]{7,20}" autocomplete="tel" maxlength="20" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input id="password" name="password" type="password" class="form-control" autocomplete="new-password" required>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input id="confirm_password" name="confirm_password" type="password" class="form-control" autocomplete="new-password" required>
            </div>
            <button class="btn btn-brand w-100" type="submit">Create Customer Account</button>
        </form>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
