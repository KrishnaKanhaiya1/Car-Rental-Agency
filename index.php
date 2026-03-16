<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$carsStmt = db()->query(
    'SELECT cars.id,
            cars.model,
            cars.vehicle_number,
            cars.seating_capacity,
            cars.rent_per_day,
            users.name AS agency_name
     FROM cars
     INNER JOIN users ON users.id = cars.agency_id
     WHERE cars.is_available = 1
     ORDER BY cars.created_at DESC'
);
$cars = $carsStmt->fetchAll();

$totalCars = count($cars);
$minRent = null;
$maxRent = null;
$avgSeats = 0;

if ($cars !== []) {
    $rentValues = array_map(static function (array $car): float {
        return (float) $car['rent_per_day'];
    }, $cars);

    $seatValues = array_map(static function (array $car): int {
        return (int) $car['seating_capacity'];
    }, $cars);

    $minRent = min($rentValues);
    $maxRent = max($rentValues);
    $avgSeats = (int) round(array_sum($seatValues) / count($seatValues));
}

$pageTitle = 'Available Cars';
$metaDescription = 'Browse available cars and book instantly with customer role-based controls.';
require_once __DIR__ . '/includes/header.php';
?>
<section class="hero-spotlight mb-4 reveal">
    <div class="hero-copy">
        <span class="eyebrow">Live Marketplace</span>
        <h1 class="hero-title">Available Cars To Rent</h1>
        <p class="hero-subtitle mb-0">Browse real-time fleet listings from verified agencies and complete secure bookings in under 60 seconds.</p>
        <div class="hero-cta mt-3">
            <?php if (!is_logged_in()): ?>
                <a class="btn btn-brand" href="<?= e(url('login.php?next=index.php')) ?>">Login to Start Booking</a>
            <?php elseif (is_customer()): ?>
                <span class="role-note">You are signed in as customer. Booking controls are unlocked.</span>
            <?php else: ?>
                <span class="role-note">You are signed in as agency. Booking is customer-only.</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="hero-highlight">
        <div class="hero-highlight-title">Booking Rules</div>
        <ul class="hero-list mb-0">
            <li>Guests are redirected to login before booking.</li>
            <li>Only customer accounts can rent cars.</li>
            <li>Booked cars are removed from live inventory automatically.</li>
        </ul>
    </div>
</section>

<section class="kpi-grid mb-4">
    <article class="kpi-card reveal">
        <h2>Cars Available</h2>
        <strong><?= $totalCars ?></strong>
        <p>Vehicles currently open for booking.</p>
    </article>
    <article class="kpi-card reveal">
        <h2>Entry Price</h2>
        <strong><?= $minRent !== null ? e(format_currency($minRent)) : 'N/A' ?></strong>
        <p>Lowest daily rent in the current inventory.</p>
    </article>
    <article class="kpi-card reveal">
        <h2>Top Price</h2>
        <strong><?= $maxRent !== null ? e(format_currency($maxRent)) : 'N/A' ?></strong>
        <p>Highest daily rent among listed vehicles.</p>
    </article>
    <article class="kpi-card reveal">
        <h2>Avg Seating</h2>
        <strong><?= $avgSeats > 0 ? $avgSeats . ' seats' : 'N/A' ?></strong>
        <p>Average seating across live listings.</p>
    </article>
</section>

<section class="section-title mb-3">
    <div>
        <h2 class="h4 mb-1">Live Inventory</h2>
        <p class="mb-0 text-secondary">Every card below supports role-aware booking behavior.</p>
    </div>
</section>

<section>
    <?php if ($cars === []): ?>
        <div class="empty-state">No cars are available right now. Please check back soon.</div>
    <?php else: ?>
        <div class="car-grid">
            <?php foreach ($cars as $car): ?>
                <article class="car-card reveal">
                    <div class="car-card-head">
                        <div>
                            <h3 class="h5 mb-1"><?= e($car['model']) ?></h3>
                            <p class="mb-0 text-secondary small">Listed by <?= e($car['agency_name']) ?></p>
                        </div>
                        <span class="badge-soft available">Available</span>
                    </div>

                    <div class="car-specs">
                        <div>
                            <span>Vehicle Number</span>
                            <strong><?= e($car['vehicle_number']) ?></strong>
                        </div>
                        <div>
                            <span>Seating</span>
                            <strong><?= e((string) $car['seating_capacity']) ?> seats</strong>
                        </div>
                        <div>
                            <span>Rent / Day</span>
                            <strong><?= e(format_currency((float) $car['rent_per_day'])) ?></strong>
                        </div>
                    </div>

                    <div class="car-card-foot">
                        <?php if (is_customer()): ?>
                            <form method="post" action="<?= e(url('rent_car.php')) ?>" class="rent-form" data-rent-per-day="<?= e((string) $car['rent_per_day']) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="car_id" value="<?= (int) $car['id'] ?>">

                                <div class="rent-grid">
                                    <select name="days" class="form-select form-select-sm" required>
                                        <option value="">Select days</option>
                                        <?php for ($day = 1; $day <= 30; $day++): ?>
                                            <option value="<?= $day ?>"><?= $day ?> day<?= $day > 1 ? 's' : '' ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <input type="date" name="start_date" class="form-control form-control-sm" data-min-today="true" required>
                                </div>

                                <div class="rent-submit-wrap">
                                    <button type="submit" class="btn btn-brand btn-sm">Rent Car</button>
                                    <span class="rent-total" aria-live="polite"></span>
                                </div>
                            </form>
                        <?php elseif (is_agency()): ?>
                            <button class="btn btn-outline-secondary btn-sm" type="button" disabled>Agency accounts cannot book</button>
                        <?php else: ?>
                            <a class="btn btn-brand btn-sm" href="<?= e(url('login.php?next=index.php')) ?>">Login to Rent</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="glass-note mt-4 reveal">
    <h2 class="h6 mb-2">How booking works</h2>
    <p class="mb-0">Customers choose rental days and start date, then submit booking in one click. The platform creates a unique booking reference and auto-updates car availability to prevent duplicate assignments.</p>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
