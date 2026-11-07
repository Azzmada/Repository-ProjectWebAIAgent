-- ============================================================
--  TerbangLebih — Seed data
--  Import AFTER database/schema.sql
--
--  Default accounts:
--    User  : demo@terbanglebih.com  / Demo#12345
--    Admin : admin@terbanglebih.com / Admin#12345
-- ============================================================

USE `terbanglebih`;
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Users & admins (bcrypt hashes)
-- ------------------------------------------------------------
INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `created_at`) VALUES
(1, 'Demo Traveler', 'demo@terbanglebih.com', '$2y$12$dWBKwCBc9kSYjgB5llB/de4rBoV5pPz3oBbH9r8GxL/taaNqLbuje', '081234567890', DATE_SUB(NOW(), INTERVAL 45 DAY)),
(2, 'Siti Rahma', 'siti@terbanglebih.com', '$2y$12$dWBKwCBc9kSYjgB5llB/de4rBoV5pPz3oBbH9r8GxL/taaNqLbuje', '081298765432', DATE_SUB(NOW(), INTERVAL 20 DAY));

INSERT INTO `admins` (`id`, `name`, `email`, `password`, `created_at`) VALUES
(1, 'Administrator', 'admin@terbanglebih.com', '$2y$12$qCP3vHRmRbf577G2I0ne7.be2oim9B7kqSmMs68L/SmB9pxfim2Pm', DATE_SUB(NOW(), INTERVAL 60 DAY));
-- ------------------------------------------------------------
-- Cities
-- ------------------------------------------------------------
INSERT INTO `cities` (`id`, `name`, `iata_code`, `country`, `image_url`, `tagline`, `start_price`, `is_popular`, `sort_order`) VALUES
(1, 'Jakarta', 'CGK', 'Indonesia', 'https://images.unsplash.com/photo-1555899434-94d1368aa7af?auto=format&fit=crop&w=800&q=80', 'Ibu Kota Indonesia', 0, 0, 5),
(2, 'Bali', 'DPS', 'Indonesia', 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80', 'Pulau Dewata', 1512000, 1, 1),
(3, 'Surabaya', 'SUB', 'Indonesia', 'https://images.unsplash.com/photo-1542314831-c6a4d1424b94?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80', 'Kota Pahlawan', 796000, 1, 4),
(4, 'Medan', 'KNO', 'Indonesia', 'https://images.unsplash.com/photo-1596422846543-75c6fc197f07?auto=format&fit=crop&w=800&q=80', 'Kota Melayu Deli', 980000, 0, 6),
(5, 'Yogyakarta', 'YIA', 'Indonesia', 'https://images.unsplash.com/photo-1584810359583-96fc3448beaa?auto=format&fit=crop&w=800&q=80', 'Kota Gudeg', 620000, 0, 7),
(6, 'Makassar', 'UPG', 'Indonesia', 'https://images.unsplash.com/photo-1596422846543-75c6fc197f07?auto=format&fit=crop&w=800&q=80', 'Kota Daeng', 1300000, 0, 8),
(7, 'Balikpapan', 'BPN', 'Indonesia', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=800&q=80', 'Kota Minyak', 1150000, 0, 9),
(8, 'Semarang', 'SRG', 'Indonesia', 'https://images.unsplash.com/photo-1584810359583-96fc3448beaa?auto=format&fit=crop&w=800&q=80', 'Kota Lumpia', 560000, 0, 10),
(9, 'Palembang', 'PLM', 'Indonesia', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=800&q=80', 'Kota Pempek', 650000, 0, 11),
(10, 'Pekanbaru', 'PKU', 'Indonesia', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=800&q=80', 'Kota Bertuah', 850000, 0, 12),
(11, 'Padang', 'PDG', 'Indonesia', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=800&q=80', 'Kota Rendang', 850000, 0, 13),
(12, 'Banjarmasin', 'BDJ', 'Indonesia', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=800&q=80', 'Kota Seribu Sungai', 980000, 0, 14),
(13, 'Manado', 'MDC', 'Indonesia', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=800&q=80', 'Kota Bunaken', 1900000, 0, 15),
(14, 'Lombok', 'LOP', 'Indonesia', 'https://images.unsplash.com/photo-1518548419970-58e3b4079ab2?auto=format&fit=crop&w=800&q=80', 'Pesona Gili', 950000, 0, 16),
(15, 'Batam', 'BTH', 'Indonesia', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=800&q=80', 'Kota Industri', 720000, 0, 17),
(16, 'Solo', 'SOC', 'Indonesia', 'https://images.unsplash.com/photo-1584810359583-96fc3448beaa?auto=format&fit=crop&w=800&q=80', 'Kota Batik', 560000, 0, 18),
(17, 'Malang', 'MLG', 'Indonesia', 'https://images.unsplash.com/photo-1584810359583-96fc3448beaa?auto=format&fit=crop&w=800&q=80', 'Kota Apel', 580000, 0, 19),
(18, 'Singapore', 'SIN', 'Singapore', 'https://images.unsplash.com/photo-1525625293386-3f8f99389edd?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80', 'Negeri Singa', 911000, 1, 2),
(19, 'Kuala Lumpur', 'KUL', 'Malaysia', 'https://images.unsplash.com/photo-1596422846543-75c6fc197f07?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80', 'Malaysia', 906000, 1, 3),
(20, 'Bangkok', 'BKK', 'Thailand', 'https://images.unsplash.com/photo-1508009603885-50cf7c579365?auto=format&fit=crop&w=800&q=80', 'Kota Gajah', 1950000, 0, 20),
(21, 'Hong Kong', 'HKG', 'Hong Kong', 'https://images.unsplash.com/photo-1536599018102-9f803c140fc1?auto=format&fit=crop&w=800&q=80', 'Kota Dinamis', 3200000, 0, 21),
(22, 'Tokyo', 'NRT', 'Japan', 'https://images.unsplash.com/photo-1540959733332-eab4deabeeaf?auto=format&fit=crop&w=800&q=80', 'Negeri Sakura', 5200000, 0, 22),
(23, 'Seoul', 'ICN', 'South Korea', 'https://images.unsplash.com/photo-1517154421773-0529f29ea451?auto=format&fit=crop&w=800&q=80', 'Kota K-Pop', 4800000, 0, 23),
(24, 'Sydney', 'SYD', 'Australia', 'https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d9?auto=format&fit=crop&w=800&q=80', 'Kota Opera', 6200000, 0, 24),
(25, 'Jeddah', 'JED', 'Saudi Arabia', 'https://images.unsplash.com/photo-1512632578888-169bbbc64f33?auto=format&fit=crop&w=800&q=80', 'Gerbang Tanah Suci', 9000000, 0, 25);

-- ------------------------------------------------------------
-- Airlines
-- ------------------------------------------------------------
INSERT INTO `airlines` (`id`, `name`, `code`, `logo_url`) VALUES
(1, 'Garuda Indonesia', 'GA', 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5c/Garuda_Indonesia_Logo.svg/320px-Garuda_Indonesia_Logo.svg.png'),
(2, 'Lion Air', 'JT', 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1e/Lion_Air_logo.svg/320px-Lion_Air_logo.svg.png'),
(3, 'Batik Air', 'ID', 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2e/Batik_Air_logo.svg/320px-Batik_Air_logo.svg.png'),
(4, 'Citilink', 'QG', 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8f/Citilink_logo.svg/320px-Citilink_logo.svg.png'),
(5, 'Sriwijaya Air', 'SJ', 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7a/Sriwijaya_Air_logo.svg/320px-Sriwijaya_Air_logo.svg.png'),
(6, 'AirAsia', 'QZ', 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f5/AirAsia_New_Logo.svg/320px-AirAsia_New_Logo.svg.png'),
(7, 'Super Air Jet', 'IU', ''),
(8, 'TransNusa', '8B', ''),
(9, 'Nam Air', 'IN', ''),
(10, 'Singapore Airlines', 'SQ', 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1a/Singapore_Airlines_Logo_2.svg/320px-Singapore_Airlines_Logo_2.svg.png'),
(11, 'Malaysia Airlines', 'MH', 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0e/Malaysia_Airlines_logo.svg/320px-Malaysia_Airlines_logo.svg.png');

-- ------------------------------------------------------------
-- Flights (103)
-- ------------------------------------------------------------
INSERT INTO `flights` (`airline_id`, `from_city_id`, `to_city_id`, `flight_num`, `aircraft`, `dep_time`, `arr_time`, `duration`, `price`, `stops`, `baggage_kg`, `meal`, `wifi`, `entertainment`, `refund_policy`, `reschedule_policy`, `is_active`) VALUES
(1, 1, 2, 'GA-402', 'Boeing 737-800', '08:00:00', '09:45:00', 105, 1250000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(2, 1, 2, 'JT-730', 'Boeing 737-900', '12:00:00', '13:50:00', 110, 850000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(3, 1, 2, 'ID-6800', 'Airbus A320', '16:00:00', '17:45:00', 105, 1050000, 0, 20, 1, 1, 0, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(4, 1, 2, 'QG-210', 'Airbus A320', '06:00:00', '07:50:00', 110, 750000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(5, 1, 2, 'SJ-180', 'Boeing 737-500', '10:30:00', '12:10:00', 100, 720000, 0, 10, 0, 0, 0, 'Tidak bisa refund', 'Tidak bisa reschedule', 1),
(6, 1, 2, 'QZ-5350', 'Airbus A320', '14:00:00', '16:00:00', 120, 650000, 0, 10, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(7, 1, 2, 'IU-720', 'Airbus A320', '18:30:00', '20:20:00', 110, 780000, 0, 10, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 1, 2, 'GA-410', 'Boeing 777-300', '19:00:00', '20:50:00', 110, 1500000, 0, 25, 1, 1, 1, 'Refund penuh', 'Gratis reschedule', 1),
(8, 1, 2, '8B-510', 'Airbus A320', '07:15:00', '09:30:00', 135, 900000, 0, 15, 0, 0, 0, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(9, 1, 2, 'IN-190', 'Boeing 737-500', '11:00:00', '13:15:00', 135, 720000, 0, 10, 0, 0, 0, 'Tidak bisa refund', 'Tidak bisa reschedule', 1),
(2, 1, 2, 'JT-790', 'Boeing 737-900', '05:00:00', '08:30:00', 210, 680000, 1, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(3, 1, 2, 'ID-6890', 'Airbus A320', '13:00:00', '16:30:00', 210, 890000, 1, 20, 1, 1, 0, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(4, 1, 2, 'QG-320', 'Airbus A320', '20:00:00', '21:50:00', 110, 800000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 1, 2, 'GA-418', 'Boeing 737-800', '21:30:00', '23:20:00', 110, 1300000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(7, 1, 2, 'IU-750', 'Airbus A320', '09:45:00', '11:35:00', 110, 750000, 0, 10, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(6, 1, 2, 'QZ-5400', 'Airbus A320', '22:00:00', '00:00:00', 120, 600000, 0, 10, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 2, 1, 'GA-403', 'Boeing 737-800', '06:10:00', '08:05:00', 115, 1280000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(2, 2, 1, 'JT-731', 'Boeing 737-900', '07:30:00', '09:25:00', 115, 870000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(3, 2, 1, 'ID-6801', 'Airbus A320', '09:15:00', '11:05:00', 110, 1080000, 0, 20, 1, 1, 0, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(4, 2, 1, 'QG-211', 'Airbus A320', '10:45:00', '12:40:00', 115, 770000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(6, 2, 1, 'QZ-5351', 'Airbus A320', '12:20:00', '14:20:00', 120, 670000, 0, 10, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(7, 2, 1, 'IU-721', 'Airbus A320', '14:05:00', '16:00:00', 115, 790000, 0, 10, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(8, 2, 1, '8B-511', 'Airbus A320', '16:30:00', '18:50:00', 140, 920000, 0, 15, 0, 0, 0, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(9, 2, 1, 'IN-191', 'Boeing 737-500', '18:10:00', '20:30:00', 140, 740000, 0, 10, 0, 0, 0, 'Tidak bisa refund', 'Tidak bisa reschedule', 1),
(1, 2, 1, 'GA-419', 'Boeing 737-800', '20:40:00', '22:35:00', 115, 1420000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(6, 2, 1, 'QZ-5401', 'Airbus A320', '22:15:00', '00:15:00', 120, 620000, 0, 10, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 1, 18, 'GA-117', 'Boeing 737-800', '05:30:00', '07:15:00', 105, 950000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(4, 1, 18, 'QG-130', 'Airbus A320', '07:45:00', '09:35:00', 110, 1121000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(2, 1, 18, 'JT-143', 'Boeing 737-900', '10:00:00', '11:40:00', 100, 874000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(3, 1, 18, 'ID-156', 'Airbus A320', '12:30:00', '14:25:00', 115, 1254000, 0, 20, 1, 1, 0, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(6, 1, 18, 'QZ-169', 'Airbus A320', '15:15:00', '16:52:00', 97, 836000, 0, 10, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(10, 1, 18, 'SQ-182', 'Airbus A350-900', '18:00:00', '21:18:00', 198, 904000, 1, 30, 1, 1, 1, 'Refund penuh', 'Gratis reschedule', 1),
(1, 18, 1, 'GA-134', 'Boeing 737-800', '07:45:00', '09:30:00', 105, 980000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(4, 18, 1, 'QG-147', 'Airbus A320', '10:00:00', '11:50:00', 110, 1156000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(6, 18, 1, 'QZ-160', 'Airbus A320', '12:30:00', '14:10:00', 100, 902000, 0, 10, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(10, 18, 1, 'SQ-173', 'Airbus A350-900', '15:15:00', '18:40:00', 205, 1100000, 1, 30, 1, 1, 1, 'Refund penuh', 'Gratis reschedule', 1),
(1, 1, 19, 'GA-151', 'Boeing 737-800', '10:00:00', '12:00:00', 120, 900000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(2, 1, 19, 'JT-164', 'Boeing 737-900', '12:30:00', '14:35:00', 125, 1062000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(3, 1, 19, 'ID-177', 'Airbus A320', '15:15:00', '17:10:00', 115, 828000, 0, 20, 1, 1, 0, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(6, 1, 19, 'QZ-190', 'Airbus A320', '18:00:00', '20:10:00', 130, 1188000, 0, 10, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(11, 1, 19, 'MH-203', 'Boeing 737-800', '20:30:00', '23:52:00', 202, 673000, 1, 30, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(1, 19, 1, 'GA-168', 'Boeing 737-800', '12:30:00', '14:30:00', 120, 920000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(2, 19, 1, 'JT-181', 'Boeing 737-900', '15:15:00', '17:20:00', 125, 1086000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(11, 19, 1, 'MH-194', 'Boeing 737-800', '18:00:00', '19:55:00', 115, 846000, 0, 30, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(1, 1, 3, 'GA-185', 'Boeing 737-800', '07:45:00', '09:20:00', 95, 700000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(2, 1, 3, 'JT-198', 'Boeing 737-900', '10:00:00', '11:40:00', 100, 826000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(3, 1, 3, 'ID-211', 'Airbus A320', '12:30:00', '14:00:00', 90, 644000, 0, 20, 1, 1, 0, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(4, 1, 3, 'QG-224', 'Airbus A320', '15:15:00', '17:00:00', 105, 924000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(7, 1, 3, 'IU-237', 'Airbus A320', '18:00:00', '19:27:00', 87, 616000, 0, 10, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(6, 1, 3, 'QZ-250', 'Airbus A320', '20:30:00', '23:38:00', 188, 666000, 1, 10, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 3, 1, 'GA-202', 'Boeing 737-800', '10:00:00', '11:35:00', 95, 720000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(2, 3, 1, 'JT-215', 'Boeing 737-900', '12:30:00', '14:10:00', 100, 850000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(4, 3, 1, 'QG-228', 'Airbus A320', '15:15:00', '16:45:00', 90, 662000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(7, 3, 1, 'IU-241', 'Airbus A320', '18:00:00', '21:15:00', 195, 808000, 1, 10, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 1, 4, 'GA-219', 'Boeing 737-800', '05:30:00', '08:00:00', 150, 950000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(2, 1, 4, 'JT-232', 'Boeing 737-900', '07:45:00', '10:20:00', 155, 1121000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(3, 1, 4, 'ID-245', 'Airbus A320', '10:00:00', '12:25:00', 145, 874000, 0, 20, 1, 1, 0, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(7, 1, 4, 'IU-258', 'Airbus A320', '12:30:00', '16:40:00', 250, 1066000, 1, 10, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 1, 5, 'GA-236', 'Boeing 737-800', '12:30:00', '13:45:00', 75, 620000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(4, 1, 5, 'QG-249', 'Airbus A320', '15:15:00', '16:35:00', 80, 732000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(2, 1, 5, 'JT-262', 'Boeing 737-900', '18:00:00', '19:10:00', 70, 570000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(7, 1, 5, 'IU-275', 'Airbus A320', '20:30:00', '23:25:00', 175, 695000, 1, 10, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 2, 3, 'GA-253', 'Boeing 737-800', '07:45:00', '08:50:00', 65, 650000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(4, 2, 3, 'QG-266', 'Airbus A320', '10:00:00', '11:10:00', 70, 767000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(2, 2, 3, 'JT-279', 'Boeing 737-900', '12:30:00', '13:30:00', 60, 598000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 1, 6, 'GA-270', 'Boeing 737-800', '10:00:00', '12:15:00', 135, 1300000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(2, 1, 6, 'JT-283', 'Boeing 737-900', '12:30:00', '14:50:00', 140, 1534000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(3, 1, 6, 'ID-296', 'Airbus A320', '15:15:00', '17:25:00', 130, 1196000, 0, 20, 1, 1, 0, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(1, 1, 7, 'GA-287', 'Boeing 737-800', '07:45:00', '09:50:00', 125, 1150000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(2, 1, 7, 'JT-300', 'Boeing 737-900', '10:00:00', '12:10:00', 130, 1357000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 1, 8, 'GA-304', 'Boeing 737-800', '05:30:00', '06:40:00', 70, 560000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(4, 1, 8, 'QG-317', 'Airbus A320', '07:45:00', '09:00:00', 75, 661000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 1, 9, 'GA-321', 'Boeing 737-800', '10:00:00', '11:20:00', 80, 650000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(2, 1, 9, 'JT-334', 'Boeing 737-900', '12:30:00', '13:55:00', 85, 767000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 1, 10, 'GA-338', 'Boeing 737-800', '07:45:00', '09:30:00', 105, 850000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(3, 1, 10, 'ID-351', 'Airbus A320', '10:00:00', '11:50:00', 110, 1003000, 0, 20, 1, 1, 0, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(1, 1, 11, 'GA-355', 'Boeing 737-800', '12:30:00', '14:20:00', 110, 850000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(2, 1, 11, 'JT-368', 'Boeing 737-900', '15:15:00', '17:10:00', 115, 1003000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 1, 12, 'GA-372', 'Boeing 737-800', '05:30:00', '07:25:00', 115, 980000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(2, 1, 12, 'JT-385', 'Boeing 737-900', '07:45:00', '09:45:00', 120, 1156000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 1, 13, 'GA-389', 'Boeing 737-800', '10:00:00', '13:15:00', 195, 1900000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(2, 1, 13, 'JT-402', 'Boeing 737-900', '12:30:00', '15:50:00', 200, 2242000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 1, 14, 'GA-406', 'Boeing 737-800', '07:45:00', '09:35:00', 110, 950000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(4, 1, 14, 'QG-419', 'Airbus A320', '10:00:00', '11:55:00', 115, 1121000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 1, 15, 'GA-423', 'Boeing 737-800', '05:30:00', '07:05:00', 95, 720000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(2, 1, 15, 'JT-436', 'Boeing 737-900', '07:45:00', '09:25:00', 100, 850000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 1, 16, 'GA-440', 'Boeing 737-800', '10:00:00', '11:10:00', 70, 560000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(4, 1, 16, 'QG-453', 'Airbus A320', '12:30:00', '13:45:00', 75, 661000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 1, 17, 'GA-457', 'Boeing 737-800', '12:30:00', '13:55:00', 85, 580000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(2, 1, 17, 'JT-470', 'Boeing 737-900', '15:15:00', '16:45:00', 90, 684000, 0, 15, 0, 0, 0, 'Tidak bisa refund', 'Bisa dengan biaya', 1),
(1, 1, 20, 'GA-474', 'Boeing 737-800', '07:45:00', '11:15:00', 210, 2100000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(10, 1, 20, 'SQ-487', 'Airbus A350-900', '10:00:00', '13:35:00', 215, 2478000, 0, 30, 1, 1, 1, 'Refund penuh', 'Gratis reschedule', 1),
(11, 1, 20, 'MH-500', 'Boeing 737-800', '12:30:00', '15:55:00', 205, 1932000, 0, 30, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(1, 1, 21, 'GA-491', 'Boeing 737-800', '10:00:00', '14:45:00', 285, 3200000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(10, 1, 21, 'SQ-504', 'Airbus A350-900', '12:30:00', '17:20:00', 290, 3776000, 0, 30, 1, 1, 1, 'Refund penuh', 'Gratis reschedule', 1),
(1, 1, 22, 'GA-508', 'Boeing 737-800', '05:30:00', '12:45:00', 435, 5200000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(10, 1, 22, 'SQ-521', 'Airbus A350-900', '07:45:00', '15:05:00', 440, 6136000, 0, 30, 1, 1, 1, 'Refund penuh', 'Gratis reschedule', 1),
(1, 1, 23, 'GA-525', 'Boeing 737-800', '12:30:00', '19:30:00', 420, 4800000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(10, 1, 23, 'SQ-538', 'Airbus A350-900', '15:15:00', '22:20:00', 425, 5664000, 0, 30, 1, 1, 1, 'Refund penuh', 'Gratis reschedule', 1),
(1, 1, 24, 'GA-542', 'Boeing 737-800', '07:45:00', '15:15:00', 450, 6200000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(10, 1, 24, 'SQ-555', 'Airbus A350-900', '10:00:00', '17:35:00', 455, 7316000, 0, 30, 1, 1, 1, 'Refund penuh', 'Gratis reschedule', 1),
(1, 1, 25, 'GA-559', 'Boeing 737-800', '10:00:00', '19:30:00', 570, 9000000, 0, 20, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1),
(11, 1, 25, 'MH-572', 'Boeing 737-800', '12:30:00', '22:05:00', 575, 10620000, 0, 30, 1, 1, 1, 'Bayar sebagian', 'Bisa dengan biaya', 1);

-- ------------------------------------------------------------
-- Promos
-- ------------------------------------------------------------
INSERT INTO `promos` (`code`, `type`, `value`, `min_transaction`, `max_usage`, `used_count`, `valid_from`, `valid_until`, `title`, `description`, `image_url`, `badge`, `badge_color`, `is_active`) VALUES
('TERBANG20', 'percent', 20, 500000, 100, 12, DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 60 DAY), 'Diskon 20% Tiket Pesawat', 'Berlaku untuk semua rute domestik. Pesan sekarang dan hemat lebih banyak!', 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80', 'PENERBANGAN', 'bg-accent', 1),
('HEMAT50K', 'nominal', 50000, 750000, 200, 38, DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 60 DAY), 'Potongan Rp 50.000', 'Potongan langsung Rp 50.000 untuk transaksi minimal Rp 750.000.', 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=600&q=80', 'SEMUA RUTE', 'bg-primary', 1),
('WEEKEND10', 'percent', 10, 0, 0, 5, DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 60 DAY), 'Diskon 10% Akhir Pekan', 'Terbang di akhir pekan? Gunakan kode ini dan nikmati diskon 10%.', 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=600&q=80', 'WEEKEND', 'bg-green-600', 1),
('NEWUSER15', 'percent', 15, 400000, 500, 20, DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 60 DAY), 'Diskon 15% Pengguna Baru', 'Khusus pengguna baru TerbangLebih. Berlaku untuk semua rute.', 'https://images.unsplash.com/photo-1500835556837-99ac94a94552?auto=format&fit=crop&w=600&q=80', 'PENGGUNA BARU', 'bg-purple-600', 1),
('JELAJAH100K', 'nominal', 100000, 1500000, 50, 3, DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 60 DAY), 'Potongan Rp 100.000', 'Untuk penerbangan internasional dengan transaksi minimal Rp 1.500.000.', 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=600&q=80', 'JELAJAH', 'bg-blue-600', 1);

-- ------------------------------------------------------------
-- FAQs
-- ------------------------------------------------------------
INSERT INTO `faqs` (`question`, `answer`, `sort_order`, `is_active`) VALUES
('Bagaimana cara memesan tiket pesawat di TerbangLebih?', 'Memesan tiket sangat mudah. Masukkan kota asal, kota tujuan, dan tanggal perjalanan Anda di halaman Pesawat. Pilih penerbangan yang diinginkan, isi data penumpang, lalu selesaikan pembayaran. Tiket elektronik akan langsung dikirim ke email Anda.', 1, 1),
('Metode pembayaran apa saja yang tersedia?', 'Kami menerima Transfer Bank (BCA, Mandiri, BNI, BRI), Virtual Account, Kartu Kredit (Visa/Mastercard), serta dompet digital seperti OVO, GoPay, dan DANA.', 2, 1),
('Bagaimana cara membatalkan atau menjadwal ulang tiket?', 'Pembatalan atau penjadwalan ulang dapat dilakukan melalui menu "Pesanan Saya" atau dengan menghubungi layanan pelanggan 24/7. Kebijakan refund dan reschedule mengikuti aturan masing-masing maskapai.', 3, 1),
('Apakah data pribadi saya aman?', 'Sangat aman. Kami menggunakan koneksi terenkripsi, password di-hash, proteksi CSRF, dan tidak pernah membagikan data Anda kepada pihak ketiga tanpa izin.', 4, 1),
('Berapa lama batas waktu pembayaran?', 'Setelah booking dibuat, Anda memiliki waktu 120 menit untuk menyelesaikan pembayaran. Jika melewati batas waktu, status booking otomatis menjadi kedaluwarsa dan kursi dilepas kembali.', 5, 1),
('Bagaimana cara mengunduh e-tiket?', 'Buka menu Pesanan Saya, pilih booking berstatus Lunas, lalu klik tombol "Unduh E-Tiket". E-tiket tersedia dalam format PDF dan juga dikirim ke email Anda.', 6, 1),
('Apakah kode promo bisa digabung?', 'Satu transaksi hanya dapat menggunakan satu kode promo. Kode promo memiliki ketentuan minimal transaksi, masa berlaku, dan kuota pemakaian.', 7, 1),
('Bagaimana jika bukti transfer saya ditolak?', 'Tim kami akan menghubungi Anda melalui email terdaftar. Anda dapat mengunggah ulang bukti pembayaran yang valid melalui halaman instruksi pembayaran.', 8, 1);

-- ------------------------------------------------------------
-- Testimonials
-- ------------------------------------------------------------
INSERT INTO `testimonials` (`name`, `role`, `city`, `initials`, `rating`, `comment`, `theme`, `sort_order`, `is_active`) VALUES
('Andi Setiawan', 'Traveler', 'Jakarta', 'AS', 5, 'Proses pesan tiketnya sangat mudah dan cepat. Harga yang ditawarkan juga lebih murah dibanding platform lain. Recommended banget!', 'blue', 1, 1),
('Rina Marlina', 'Business Owner', 'Surabaya', 'RM', 5, 'CS-nya responsif banget, sempat ada masalah dengan jadwal penerbangan dan langsung dibantu sampai selesai. Terima kasih TerbangLebih!', 'orange', 2, 1),
('Budi Pratama', 'Karyawan Swasta', 'Bandung', 'BP', 5, 'Sudah jadi langganan sejak 2 tahun lalu. Promonya selalu menarik dan proses refund-nya jelas. Nggak pernah kecewa!', 'green', 3, 1),
('Maya Kusuma', 'Content Creator', 'Yogyakarta', 'MK', 5, 'Fitur multi-kota-nya sangat membantu saat roadshow. Semua rute bisa dipesan dalam satu transaksi.', 'blue', 4, 1),
('Fajar Nugroho', 'Engineer', 'Balikpapan', 'FN', 5, 'Pembayaran virtual account langsung terverifikasi dan e-tiket datang ke email dalam hitungan detik.', 'orange', 5, 1);

-- ------------------------------------------------------------
-- Settings
-- ------------------------------------------------------------
INSERT INTO `settings` (`key`, `value`) VALUES
('site_name', 'TerbangLebih'),
('site_tagline', 'Terbang lebih mudah, jelajahi dunia tanpa ribet.'),
('contact_email', 'support@terbanglebih.com'),
('contact_phone', '0800-1-888-999'),
('stat_airlines', '500'),
('stat_tickets', '500'),
('stat_rating', '49'),
('stat_cs_hours', '24'),
('booking_expiry_minutes', '120'),
('payment_deadline_hours', '2'),
('bank_bca_number', '1234567890'),
('bank_bca_name', 'PT TerbangLebih Nusantara'),
('bank_mandiri_number', '1370009876543'),
('bank_mandiri_name', 'PT TerbangLebih Nusantara'),
('bank_bni_number', '0123456789'),
('bank_bni_name', 'PT TerbangLebih Nusantara'),
('bank_bri_number', '001201000123456'),
('bank_bri_name', 'PT TerbangLebih Nusantara'),
('ewallet_ovo_number', '081234567890'),
('ewallet_gopay_number', '081234567890'),
('ewallet_dana_number', '081234567890'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_user', ''),
('smtp_from', 'no-reply@terbanglebih.test');

-- ------------------------------------------------------------
-- Demo bookings (so "Pesanan Saya" is populated after login)
-- ------------------------------------------------------------
INSERT INTO `bookings`
(`id`, `user_id`, `booking_code`, `flight_id`, `subtotal`, `discount`, `total_price`, `status`, `travel_date`, `cabin_class`, `pax`, `promo_id`, `expires_at`, `created_at`) VALUES
(1, 1, 'TL-DEMO01', 1, 1250000, 0, 1250000, 'paid', DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Ekonomi', 1, NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 1, 'TL-DEMO02', 17, 1280000, 0, 1280000, 'pending', DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'Ekonomi', 2, NULL, DATE_ADD(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 10 MINUTE));

INSERT INTO `booking_flights` (`booking_id`, `flight_id`, `travel_date`, `leg_order`, `price`) VALUES
(1, 1, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 1, 1250000),
(2, 17, DATE_ADD(CURDATE(), INTERVAL 14 DAY), 1, 1280000);

INSERT INTO `booking_passengers` (`booking_id`, `name`, `type`, `email`, `phone`) VALUES
(1, 'Demo Traveler', 'adult', 'demo@terbanglebih.com', '081234567890'),
(2, 'Demo Traveler', 'adult', 'demo@terbanglebih.com', '081234567890'),
(2, 'Siti Rahma', 'adult', 'siti@terbanglebih.com', '081298765432');

INSERT INTO `payments` (`booking_id`, `method`, `amount`, `status`, `reference`, `paid_at`, `created_at`) VALUES
(1, 'transfer', 1250000, 'paid', 'TRF-DEMO01', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 'va', 1280000, 'pending', 'VA-DEMO02', NULL, DATE_SUB(NOW(), INTERVAL 10 MINUTE));

INSERT INTO `reviews` (`user_id`, `booking_id`, `rating`, `comment`, `is_published`, `created_at`) VALUES
(1, 1, 5, 'Penerbangan tepat waktu, pelayanan ramah, dan proses check-in sangat mudah.', 1, DATE_SUB(NOW(), INTERVAL 1 DAY));