# Car Rental Agency

A simple car rental platform built with Core PHP and MySQL. It features separate dashboards for customers and agencies.

## Features

- Separate signup for customers and car rental agencies.
- Real-time car inventory listing.
- Agencies can add and manage their cars.
- Customers can book available cars for a specific duration.
- View booked cars and customer contact details.
- Secure login and registration.

## Tech Stack

- **PHP** (v7.4+)
- **MySQL**
- **Bootstrap 5** (for UI)

## Installation

1. Import `sql/schema.sql` into your MySQL database.
2. Configure your database settings in `config/database.php`.
3. Set the project folder in your web server (XAMPP/WAMP htdocs).
4. Browse to the project URL (e.g., `http://localhost/Car Rental Agency`).

## How it works

### For Agencies
- Log in to your agency account.
- Add vehicles with model, number, seating, and rent per day.
- View list of customers who booked your cars.

### For Customers
- Browse available cars on the home page.
- Log in to book a vehicle by selecting the number of days and start date.
- Booking confirmation displays a unique reference code.
