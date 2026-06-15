# 🚐 MatatuTrack — Real-Time Matatu Tracking System
### Urban Transport Platform for Nairobi, Kenya

---

## 📋 System Overview

MatatuTrack is a full-stack web platform for real-time GPS tracking of matatus (minibuses) across Nairobi's major routes. It serves three user roles through a unified login interface:

- **Passengers** — View live matatu positions on a map, get ETAs, browse routes and stages
- **Drivers** — Activate GPS tracking, update passenger counts, manage trip status
- **Admins** — Monitor the full fleet, broadcast alerts, view analytics

---

## 🛠 Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML5, CSS3, JavaScript (ES2020+) |
| Backend | PHP 8.1+ |
| Database | MySQL 8.0+ |
| Maps | Leaflet.js + OpenStreetMap/CartoDB |
| Geolocation | HTML5 Geolocation API (Watchposition) |
| Auth | PHP Sessions + bcrypt password hashing |

---

## 🗂 File Structure

```
matatu-tracker/
├── index.php               ← Landing page + Login/Register
├── passenger-dashboard.php ← Passenger live map view
├── driver-dashboard.php    ← Driver GPS tracking console
├── admin-dashboard.php     ← Admin analytics & fleet management
├── auth.php                ← Authentication handler (login/register/logout)
├── database.sql            ← Full MySQL schema + seed data
│
├── includes/
│   └── config.php          ← DB config, session helpers, utilities
│
└── api/
    ├── tracking.php        ← GPS update/fetch API
    ├── routes.php          ← Routes & stages API
    └── alerts.php          ← System alerts API
```

---

## ⚙️ Setup Instructions

### 1. Requirements
- PHP 8.1 or higher with PDO MySQL extension
- MySQL 8.0 or MariaDB 10.6+
- Web server: Apache (XAMPP/WAMP) or Nginx
- A modern browser with geolocation support

### 2. Database Setup

```sql
-- In MySQL/phpMyAdmin, run:
SOURCE /path/to/matatu-tracker/database.sql;
```

Or import via phpMyAdmin: `Import → database.sql`

### 3. Configure Database Connection

Edit `includes/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'matatu_tracker');
define('DB_USER', 'your_mysql_username');
define('DB_PASS', 'your_mysql_password');
```

### 4. Place Files on Server

**XAMPP/WAMP:**
```
C:/xampp/htdocs/matatu-tracker/
```

**Linux Apache:**
```
/var/www/html/matatu-tracker/
```

### 5. Access the Application

```
http://localhost/matatu-tracker/
```

---

## 🔐 Demo Accounts

All demo passwords are: **`password`**

| Role | Email | Notes |
|------|-------|-------|
| Passenger | alice.njeri@gmail.com | Full passenger dashboard |
| Driver | john.kamau@gmail.com | GPS tracking console (Route 111) |
| Driver | peter.mwangi@gmail.com | Route 23 driver |
| Admin | admin@matatutrack.co.ke | Full admin access |

---

## 🗺 Routes Covered

| Route | Corridor | Fare |
|-------|----------|------|
| Route 111 | CBD ↔ Rongai | KES 70–100 |
| Route 23 | CBD ↔ Eastleigh | KES 30–50 |
| Route 44 | Westlands ↔ CBD | KES 30–50 |
| Route 58 | CBD ↔ Githurai 45 | KES 50–80 |
| Route 33 | CBD ↔ Kawangware | KES 40–60 |
| Route 9 | CBD ↔ Ngong Road | KES 60–90 |
| Route 45 | CBD ↔ Thika Town | KES 80–120 |
| Route 14 | CBD ↔ South B/C | KES 35–55 |

---

## 🔄 Real-Time Architecture

```
Driver's Phone (GPS)
     │
     ▼ POST every 4 seconds
api/tracking.php
     │
     ▼ Upsert
live_tracking table (current position)
     │
     ├─▶ location_history table (audit trail)
     │
     ▼ SELECT every 5 seconds
passenger-dashboard.php
     │
     ▼
Leaflet.js map markers update
```

### GPS Data Flow:
1. Driver clicks **START** → `navigator.geolocation.watchPosition()` activates
2. Every position change → JavaScript calls `api/tracking.php` via POST
3. Server upserts `live_tracking` table (one row per matatu)
4. Passenger dashboard polls `api/tracking.php?action=get_active` every 5s
5. Map markers animate to new positions

---

## 🧩 Key API Endpoints

### GET Endpoints
```
GET api/tracking.php?action=get_active         → All active matatus with positions
GET api/tracking.php?action=get_matatu&id=1    → Single matatu details
GET api/tracking.php?action=driver_stats&driver_id=2
GET api/tracking.php?action=route_history&matatu_id=1&minutes=60
GET api/routes.php?action=list                 → All routes
GET api/routes.php?action=stages&route_id=1    → Stages for a route
```

### POST Endpoints
```
POST api/tracking.php
  { action: "update_location", matatu_id, driver_id, latitude, longitude, speed_kmh, passenger_count }

POST api/tracking.php
  { action: "set_offline", matatu_id, driver_id }

POST api/alerts.php
  { action: "create", title, message, alert_type }
```

---

## 🔧 Extending the System

### Add MPESA Fare Payment
- Integrate Safaricom Daraja API
- Add `payments` table to schema
- Trigger payment on trip completion

### Add Push Notifications
- Integrate Firebase Cloud Messaging (FCM)
- Notify passengers when their matatu is 500m away

### Add Route Optimization
- Google Maps Distance Matrix API
- Calculate real-time ETAs based on live traffic

### Add Driver Performance Scoring
- Speed compliance, punctuality, passenger ratings
- Leaderboard view in admin dashboard

---

## 📊 Database Key Tables

| Table | Purpose |
|-------|---------|
| `users` | Passengers, drivers, admins |
| `routes` | Route definitions with fares |
| `stages` | GPS coordinates of all bus stops |
| `route_stages` | Many-to-many: stops on each route |
| `matatus` | Vehicle registry |
| `live_tracking` | Current GPS position (1 row/matatu) |
| `location_history` | Full GPS audit trail |
| `trips` | Trip sessions (start/end) |
| `system_alerts` | Admin broadcast messages |
| `feedback` | Passenger ratings |

---

## 🚀 Production Deployment Notes

1. **HTTPS Required** — Geolocation API requires HTTPS in production
2. **Update DB credentials** in `includes/config.php`
3. **MySQL user permissions** — Grant SELECT, INSERT, UPDATE, DELETE
4. **Set timezone** — Update `php.ini`: `date.timezone = Africa/Nairobi`
5. **Session security** — Enable `session.cookie_secure = 1` with HTTPS
6. **Rate limiting** — Add rate limiting to `api/tracking.php` for production
7. **Cron job** — Clean `location_history` records older than 30 days

```sql
-- Monthly cleanup job
DELETE FROM location_history WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

---

*Built for Nairobi's urban mobility challenge — MatatuTrack © 2024*
