<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? APP_NAME;
$metaDescription = $metaDescription ?? 'Simple car rental platform for customers and agencies.';
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$user = current_user();
$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="theme-color" content="#0d2a24">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="<?= e(asset('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
    <a class="skip-link" href="#mainContent">Skip to main content</a>
    <div class="noise-layer"></div>
    <div class="bg-shape bg-shape-1"></div>
    <div class="bg-shape bg-shape-2"></div>

    <nav class="navbar navbar-expand-lg sticky-top app-navbar">
        <div class="container nav-shell">
            <a class="navbar-brand" href="<?= e(url('index.php')) ?>">
                <span class="brand-mark">CA</span>
                <span class="brand-copy">
                    <strong>Car Rental Agency</strong>
                </span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= $currentScript === 'index.php' ? 'active' : '' ?>" href="<?= e(url('index.php')) ?>">Available Cars</a>
                    </li>
                    <?php if (is_agency()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentScript === 'cars.php' || $currentScript === 'edit_car.php' ? 'active' : '' ?>" href="<?= e(url('agency/cars.php')) ?>">Manage Cars</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentScript === 'bookings.php' ? 'active' : '' ?>" href="<?= e(url('agency/bookings.php')) ?>">Booked Cars</a>
                        </li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex align-items-center gap-2">
                    <?php if ($user === null): ?>
                        <a class="btn btn-light btn-sm nav-action" href="<?= e(url('register_customer.php')) ?>">Customer Signup</a>
                        <a class="btn btn-light btn-sm nav-action" href="<?= e(url('register_agency.php')) ?>">Agency Signup</a>
                        <a class="btn btn-brand btn-sm nav-action" href="<?= e(url('login.php')) ?>">Login</a>
                    <?php else: ?>
                        <span class="user-chip"><?= e($user['name']) ?> <small>(<?= e(ucfirst($user['role'])) ?>)</small></span>
                        <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline-dark btn-sm nav-action" type="submit">Logout</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-4 py-md-5" id="mainContent">
        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
