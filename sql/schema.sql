CREATE DATABASE IF NOT EXISTS car_rental_agency
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE car_rental_agency;

DROP TABLE IF EXISTS rentals;
DROP TABLE IF EXISTS cars;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('customer', 'agency') NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(30) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_users_role (role)
) ENGINE=InnoDB;

CREATE TABLE cars (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_id INT UNSIGNED NOT NULL,
    model VARCHAR(120) NOT NULL,
    vehicle_number VARCHAR(50) NOT NULL UNIQUE,
    seating_capacity TINYINT UNSIGNED NOT NULL,
    rent_per_day DECIMAL(10, 2) NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT chk_cars_seating_capacity CHECK (seating_capacity >= 1 AND seating_capacity <= 20),
    CONSTRAINT chk_cars_rent_per_day CHECK (rent_per_day > 0),
    CONSTRAINT fk_cars_agency
        FOREIGN KEY (agency_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    INDEX idx_cars_agency_id (agency_id),
    INDEX idx_cars_is_available (is_available)
) ENGINE=InnoDB;

CREATE TABLE rentals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_code VARCHAR(20) NOT NULL UNIQUE,
    car_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    agency_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    days TINYINT UNSIGNED NOT NULL,
    total_cost DECIMAL(12, 2) NOT NULL,
    status ENUM('booked', 'completed', 'cancelled') NOT NULL DEFAULT 'booked',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_rentals_days CHECK (days >= 1 AND days <= 30),
    CONSTRAINT chk_rentals_total_cost CHECK (total_cost > 0),
    CONSTRAINT fk_rentals_car
        FOREIGN KEY (car_id) REFERENCES cars (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_rentals_customer
        FOREIGN KEY (customer_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_rentals_agency
        FOREIGN KEY (agency_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    INDEX idx_rentals_car_id (car_id),
    INDEX idx_rentals_customer_id (customer_id),
    INDEX idx_rentals_agency_id (agency_id),
    INDEX idx_rentals_status_start_date (status, start_date)
) ENGINE=InnoDB;
