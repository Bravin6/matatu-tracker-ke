<?php
// ============================================================
// Routes API
// ============================================================
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    try {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT r.*,
                   COUNT(DISTINCT rs.stage_id) AS stage_count,
                   COUNT(DISTINCT m.id) AS matatu_count
            FROM routes r
            LEFT JOIN route_stages rs ON r.id = rs.route_id
            LEFT JOIN matatus m ON r.id = m.route_id AND m.is_active=1
            WHERE r.is_active=1
            GROUP BY r.id
            ORDER BY r.route_number
        ");
        jsonResponse(['success' => true, 'routes' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        jsonResponse(['success' => true, 'routes' => getDemoRoutes()]);
    }
}

if ($action === 'stages') {
    $route_id = intval($_GET['route_id'] ?? 0);
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT s.*, rs.stop_order, rs.distance_from_origin_km, rs.estimated_time_from_origin_min
            FROM stages s
            JOIN route_stages rs ON s.id = rs.stage_id
            WHERE rs.route_id = :rid
            ORDER BY rs.stop_order
        ");
        $stmt->execute([':rid' => $route_id]);
        jsonResponse(['success' => true, 'stages' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
    }
}

if ($action === 'all_stages') {
    try {
        $db = Database::getConnection();
        $stages = $db->query("SELECT * FROM stages ORDER BY stage_name")->fetchAll();
        jsonResponse(['success' => true, 'stages' => $stages]);
    } catch (Exception $e) {
        jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
    }
}

function getDemoRoutes(): array {
    return [
        ['id'=>1,'route_number'=>'Route 111','route_name'=>'CBD – Rongai','origin'=>'Kencom','destination'=>'Rongai','fare_min'=>70,'fare_max'=>100,'color_code'=>'#FF5722','stage_count'=>5,'matatu_count'=>3],
        ['id'=>2,'route_number'=>'Route 23','route_name'=>'CBD – Eastleigh','origin'=>'Kencom','destination'=>'Eastleigh','fare_min'=>30,'fare_max'=>50,'color_code'=>'#2196F3','stage_count'=>3,'matatu_count'=>2],
        ['id'=>3,'route_number'=>'Route 44','route_name'=>'Westlands – CBD','origin'=>'Westlands','destination'=>'Kencom','fare_min'=>30,'fare_max'=>50,'color_code'=>'#4CAF50','stage_count'=>3,'matatu_count'=>2],
        ['id'=>4,'route_number'=>'Route 58','route_name'=>'CBD – Githurai 45','origin'=>'Kencom','destination'=>'Githurai 45','fare_min'=>50,'fare_max'=>80,'color_code'=>'#9C27B0','stage_count'=>5,'matatu_count'=>2],
        ['id'=>5,'route_number'=>'Route 33','route_name'=>'CBD – Kawangware','origin'=>'Kencom','destination'=>'Kawangware','fare_min'=>40,'fare_max'=>60,'color_code'=>'#FF9800','stage_count'=>3,'matatu_count'=>1],
        ['id'=>6,'route_number'=>'Route 9','route_name'=>'CBD – Ngong','origin'=>'Archives','destination'=>'Ngong Town','fare_min'=>60,'fare_max'=>90,'color_code'=>'#00BCD4','stage_count'=>4,'matatu_count'=>1],
        ['id'=>7,'route_number'=>'Route 45','route_name'=>'CBD – Thika','origin'=>'Kencom','destination'=>'Thika Town','fare_min'=>80,'fare_max'=>120,'color_code'=>'#F44336','stage_count'=>4,'matatu_count'=>1],
        ['id'=>8,'route_number'=>'Route 14','route_name'=>'CBD – South B/C','origin'=>'Kencom','destination'=>'South C','fare_min'=>35,'fare_max'=>55,'color_code'=>'#607D8B','stage_count'=>3,'matatu_count'=>1]
    ];
}

jsonResponse(['error' => true, 'message' => 'Invalid request'], 400);
