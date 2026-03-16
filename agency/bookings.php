<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_login('agency');

$agencyId = (int) (current_user()['id'] ?? 0);

$bookingsStmt = db()->prepare(
    'SELECT rentals.id,
            rentals.booking_code,
            rentals.start_date,
            rentals.days,
            rentals.total_cost,
            rentals.status,
            rentals.created_at,
            cars.model,
            cars.vehicle_number,
            customers.name AS customer_name,
            customers.email AS customer_email,
            customers.phone AS customer_phone
     FROM rentals
     INNER JOIN cars ON cars.id = rentals.car_id
     INNER JOIN users AS customers ON customers.id = rentals.customer_id
     WHERE rentals.agency_id = ?
     ORDER BY rentals.created_at DESC'
);
$bookingsStmt->execute([$agencyId]);
$bookings = $bookingsStmt->fetchAll();

$bookingCount = count($bookings);
$bookedCount = 0;
$completedCount = 0;
$totalRevenue = 0.0;

foreach ($bookings as $bookingRow) {
    $totalRevenue += (float) $bookingRow['total_cost'];

    if ($bookingRow['status'] === 'booked') {
        $bookedCount++;
    }

    if ($bookingRow['status'] === 'completed') {
        $completedCount++;
    }
}

$pageTitle = 'Booked Cars';
$metaDescription = 'Agency view of customer bookings and rental revenue.';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="hero-spotlight mb-4 reveal">
    <div class="hero-copy">
        <span class="eyebrow">Agency Analytics</span>
        <h1 class="hero-title">Booked Cars</h1>
        <p class="hero-subtitle mb-0">Track all customer reservations for vehicles listed by your agency.</p>
    </div>
</section>

<section class="kpi-grid mb-4">
    <article class="kpi-card reveal">
        <h2>Total Bookings</h2>
        <strong><?= $bookingCount ?></strong>
        <p>All reservations captured for your fleet.</p>
    </article>
    <article class="kpi-card reveal">
        <h2>Active</h2>
        <strong><?= $bookedCount ?></strong>
        <p>Bookings currently marked as booked.</p>
    </article>
    <article class="kpi-card reveal">
        <h2>Completed</h2>
        <strong><?= $completedCount ?></strong>
        <p>Trips completed by your customers.</p>
    </article>
    <article class="kpi-card reveal">
        <h2>Total Revenue</h2>
        <strong><?= $bookingCount > 0 ? e(format_currency($totalRevenue)) : 'N/A' ?></strong>
        <p>Cumulative value from captured bookings.</p>
    </article>
</section>

<section class="section-title">
    <div>
        <h2 class="h4 mb-1">Booking Records</h2>
        <p class="mb-0 text-secondary">Customers who booked cars listed by your agency.</p>
    </div>
</section>

<section class="card card-soft p-4 reveal">
    <?php if ($bookings === []): ?>
        <div class="empty-state">No bookings yet. Customer bookings will appear here.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Car</th>
                        <th>Vehicle No.</th>
                        <th>Customer</th>
                        <th>Start Date</th>
                        <th>Days</th>
                        <th>Total Cost</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><span class="badge-soft available"><?= e($booking['booking_code']) ?></span></td>
                            <td><?= e($booking['model']) ?></td>
                            <td><?= e($booking['vehicle_number']) ?></td>
                            <td>
                                <strong><?= e($booking['customer_name']) ?></strong><br>
                                <small class="text-secondary"><?= e($booking['customer_email']) ?> | <?= e($booking['customer_phone']) ?></small>
                            </td>
                            <td><?= e($booking['start_date']) ?></td>
                            <td><?= e((string) $booking['days']) ?></td>
                            <td><?= e(format_currency((float) $booking['total_cost'])) ?></td>
                            <td>
                                <span class="badge-soft <?= $booking['status'] === 'booked' ? 'available' : 'booked' ?>">
                                    <?= e(ucfirst($booking['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($booking['status'] === 'booked'): ?>
                                    <form method="post" action="<?= e(url('agency/update_booking_status.php')) ?>" class="m-0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" class="btn btn-sm btn-outline-success">Mark Completed</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-secondary small">No action</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
