<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

enforce_csrf('index.php');

if (!is_logged_in()) {
    flash('warning', 'Please log in as a customer to rent a car.');
    redirect('login.php?next=index.php');
}

if (!is_customer()) {
    flash('danger', 'Only customer accounts can book available cars.');
    redirect('index.php');
}

$carId = (int) ($_POST['car_id'] ?? 0);
$days = (int) ($_POST['days'] ?? 0);
$startDate = trim((string) ($_POST['start_date'] ?? ''));

if ($carId <= 0 || $days < 1 || $days > 30 || $startDate === '') {
    flash('danger', 'Please provide valid booking details.');
    redirect('index.php');
}

$startDateObject = DateTime::createFromFormat('Y-m-d', $startDate);
$today = new DateTime('today');

if ($startDateObject === false || $startDateObject->format('Y-m-d') !== $startDate || $startDateObject < $today) {
    flash('danger', 'Start date must be today or later.');
    redirect('index.php');
}

$pdo = db();
$pdo->beginTransaction();

try {
    $carStmt = $pdo->prepare('SELECT id, agency_id, model, rent_per_day, is_available FROM cars WHERE id = ? FOR UPDATE');
    $carStmt->execute([$carId]);
    $car = $carStmt->fetch();

    if ($car === false || (int) $car['is_available'] !== 1) {
        $pdo->rollBack();
        flash('danger', 'This car is no longer available for booking.');
        redirect('index.php');
    }

    $customerId = (int) (current_user()['id'] ?? 0);
    $totalCost = (float) $car['rent_per_day'] * $days;

    $insertBookingStmt = $pdo->prepare('INSERT INTO rentals (booking_code, car_id, customer_id, agency_id, start_date, days, total_cost, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

    $bookingCode = '';
    $isInserted = false;

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $candidateCode = generate_booking_code();

        try {
            $insertBookingStmt->execute([
                $candidateCode,
                $carId,
                $customerId,
                (int) $car['agency_id'],
                $startDate,
                $days,
                $totalCost,
                'booked',
            ]);

            $bookingCode = $candidateCode;
            $isInserted = true;
            break;
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
        }
    }

    if (!$isInserted) {
        throw new RuntimeException('Could not allocate a booking reference code.');
    }

    $updateCarStmt = $pdo->prepare('UPDATE cars SET is_available = 0, updated_at = NOW() WHERE id = ?');
    $updateCarStmt->execute([$carId]);

    $pdo->commit();

    flash('success', 'Booking confirmed for ' . $car['model'] . '. Reference: ' . $bookingCode . '.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flash('danger', 'Booking failed due to a server issue. Please try again.');
}

redirect('index.php');
