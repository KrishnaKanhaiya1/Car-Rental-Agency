<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_login('agency');

$agencyId = (int) (current_user()['id'] ?? 0);
$carId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($carId <= 0) {
    flash('danger', 'Invalid car selection.');
    redirect('agency/cars.php');
}

$carStmt = db()->prepare('SELECT id, model, vehicle_number, seating_capacity, rent_per_day, is_available FROM cars WHERE id = ? AND agency_id = ? LIMIT 1');
$carStmt->execute([$carId, $agencyId]);
$car = $carStmt->fetch();

if ($car === false) {
    flash('danger', 'Car not found or access denied.');
    redirect('agency/cars.php');
}

$form = [
    'model' => $car['model'],
    'vehicle_number' => $car['vehicle_number'],
    'seating_capacity' => (string) $car['seating_capacity'],
    'rent_per_day' => (string) $car['rent_per_day'],
    'is_available' => (string) $car['is_available'],
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('agency/edit_car.php?id=' . $carId);

    $form['model'] = trim((string) ($_POST['model'] ?? ''));
    $form['vehicle_number'] = normalize_vehicle_number((string) ($_POST['vehicle_number'] ?? ''));
    $form['seating_capacity'] = trim((string) ($_POST['seating_capacity'] ?? ''));
    $form['rent_per_day'] = trim((string) ($_POST['rent_per_day'] ?? ''));
    $form['is_available'] = (string) ($_POST['is_available'] ?? '1');

    $seatingCapacity = (int) $form['seating_capacity'];
    $rentPerDay = (float) $form['rent_per_day'];
    $isAvailable = $form['is_available'] === '1' ? 1 : 0;

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
        $duplicateCheckStmt = db()->prepare('SELECT id FROM cars WHERE vehicle_number = ? AND id <> ? LIMIT 1');
        $duplicateCheckStmt->execute([$form['vehicle_number'], $carId]);

        if ($duplicateCheckStmt->fetch() !== false) {
            $errors[] = 'This vehicle number is already used by another car.';
        }
    }

    if ($errors === []) {
        $updateStmt = db()->prepare('UPDATE cars SET model = ?, vehicle_number = ?, seating_capacity = ?, rent_per_day = ?, is_available = ?, updated_at = NOW() WHERE id = ? AND agency_id = ?');
        $updateStmt->execute([
            $form['model'],
            $form['vehicle_number'],
            $seatingCapacity,
            $rentPerDay,
            $isAvailable,
            $carId,
            $agencyId,
        ]);

        flash('success', 'Car details updated successfully.');
        redirect('agency/cars.php');
    }
}

$pageTitle = 'Edit Car';
$metaDescription = 'Edit vehicle details, pricing, and availability from the agency console.';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="auth-panel">
    <div class="hero-spotlight mb-4 reveal">
        <div class="hero-copy">
            <span class="eyebrow">Fleet Control</span>
            <h1 class="hero-title">Edit Car Details</h1>
            <p class="hero-subtitle mb-0">Update pricing, capacity, registration details, or availability in one place.</p>
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

            <input type="hidden" name="id" value="<?= (int) $carId ?>">

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
            <div class="mb-3">
                <label for="is_available" class="form-label">Availability</label>
                <select id="is_available" name="is_available" class="form-select">
                    <option value="1" <?= $form['is_available'] === '1' ? 'selected' : '' ?>>Available to rent</option>
                    <option value="0" <?= $form['is_available'] === '0' ? 'selected' : '' ?>>Unavailable / booked</option>
                </select>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-brand" type="submit">Update Car</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('agency/cars.php')) ?>">Back</a>
            </div>
        </form>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
