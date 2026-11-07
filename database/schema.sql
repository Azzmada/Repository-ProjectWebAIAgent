-- ============================================================
--  TerbangLebih — Database schema (MySQL 8.0+ / MariaDB 10.4+)
--  Import order: schema.sql then seed.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `terbanglebih`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `terbanglebih`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `promo_usage`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `booking_passengers`;
DROP TABLE IF EXISTS `booking_flights`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `flights`;
DROP TABLE IF EXISTS `airlines`;
DROP TABLE IF EXISTS `cities`;
DROP TABLE IF EXISTS `promos`;
DROP TABLE IF EXISTS `faqs`;
DROP TABLE IF EXISTS `testimonials`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Users
-- ------------------------------------------------------------
CREATE TABLE `users` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL,
    `email`      VARCHAR(150) NOT NULL,
    `password`   VARCHAR(255) NOT NULL,
    `phone`      VARCHAR(30)  NOT NULL DEFAULT '',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Admins (separate login)
-- ------------------------------------------------------------
CREATE TABLE `admins` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL,
    `email`      VARCHAR(150) NOT NULL,
    `password`   VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `admins_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Password reset tokens (token stored as SHA-256 hash)
-- ------------------------------------------------------------
CREATE TABLE `password_resets` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`      VARCHAR(150) NOT NULL,
    `token`      CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at`    DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `password_resets_email_idx` (`email`),
    KEY `password_resets_token_idx` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Cities
-- ------------------------------------------------------------
CREATE TABLE `cities` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `iata_code`   CHAR(3) NOT NULL,
    `country`     VARCHAR(80) NOT NULL DEFAULT 'Indonesia',
    `image_url`   VARCHAR(500) NULL DEFAULT NULL,
    `tagline`     VARCHAR(150) NULL DEFAULT NULL,
    `start_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `is_popular`  TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order`  INT NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `cities_iata_unique` (`iata_code`),
    KEY `cities_name_idx` (`name`),
    KEY `cities_popular_idx` (`is_popular`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Airlines
-- ------------------------------------------------------------
CREATE TABLE `airlines` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL,
    `code`       VARCHAR(5) NOT NULL,
    `logo_url`   VARCHAR(500) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `airlines_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Flights (daily scheduled services; bookings store the travel date)
-- ------------------------------------------------------------
CREATE TABLE `flights` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `airline_id`      INT UNSIGNED NOT NULL,
    `from_city_id`    INT UNSIGNED NOT NULL,
    `to_city_id`      INT UNSIGNED NOT NULL,
    `flight_num`      VARCHAR(12) NOT NULL,
    `aircraft`        VARCHAR(60) NOT NULL DEFAULT '',
    `dep_time`        TIME NOT NULL,
    `arr_time`        TIME NOT NULL,
    `duration`        SMALLINT UNSIGNED NOT NULL COMMENT 'minutes',
    `price`           DECIMAL(12,2) NOT NULL,
    `stops`           TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `baggage_kg`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `meal`            TINYINT(1) NOT NULL DEFAULT 0,
    `wifi`            TINYINT(1) NOT NULL DEFAULT 0,
    `entertainment`   TINYINT(1) NOT NULL DEFAULT 0,
    `refund_policy`   VARCHAR(100) NOT NULL DEFAULT 'Tidak bisa refund',
    `reschedule_policy` VARCHAR(100) NOT NULL DEFAULT 'Bisa dengan biaya',
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `flights_route_idx` (`from_city_id`, `to_city_id`, `is_active`),
    KEY `flights_airline_idx` (`airline_id`),
    CONSTRAINT `flights_airline_fk` FOREIGN KEY (`airline_id`) REFERENCES `airlines` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `flights_from_city_fk` FOREIGN KEY (`from_city_id`) REFERENCES `cities` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `flights_to_city_fk` FOREIGN KEY (`to_city_id`) REFERENCES `cities` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Promos
-- ------------------------------------------------------------
CREATE TABLE `promos` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`            VARCHAR(40) NOT NULL,
    `type`            ENUM('percent','nominal') NOT NULL DEFAULT 'percent',
    `value`           DECIMAL(12,2) NOT NULL,
    `min_transaction` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `max_usage`       INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = unlimited',
    `used_count`      INT UNSIGNED NOT NULL DEFAULT 0,
    `valid_from`      DATE NOT NULL,
    `valid_until`     DATE NOT NULL,
    `title`           VARCHAR(150) NOT NULL DEFAULT '',
    `description`     TEXT NULL,
    `image_url`       VARCHAR(500) NULL,
    `badge`           VARCHAR(40) NOT NULL DEFAULT 'PROMO',
    `badge_color`     VARCHAR(30) NOT NULL DEFAULT 'bg-accent',
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `promos_code_unique` (`code`),
    KEY `promos_valid_idx` (`is_active`, `valid_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Bookings
-- ------------------------------------------------------------
CREATE TABLE `bookings` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NOT NULL,
    `booking_code` VARCHAR(20) NOT NULL,
    `flight_id`    INT UNSIGNED NOT NULL COMMENT 'primary (first) leg',
    `subtotal`     DECIMAL(12,2) NOT NULL DEFAULT 0,
    `discount`     DECIMAL(12,2) NOT NULL DEFAULT 0,
    `total_price`  DECIMAL(12,2) NOT NULL DEFAULT 0,
    `status`       ENUM('pending','paid','cancelled','expired') NOT NULL DEFAULT 'pending',
    `travel_date`  DATE NOT NULL,
    `cabin_class`  VARCHAR(20) NOT NULL DEFAULT 'Ekonomi',
    `pax`          TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `promo_id`     INT UNSIGNED NULL DEFAULT NULL,
    `expires_at`   DATETIME NULL DEFAULT NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `bookings_code_unique` (`booking_code`),
    KEY `bookings_user_idx` (`user_id`, `created_at`),
    KEY `bookings_status_idx` (`status`),
    KEY `bookings_flight_idx` (`flight_id`),
    CONSTRAINT `bookings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `bookings_flight_fk` FOREIGN KEY (`flight_id`) REFERENCES `flights` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `bookings_promo_fk` FOREIGN KEY (`promo_id`) REFERENCES `promos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- All legs of a booking (supports round-trip & multi-city)
CREATE TABLE `booking_flights` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id`  INT UNSIGNED NOT NULL,
    `flight_id`   INT UNSIGNED NOT NULL,
    `travel_date` DATE NOT NULL,
    `leg_order`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `price`       DECIMAL(12,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `booking_flights_booking_idx` (`booking_id`, `leg_order`),
    CONSTRAINT `booking_flights_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `booking_flights_flight_fk` FOREIGN KEY (`flight_id`) REFERENCES `flights` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Booking passengers
-- ------------------------------------------------------------
CREATE TABLE `booking_passengers` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id` INT UNSIGNED NOT NULL,
    `name`       VARCHAR(120) NOT NULL,
    `type`       ENUM('adult','child','infant') NOT NULL DEFAULT 'adult',
    `email`      VARCHAR(150) NULL DEFAULT NULL,
    `phone`      VARCHAR(30) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `booking_passengers_booking_idx` (`booking_id`),
    CONSTRAINT `booking_passengers_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Payments
-- ------------------------------------------------------------
CREATE TABLE `payments` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id` INT UNSIGNED NOT NULL,
    `method`     VARCHAR(30) NOT NULL DEFAULT 'transfer',
    `amount`     DECIMAL(12,2) NOT NULL DEFAULT 0,
    `status`     ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    `reference`  VARCHAR(60) NULL DEFAULT NULL,
    `proof_path` VARCHAR(255) NULL DEFAULT NULL,
    `paid_at`    DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `payments_booking_idx` (`booking_id`),
    CONSTRAINT `payments_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Promo usage
-- ------------------------------------------------------------
CREATE TABLE `promo_usage` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `promo_id`   INT UNSIGNED NOT NULL,
    `booking_id` INT UNSIGNED NOT NULL,
    `used_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `promo_usage_unique` (`promo_id`, `booking_id`),
    KEY `promo_usage_booking_idx` (`booking_id`),
    CONSTRAINT `promo_usage_promo_fk` FOREIGN KEY (`promo_id`) REFERENCES `promos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `promo_usage_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Reviews (verified travellers)
-- ------------------------------------------------------------
CREATE TABLE `reviews` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `booking_id` INT UNSIGNED NOT NULL,
    `rating`     TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `comment`    TEXT NULL,
    `is_published` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `reviews_booking_idx` (`booking_id`),
    CONSTRAINT `reviews_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `reviews_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- FAQs
-- ------------------------------------------------------------
CREATE TABLE `faqs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `question`   VARCHAR(255) NOT NULL,
    `answer`     TEXT NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `faqs_order_idx` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Homepage testimonials
-- ------------------------------------------------------------
CREATE TABLE `testimonials` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL,
    `role`       VARCHAR(100) NOT NULL DEFAULT '',
    `city`       VARCHAR(80) NOT NULL DEFAULT '',
    `initials`   VARCHAR(4) NOT NULL DEFAULT '',
    `rating`     TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `comment`    TEXT NOT NULL,
    `theme`      VARCHAR(20) NOT NULL DEFAULT 'blue',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `testimonials_order_idx` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Settings (key/value)
-- ------------------------------------------------------------
CREATE TABLE `settings` (
    `key`   VARCHAR(64) NOT NULL,
    `value` TEXT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Login attempt log (rate limiting)
-- ------------------------------------------------------------
CREATE TABLE `login_attempts` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identifier`   VARCHAR(190) NOT NULL,
    `ip`           VARCHAR(45) NOT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `login_attempts_lookup` (`identifier`, `attempted_at`),
    KEY `login_attempts_ip` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
