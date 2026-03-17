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

/**
 * @return array<string, array<string, mixed>>
 */
function cars_table_columns(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME,
                DATA_TYPE,
                COLUMN_TYPE,
                IS_NULLABLE,
                COLUMN_DEFAULT,
            COLUMN_KEY,
                CHARACTER_MAXIMUM_LENGTH,
                EXTRA
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ?
         ORDER BY ORDINAL_POSITION ASC'
    );
    $stmt->execute(['cars']);

    $columns = [];

    foreach ($stmt->fetchAll() as $column) {
        $columnName = (string) ($column['COLUMN_NAME'] ?? '');

        if ($columnName !== '') {
            $columns[$columnName] = $column;
        }
    }

    return $columns;
}

function cars_enum_first_option(string $columnType): ?string
{
    if (preg_match("/^enum\('((?:[^'\\\\]|\\\\.)*)'/i", $columnType, $matches) !== 1) {
        return null;
    }

    return str_replace("\\'", "'", (string) $matches[1]);
}

/**
 * @param array<string, mixed> $columnMeta
 */
function cars_apply_column_constraints($value, array $columnMeta)
{
    if (!is_string($value)) {
        return $value;
    }

    $maxLengthRaw = $columnMeta['CHARACTER_MAXIMUM_LENGTH'] ?? null;

    if ($maxLengthRaw === null || !is_numeric((string) $maxLengthRaw)) {
        return $value;
    }

    $maxLength = (int) $maxLengthRaw;

    if ($maxLength <= 0) {
        return $value;
    }

    return substr($value, 0, $maxLength);
}

/**
 * @param array<string, mixed> $columnMeta
 * @param array<string, mixed> $sampleRow
 */
function cars_required_fallback_value(string $columnName, array $columnMeta, array $sampleRow)
{
    $columnKey = strtoupper((string) ($columnMeta['COLUMN_KEY'] ?? ''));
    $canReuseSampleValue = $columnKey !== 'PRI' && $columnKey !== 'UNI';

    if ($canReuseSampleValue && array_key_exists($columnName, $sampleRow) && $sampleRow[$columnName] !== null) {
        return cars_apply_column_constraints($sampleRow[$columnName], $columnMeta);
    }

    $dataType = strtolower((string) ($columnMeta['DATA_TYPE'] ?? ''));

    switch ($dataType) {
        case 'tinyint':
        case 'smallint':
        case 'mediumint':
        case 'int':
        case 'bigint':
            return 0;

        case 'decimal':
        case 'double':
        case 'float':
            return 0.0;

        case 'date':
            return date('Y-m-d');

        case 'datetime':
        case 'timestamp':
            return date('Y-m-d H:i:s');

        case 'time':
            return date('H:i:s');

        case 'enum':
            $firstOption = cars_enum_first_option((string) ($columnMeta['COLUMN_TYPE'] ?? ''));
            return $firstOption ?? '';

        default:
            return cars_apply_column_constraints('', $columnMeta);
    }
}

/**
 * @param array<string, mixed> $baseValues
 * @param mixed $value
 */
function cars_guess_column_value(string $columnName, array $baseValues, &$value): bool
{
    $aliases = [
        'agency_id' => 'agency_id',
        'owner_id' => 'agency_id',
        'user_id' => 'agency_id',
        'company_id' => 'agency_id',
        'vendor_id' => 'agency_id',
        'provider_id' => 'agency_id',
        'model' => 'model',
        'vehicle_model' => 'model',
        'car_model' => 'model',
        'name' => 'model',
        'vehicle_number' => 'vehicle_number',
        'number' => 'vehicle_number',
        'vehicle_no' => 'vehicle_number',
        'vehicle_num' => 'vehicle_number',
        'car_number' => 'vehicle_number',
        'number_plate' => 'vehicle_number',
        'plate_number' => 'vehicle_number',
        'registration_number' => 'vehicle_number',
        'registration_no' => 'vehicle_number',
        'seating_capacity' => 'seating_capacity',
        'capacity' => 'seating_capacity',
        'seats' => 'seating_capacity',
        'seat_capacity' => 'seating_capacity',
        'rent_per_day' => 'rent_per_day',
        'daily_rent' => 'rent_per_day',
        'price_per_day' => 'rent_per_day',
        'rent_perday' => 'rent_per_day',
        'rent' => 'rent_per_day',
        'is_available' => 'is_available',
        'available' => 'is_available',
    ];

    $normalized = strtolower($columnName);

    if (!isset($aliases[$normalized])) {
        return false;
    }

    $baseKey = $aliases[$normalized];

    if (!array_key_exists($baseKey, $baseValues)) {
        return false;
    }

    $value = $baseValues[$baseKey];
    return true;
}

/**
 * @param array<string, mixed> $baseValues
 * @return array<string, mixed>
 */
function cars_build_insert_values(PDO $pdo, array $baseValues): array
{
    $columns = cars_table_columns($pdo);

    if ($columns === []) {
        return $baseValues;
    }

    $sampleStmt = $pdo->query('SELECT * FROM cars ORDER BY id DESC LIMIT 1');
    $sampleRow = $sampleStmt !== false ? ($sampleStmt->fetch() ?: []) : [];

    $insertValues = [];

    foreach ($columns as $columnName => $columnMeta) {
        $extra = strtolower((string) ($columnMeta['EXTRA'] ?? ''));

        if (strpos($extra, 'auto_increment') !== false) {
            continue;
        }

        if (array_key_exists($columnName, $baseValues)) {
            $insertValues[$columnName] = cars_apply_column_constraints($baseValues[$columnName], $columnMeta);
            continue;
        }

        $guessedValue = null;

        if (cars_guess_column_value($columnName, $baseValues, $guessedValue)) {
            $insertValues[$columnName] = cars_apply_column_constraints($guessedValue, $columnMeta);
            continue;
        }

        $isRequired = ((string) ($columnMeta['IS_NULLABLE'] ?? 'YES')) === 'NO'
            && ($columnMeta['COLUMN_DEFAULT'] ?? null) === null;

        if (!$isRequired) {
            continue;
        }

        $insertValues[$columnName] = cars_required_fallback_value($columnName, $columnMeta, is_array($sampleRow) ? $sampleRow : []);
    }

    return $insertValues;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf('agency/cars.php');

    $carsColumns = cars_table_columns(db());

    $form['model'] = trim((string) ($_POST['model'] ?? ''));
    $form['vehicle_number'] = normalize_vehicle_number((string) ($_POST['vehicle_number'] ?? ''));
    $form['seating_capacity'] = trim((string) ($_POST['seating_capacity'] ?? ''));
    $form['rent_per_day'] = trim((string) ($_POST['rent_per_day'] ?? ''));
    $form['is_available'] = isset($_POST['is_available']) ? '1' : '0';

    $seatingCapacity = (int) $form['seating_capacity'];
    $rentPerDay = (float) $form['rent_per_day'];

    $modelMaxLength = 120;
    $vehicleNumberMaxLength = 20;

    if (isset($carsColumns['model']['CHARACTER_MAXIMUM_LENGTH']) && is_numeric((string) $carsColumns['model']['CHARACTER_MAXIMUM_LENGTH'])) {
        $modelMaxLength = (int) $carsColumns['model']['CHARACTER_MAXIMUM_LENGTH'];
    }

    if (isset($carsColumns['vehicle_number']['CHARACTER_MAXIMUM_LENGTH']) && is_numeric((string) $carsColumns['vehicle_number']['CHARACTER_MAXIMUM_LENGTH'])) {
        $vehicleNumberMaxLength = (int) $carsColumns['vehicle_number']['CHARACTER_MAXIMUM_LENGTH'];
    }

    if ($form['model'] === '') {
        $errors[] = 'Vehicle model is required.';
    } elseif ($modelMaxLength > 0 && strlen($form['model']) > $modelMaxLength) {
        $errors[] = 'Vehicle model cannot exceed ' . $modelMaxLength . ' characters for this deployment schema.';
    }

    if ($form['vehicle_number'] === '') {
        $errors[] = 'Vehicle number is required.';
    } elseif (!validate_vehicle_number($form['vehicle_number'])) {
        $errors[] = 'Vehicle number should contain letters, numbers, spaces, or dashes only.';
    } elseif ($vehicleNumberMaxLength > 0 && strlen($form['vehicle_number']) > $vehicleNumberMaxLength) {
        $errors[] = 'Vehicle number cannot exceed ' . $vehicleNumberMaxLength . ' characters for this deployment schema.';
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
        $pdo = db();
        $insertSucceeded = false;
        $baseInsertValues = [
            'agency_id' => $agencyId,
            'model' => $form['model'],
            'vehicle_number' => $form['vehicle_number'],
            'seating_capacity' => $seatingCapacity,
            'rent_per_day' => $rentPerDay,
            'is_available' => (int) $form['is_available'],
        ];

        try {
            $insertCarStmt = $pdo->prepare('INSERT INTO cars (agency_id, model, vehicle_number, seating_capacity, rent_per_day, is_available) VALUES (?, ?, ?, ?, ?, ?)');
            $insertCarStmt->execute([
                $agencyId,
                $form['model'],
                $form['vehicle_number'],
                $seatingCapacity,
                $rentPerDay,
                (int) $form['is_available'],
            ]);

            $insertSucceeded = true;
        } catch (PDOException $primaryException) {
            try {
                // Fallback path for legacy deployments with extra required columns in `cars`.
                $fallbackInsertValues = cars_build_insert_values($pdo, $baseInsertValues);

                if ($fallbackInsertValues === []) {
                    throw new RuntimeException('No insertable columns available for cars table.');
                }

                $columns = array_keys($fallbackInsertValues);
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $quotedColumns = implode(', ', array_map(static function (string $column): string {
                    return '`' . str_replace('`', '``', $column) . '`';
                }, $columns));

                $fallbackInsertStmt = $pdo->prepare('INSERT INTO cars (' . $quotedColumns . ') VALUES (' . $placeholders . ')');
                $fallbackInsertStmt->execute(array_values($fallbackInsertValues));
                $insertSucceeded = true;
            } catch (Throwable $fallbackException) {
                error_log(sprintf(
                    '[%s] Car insert failed. Primary: %s | Fallback: %s',
                    date('c'),
                    $primaryException->getMessage(),
                    $fallbackException->getMessage()
                ));

                $errors[] = 'Could not save car due to a deployment schema issue. Please try again or contact support.';
            }
        }

        if ($insertSucceeded) {
            flash('success', 'Car added to your fleet successfully.');
            redirect('agency/cars.php');
        }
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
