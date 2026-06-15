# 🚐 MatatuTrack KE
http://localhost:8080/matatu_project/index.php?msg

![PHP](https://img.shields.io/badge/PHP-8.x-blue)
![MySQL](https://img.shields.io/badge/MySQL-8.x-orange)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-purple)
![Leaflet](https://img.shields.io/badge/Leaflet.js-Mapping-green)
![License](https://img.shields.io/badge/License-MIT-yellow)

> Transforming Nairobi's matatu experience through real-time matatu tracking and passenger information.

A Real-Time Matatu Tracking and Passenger Information System designed to improve urban public transportation in Nairobi, Kenya. The platform enables passengers to track matatus in real time, view estimated arrival times (ETA), access route information, and make informed travel decisions while providing transport operators with fleet monitoring and management capabilities.

---

## 📖 Overview

Public transport users in Nairobi often face uncertainty regarding matatu locations, arrival times, and route availability. MatatuTrack KE addresses these challenges by providing a centralized platform that delivers live vehicle tracking, route visibility, and operational analytics.

The system consists of three major modules:

* Passenger Portal
* Driver Console
* Administrator Dashboard

Together, these modules create a connected ecosystem that improves transparency, efficiency, and commuter experience.

---

## ✨ Key Features

### Passenger Module

* Real-time matatu tracking
* Live route monitoring
* Estimated Time of Arrival (ETA)
* Route and stage information
* Fare information
* Saved routes
* Notifications and alerts

### Driver Module

* GPS location tracking
* Live trip monitoring
* Speed tracking
* Distance monitoring
* Passenger count updates
* Trip management

### Administrator Module

* Fleet monitoring
* Driver management
* User management
* Route management
* Transport analytics
* Reports generation
* Broadcast alerts

---

## 🛠 Technology Stack

### Frontend

* HTML5
* CSS3
* JavaScript
* Bootstrap 5

### Backend

* PHP

### Database

* MySQL

### Mapping & GIS

* Leaflet.js
* OpenStreetMap

### APIs

* Geolocation API
* Fetch API (AJAX)

### Visualization

* Chart.js

---

## 🏗 System Architecture

The platform follows a three-tier architecture consisting of presentation, application, and data layers.

![System Architecture](screenshots/system-architecture.png):

### Presentation Layer

* Passenger Dashboard
* Driver Dashboard
* Admin Dashboard

### Application Layer

* Authentication Services
* GPS Tracking Services
* ETA Computation Engine
* Route Management Logic
* Notification Services

### Data Layer

* MySQL Database
* Vehicle Data
* Route Data
* User Data
* Tracking Records

---

## 📸 System Screenshots

### Homepage

The landing page introduces the platform and highlights the real-time tracking capability available to commuters.

![Homepage](screenshots/homepage.png)

---

### Admin Dashboard

Administrators can monitor fleet operations, manage drivers, routes, users, and generate reports.

![Admin Dashboard](screenshots/admin-dashboard.png)

---

### Driver Dashboard

Drivers can activate GPS tracking, monitor trip statistics, and update operational data in real time.

![Driver Dashboard](screenshots/driver-dashboard.png)

---

### Passenger Live Map

Passengers can view active matatus, track routes, and monitor estimated arrival times through a live interactive map.

![Passenger Live Map](screenshots/passenger-live-map.png)

---

## 🚀 Installation

### Clone the Repository

```bash
git clone https://github.com/Bravin6/matatu-tracker-ke.git
```

### Navigate to Project Directory

```bash
cd matatu-tracker-ke
```

### Configure Database

1. Create a MySQL database.
2. Import the provided SQL file.
3. Update database credentials in the configuration file.

### Run the Application

Place the project inside:

```text
xampp/htdocs/
```

Start:

* Apache
* MySQL

Open:

```text
http://localhost/matatu_project
```

---

## 🔐 Security Features

* Role-Based Access Control (RBAC)
* Session Management
* Password Hashing
* Input Validation
* SQL Injection Protection
* XSS Protection

---

## 📱 Responsive Design

The platform is optimized for:

* Desktop
* Tablet
* Mobile Devices

---

## 🌍 Future Enhancements

* Mobile Application (Android & iOS)
* AI-Based ETA Prediction
* Traffic-Aware Route Optimization
* Push Notifications
* Digital Ticketing
* M-Pesa Integration
* SACCO Integration
* GTFS-Realtime Support

---

## 🎓 Academic Project

**Final Year Project**

Bachelor of Science in Information Technology

Jomo Kenyatta University of Agriculture and Technology (JKUAT)

**Author:** Masinde Bravin Wekhuyi

**Year:** 2026

---

## 👨‍💻 Author

**Masinde Bravin Wekhuyi**

GitHub: https://github.com/Bravin6

---

## 📄 License

This project is licensed under the MIT License.

See the LICENSE file for more information.

