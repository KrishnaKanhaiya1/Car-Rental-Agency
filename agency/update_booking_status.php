<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_login('agency');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('agency/bookings.php');
}

enforce_csrf('agency/bookings.php');

$agencyId = (int) (current_user()['id'] ?? 0);
$bookingId = (int) ($_POST['booking_id'] ?? 0);
$nextStatus = trim((string) ($_POST['status'] ?? ''));

if ($bookingId <= 0 || $nextStatus !== 'completed') {
    flash('danger', 'Invalid booking update request.');
    redirect('agency/bookings.php');
}

$pdo = db();
$pdo->beginTransaction();

try {
    $bookingStmt = $pdo->prepare(
        'SELECT rentals.id, rentals.car_id, rentals.status
         FROM rentals
         WHERE rentals.id = ? AND rentals.agency_id = ?
         FOR UPDATE'
    );
    $bookingStmt->execute([$bookingId, $agencyId]);
    $booking = $bookingStmt->fetch();

    if ($booking === false) {
        $pdo->rollBack();
        flash('danger', 'Booking not found or access denied.');
        redirect('agency/bookings.php');
    }

    if ($booking['status'] !== 'booked') {
        $pdo->rollBack();
        flash('warning', 'Only active bookings can be marked as completed.');
        redirect('agency/bookings.php');
    }

    $updateBookingStmt = $pdo->prepare('UPDATE rentals SET status = ? WHERE id = ? AND agency_id = ?');
    $updateBookingStmt->execute([
        'completed',
        $bookingId,
        $agencyId,
    ]);

    $releaseCarStmt = $pdo->prepare('UPDATE cars SET is_available = 1, updated_at = NOW() WHERE id = ? AND agency_id = ?');
    $releaseCarStmt->execute([
        (int) $booking['car_id'],
        $agencyId,
    ]);

    $pdo->commit();
    flash('success', 'Booking marked completed and car is now available for new bookings.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flash('danger', 'Could not update booking status. Please try again.');
}

redirect('agency/bookings.php');
