<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_login('agency');

$agencyId = (int) (current_user()['id'] ?? 0);
$form = [
    'model' => '',
    'vehicle_number' => '',
    'seating_capacity' => '',
    'rent_per_day' => '',
    'is_available' => '1',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('agency/cars.php');

    $form['model'] = trim((string) ($_POST['model'] ?? ''));
    $form['vehicle_number'] = normalize_vehicle_number((string) ($_POST['vehicle_number'] ?? ''));
    $form['seating_capacity'] = trim((string) ($_POST['seating_capacity'] ?? ''));
    $form['rent_per_day'] = trim((string) ($_POST['rent_per_day'] ?? ''));
    $form['is_available'] = isset($_POST['is_available']) ? '1' : '0';

    $seatingCapacity = (int) $form['seating_capacity'];
    $rentPerDay = (float) $form['rent_per_day'];

    if ($form['model'] === '') {
        $errors[] = 'Vehicle model is required.';
    }

    if ($form['vehicle_number'] === '') {
        $errors[] = 'Vehicle number is required.';
    } elseif (!validate_vehicle_number($form['vehicle_number'])) {
        $errors[] = 'Vehicle number should contain letters, numbers, spaces, or dashes only.';
    }

    if ($seatingCapacity < 1 || $seatingCapacity > 20) {
        $errors[] = 'Seating capacity must be between 1 and 20.';
    }

    if ($rentPerDay <= 0 || $rentPerDay > 100000) {
        $errors[] = 'Rent per day must be greater than 0.';
    }

    if ($errors === []) {
        $duplicateCheckStmt = db()->prepare('SELECT id FROM cars WHERE vehicle_number = ? LIMIT 1');
        $duplicateCheckStmt->execute([$form['vehicle_number']]);

        if ($duplicateCheckStmt->fetch() !== false) {
            $errors[] = 'This vehicle number is already used in the system.';
        }
    }

    if ($errors === []) {
        $insertCarStmt = db()->prepare('INSERT INTO cars (agency_id, model, vehicle_number, seating_capacity, rent_per_day, is_available) VALUES (?, ?, ?, ?, ?, ?)');
        $insertCarStmt->execute([
            $agencyId,
            $form['model'],
            $form['vehicle_number'],
            $seatingCapacity,
            $rentPerDay,
            (int) $form['is_available'],
        ]);

        flash('success', 'Car added to your fleet successfully.');
        redirect('agency/cars.php');
    }
}

$carsStmt = db()->prepare('SELECT id, model, vehicle_number, seating_capacity, rent_per_day, is_available, created_at FROM cars WHERE agency_id = ? ORDER BY created_at DESC');
$carsStmt->execute([$agencyId]);
$cars = $carsStmt->fetchAll();

$totalCars = count($cars);
$availableCars = 0;
$unavailableCars = 0;
$averageRent = 0.0;

if ($cars !== []) {
    $rentValues = [];

    foreach ($cars as $carItem) {
        $rentValues[] = (float) $carItem['rent_per_day'];

        if ((int) $carItem['is_available'] === 1) {
            $availableCars++;
        } else {
            $unavailableCars++;
        }
    }

    $averageRent = array_sum($rentValues) / count($rentValues);
}

$pageTitle = 'Manage Cars';
$metaDescription = 'Agency fleet dashboard to add, edit, and monitor vehicle availability.';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="hero-spotlight mb-4 reveal">
    <div class="hero-copy">
        <span class="eyebrow">Agency Console</span>
        <h1 class="hero-title">Fleet Management</h1>
        <p class="hero-subtitle mb-0">Add vehicles, adjust pricing, and keep availability up to date with a single dashboard.</p>
    </div>
    <div class="hero-highlight">
        <div class="hero-highlight-title">Ops Checklist</div>
        <ul class="hero-list mb-0">
            <li>Use accurate vehicle numbers to avoid duplicates.</li>
            <li>Switch availability after handover completion.</li>
            <li>Keep daily rent aligned with market demand.</li>
        </ul>
    </div>
</section>

<section class="kpi-grid mb-4">
    <article class="kpi-card reveal">
        <h2>Total Cars</h2>
        <strong><?= $totalCars ?></strong>
        <p>Total vehicles in your fleet inventory.</p>
    </article>
    <article class="kpi-card reveal">
        <h2>Available</h2>
        <strong><?= $availableCars ?></strong>
        <p>Cars open for immediate booking.</p>
    </article>
    <article class="kpi-card reveal">
        <h2>Unavailable</h2>
        <strong><?= $unavailableCars ?></strong>
        <p>Cars currently not visible for customers.</p>
    </article>
    <article class="kpi-card reveal">
        <h2>Avg Daily Rent</h2>
        <strong><?= $totalCars > 0 ? e(format_currency($averageRent)) : 'N/A' ?></strong>
        <p>Average pricing across your listed vehicles.</p>
    </article>
</section>

<div class="panel-grid">
    <section class="card card-soft p-4 reveal">
        <h2 class="h5 mb-3">Add New Car</h2>

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
                <label for="model" class="form-label">Vehicle Model</label>
                <input id="model" name="model" type="text" class="form-control" value="<?= e($form['model']) ?>" maxlength="120" required>
            </div>
            <div class="mb-3">
                <label for="vehicle_number" class="form-label">Vehicle Number</label>
                <input id="vehicle_number" name="vehicle_number" type="text" class="form-control" value="<?= e($form['vehicle_number']) ?>" pattern="[A-Z0-9\- ]{4,20}" maxlength="20" required>
            </div>
            <div class="mb-3">
                <label for="seating_capacity" class="form-label">Seating Capacity</label>
                <input id="seating_capacity" name="seating_capacity" type="number" min="1" max="20" class="form-control" value="<?= e($form['seating_capacity']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="rent_per_day" class="form-label">Rent Per Day</label>
                <input id="rent_per_day" name="rent_per_day" type="number" min="1" step="0.01" class="form-control" value="<?= e($form['rent_per_day']) ?>" required>
            </div>
            <div class="form-check mb-3">
                <input id="is_available" name="is_available" class="form-check-input" type="checkbox" <?= $form['is_available'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_available">Mark as available for booking</label>
            </div>
            <button class="btn btn-brand w-100" type="submit">Save Car</button>
        </form>
    </section>

    <section class="card card-soft p-4 reveal">
        <div class="section-title mb-3">
            <h2 class="h5 mb-0">Your Listed Cars</h2>
            <span class="text-secondary small"><?= count($cars) ?> total</span>
        </div>

        <?php if ($cars === []): ?>
            <div class="empty-state">You have not added any cars yet.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>Vehicle Number</th>
                            <th>Seats</th>
                            <th>Rent / Day</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cars as $car): ?>
                            <tr>
                                <td><?= e($car['model']) ?></td>
                                <td><?= e($car['vehicle_number']) ?></td>
                                <td><?= e((string) $car['seating_capacity']) ?></td>
                                <td><?= e(format_currency((float) $car['rent_per_day'])) ?></td>
                                <td>
                                    <span class="badge-soft <?= (int) $car['is_available'] === 1 ? 'available' : 'booked' ?>">
                                        <?= (int) $car['is_available'] === 1 ? 'Available' : 'Booked/Hidden' ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('agency/edit_car.php?id=' . (int) $car['id'])) ?>">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
