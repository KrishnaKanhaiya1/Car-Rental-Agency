<?php
declare(strict_types=1);

// Basic URL helper
function url(string $path = ''): string
{
    $normalizedPath = ltrim($path, '/');

    if (BASE_PATH === '') {
        return $normalizedPath === '' ? '/' : '/' . $normalizedPath;
    }

    return $normalizedPath === '' ? BASE_PATH : BASE_PATH . '/' . $normalizedPath;
}

function asset(string $path): string
{
    return url($path);
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function flash(string $type, string $message): void
{
    if (!isset($_SESSION['flashes'])) {
        $_SESSION['flashes'] = [];
    }

    $_SESSION['flashes'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);

    return $flashes;
}

function current_user(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_customer(): bool
{
    return is_logged_in() && (current_user()['role'] ?? '') === 'customer';
}

function is_agency(): bool
{
    return is_logged_in() && (current_user()['role'] ?? '') === 'agency';
}

function require_login(?string $role = null): void
{
    if (!is_logged_in()) {
        flash('warning', 'Please log in to continue.');
        redirect('login.php');
    }

    if ($role !== null && (current_user()['role'] ?? '') !== $role) {
        flash('danger', 'You are not authorized to access that page.');
        redirect('index.php');
    }
}

function clean_next_path(?string $next): ?string
{
    if ($next === null) {
        return null;
    }

    $next = trim($next);

    if ($next === '' || strpos($next, '://') !== false || strpos($next, '//') === 0) {
        return null;
    }

    if (!preg_match('/^[a-zA-Z0-9_\/.?=&%-]+$/', $next)) {
        return null;
    }

    return ltrim($next, '/');
}

function format_currency(float $amount): string
{
    return 'Rs ' . number_format($amount, 2);
}

function normalize_vehicle_number(string $value): string
{
    $value = strtoupper(trim($value));
    $value = preg_replace('/\s+/', ' ', $value);

    return $value ?? '';
}

function validate_vehicle_number(string $value): bool
{
    return preg_match('/^[A-Z0-9\- ]{4,20}$/', $value) === 1;
}

function validate_phone(string $value): bool
{
    return preg_match('/^[0-9+\-()\s]{7,20}$/', $value) === 1;
}

function csrf_token(): string
{
    $token = $_SESSION['csrf_token'] ?? null;
    $expiresAt = $_SESSION['csrf_expires_at'] ?? 0;

    if (!is_string($token) || $token === '' || !is_int($expiresAt) || $expiresAt < time()) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_expires_at'] = time() + 7200;
    }

    return $token;
}

function verify_csrf_token(?string $token): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? null;

    return is_string($token)
        && is_string($sessionToken)
        && $token !== ''
        && hash_equals($sessionToken, $token);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function enforce_csrf(string $fallbackPath = 'index.php'): void
{
    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        flash('danger', 'The form session expired. Please try again.');
        redirect($fallbackPath);
    }
}

function login_rate_limited(): bool
{
    $attempts = $_SESSION['failed_login_attempts'] ?? [];

    if (!is_array($attempts)) {
        $attempts = [];
    }

    $windowStart = time() - 600;
    $filteredAttempts = [];

    foreach ($attempts as $timestamp) {
        if (is_int($timestamp) && $timestamp >= $windowStart) {
            $filteredAttempts[] = $timestamp;
        }
    }

    $_SESSION['failed_login_attempts'] = $filteredAttempts;
    return count($filteredAttempts) >= 5;
}

function register_failed_login_attempt(): void
{
    $attempts = $_SESSION['failed_login_attempts'] ?? [];

    if (!is_array($attempts)) {
        $attempts = [];
    }

    $attempts[] = time();
    $_SESSION['failed_login_attempts'] = $attempts;
}

function clear_failed_login_attempts(): void
{
    unset($_SESSION['failed_login_attempts']);
}

function generate_booking_code(): string
{
    return 'CA' . strtoupper(bin2hex(random_bytes(5)));
}

function redirect_for_role(array $user): void
{
    if (($user['role'] ?? '') === 'agency') {
        redirect('agency/cars.php');
    }

    redirect('index.php');
}
