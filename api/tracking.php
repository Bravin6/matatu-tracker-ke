<?php
// ============================================================
// Real-Time Tracking API
// POST: update_location, set_offline
// GET:  get_active, get_matatu, driver_stats
// ============================================================
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

$action = $_GET['action'] ?? '';

// ============================================================
// POST: Update Location
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'update_location') {
        $matatu_id      = intval($input['matatu_id'] ?? 0);
        $driver_id      = intval($input['driver_id'] ?? 0);
        $lat            = floatval($input['latitude'] ?? 0);
        $lng            = floatval($input['longitude'] ?? 0);
        $speed          = floatval($input['speed_kmh'] ?? 0);
        $heading        = floatval($input['heading'] ?? 0);
        $accuracy       = floatval($input['accuracy'] ?? 0);
        $passengers     = intval($input['passenger_count'] ?? 0);
        $current_stage  = $input['current_stage_id'] ?: null;
        $status         = in_array($input['status'] ?? '', ['active','idle','offline','breakdown'])
                          ? $input['status'] : 'active';

        if (!$matatu_id || !$driver_id || !$lat || !$lng) {
            jsonResponse(['error' => true, 'message' => 'Missing required fields'], 400);
        }

        try {
            $db = Database::getConnection();

            // Upsert live_tracking
            $sql = "INSERT INTO live_tracking 
                        (matatu_id, driver_id, latitude, longitude, speed_kmh, heading, accuracy, passenger_count, current_stage_id, status, last_updated)
                    VALUES 
                        (:mid, :did, :lat, :lng, :spd, :hdg, :acc, :pax, :cstage, :stat, NOW())
                    ON DUPLICATE KEY UPDATE
                        latitude=:lat2, longitude=:lng2, speed_kmh=:spd2, heading=:hdg2,
                        accuracy=:acc2, passenger_count=:pax2, current_stage_id=:cstage2,
                        status=:stat2, last_updated=NOW()";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':mid'=>$matatu_id, ':did'=>$driver_id,
                ':lat'=>$lat, ':lng'=>$lng, ':spd'=>$speed, ':hdg'=>$heading,
                ':acc'=>$accuracy, ':pax'=>$passengers, ':cstage'=>$current_stage, ':stat'=>$status,
                ':lat2'=>$lat, ':lng2'=>$lng, ':spd2'=>$speed, ':hdg2'=>$heading,
                ':acc2'=>$accuracy, ':pax2'=>$passengers, ':cstage2'=>$current_stage, ':stat2'=>$status
            ]);

            // Log to history (every call)
            $hist = $db->prepare("INSERT INTO location_history (matatu_id, driver_id, latitude, longitude, speed_kmh, heading) VALUES (:m,:d,:la,:lo,:s,:h)");
            $hist->execute([':m'=>$matatu_id,':d'=>$driver_id,':la'=>$lat,':lo'=>$lng,':s'=>$speed,':h'=>$heading]);

            jsonResponse(['success' => true, 'timestamp' => date('c')]);
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    if ($action === 'set_offline') {
        $matatu_id = intval($input['matatu_id'] ?? 0);
        $status = in_array($input['status'] ?? '', ['offline','breakdown']) ? $input['status'] : 'offline';
        try {
            $db = Database::getConnection();
            $db->prepare("UPDATE live_tracking SET status=:s, last_updated=NOW() WHERE matatu_id=:m")
               ->execute([':s'=>$status, ':m'=>$matatu_id]);
            jsonResponse(['success' => true]);
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    jsonResponse(['error' => true, 'message' => 'Unknown action'], 400);
}

// ============================================================
// GET: Active Matatus
// ============================================================
if ($action === 'get_active') {
    try {
        $db = Database::getConnection();
        // Only include matatus updated in last 2 minutes
        $stmt = $db->prepare("
            SELECT
                m.id, m.registration_plate AS plate, m.sacco_name AS sacco,
                m.capacity, m.route_id,
                lt.latitude AS lat, lt.longitude AS lng,
                lt.speed_kmh AS speed, lt.passenger_count AS passengers,
                lt.heading, lt.status, lt.last_updated,
                r.route_number, r.route_name, r.color_code AS color,
                r.fare_min, r.fare_max,
                u.full_name AS driver, u.phone AS driver_phone,
                s1.stage_name AS current_stage,
                s2.stage_name AS next_stage,
                TIMESTAMPDIFF(MINUTE, lt.last_updated, NOW()) AS mins_since_update
            FROM matatus m
            JOIN live_tracking lt ON m.id = lt.matatu_id
            JOIN routes r ON m.route_id = r.id
            LEFT JOIN users u ON m.driver_id = u.id
            LEFT JOIN stages s1 ON lt.current_stage_id = s1.id
            LEFT JOIN stages s2 ON lt.next_stage_id = s2.id
            WHERE lt.status IN ('active','idle')
              AND lt.last_updated >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
            ORDER BY lt.last_updated DESC
        ");
        $stmt->execute();
        $matatus = $stmt->fetchAll();

        // Compute ETA estimates (simple: distance to next stage / avg speed)
        foreach ($matatus as &$m) {
            $m['eta_min'] = $m['speed'] > 5
                ? max(1, round(2.5 / max($m['speed'], 1) * 60))
                : null;
        }

        jsonResponse(['success' => true, 'matatus' => $matatus, 'timestamp' => date('c')]);
    } catch (Exception $e) {
        // Return demo data if DB unavailable
        jsonResponse(['success' => true, 'matatus' => getDemoMatatus(), 'demo' => true]);
    }
}

// ============================================================
// GET: Single matatu details
// ============================================================
if ($action === 'get_matatu') {
    $id = intval($_GET['id'] ?? 0);
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT m.*, lt.latitude, lt.longitude, lt.speed_kmh, lt.passenger_count,
                   lt.heading, lt.status, lt.last_updated,
                   r.route_number, r.route_name, r.color_code, r.fare_min, r.fare_max,
                   r.origin, r.destination,
                   u.full_name AS driver_name, u.phone AS driver_phone,
                   s1.stage_name AS current_stage, s2.stage_name AS next_stage
            FROM matatus m
            LEFT JOIN live_tracking lt ON m.id = lt.matatu_id
            LEFT JOIN routes r ON m.route_id = r.id
            LEFT JOIN users u ON m.driver_id = u.id
            LEFT JOIN stages s1 ON lt.current_stage_id = s1.id
            LEFT JOIN stages s2 ON lt.next_stage_id = s2.id
            WHERE m.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $matatu = $stmt->fetch();
        if ($matatu) jsonResponse(['success' => true, 'matatu' => $matatu]);
        else jsonResponse(['error' => true, 'message' => 'Not found'], 404);
    } catch (Exception $e) {
        jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
    }
}

// ============================================================
// GET: Driver stats
// ============================================================
if ($action === 'driver_stats') {
    $driver_id = intval($_GET['driver_id'] ?? 0);
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT
                COUNT(*) AS trips_today,
                SUM(passengers_carried) AS total_passengers,
                AVG(total_distance_km) AS avg_distance
            FROM trips
            WHERE driver_id = :did
              AND DATE(trip_start) = CURDATE()
        ");
        $stmt->execute([':did' => $driver_id]);
        $stats = $stmt->fetch();
        jsonResponse(['success' => true, ...$stats]);
    } catch (Exception $e) {
        jsonResponse(['success' => true, 'trips_today' => 0, 'total_passengers' => 0]);
    }
}

// ============================================================
// GET: Route history for a matatu
// ============================================================
if ($action === 'route_history') {
    $matatu_id = intval($_GET['matatu_id'] ?? 0);
    $minutes   = intval($_GET['minutes'] ?? 60);
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT latitude, longitude, speed_kmh, recorded_at
            FROM location_history
            WHERE matatu_id = :mid
              AND recorded_at >= DATE_SUB(NOW(), INTERVAL :mins MINUTE)
            ORDER BY recorded_at ASC
            LIMIT 500
        ");
        $stmt->execute([':mid' => $matatu_id, ':mins' => $minutes]);
        $points = $stmt->fetchAll();
        jsonResponse(['success' => true, 'points' => $points]);
    } catch (Exception $e) {
        jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
    }
}

// ============================================================
// Demo data fallback
// ============================================================
function getDemoMatatus(): array {
    return [
        ['id'=>1,'plate'=>'KDA 123A','sacco'=>'Forward Travelers','capacity'=>14,'route_id'=>1,
         'lat'=>-1.3317,'lng'=>36.7877,'speed'=>45.5,'passengers'=>8,'heading'=>225,'status'=>'active',
         'route_number'=>'Route 111','route_name'=>'CBD – Rongai','color'=>'#FF5722',
         'fare_min'=>70,'fare_max'=>100,'driver'=>'John Kamau','current_stage'=>'Langata Road',
         'next_stage'=>'Galleria','eta_min'=>12,'mins_since_update'=>0],
        ['id'=>2,'plate'=>'KDB 456B','sacco'=>'City Hoppa','capacity'=>33,'route_id'=>2,
         'lat'=>-1.2750,'lng'=>36.8400,'speed'=>22.0,'passengers'=>25,'heading'=>90,'status'=>'active',
         'route_number'=>'Route 23','route_name'=>'CBD – Eastleigh','color'=>'#2196F3',
         'fare_min'=>30,'fare_max'=>50,'driver'=>'Peter Mwangi','current_stage'=>'Pangani',
         'next_stage'=>'Eastleigh','eta_min'=>8,'mins_since_update'=>1],
        ['id'=>3,'plate'=>'KDC 789C','sacco'=>'Westlands Express','capacity'=>14,'route_id'=>3,
         'lat'=>-1.2650,'lng'=>36.8058,'speed'=>35.0,'passengers'=>6,'heading'=>135,'status'=>'active',
         'route_number'=>'Route 44','route_name'=>'Westlands – CBD','color'=>'#4CAF50',
         'fare_min'=>30,'fare_max'=>50,'driver'=>'Grace Wanjiru','current_stage'=>'Sarit Center',
         'next_stage'=>'Kencom','eta_min'=>15,'mins_since_update'=>0],
        ['id'=>4,'plate'=>'KDD 012D','sacco'=>'Githurai Riders','capacity'=>14,'route_id'=>4,
         'lat'=>-1.2267,'lng'=>36.8756,'speed'=>50.0,'passengers'=>11,'heading'=>0,'status'=>'active',
         'route_number'=>'Route 58','route_name'=>'CBD – Githurai 45','color'=>'#9C27B0',
         'fare_min'=>50,'fare_max'=>80,'driver'=>'Samuel Odhiambo','current_stage'=>'Roysambu',
         'next_stage'=>'Githurai 45','eta_min'=>20,'mins_since_update'=>2]
    ];
}

jsonResponse(['error' => true, 'message' => 'Invalid request'], 400);
