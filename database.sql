-- ============================================================
-- Real-Time Matatu Tracking System - Database Schema
-- Nairobi Urban Transport Platform
-- ============================================================

CREATE DATABASE IF NOT EXISTS matatu_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE matatu_tracker;

-- ============================================================
-- USERS TABLE (Passengers & Drivers)
-- ============================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('passenger', 'driver', 'admin') NOT NULL DEFAULT 'passenger',
    profile_photo VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_role (role)
);

-- ============================================================
-- ROUTES TABLE
-- ============================================================
CREATE TABLE routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_number VARCHAR(20) NOT NULL UNIQUE,
    route_name VARCHAR(150) NOT NULL,
    origin VARCHAR(100) NOT NULL,
    destination VARCHAR(100) NOT NULL,
    description TEXT,
    fare_min DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    fare_max DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    distance_km DECIMAL(6,2),
    avg_duration_minutes INT,
    is_active TINYINT(1) DEFAULT 1,
    color_code VARCHAR(7) DEFAULT '#00C853',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_route_number (route_number),
    INDEX idx_active (is_active)
);

-- ============================================================
-- STAGES (BUS STOPS) TABLE
-- ============================================================
CREATE TABLE stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stage_name VARCHAR(100) NOT NULL,
    stage_code VARCHAR(20) UNIQUE NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    area VARCHAR(100),
    landmark VARCHAR(150),
    is_terminal TINYINT(1) DEFAULT 0,
    amenities JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stage_code (stage_code),
    INDEX idx_location (latitude, longitude)
);

-- ============================================================
-- ROUTE STAGES (Many-to-Many: which stages are on which route)
-- ============================================================
CREATE TABLE route_stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    stage_id INT NOT NULL,
    stop_order INT NOT NULL,
    distance_from_origin_km DECIMAL(6,2),
    estimated_time_from_origin_min INT,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE,
    FOREIGN KEY (stage_id) REFERENCES stages(id) ON DELETE CASCADE,
    UNIQUE KEY unique_route_stage (route_id, stage_id),
    INDEX idx_route_id (route_id),
    INDEX idx_stop_order (route_id, stop_order)
);

-- ============================================================
-- MATATUS (VEHICLES) TABLE
-- ============================================================
CREATE TABLE matatus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_plate VARCHAR(20) UNIQUE NOT NULL,
    sacco_name VARCHAR(100),
    route_id INT,
    driver_id INT UNIQUE,
    capacity INT DEFAULT 14,
    vehicle_model VARCHAR(100),
    manufacture_year INT,
    color VARCHAR(50),
    is_active TINYINT(1) DEFAULT 1,
    insurance_expiry DATE,
    inspection_expiry DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE SET NULL,
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_plate (registration_plate),
    INDEX idx_route (route_id),
    INDEX idx_driver (driver_id)
);

-- ============================================================
-- LIVE TRACKING TABLE (Current position of active matatus)
-- ============================================================
CREATE TABLE live_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matatu_id INT NOT NULL,
    driver_id INT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    speed_kmh DECIMAL(5,2) DEFAULT 0.00,
    heading DECIMAL(6,2) DEFAULT 0.00,
    accuracy DECIMAL(6,2),
    current_stage_id INT,
    next_stage_id INT,
    passenger_count INT DEFAULT 0,
    status ENUM('active', 'idle', 'offline', 'breakdown') DEFAULT 'active',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (matatu_id) REFERENCES matatus(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_matatu (matatu_id),
    INDEX idx_driver_id (driver_id),
    INDEX idx_status (status),
    INDEX idx_last_updated (last_updated)
);

-- ============================================================
-- LOCATION HISTORY TABLE (GPS trail)
-- ============================================================
CREATE TABLE location_history (
    id BIGINT AUTO_INCREMENT,
    PRIMARY KEY (id, recorded_at),
    matatu_id INT NOT NULL,
    driver_id INT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    speed_kmh DECIMAL(5,2),
    heading DECIMAL(6,2),
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_matatu_time (matatu_id, recorded_at),
    INDEX idx_recorded_at (recorded_at)
) PARTITION BY RANGE (YEAR(recorded_at)) (
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- ============================================================
-- DRIVER TRIPS TABLE (Trip sessions)
-- ============================================================
CREATE TABLE trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matatu_id INT NOT NULL,
    driver_id INT NOT NULL,
    route_id INT NOT NULL,
    start_stage_id INT,
    end_stage_id INT,
    trip_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    trip_end TIMESTAMP NULL,
    passengers_carried INT DEFAULT 0,
    total_distance_km DECIMAL(8,2),
    status ENUM('ongoing', 'completed', 'cancelled') DEFAULT 'ongoing',
    FOREIGN KEY (matatu_id) REFERENCES matatus(id),
    FOREIGN KEY (driver_id) REFERENCES users(id),
    FOREIGN KEY (route_id) REFERENCES routes(id),
    INDEX idx_matatu (matatu_id),
    INDEX idx_driver (driver_id),
    INDEX idx_status (status),
    INDEX idx_start (trip_start)
);

-- ============================================================
-- NOTIFICATIONS TABLE
-- ============================================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'alert', 'success') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    route_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read)
);

-- ============================================================
-- SYSTEM ALERTS (Broadcasts to all users)
-- ============================================================
CREATE TABLE system_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    alert_type ENUM('maintenance', 'disruption', 'info', 'emergency') DEFAULT 'info',
    affected_routes JSON,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- ============================================================
-- FEEDBACK TABLE
-- ============================================================
CREATE TABLE feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    matatu_id INT,
    driver_id INT,
    route_id INT,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    trip_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (matatu_id) REFERENCES matatus(id),
    INDEX idx_matatu_rating (matatu_id, rating)
);

-- ============================================================
-- ANALYTICS SUMMARY TABLE (Pre-computed stats)
-- ============================================================
CREATE TABLE analytics_daily (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    stat_date DATE NOT NULL,
    total_trips INT DEFAULT 0,
    total_passengers INT DEFAULT 0,
    avg_speed_kmh DECIMAL(5,2),
    peak_hour_start TIME,
    peak_hour_end TIME,
    total_distance_km DECIMAL(10,2),
    FOREIGN KEY (route_id) REFERENCES routes(id),
    UNIQUE KEY unique_route_date (route_id, stat_date)
);

-- ============================================================
-- SEED DATA: Users
-- ============================================================
INSERT INTO users (full_name, email, phone, password_hash, role) VALUES
('System Admin', 'admin@matatutrack.co.ke', '+254700000001', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('John Kamau', 'john.kamau@gmail.com', '+254711223344', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver'),
('Peter Mwangi', 'peter.mwangi@gmail.com', '+254722334455', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver'),
('Grace Wanjiru', 'grace.wanjiru@gmail.com', '+254733445566', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver'),
('Samuel Odhiambo', 'samuel.o@gmail.com', '+254744556677', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver'),
('Alice Njeri', 'alice.njeri@gmail.com', '+254755667788', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'passenger'),
('David Kipchoge', 'david.kip@gmail.com', '+254766778899', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'passenger'),
('Mary Akinyi', 'mary.akinyi@gmail.com', '+254777889900', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'passenger');
-- Default password for all demo users: "password"

-- ============================================================
-- SEED DATA: Routes
-- ============================================================
INSERT INTO routes (route_number, route_name, origin, destination, fare_min, fare_max, distance_km, avg_duration_minutes, color_code) VALUES
('Route 111', 'CBD - Rongai', 'Kencom/CBD', 'Rongai', 70.00, 100.00, 28.5, 75, '#FF5722'),
('Route 23', 'CBD - Eastleigh', 'Kencom/CBD', 'Eastleigh Stage', 30.00, 50.00, 8.2, 30, '#2196F3'),
('Route 44', 'Westlands - CBD', 'Westlands Stage', 'Kencom/CBD', 30.00, 50.00, 7.5, 25, '#4CAF50'),
('Route 58', 'CBD - Githurai 45', 'Kencom/CBD', 'Githurai 45', 50.00, 80.00, 18.0, 55, '#9C27B0'),
('Route 33', 'CBD - Kawangware', 'Kencom/CBD', 'Kawangware Stage', 40.00, 60.00, 11.0, 40, '#FF9800'),
('Route 9', 'CBD - Ngong Road', 'Archives/CBD', 'Ngong Town', 60.00, 90.00, 22.0, 60, '#00BCD4'),
('Route 45', 'CBD - Thika Road', 'Kencom/CBD', 'Thika Town', 80.00, 120.00, 45.0, 90, '#F44336'),
('Route 14', 'CBD - South B/C', 'Kencom/CBD', 'South C Stage', 35.00, 55.00, 9.5, 35, '#607D8B');

-- ============================================================
-- SEED DATA: Stages (GPS coords for Nairobi)
-- ============================================================
INSERT INTO stages (stage_name, stage_code, latitude, longitude, area, landmark, is_terminal) VALUES
('Kencom Stage', 'KNC001', -1.2841, 36.8230, 'CBD', 'Kencom House', 1),
('Archives Stage', 'ARC001', -1.2864, 36.8219, 'CBD', 'Kenya National Archives', 1),
('Rongai Stage', 'RNG001', -1.4275, 36.7453, 'Rongai', 'Rongai Town Center', 1),
('Langata Road Stage', 'LNG001', -1.3317, 36.7877, 'Langata', 'Junction Mall', 0),
('Galleria Stage', 'GAL001', -1.3892, 36.7621, 'Galleria', 'Galleria Mall', 0),
('Eastleigh Stage', 'EST001', -1.2744, 36.8478, 'Eastleigh', 'Section 1 Eastleigh', 1),
('Pangani Stage', 'PAN001', -1.2756, 36.8378, 'Pangani', 'Pangani Roundabout', 0),
('Westlands Stage', 'WST001', -1.2672, 36.8067, 'Westlands', 'Westlands Roundabout', 1),
('Sarit Center Stage', 'SAR001', -1.2614, 36.8046, 'Westlands', 'Sarit Center', 0),
('Githurai 45 Stage', 'GTH001', -1.2178, 36.8956, 'Githurai', 'Githurai 45 Market', 1),
('Roysambu Stage', 'ROY001', -1.2267, 36.8756, 'Roysambu', 'Roysambu Junction', 0),
('Githurai 44 Stage', 'GT44001', -1.2211, 36.8867, 'Githurai', 'Githurai 44 Stage', 0),
('Kawangware Stage', 'KWN001', -1.2836, 36.7656, 'Kawangware', 'Kawangware 56', 1),
('Kangemi Stage', 'KNG001', -1.2714, 36.7767, 'Kangemi', 'Kangemi Market', 0),
('Ngong Town Stage', 'NGN001', -1.3564, 36.6631, 'Ngong', 'Ngong Town Center', 1),
('Dagoretti Corner', 'DAG001', -1.3089, 36.7456, 'Dagoretti', 'Dagoretti Corner', 0),
('Thika Town Stage', 'THK001', -1.0332, 37.0693, 'Thika', 'Thika Bus Station', 1),
('Roysambu Junction', 'RJN001', -1.2294, 36.8689, 'Roysambu', 'TRM Mall Area', 0),
('South C Stage', 'STC001', -1.3139, 36.8317, 'South C', 'South C Shopping Center', 1),
('South B Stage', 'STB001', -1.3017, 36.8289, 'South B', 'South B Market', 0),
('Bomas Stage', 'BOM001', -1.3469, 36.7681, 'Langata', 'Bomas of Kenya', 0),
('Junction Stage', 'JCT001', -1.2978, 36.7817, 'Ngong Road', 'The Junction Mall', 0);

-- ============================================================
-- SEED DATA: Route Stages Mapping
-- ============================================================
-- Route 111: CBD - Rongai
INSERT INTO route_stages (route_id, stage_id, stop_order, distance_from_origin_km, estimated_time_from_origin_min) VALUES
(1, 1, 1, 0.0, 0),    -- Kencom
(1, 4, 2, 8.5, 20),   -- Langata Road
(1, 21, 3, 14.2, 35), -- Bomas
(1, 5, 4, 19.8, 50),  -- Galleria
(1, 3, 5, 28.5, 75);  -- Rongai

-- Route 23: CBD - Eastleigh
INSERT INTO route_stages (route_id, stage_id, stop_order, distance_from_origin_km, estimated_time_from_origin_min) VALUES
(2, 1, 1, 0.0, 0),   -- Kencom
(2, 7, 2, 3.5, 12),  -- Pangani
(2, 6, 3, 8.2, 30);  -- Eastleigh

-- Route 44: Westlands - CBD
INSERT INTO route_stages (route_id, stage_id, stop_order, distance_from_origin_km, estimated_time_from_origin_min) VALUES
(3, 8, 1, 0.0, 0),   -- Westlands
(3, 9, 2, 1.2, 5),   -- Sarit
(3, 1, 3, 7.5, 25);  -- Kencom

-- Route 58: CBD - Githurai 45
INSERT INTO route_stages (route_id, stage_id, stop_order, distance_from_origin_km, estimated_time_from_origin_min) VALUES
(4, 1, 1, 0.0, 0),    -- Kencom
(4, 18, 2, 6.5, 18),  -- Roysambu Junction
(4, 11, 3, 10.5, 30), -- Roysambu
(4, 12, 4, 15.0, 42), -- Githurai 44
(4, 10, 5, 18.0, 55); -- Githurai 45

-- Route 33: CBD - Kawangware
INSERT INTO route_stages (route_id, stage_id, stop_order, distance_from_origin_km, estimated_time_from_origin_min) VALUES
(5, 1, 1, 0.0, 0),    -- Kencom
(5, 14, 2, 5.5, 18),  -- Kangemi
(5, 13, 3, 11.0, 40); -- Kawangware

-- Route 9: CBD - Ngong Road
INSERT INTO route_stages (route_id, stage_id, stop_order, distance_from_origin_km, estimated_time_from_origin_min) VALUES
(6, 2, 1, 0.0, 0),    -- Archives
(6, 22, 2, 5.2, 15),  -- Junction
(6, 16, 3, 12.5, 35), -- Dagoretti
(6, 15, 4, 22.0, 60); -- Ngong Town

-- Route 45: CBD - Thika
INSERT INTO route_stages (route_id, stage_id, stop_order, distance_from_origin_km, estimated_time_from_origin_min) VALUES
(7, 1, 1, 0.0, 0),
(7, 18, 2, 6.5, 18),
(7, 11, 3, 12.0, 30),
(7, 17, 4, 45.0, 90);

-- Route 14: CBD - South B/C
INSERT INTO route_stages (route_id, stage_id, stop_order, distance_from_origin_km, estimated_time_from_origin_min) VALUES
(8, 1, 1, 0.0, 0),
(8, 20, 2, 5.0, 18),
(8, 19, 3, 9.5, 35);

-- ============================================================
-- SEED DATA: Matatus
-- ============================================================
INSERT INTO matatus (registration_plate, sacco_name, route_id, driver_id, capacity, vehicle_model, color) VALUES
('KDA 123A', 'Forward Travelers SACCO', 1, 2, 14, 'Toyota HiAce', 'White/Green'),
('KDB 456B', 'City Hoppa SACCO', 2, 3, 33, 'Nissan Civilian', 'White/Blue'),
('KDC 789C', 'Westlands Express', 3, 4, 14, 'Toyota HiAce', 'White/Yellow'),
('KDD 012D', 'Githurai Riders SACCO', 4, 5, 14, 'Toyota HiAce', 'White/Purple'),
('KDE 345E', 'Kawangware Shuttle', 5, NULL, 14, 'Toyota HiAce', 'White/Orange'),
('KDF 678F', 'Ngong Road SACCO', 6, NULL, 33, 'Isuzu NHR', 'White/Cyan');

-- ============================================================
-- SEED DATA: Active Tracking (Simulated positions)
-- ============================================================
INSERT INTO live_tracking (matatu_id, driver_id, latitude, longitude, speed_kmh, heading, passenger_count, status, current_stage_id, next_stage_id) VALUES
(1, 2, -1.3317, 36.7877, 45.5, 225.0, 8, 'active', 4, 5),
(2, 3, -1.2750, 36.8400, 22.0, 90.0, 15, 'active', 7, 6),
(3, 4, -1.2650, 36.8058, 35.0, 135.0, 6, 'active', 9, 1),
(4, 5, -1.2267, 36.8756, 50.0, 0.0, 11, 'active', 11, 10);

-- ============================================================
-- SEED DATA: System Alerts
-- ============================================================
INSERT INTO system_alerts (title, message, alert_type, affected_routes, is_active, created_by) VALUES
('Traffic Advisory - Mombasa Road', 'Heavy traffic reported along Mombasa Road due to ongoing construction. Expect delays on Route 111.', 'disruption', '[1]', 1, 1),
('Service Update - Thika Road', 'Normal service resumed on Route 45 after earlier breakdown.', 'info', '[7]', 1, 1);

-- ============================================================
-- STORED PROCEDURE: Create manually in phpMyAdmin
-- Go to: matatu_tracker database > Routines > Add Routine
-- Routine name: GetNearbyMatatus
-- Parameters: p_latitude DECIMAL(10,8) IN, p_longitude DECIMAL(11,8) IN, p_radius_km DECIMAL(5,2) IN
-- Paste the body below into the "Definition" box
-- ============================================================

-- ============================================================
-- VIEW: Active Matatu Dashboard
-- ============================================================
CREATE VIEW v_active_matatus AS
SELECT
    m.id AS matatu_id,
    m.registration_plate,
    m.sacco_name,
    m.capacity,
    lt.latitude,
    lt.longitude,
    lt.speed_kmh,
    lt.passenger_count,
    lt.status,
    lt.last_updated,
    r.route_number,
    r.route_name,
    r.origin,
    r.destination,
    r.fare_min,
    r.fare_max,
    r.color_code,
    u.full_name AS driver_name,
    u.phone AS driver_phone,
    s1.stage_name AS current_stage,
    s2.stage_name AS next_stage,
    ROUND((m.capacity - lt.passenger_count) / m.capacity * 100) AS availability_pct
FROM matatus m
JOIN live_tracking lt ON m.id = lt.matatu_id
JOIN routes r ON m.route_id = r.id
LEFT JOIN users u ON m.driver_id = u.id
LEFT JOIN stages s1 ON lt.current_stage_id = s1.id
LEFT JOIN stages s2 ON lt.next_stage_id = s2.id
WHERE lt.status IN ('active', 'idle');

UPDATE users SET password_hash = '$2y$12$pVP39kmLJCzu7afY9zW5eeEASTQb0i.OYw6YGSJteI05D/iPberGe';


-- ============================================================
-- MatatuTrack — Wallet & Fare Schedule Patch (FIXED)
-- Run this in phpMyAdmin > SQL tab on your matatu_tracker DB
-- ============================================================
-- FIX: All foreign key columns use signed INT to match
--      users.id (INT) and routes.id (INT) in the base schema.
--      MySQL errno 150 is always a column type mismatch on FK.
-- ============================================================

-- ── 1. WALLETS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wallets (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL UNIQUE,
  balance       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_topped  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_spent   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 2. WALLET TRANSACTIONS ───────────────────────────────────
CREATE TABLE IF NOT EXISTS wallet_transactions (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  wallet_id       INT UNSIGNED NOT NULL,
  user_id         INT NOT NULL,
  type            ENUM('credit','debit') NOT NULL,
  amount          DECIMAL(10,2) NOT NULL,
  balance_after   DECIMAL(10,2) NOT NULL,
  description     VARCHAR(255) NOT NULL,
  reference       VARCHAR(100) DEFAULT NULL,
  mpesa_code      VARCHAR(20)  DEFAULT NULL,
  performed_by    INT DEFAULT NULL,
  trip_id         INT DEFAULT NULL,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (wallet_id)    REFERENCES wallets(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)      REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY (performed_by) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 3. FARE SCHEDULE ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fare_schedule (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  route_id    INT NOT NULL,
  label       VARCHAR(50)  NOT NULL,
  hour_start  TINYINT      NOT NULL,
  hour_end    TINYINT      NOT NULL,
  multiplier  DECIMAL(4,2) NOT NULL DEFAULT 1.00,
  flat_fare   DECIMAL(8,2) DEFAULT NULL,
  color       VARCHAR(20)  DEFAULT '#FFB300',
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE,
  INDEX idx_route_hour (route_id, hour_start, hour_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 4. Wallets for all existing users ───────────────────────
INSERT IGNORE INTO wallets (user_id, balance)
SELECT id, 0.00 FROM users;

-- ── 5. Default fare schedule bands for all existing routes ──
INSERT IGNORE INTO fare_schedule (route_id, label, hour_start, hour_end, multiplier, color)
SELECT r.id, 'Off-Peak',      0,  5,  0.80, '#7A9B80' FROM routes r
UNION ALL
SELECT r.id, 'Morning Peak',  6, 10,  1.50, '#FF5722' FROM routes r
UNION ALL
SELECT r.id, 'Midday Normal', 11, 15, 1.00, '#00E676' FROM routes r
UNION ALL
SELECT r.id, 'Afternoon',     16, 18, 1.20, '#FFB300' FROM routes r
UNION ALL
SELECT r.id, 'Evening Peak',  19, 21, 1.60, '#FF3D3D' FROM routes r
UNION ALL
SELECT r.id, 'Night',         22, 23, 0.90, '#2196F3' FROM routes r;

CREATE TABLE IF NOT EXISTS mpesa_transactions (
    id                  INT             NOT NULL AUTO_INCREMENT,
    user_id             INT             NOT NULL,
    phone               VARCHAR(20)     NOT NULL,
    amount              DECIMAL(10,2)   NOT NULL,
    amount_paid         DECIMAL(10,2)   NULL,
    checkout_request_id VARCHAR(100)    NOT NULL UNIQUE,
    merchant_request_id VARCHAR(100)    NOT NULL,
    mpesa_code          VARCHAR(30)     NULL,
    status              ENUM('pending','complete','failed') NOT NULL DEFAULT 'pending',
    result_desc         VARCHAR(255)    NULL,
    callback_payload    JSON            NULL,
    initiated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at        DATETIME        NULL,

    PRIMARY KEY (id),
    KEY idx_user_id    (user_id),
    KEY idx_status     (status),
    KEY idx_mpesa_code (mpesa_code),

    CONSTRAINT fk_mpesa_tx_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;