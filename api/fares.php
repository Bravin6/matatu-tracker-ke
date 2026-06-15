<?php
// ============================================================
// api/fares.php  — Fare schedule & real-time pricing
// Actions: schedule | current | chart_data
// ============================================================
ob_start();

require_once __DIR__ . '/../includes/config.php';

ob_clean();

// FIX: Release the session file lock immediately.
// fares.php doesn't need to write to the session at all, but
// require_once config.php calls startSecureSession() which locks
// the session file. If wallet.php runs concurrently (Promise.all),
// it blocks waiting for this lock and $_SESSION appears empty,
// making isLoggedIn() return false and the balance come back as 0.
// session_write_close() releases the lock so wallet.php can read
// the session correctly.
startSecureSession();
session_write_close();

header('Content-Type: application/json');
header('Cache-Control: max-age=300');
error_reporting(0);

$db     = Database::getConnection();
$action = $_GET['action'] ?? 'chart_data';

// ============================================================
// Load entire fare_schedule in ONE query — prevents 192 DB hits
// ============================================================
function loadAllSchedules($db) {
    $rows = $db->query(
        "SELECT route_id, label, hour_start, hour_end, multiplier, flat_fare, color
         FROM fare_schedule WHERE is_active = 1
         ORDER BY route_id, hour_start"
    )->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($rows as $row) {
        $map[(int)$row['route_id']][] = $row;
    }
    return $map;
}

function findBandInMap($scheduleMap, $routeId, $hour) {
    if (empty($scheduleMap[$routeId])) return null;
    $match = null;
    foreach ($scheduleMap[$routeId] as $band) {
        if ((int)$band['hour_start'] <= $hour && (int)$band['hour_end'] >= $hour) {
            $match = $band;
        }
    }
    return $match;
}

function computeFareFromMap($scheduleMap, $routeId, $hour, $fareMin, $fareMax) {
    $base  = ((float)$fareMin + (float)$fareMax) / 2;
    $sched = findBandInMap($scheduleMap, $routeId, $hour);
    if ($sched) {
        if (!empty($sched['flat_fare'])) return round((float)$sched['flat_fare'], 0);
        return round($base * (float)$sched['multiplier'], 0);
    }
    $defaults = [
        0=>0.85,1=>0.80,2=>0.80,3=>0.80,4=>0.85,5=>0.90,
        6=>1.40,7=>1.60,8=>1.50,9=>1.30,10=>1.10,11=>1.00,
        12=>1.05,13=>1.00,14=>1.05,15=>1.10,16=>1.25,17=>1.55,
        18=>1.60,19=>1.40,20=>1.20,21=>1.05,22=>0.90,23=>0.85,
    ];
    return round($base * ($defaults[$hour] ?? 1.0), 0);
}

function computeFare($db, $routeId, $hour) {
    $s = $db->prepare(
        "SELECT multiplier, flat_fare FROM fare_schedule
         WHERE route_id=:r AND is_active=1 AND hour_start<=:h AND hour_end>=:h
         ORDER BY hour_start DESC LIMIT 1"
    );
    $s->execute([':r'=>$routeId,':h'=>$hour]);
    $sched = $s->fetch(PDO::FETCH_ASSOC);
    $r = $db->prepare("SELECT fare_min, fare_max FROM routes WHERE id=:id");
    $r->execute([':id'=>$routeId]);
    $route = $r->fetch(PDO::FETCH_ASSOC);
    if (!$route) return 50;
    $base = ((float)$route['fare_min'] + (float)$route['fare_max']) / 2;
    if ($sched) {
        if (!empty($sched['flat_fare'])) return round((float)$sched['flat_fare'], 0);
        return round($base * (float)$sched['multiplier'], 0);
    }
    $defaults=[0=>0.85,1=>0.80,2=>0.80,3=>0.80,4=>0.85,5=>0.90,6=>1.40,7=>1.60,
               8=>1.50,9=>1.30,10=>1.10,11=>1.00,12=>1.05,13=>1.00,14=>1.05,
               15=>1.10,16=>1.25,17=>1.55,18=>1.60,19=>1.40,20=>1.20,21=>1.05,
               22=>0.90,23=>0.85];
    return round($base * ($defaults[$hour] ?? 1.0), 0);
}

function getBand($db, $routeId, $hour) {
    $s = $db->prepare(
        "SELECT label, color FROM fare_schedule
         WHERE route_id=:r AND is_active=1 AND hour_start<=:h AND hour_end>=:h
         ORDER BY hour_start DESC LIMIT 1"
    );
    $s->execute([':r'=>$routeId,':h'=>$hour]);
    return $s->fetch(PDO::FETCH_ASSOC);
}

// ============================================================
// GET: chart_data
// ============================================================
if ($action === 'chart_data') {
    try {
        $routes = $db->query(
            "SELECT id, route_number, route_name, fare_min, fare_max, color_code
             FROM routes WHERE is_active=1 ORDER BY route_number"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($routes)) {
            jsonResponse(['success'=>false,'message'=>'No active routes found in database.']);
        }

        $scheduleMap = loadAllSchedules($db);
        $now         = (int)date('H');
        $datasets    = [];

        foreach ($routes as $route) {
            $rid     = (int)$route['id'];
            $fareMin = (float)$route['fare_min'];
            $fareMax = (float)$route['fare_max'];
            $base    = ($fareMin + $fareMax) / 2;
            $hourly  = [];
            for ($h = 0; $h < 24; $h++) {
                $hourly[] = (float)computeFareFromMap($scheduleMap, $rid, $h, $fareMin, $fareMax);
            }
            $currentFare = $hourly[$now];
            $multiplier  = $base > 0 ? round($currentFare / $base, 2) : 1.0;
            $datasets[]  = [
                'route_id'           => $rid,
                'route_number'       => $route['route_number'],
                'route_name'         => $route['route_name'],
                'color'              => $route['color_code'],
                'fare_min'           => $fareMin,
                'fare_max'           => $fareMax,
                'hourly_fares'       => $hourly,
                'current_fare'       => (float)$currentFare,
                'current_multiplier' => (float)$multiplier,
            ];
        }

        $firstId = (int)($routes[0]['id'] ?? 0);
        $bands   = [];
        if (!empty($scheduleMap[$firstId])) {
            foreach ($scheduleMap[$firstId] as $b) {
                $bands[] = [
                    'label'      => $b['label'],
                    'hour_start' => (int)$b['hour_start'],
                    'hour_end'   => (int)$b['hour_end'],
                    'color'      => $b['color'],
                    'multiplier' => (float)$b['multiplier'],
                ];
            }
        }

        jsonResponse([
            'success' => true,
            'data'    => ['datasets'=>$datasets,'bands'=>$bands,'current_hour'=>$now],
        ]);
    } catch (Exception $e) {
        jsonResponse(['success'=>false,'message'=>'DB error: '.$e->getMessage()]);
    }
}

// ============================================================
// GET: current
// ============================================================
if ($action === 'current') {
    $rid = intval($_GET['route_id'] ?? 0);
    if (!$rid) jsonResponse(['error'=>true,'message'=>'route_id required'], 400);
    $now  = (int)date('H');
    $fare = computeFare($db, $rid, $now);
    $band = getBand($db, $rid, $now);
    jsonResponse([
        'success'      => true,
        'fare'         => (float)$fare,
        'hour'         => $now,
        'period_label' => $band ? $band['label'] : 'Standard',
        'period_color' => $band ? $band['color']  : '#00E676',
    ]);
}

// ============================================================
// GET: schedule
// ============================================================
if ($action === 'schedule') {
    $rid   = intval($_GET['route_id'] ?? 0);
    $where = $rid ? "WHERE fs.route_id=:rid" : "";
    $s = $db->prepare(
        "SELECT fs.*, r.route_number, r.route_name FROM fare_schedule fs
         JOIN routes r ON fs.route_id=r.id $where
         ORDER BY fs.route_id, fs.hour_start"
    );
    $s->execute($rid ? [':rid'=>$rid] : []);
    jsonResponse(['success'=>true,'data'=>$s->fetchAll(PDO::FETCH_ASSOC)]);
}

jsonResponse(['error'=>true,'message'=>'Unknown action'], 400);