<?php
require_once 'includes/config.php';
requireLogin();
$user = getCurrentUser();
if ($user['role'] !== 'admin') {
    header('Location: passenger-dashboard.php');
    exit;
}

// ============================================================
// CRUD HANDLERS
// ============================================================
$crudMsg = ''; $crudType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    try {
        $db = Database::getConnection();
        $action = $_POST['crud_action'];
        if ($action === 'create_matatu') {
            $db->prepare("INSERT INTO matatus (registration_plate,sacco_name,route_id,driver_id,capacity,vehicle_model,color) VALUES (?,?,?,?,?,?,?)")->execute([strtoupper(trim($_POST['plate'])),$_POST['sacco']?:null,$_POST['route_id']?:null,$_POST['driver_id']?:null,(int)($_POST['capacity']?:14),$_POST['model']?:null,$_POST['color']?:null]);
            $crudMsg='Matatu added.';$crudType='success';
        } elseif ($action==='update_matatu') {
            $db->prepare("UPDATE matatus SET registration_plate=?,sacco_name=?,route_id=?,driver_id=?,capacity=?,vehicle_model=?,color=?,is_active=? WHERE id=?")->execute([strtoupper(trim($_POST['plate'])),$_POST['sacco']?:null,$_POST['route_id']?:null,$_POST['driver_id']?:null,(int)$_POST['capacity'],$_POST['model']?:null,$_POST['color']?:null,isset($_POST['is_active'])?1:0,(int)$_POST['matatu_id']]);
            $crudMsg='Matatu updated.';$crudType='success';
        } elseif ($action==='delete_matatu') {
            $db->prepare("DELETE FROM matatus WHERE id=?")->execute([(int)$_POST['matatu_id']]);
            $crudMsg='Matatu deleted.';$crudType='warning';
        } elseif ($action==='create_route') {
            $db->prepare("INSERT INTO routes (route_number,route_name,origin,destination,fare_min,fare_max,distance_km,avg_duration_minutes,color_code) VALUES (?,?,?,?,?,?,?,?,?)")->execute([strtoupper(trim($_POST['route_number'])),$_POST['route_name'],$_POST['origin'],$_POST['destination'],(float)$_POST['fare_min'],(float)$_POST['fare_max'],$_POST['distance_km']?:null,$_POST['duration']?:null,$_POST['color_code']?:'#00C853']);
            $crudMsg='Route created.';$crudType='success';
        } elseif ($action==='update_route') {
            $db->prepare("UPDATE routes SET route_number=?,route_name=?,origin=?,destination=?,fare_min=?,fare_max=?,distance_km=?,avg_duration_minutes=?,color_code=?,is_active=? WHERE id=?")->execute([strtoupper(trim($_POST['route_number'])),$_POST['route_name'],$_POST['origin'],$_POST['destination'],(float)$_POST['fare_min'],(float)$_POST['fare_max'],$_POST['distance_km']?:null,$_POST['duration']?:null,$_POST['color_code']?:'#00C853',isset($_POST['is_active'])?1:0,(int)$_POST['route_id']]);
            $crudMsg='Route updated.';$crudType='success';
        } elseif ($action==='delete_route') {
            $db->prepare("UPDATE routes SET is_active=0 WHERE id=?")->execute([(int)$_POST['route_id']]);
            $crudMsg='Route deactivated.';$crudType='warning';
        } elseif ($action==='create_user') {
            $db->prepare("INSERT INTO users (full_name,email,phone,password_hash,role) VALUES (?,?,?,?,?)")->execute([$_POST['full_name'],$_POST['email'],$_POST['phone'],password_hash($_POST['password'],PASSWORD_BCRYPT),$_POST['role']]);
            $crudMsg='User created.';$crudType='success';
        } elseif ($action==='update_user') {
            if(!empty($_POST['password'])){
                $db->prepare("UPDATE users SET full_name=?,email=?,phone=?,role=?,is_active=?,password_hash=? WHERE id=?")->execute([$_POST['full_name'],$_POST['email'],$_POST['phone'],$_POST['role'],isset($_POST['is_active'])?1:0,password_hash($_POST['password'],PASSWORD_BCRYPT),(int)$_POST['user_id']]);
            } else {
                $db->prepare("UPDATE users SET full_name=?,email=?,phone=?,role=?,is_active=? WHERE id=?")->execute([$_POST['full_name'],$_POST['email'],$_POST['phone'],$_POST['role'],isset($_POST['is_active'])?1:0,(int)$_POST['user_id']]);
            }
            $crudMsg='User updated.';$crudType='success';
        } elseif ($action==='delete_user') {
            $db->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([(int)$_POST['user_id']]);
            $crudMsg='User deactivated.';$crudType='warning';
        } elseif ($action==='create_alert') {
            $db->prepare("INSERT INTO system_alerts (title,message,alert_type,is_active,created_by) VALUES (?,?,?,1,?)")->execute([$_POST['title'],$_POST['message'],$_POST['alert_type'],$user['id']]);
            $crudMsg='Alert broadcast.';$crudType='success';
        } elseif ($action==='toggle_alert') {
            $db->prepare("UPDATE system_alerts SET is_active=NOT is_active WHERE id=?")->execute([(int)$_POST['alert_id']]);
            $crudMsg='Alert toggled.';$crudType='success';
        } elseif ($action==='delete_alert') {
            $db->prepare("DELETE FROM system_alerts WHERE id=?")->execute([(int)$_POST['alert_id']]);
            $crudMsg='Alert deleted.';$crudType='warning';
        } elseif ($action==='wallet_topup') {
            $uid      = (int)$_POST['topup_user_id'];
            $amount   = round((float)$_POST['topup_amount'], 2);
            $mpesa    = trim($_POST['mpesa_code']);
            $note     = trim($_POST['topup_note'] ?: 'M-PESA top-up by admin');
            if (!$uid || $amount <= 0)          { $crudMsg='Invalid user or amount.'; $crudType='error'; }
            elseif (empty($mpesa))              { $crudMsg='M-PESA code is required.'; $crudType='error'; }
            else {
                // Check duplicate code
                $dup=$db->prepare("SELECT id FROM wallet_transactions WHERE mpesa_code=? LIMIT 1");
                $dup->execute([$mpesa]);
                if ($dup->fetch()) { $crudMsg='This M-PESA code has already been used.'; $crudType='error'; }
                else {
                    $db->prepare("INSERT IGNORE INTO wallets (user_id,balance) VALUES (?,0.00)")->execute([$uid]);
                    $db->prepare("UPDATE wallets SET balance=balance+?,total_topped=total_topped+?,updated_at=NOW() WHERE user_id=?")->execute([$amount,$amount,$uid]);
                    $w=$db->prepare("SELECT * FROM wallets WHERE user_id=?"); $w->execute([$uid]); $wrow=$w->fetch();
                    $db->prepare("INSERT INTO wallet_transactions (wallet_id,user_id,type,amount,balance_after,description,mpesa_code,performed_by) VALUES (?,?,'credit',?,?,?,?,?)")
                       ->execute([$wrow['id'],$uid,$amount,$wrow['balance'],$note,$mpesa,$user['id']]);
                    $crudMsg='KES '.number_format($amount,2).' credited successfully.'; $crudType='success';
                }
            }
        } elseif ($action==='wallet_topup') {
            $uid      = (int)$_POST['topup_user_id'];
            $amount   = round((float)$_POST['topup_amount'], 2);
            $mpesa    = trim($_POST['mpesa_code']);
            $note     = trim($_POST['topup_note'] ?: 'M-PESA top-up');
            if (!$uid || $amount <= 0)      throw new Exception('Invalid user or amount.');
            if (empty($mpesa))              throw new Exception('M-PESA code is required.');
            if ($amount > 50000)            throw new Exception('Max top-up is KES 50,000.');
            // Duplicate check
            $dup = $db->prepare("SELECT id FROM wallet_transactions WHERE mpesa_code=? LIMIT 1");
            $dup->execute([$mpesa]);
            if ($dup->fetch())              throw new Exception('This M-PESA code was already used.');
            $db->beginTransaction();
            $db->prepare("INSERT IGNORE INTO wallets (user_id,balance) VALUES (?,0)")->execute([$uid]);
            $db->prepare("UPDATE wallets SET balance=balance+?,total_topped=total_topped+?,updated_at=NOW() WHERE user_id=?")->execute([$amount,$amount,$uid]);
            $w = $db->prepare("SELECT * FROM wallets WHERE user_id=?");
            $w->execute([$uid]); $wal = $w->fetch();
            $db->prepare("INSERT INTO wallet_transactions (wallet_id,user_id,type,amount,balance_after,description,mpesa_code,performed_by) VALUES (?,?,'credit',?,?,?,?,?)")->execute([$wal['id'],$uid,$amount,$wal['balance'],$note,$mpesa,$user['id']]);
            $db->commit();
            $crudMsg="KES ".number_format($amount,2)." credited to wallet."; $crudType='success';
        }
    } catch (Exception $e) { $crudMsg='Error: '.$e->getMessage();$crudType='error'; }
}

// ============================================================
// FETCH DATA
// ============================================================
try {
    $db = Database::getConnection();
    $activeMatatus = $db->query("SELECT COUNT(*) FROM live_tracking WHERE status='active' AND last_updated>=DATE_SUB(NOW(),INTERVAL 2 MINUTE)")->fetchColumn();
    $totalUsers    = $db->query("SELECT COUNT(*) FROM users WHERE role='passenger'")->fetchColumn();
    $totalDrivers  = $db->query("SELECT COUNT(*) FROM users WHERE role='driver'")->fetchColumn();
    $tripsToday    = $db->query("SELECT COUNT(*) FROM trips WHERE DATE(trip_start)=CURDATE()")->fetchColumn();
    $tripsWeek     = $db->query("SELECT COUNT(*) FROM trips WHERE trip_start>=DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetchColumn();
    $tripsMonth    = $db->query("SELECT COUNT(*) FROM trips WHERE trip_start>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetchColumn();
    $allMatatus    = $db->query("SELECT m.*,lt.status,lt.speed_kmh,lt.passenger_count,lt.last_updated,r.route_number,u.full_name AS driver FROM matatus m LEFT JOIN live_tracking lt ON m.id=lt.matatu_id LEFT JOIN routes r ON m.route_id=r.id LEFT JOIN users u ON m.driver_id=u.id ORDER BY lt.last_updated DESC")->fetchAll();
    $alerts        = $db->query("SELECT * FROM system_alerts WHERE is_active=1 ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $allRoutes     = $db->query("SELECT * FROM routes ORDER BY route_number")->fetchAll();
    $allUsers      = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
    $allDrivers    = $db->query("SELECT u.*,m.registration_plate,r.route_number,lt.status AS tracking_status FROM users u LEFT JOIN matatus m ON u.id=m.driver_id LEFT JOIN routes r ON m.route_id=r.id LEFT JOIN live_tracking lt ON m.id=lt.matatu_id WHERE u.role='driver' ORDER BY u.full_name")->fetchAll();
    $allAlerts     = $db->query("SELECT sa.*,u.full_name AS created_by_name FROM system_alerts sa LEFT JOIN users u ON sa.created_by=u.id ORDER BY sa.created_at DESC")->fetchAll();
    $unassignedDrivers = $db->query("SELECT u.id,u.full_name FROM users u LEFT JOIN matatus m ON u.id=m.driver_id WHERE u.role='driver' AND m.id IS NULL")->fetchAll();
    $topFeedback   = $db->query("SELECT f.*,u.full_name,m.registration_plate FROM feedback f LEFT JOIN users u ON f.user_id=u.id LEFT JOIN matatus m ON f.matatu_id=m.id ORDER BY f.created_at DESC LIMIT 50")->fetchAll();
    $weeklyTrips   = $db->query("SELECT DATE(trip_start) AS day,COUNT(*) AS cnt FROM trips WHERE trip_start>=DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY DATE(trip_start) ORDER BY day")->fetchAll(PDO::FETCH_KEY_PAIR);
    $routeRatings  = $db->query("SELECT r.route_number,ROUND(AVG(f.rating),1) AS avg_rating FROM feedback f JOIN matatus m ON f.matatu_id=m.id JOIN routes r ON m.route_id=r.id GROUP BY r.id ORDER BY avg_rating DESC LIMIT 8")->fetchAll();
    $userGrowth    = $db->query("SELECT DATE_FORMAT(created_at,'%b') AS month,COUNT(*) AS cnt FROM users WHERE created_at>=DATE_SUB(NOW(),INTERVAL 6 MONTH) GROUP BY MONTH(created_at) ORDER BY created_at")->fetchAll(PDO::FETCH_KEY_PAIR);
    $fleetStatus   = $db->query("SELECT status,COUNT(*) AS cnt FROM live_tracking GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $avgSpeed      = $db->query("SELECT ROUND(AVG(speed_kmh),1) FROM live_tracking WHERE status='active'")->fetchColumn() ?: 0;
    // Wallet aggregates
    try {
        $walletPassengers = $db->query("SELECT u.id,u.full_name,u.email,u.phone,COALESCE(w.balance,0) AS balance,COALESCE(w.total_topped,0) AS total_topped,COALESCE(w.total_spent,0) AS total_spent,w.updated_at FROM users u LEFT JOIN wallets w ON u.id=w.user_id WHERE u.role='passenger' ORDER BY u.full_name")->fetchAll();
        $walletTotalFloat = $db->query("SELECT COALESCE(SUM(balance),0) FROM wallets w JOIN users u ON w.user_id=u.id WHERE u.role='passenger'")->fetchColumn();
        $walletTxRecent   = $db->query("SELECT wt.*,u.full_name,au.full_name AS admin_name FROM wallet_transactions wt JOIN users u ON wt.user_id=u.id LEFT JOIN users au ON wt.performed_by=au.id ORDER BY wt.created_at DESC LIMIT 50")->fetchAll();
    } catch(Exception $e2) {
        $walletPassengers=[]; $walletTotalFloat=0; $walletTxRecent=[];
    }
} catch (Exception $e) {
    $activeMatatus=4;$totalUsers=6;$totalDrivers=4;$tripsToday=23;$tripsWeek=142;$tripsMonth=580;
    $allMatatus=[];$alerts=[];$allRoutes=[];$allUsers=[];$allDrivers=[];$allAlerts=[];$unassignedDrivers=[];$topFeedback=[];
    $weeklyTrips=[];$routeRatings=[];$userGrowth=[];$fleetStatus=[];$avgSpeed=38.5;
    $walletPassengers=[];$walletTotalFloat=0;$walletTxRecent=[];
}

// Build chart arrays
$weekLabels=[]; $weekCounts=[];
for($i=6;$i>=0;$i--){$d=date('Y-m-d',strtotime("-$i days"));$weekLabels[]=date('D',strtotime($d));$weekCounts[]=(int)($weeklyTrips[$d]??0);}
$ratingLabels=array_column($routeRatings,'route_number');
$ratingValues=array_column($routeRatings,'avg_rating');
$ugLabels=array_keys($userGrowth);$ugValues=array_values($userGrowth);
$flA=(int)($fleetStatus['active']??4);$flI=(int)($fleetStatus['idle']??1);$flO=(int)($fleetStatus['offline']??max(0,count($allMatatus)-5));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>MatatuTrack — Admin Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
:root{--green:#00E676;--amber:#FFB300;--red:#FF3D3D;--blue:#2196F3;--purple:#9C27B0;--ink:#0A0F0D;--ink2:#141A16;--surface:#1A2218;--surface2:#212E22;--border:rgba(0,230,118,0.15);--text:#E8F5E9;--muted:#7A9B80;}
*{margin:0;padding:0;box-sizing:border-box;}
body{background:var(--ink);color:var(--text);font-family:'DM Sans',sans-serif;display:flex;min-height:100vh;}
.sidebar{width:240px;min-width:240px;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;height:100vh;position:sticky;top:0;overflow-y:auto;}
.sb-logo{display:flex;align-items:center;gap:.75rem;padding:1.5rem;border-bottom:1px solid var(--border);}
.sb-logo .ic{width:36px;height:36px;background:var(--amber);border-radius:8px;display:grid;place-items:center;color:var(--ink);font-size:.9rem;}
.sb-logo span{font-family:'Syne',sans-serif;font-weight:800;font-size:1.1rem;}
.sb-logo .badge{background:rgba(255,179,0,.2);color:var(--amber);border-radius:6px;padding:.15rem .5rem;font-size:.7rem;font-weight:700;margin-left:auto;}
.sb-sec{padding:1.25rem .75rem .5rem;font-size:.7rem;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;font-weight:600;}
.sb-item{display:flex;align-items:center;gap:.75rem;padding:.65rem 1rem;margin:0 .5rem;border-radius:10px;cursor:pointer;color:var(--muted);font-size:.88rem;font-weight:500;text-decoration:none;transition:all .2s;}
.sb-item:hover{background:var(--surface2);color:var(--text);}
.sb-item.active{background:rgba(255,179,0,.1);color:var(--amber);}
.sb-item i{width:18px;text-align:center;}
.sb-bottom{margin-top:auto;padding:1rem;border-top:1px solid var(--border);}
.sb-user{display:flex;align-items:center;gap:.75rem;padding:.75rem;background:var(--ink2);border-radius:12px;}
.sb-avatar{width:34px;height:34px;border-radius:50%;background:rgba(255,179,0,.2);display:grid;place-items:center;color:var(--amber);}
.main{flex:1;overflow-y:auto;}
.topbar{display:flex;align-items:center;justify-content:space-between;padding:1.25rem 2rem;border-bottom:1px solid var(--border);background:rgba(10,15,13,.8);backdrop-filter:blur(10px);position:sticky;top:0;z-index:50;}
.tb-title{font-family:'Syne',sans-serif;font-weight:800;font-size:1.3rem;}
.tb-sub{font-size:.8rem;color:var(--muted);margin-top:.1rem;}
.tb-actions{display:flex;gap:.75rem;}
.btn{padding:.5rem 1.1rem;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:.88rem;font-weight:500;cursor:pointer;transition:all .2s;border:none;display:inline-flex;align-items:center;gap:.4rem;}
.btn-primary{background:var(--green);color:var(--ink);}
.btn-primary:hover{background:#33eb91;}
.btn-ghost{background:var(--surface2);color:var(--muted);border:1px solid var(--border);}
.btn-ghost:hover{color:var(--text);}
.btn-amber{background:rgba(255,179,0,.15);color:var(--amber);border:1px solid rgba(255,179,0,.3);}
.btn-amber:hover{background:rgba(255,179,0,.25);}
.btn-red{background:rgba(255,61,61,.15);color:var(--red);border:1px solid rgba(255,61,61,.3);}
.btn-red:hover{background:rgba(255,61,61,.25);}
.btn-sm{padding:.3rem .7rem;font-size:.78rem;border-radius:7px;}
.btn-icon{width:30px;height:30px;padding:0;justify-content:center;border-radius:7px;}
.page{padding:2rem;max-width:1500px;}
.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1.25rem;margin-bottom:2rem;}
.kpi-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:1.5rem;position:relative;overflow:hidden;}
.kpi-card::after{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--accent);}
.kpi-icon{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;font-size:1.1rem;margin-bottom:1rem;background:var(--icon-bg);color:var(--accent);}
.kpi-val{font-family:'Syne',sans-serif;font-weight:800;font-size:2rem;}
.kpi-label{font-size:.78rem;color:var(--muted);margin-top:.25rem;}
.kpi-change{font-size:.78rem;margin-top:.5rem;}
.kpi-change.up{color:var(--green);}
.kpi-change.down{color:var(--red);}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;}
.grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:1.25rem;margin-bottom:1.5rem;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;}
.card-header{display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--border);}
.card-title{font-family:'Syne',sans-serif;font-weight:700;font-size:1rem;}
.card-sub{font-size:.78rem;color:var(--muted);}
.chart-wrap{padding:1rem 1.5rem 1.5rem;}
.data-table{width:100%;border-collapse:collapse;font-size:.85rem;}
.data-table th{padding:.75rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);font-weight:600;border-bottom:1px solid var(--border);}
.data-table td{padding:.75rem 1rem;border-bottom:1px solid rgba(0,230,118,.06);vertical-align:middle;}
.data-table tr:hover td{background:var(--surface2);}
.data-table tr:last-child td{border-bottom:none;}
.plate-badge{font-family:'Syne',sans-serif;font-weight:700;font-size:.85rem;background:var(--ink2);padding:.2rem .5rem;border-radius:6px;white-space:nowrap;}
.status-pill{padding:.25rem .6rem;border-radius:100px;font-size:.72rem;font-weight:600;white-space:nowrap;}
.s-active{background:rgba(0,230,118,.15);color:var(--green);}
.s-idle{background:rgba(255,179,0,.15);color:var(--amber);}
.s-offline{background:rgba(255,61,61,.15);color:var(--red);}
.actions-cell{display:flex;gap:.4rem;align-items:center;}
.map-card{height:380px;position:relative;}
#adminMap{width:100%;height:100%;}
.sec-page{display:none;}
.sec-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;}
.sec-title{font-family:'Syne',sans-serif;font-weight:800;font-size:1.2rem;}
.table-wrap{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;}
.table-scroll{overflow-x:auto;}
.table-toolbar{display:flex;gap:.75rem;padding:1rem 1.5rem;border-bottom:1px solid var(--border);align-items:center;flex-wrap:wrap;}
.search-input{background:var(--ink2);border:1px solid var(--border);border-radius:9px;padding:.5rem .9rem;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.85rem;outline:none;transition:border .2s;flex:1;max-width:300px;}
.search-input:focus{border-color:var(--green);}
.filter-select{background:var(--ink2);border:1px solid var(--border);border-radius:9px;padding:.5rem .9rem;color:var(--muted);font-size:.82rem;outline:none;}
.filter-select:focus{border-color:var(--green);color:var(--text);}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:999;align-items:center;justify-content:center;backdrop-filter:blur(8px);}
.modal-backdrop.open{display:flex;}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:2rem;width:min(520px,92%);position:relative;max-height:90vh;overflow-y:auto;}
.modal-title{font-family:'Syne',sans-serif;font-weight:800;font-size:1.25rem;margin-bottom:.3rem;}
.modal-sub{color:var(--muted);font-size:.85rem;margin-bottom:1.5rem;}
.modal-close{position:absolute;top:1rem;right:1rem;background:var(--surface2);border:none;color:var(--muted);width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:1rem;display:grid;place-items:center;}
.modal-close:hover{color:var(--text);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;}
.form-group{display:flex;flex-direction:column;gap:.4rem;margin-bottom:.75rem;}
.form-label{font-size:.78rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;}
.form-input{width:100%;background:var(--ink2);border:1px solid var(--border);border-radius:10px;padding:.65rem .9rem;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.88rem;outline:none;transition:border .2s;}
.form-input:focus{border-color:var(--green);}
select.form-input option{background:var(--ink2);}
textarea.form-input{resize:vertical;min-height:80px;}
.form-check{display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.88rem;}
.form-check input{accent-color:var(--green);width:16px;height:16px;}
.toast{position:fixed;bottom:2rem;right:2rem;z-index:9999;padding:.85rem 1.4rem;border-radius:12px;font-size:.88rem;font-weight:500;display:flex;align-items:center;gap:.6rem;transform:translateY(100px);opacity:0;transition:all .4s cubic-bezier(.34,1.56,.64,1);}
.toast.show{transform:translateY(0);opacity:1;}
.toast.success{background:#00E676;color:var(--ink);}
.toast.warning{background:var(--amber);color:var(--ink);}
.toast.error{background:var(--red);color:#fff;}
.confirm-dialog{background:var(--surface2);border:1px solid rgba(255,61,61,.3);border-radius:16px;padding:1.5rem;width:min(380px,90%);}
.confirm-dialog h3{font-family:'Syne',sans-serif;font-weight:700;margin-bottom:.5rem;}
.confirm-dialog p{font-size:.85rem;color:var(--muted);margin-bottom:1.25rem;}
.confirm-actions{display:flex;gap:.75rem;justify-content:flex-end;}
.scroll-page{overflow-y:auto;max-height:calc(100vh - 61px);}
.donut-wrap{display:flex;align-items:center;justify-content:center;gap:1.5rem;padding:1rem 1.5rem 1.5rem;}
/* WALLET ADMIN */
.wallet-kpi-row{display:grid;grid-template-columns:repeat(3,1fr);gap:1.25rem;margin-bottom:1.5rem;}
.wallet-user-row{display:flex;align-items:center;gap:0.75rem;padding:0.75rem 1rem;border-bottom:1px solid rgba(0,230,118,.06);transition:background .15s;}
.wallet-user-row:hover{background:var(--surface2);}
.wallet-user-row:last-child{border-bottom:none;}
.wu-avatar{width:34px;height:34px;border-radius:50%;background:rgba(0,230,118,.12);display:grid;place-items:center;color:var(--green);font-size:.85rem;flex-shrink:0;}
.wu-info{flex:1;min-width:0;}
.wu-name{font-size:.88rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.wu-phone{font-size:.75rem;color:var(--muted);}
.wu-bal{font-family:'Syne',sans-serif;font-weight:800;font-size:1rem;color:var(--green);white-space:nowrap;}
.wu-bal.low{color:var(--red);}
.topup-form{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:1.5rem;margin-bottom:1.5rem;}
.topup-title{font-family:'Syne',sans-serif;font-weight:800;font-size:1.05rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.6rem;}
.tx-admin-row{display:flex;align-items:center;gap:.75rem;padding:.7rem 1rem;border-bottom:1px solid rgba(0,230,118,.06);font-size:.83rem;}
.tx-admin-row:last-child{border-bottom:none;}
.tx-type-badge{padding:.2rem .55rem;border-radius:100px;font-size:.7rem;font-weight:700;white-space:nowrap;}
.tx-credit{background:rgba(0,230,118,.12);color:var(--green);}
.tx-debit{background:rgba(255,61,61,.12);color:var(--red);}

/* ═══════════════════════════════════════════
   PRINT / PDF STYLES
═══════════════════════════════════════════ */
@media print {
  body>*:not(#rpt-wrapper){display:none!important;}
  #rpt-wrapper{display:block!important;}
  .no-print{display:none!important;}
  body{background:#fff!important;color:#111!important;font-family:'DM Sans',sans-serif;display:block;}
  #rpt-wrapper{padding:0;width:100%;}
  .rpt-page{page-break-after:always;padding:2cm 1.8cm;}
  .rpt-page:last-child{page-break-after:avoid;}
  .rpt-head{display:flex;align-items:flex-start;justify-content:space-between;border-bottom:3px solid #00C853;padding-bottom:1rem;margin-bottom:1.5rem;}
  .rpt-logo{font-size:1.5rem;font-weight:800;color:#0A0F0D;}
  .rpt-logo small{display:block;font-size:.8rem;font-weight:400;color:#555;margin-top:.2rem;}
  .rpt-meta{text-align:right;font-size:.78rem;color:#555;}
  .rpt-meta strong{display:block;font-size:1rem;color:#0A0F0D;font-weight:700;}
  .rpt-h2{font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#0A0F0D;border-bottom:1px solid #ddd;padding-bottom:.35rem;margin:1.25rem 0 .75rem;}
  .rpt-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:.75rem;margin-bottom:1.25rem;}
  .rpt-kpi{background:#f5f5f5;border-radius:8px;padding:.85rem;border-left:4px solid #00C853;}
  .rpt-kpi-v{font-size:1.6rem;font-weight:800;color:#0A0F0D;}
  .rpt-kpi-l{font-size:.68rem;color:#666;text-transform:uppercase;letter-spacing:.04em;margin-top:.15rem;}
  .rpt-charts{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;}
  .rpt-cbox{background:#f9f9f9;border-radius:8px;padding:.85rem;}
  .rpt-ctitle{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#555;margin-bottom:.6rem;}
  .rpt-t{width:100%;border-collapse:collapse;font-size:.78rem;margin-bottom:1.25rem;}
  .rpt-t th{background:#0A0F0D;color:#fff;padding:.5rem .7rem;text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;}
  .rpt-t td{padding:.45rem .7rem;border-bottom:1px solid #eee;}
  .rpt-t tr:nth-child(even) td{background:#f9f9f9;}
  .rpt-foot{border-top:1px solid #ddd;padding-top:.6rem;margin-top:1.5rem;display:flex;justify-content:space-between;font-size:.7rem;color:#888;}
  .pp{display:inline-block;padding:.12rem .45rem;border-radius:100px;font-size:.65rem;font-weight:600;}
  .pp-on{background:#d4fce6;color:#006629;}
  .pp-off{background:#ffe0e0;color:#b00;}
  canvas{max-width:100%!important;}
}
</style>
</head>
<body>

<?php if($crudMsg): ?>
<div class="toast <?=$crudType?> show" id="pgToast">
  <i class="fas fa-<?=$crudType==='success'?'check-circle':($crudType==='warning'?'exclamation-triangle':'times-circle')?>"></i>
  <?=htmlspecialchars($crudMsg)?>
</div>
<script>
  setTimeout(()=>document.getElementById('pgToast').classList.remove('show'),4000);
  <?php if(strpos($crudMsg,'credited')!==false || strpos($crudMsg,'M-PESA')!==false || strpos($crudMsg,'wallet')!==false || strpos($crudMsg,'code')!==false): ?>
  window.addEventListener('DOMContentLoaded',()=>adminNav('wallets'));
  <?php endif; ?>
</script>
<?php endif; ?>

<!-- ══════════════════ SIDEBAR ══════════════════ -->
<aside class="sidebar">
  <div class="sb-logo">
    <div class="ic"><i class="fas fa-shield-halved"></i></div>
    <span>MatatuTrack</span>
    <span class="badge">ADMIN</span>
  </div>
  <div class="sb-sec">Overview</div>
  <a href="#" class="sb-item active" id="anav-dashboard" onclick="adminNav('dashboard');return false;"><i class="fas fa-gauge-high"></i> Dashboard</a>
  <div class="sb-sec">Management</div>
  <a href="#" class="sb-item" id="anav-matatus" onclick="adminNav('matatus');return false;"><i class="fas fa-bus"></i> Matatus</a>
  <a href="#" class="sb-item" id="anav-routes" onclick="adminNav('routes');return false;"><i class="fas fa-route"></i> Routes</a>
  <a href="#" class="sb-item" id="anav-users" onclick="adminNav('users');return false;"><i class="fas fa-users"></i> Users</a>
  <a href="#" class="sb-item" id="anav-drivers" onclick="adminNav('drivers');return false;"><i class="fa fa-drivers-license"></i> Drivers</a>
  <div class="sb-sec">Analytics</div>
  <a href="#" class="sb-item" id="anav-wallets" onclick="adminNav('wallets');return false;"><i class="fas fa-wallet"></i> Wallets</a>
  <a href="#" class="sb-item" id="anav-reports" onclick="adminNav('reports');return false;"><i class="fas fa-chart-bar"></i> Reports</a>
  <a href="#" class="sb-item" id="anav-feedback" onclick="adminNav('feedback');return false;"><i class="fas fa-star"></i> Feedback</a>
  <a href="#" class="sb-item" id="anav-alerts" onclick="adminNav('alerts');return false;"><i class="fas fa-bell"></i> Alerts</a>
  <div class="sb-bottom">
    <div class="sb-user">
      <div class="sb-avatar"><i class="fas fa-user-shield"></i></div>
      <div>
        <div style="font-size:.85rem;font-weight:600"><?=htmlspecialchars($user['name'])?></div>
        <div style="font-size:.72rem;color:var(--amber)">System Admin</div>
      </div>
    </div>
    <a href="auth.php?action=logout" style="display:flex;align-items:center;gap:.5rem;color:var(--muted);text-decoration:none;font-size:.82rem;margin-top:.75rem;padding:0 .25rem;">
      <i class="fas fa-sign-out-alt"></i> Sign Out
    </a>
  </div>
</aside>

<!-- ══════════════════ MAIN ══════════════════ -->
<main class="main">
  <div class="topbar">
    <div>
      <div class="tb-title" id="adminPageTitle">Admin Dashboard</div>
      <div class="tb-sub" id="lastRefresh">Real-time monitoring — <?=date('D, d M Y H:i')?></div>
    </div>
    <div class="tb-actions">
      <button class="btn btn-ghost no-print" onclick="refreshData()"><i class="fas fa-sync"></i> Refresh</button>
      <button class="btn btn-primary no-print" onclick="openModal('alertModal')"><i class="fas fa-bullhorn"></i> Broadcast Alert</button>
    </div>
  </div>

  <!-- ── DASHBOARD ── -->
  <div class="page" id="admin-main-page">
    <div class="kpi-grid">
      <div class="kpi-card" style="--accent:var(--green);--icon-bg:rgba(0,230,118,.12)">
        <div class="kpi-icon"><i class="fas fa-bus"></i></div>
        <div class="kpi-val" id="kpiActive"><?=$activeMatatus?></div>
        <div class="kpi-label">Active Matatus</div>
        <div class="kpi-change up"><i class="fas fa-circle" style="font-size:.4rem"></i> Live now</div>
      </div>
      <div class="kpi-card" style="--accent:var(--amber);--icon-bg:rgba(255,179,0,.12)">
        <div class="kpi-icon"><i class="fas fa-route"></i></div>
        <div class="kpi-val" id="kpiTrips"><?=$tripsToday?></div>
        <div class="kpi-label">Trips Today</div>
        <div class="kpi-change up"><i class="fas fa-arrow-up"></i> +12% vs yesterday</div>
      </div>
      <div class="kpi-card" style="--accent:var(--blue);--icon-bg:rgba(33,150,243,.12)">
        <div class="kpi-icon"><i class="fas fa-users"></i></div>
        <div class="kpi-val"><?=$totalUsers?></div>
        <div class="kpi-label">Passengers</div>
        <div class="kpi-change up"><i class="fas fa-arrow-up"></i> +3 this week</div>
      </div>
      <div class="kpi-card" style="--accent:var(--purple);--icon-bg:rgba(156,39,176,.12)">
        <div class="kpi-icon"><i class="fa fa-drivers-license"></i></div>
        <div class="kpi-val"><?=$totalDrivers?></div>
        <div class="kpi-label">Active Drivers</div>
        <div class="kpi-change down"><i class="fas fa-circle" style="font-size:.4rem"></i> <?=$activeMatatus?> online</div>
      </div>
    </div>

    <!-- Map + Fleet table -->
    <div class="grid-2">
      <div class="card map-card"><div id="adminMap"></div></div>
      <div class="card">
        <div class="card-header">
          <div><div class="card-title">Fleet Status</div><div class="card-sub">All registered matatus</div></div>
          <span class="status-pill s-active" id="fleetCount">Loading...</span>
        </div>
        <div style="overflow-y:auto;max-height:340px">
          <table class="data-table">
            <thead><tr><th>Plate</th><th>Route</th><th>Driver</th><th>Speed</th><th>Status</th></tr></thead>
            <tbody id="fleetTable">
              <?php foreach($allMatatus as $m): ?>
              <tr>
                <td><span class="plate-badge"><?=htmlspecialchars($m['registration_plate'])?></span></td>
                <td><?=htmlspecialchars($m['route_number']??'—')?></td>
                <td><?=htmlspecialchars($m['driver']??'Unassigned')?></td>
                <td><?=$m['speed_kmh']?round($m['speed_kmh']).' km/h':'—'?></td>
                <td><span class="status-pill s-<?=$m['status']??'offline'?>"><?=strtoupper($m['status']??'OFFLINE')?></span></td>
              </tr>
              <?php endforeach; if(!$allMatatus): ?><tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem">Loading...</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Charts row 1 -->
    <div class="grid-3">
      <div class="card">
        <div class="card-header"><div class="card-title">Trips — Last 7 Days</div><div class="card-sub">Daily volume</div></div>
        <div class="chart-wrap"><canvas id="weeklyChart" height="190"></canvas></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Fleet Breakdown</div><div class="card-sub">Current status split</div></div>
        <div class="donut-wrap">
          <canvas id="fleetDonut" height="160" style="max-width:160px"></canvas>
          <div id="fleetLegend" style="font-size:.78rem;display:flex;flex-direction:column;gap:.5rem"></div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">User Growth</div><div class="card-sub">New registrations / month</div></div>
        <div class="chart-wrap"><canvas id="userGrowthChart" height="190"></canvas></div>
      </div>
    </div>

    <!-- Charts row 2 -->
    <div class="grid-3">
      <div class="card">
        <div class="card-header"><div class="card-title">Route Ratings</div><div class="card-sub">Avg passenger score</div></div>
        <div class="chart-wrap"><canvas id="routeRatingsChart" height="200"></canvas></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Trips by Hour</div><div class="card-sub">Today's pattern</div></div>
        <div class="chart-wrap"><canvas id="hourlyChart" height="200"></canvas></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Active Alerts</div><span class="status-pill s-idle"><?=count($alerts)?> active</span></div>
        <div style="padding:1rem">
          <?php if($alerts): foreach($alerts as $a): ?>
          <div style="background:var(--ink2);border-radius:10px;padding:.85rem;margin-bottom:.6rem;border-left:3px solid var(--amber)">
            <div style="font-size:.85rem;font-weight:600;margin-bottom:.25rem"><?=htmlspecialchars($a['title'])?></div>
            <div style="font-size:.78rem;color:var(--muted)"><?=htmlspecialchars(substr($a['message'],0,80))?>...</div>
          </div>
          <?php endforeach; else: ?>
          <div style="background:var(--ink2);border-radius:10px;padding:.85rem;border-left:3px solid var(--amber)">
            <div style="font-size:.85rem;font-weight:600;margin-bottom:.25rem">Traffic Advisory</div>
            <div style="font-size:.78rem;color:var(--muted)">Heavy traffic on Mombasa Road. Route 111 delays.</div>
          </div>
          <?php endif; ?>
          <button class="btn btn-ghost" style="width:100%;margin-top:.5rem;font-size:.82rem" onclick="openModal('alertModal')"><i class="fas fa-plus"></i> Add Alert</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ── MATATUS ── -->
  <div id="asec-matatus" class="sec-page scroll-page"><div style="padding:1.5rem">
    <div class="sec-header">
      <div class="sec-title">Matatu Management <span style="font-size:.8rem;color:var(--muted);font-weight:400">(<?=count($allMatatus)?> total)</span></div>
      <button class="btn btn-primary" onclick="openModal('matatuCreateModal')"><i class="fas fa-plus"></i> Add Matatu</button>
    </div>
    <div class="table-wrap">
      <div class="table-toolbar">
        <input type="text" class="search-input" placeholder="Search plate, SACCO, driver…" oninput="tSearch(this,'matatuTable')">
        <select class="filter-select" onchange="tFilter(this,'matatuTable',6)">
          <option value="">All Statuses</option><option value="ACTIVE">Active</option><option value="IDLE">Idle</option><option value="OFFLINE">Offline</option>
        </select>
      </div>
      <div class="table-scroll"><table class="data-table" id="matatuTable">
        <thead><tr><th>Plate</th><th>SACCO</th><th>Route</th><th>Driver</th><th>Capacity</th><th>Model</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($allMatatus as $m): ?>
          <tr>
            <td><span class="plate-badge"><?=htmlspecialchars($m['registration_plate'])?></span></td>
            <td><?=htmlspecialchars($m['sacco_name']??'—')?></td>
            <td><?=htmlspecialchars($m['route_number']??'—')?></td>
            <td><?=htmlspecialchars($m['driver']??'Unassigned')?></td>
            <td><?=$m['capacity']??14?> seats</td>
            <td><?=htmlspecialchars($m['vehicle_model']??'—')?></td>
            <td><span class="status-pill s-<?=$m['status']??'offline'?>"><?=strtoupper($m['status']??'OFFLINE')?></span></td>
            <td><div class="actions-cell">
              <button class="btn btn-amber btn-sm btn-icon" onclick="editMatatu(<?=htmlspecialchars(json_encode($m))?>)"><i class="fas fa-pen"></i></button>
              <button class="btn btn-red btn-sm btn-icon" onclick="confirmDel('matatu',<?=$m['id']?>,'<?=addslashes($m['registration_plate'])?>')"><i class="fas fa-trash"></i></button>
            </div></td>
          </tr>
          <?php endforeach; if(!$allMatatus): ?><tr><td colspan="8" style="text-align:center;color:var(--muted);padding:2rem">No matatus found</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div></div>

  <!-- ── ROUTES ── -->
  <div id="asec-routes" class="sec-page scroll-page"><div style="padding:1.5rem">
    <div class="sec-header">
      <div class="sec-title">Route Management <span style="font-size:.8rem;color:var(--muted);font-weight:400">(<?=count($allRoutes)?> routes)</span></div>
      <button class="btn btn-primary" onclick="openModal('routeCreateModal')"><i class="fas fa-plus"></i> Add Route</button>
    </div>
    <div class="table-wrap">
      <div class="table-toolbar"><input type="text" class="search-input" placeholder="Search route, name…" oninput="tSearch(this,'routeTable')"></div>
      <div class="table-scroll"><table class="data-table" id="routeTable">
        <thead><tr><th>Route No.</th><th>Name</th><th>Origin</th><th>Destination</th><th>Fare (KES)</th><th>Distance</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($allRoutes as $r): ?>
          <tr>
            <td><span style="background:<?=htmlspecialchars($r['color_code'])?>;color:#fff;padding:.2rem .6rem;border-radius:6px;font-family:'Syne',sans-serif;font-weight:700;font-size:.82rem"><?=htmlspecialchars($r['route_number'])?></span></td>
            <td><?=htmlspecialchars($r['route_name'])?></td>
            <td><?=htmlspecialchars($r['origin'])?></td>
            <td><?=htmlspecialchars($r['destination'])?></td>
            <td><?=$r['fare_min']?>–<?=$r['fare_max']?></td>
            <td><?=$r['distance_km']??'—'?> km</td>
            <td><span class="status-pill <?=$r['is_active']?'s-active':'s-offline'?>"><?=$r['is_active']?'ACTIVE':'INACTIVE'?></span></td>
            <td><div class="actions-cell">
              <button class="btn btn-amber btn-sm btn-icon" onclick="editRoute(<?=htmlspecialchars(json_encode($r))?>)"><i class="fas fa-pen"></i></button>
              <button class="btn btn-red btn-sm btn-icon" onclick="confirmDel('route',<?=$r['id']?>,'<?=addslashes($r['route_number'])?>')"><i class="fas fa-ban"></i></button>
            </div></td>
          </tr>
          <?php endforeach; if(!$allRoutes): ?><tr><td colspan="8" style="text-align:center;color:var(--muted);padding:2rem">No routes found</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div></div>

  <!-- ── USERS ── -->
  <div id="asec-users" class="sec-page scroll-page"><div style="padding:1.5rem">
    <div class="sec-header">
      <div class="sec-title">User Management <span style="font-size:.8rem;color:var(--muted);font-weight:400">(<?=count($allUsers)?> total)</span></div>
      <button class="btn btn-primary" onclick="openModal('userCreateModal')"><i class="fas fa-plus"></i> Add User</button>
    </div>
    <div class="table-wrap">
      <div class="table-toolbar">
        <input type="text" class="search-input" placeholder="Search name, email…" oninput="tSearch(this,'userTable')">
        <select class="filter-select" onchange="tFilter(this,'userTable',3)">
          <option value="">All Roles</option><option value="PASSENGER">Passenger</option><option value="DRIVER">Driver</option><option value="ADMIN">Admin</option>
        </select>
      </div>
      <div class="table-scroll"><table class="data-table" id="userTable">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Joined</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($allUsers as $u): ?>
          <tr>
            <td style="font-weight:600"><?=htmlspecialchars($u['full_name'])?></td>
            <td style="color:var(--muted)"><?=htmlspecialchars($u['email'])?></td>
            <td><?=htmlspecialchars($u['phone'])?></td>
            <td><span class="status-pill" style="background:<?=$u['role']==='admin'?'rgba(255,179,0,.15)':($u['role']==='driver'?'rgba(33,150,243,.15)':'rgba(0,230,118,.15)')?>;color:<?=$u['role']==='admin'?'var(--amber)':($u['role']==='driver'?'var(--blue)':'var(--green)')?>"><?=strtoupper($u['role'])?></span></td>
            <td><?=date('d M Y',strtotime($u['created_at']))?></td>
            <td style="color:var(--muted)"><?=$u['last_login']?date('d M Y H:i',strtotime($u['last_login'])):'Never'?></td>
            <td><span class="status-pill <?=$u['is_active']?'s-active':'s-offline'?>"><?=$u['is_active']?'ACTIVE':'INACTIVE'?></span></td>
            <td><div class="actions-cell">
              <button class="btn btn-amber btn-sm btn-icon" onclick="editUser(<?=htmlspecialchars(json_encode($u))?>)"><i class="fas fa-pen"></i></button>
              <?php if($u['id']!=$user['id']): ?><button class="btn btn-red btn-sm btn-icon" onclick="confirmDel('user',<?=$u['id']?>,'<?=addslashes($u['full_name'])?>')"><i class="fas fa-user-slash"></i></button><?php endif; ?>
            </div></td>
          </tr>
          <?php endforeach; if(!$allUsers): ?><tr><td colspan="8" style="text-align:center;color:var(--muted);padding:2rem">No users found</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div></div>

  <!-- ── DRIVERS ── -->
  <div id="asec-drivers" class="sec-page scroll-page"><div style="padding:1.5rem">
    <div class="sec-header">
      <div class="sec-title">Driver Management <span style="font-size:.8rem;color:var(--muted);font-weight:400">(<?=count($allDrivers)?> drivers)</span></div>
      <button class="btn btn-primary" onclick="openModal('userCreateModal');document.getElementById('uc-role').value='driver'"><i class="fas fa-plus"></i> Add Driver</button>
    </div>
    <div class="table-wrap">
      <div class="table-toolbar"><input type="text" class="search-input" placeholder="Search driver name…" oninput="tSearch(this,'driverTable')"></div>
      <div class="table-scroll"><table class="data-table" id="driverTable">
        <thead><tr><th>Driver</th><th>Phone</th><th>Email</th><th>Matatu</th><th>Route</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($allDrivers as $d): ?>
          <tr>
            <td style="font-weight:600"><?=htmlspecialchars($d['full_name'])?></td>
            <td><?=htmlspecialchars($d['phone'])?></td>
            <td style="color:var(--muted)"><?=htmlspecialchars($d['email'])?></td>
            <td><?=$d['registration_plate']?'<span class="plate-badge">'.htmlspecialchars($d['registration_plate']).'</span>':'<span style="color:var(--muted)">Unassigned</span>'?></td>
            <td><?=htmlspecialchars($d['route_number']??'—')?></td>
            <td><span class="status-pill s-<?=$d['tracking_status']??'offline'?>"><?=strtoupper($d['tracking_status']??'OFFLINE')?></span></td>
            <td><div class="actions-cell">
              <button class="btn btn-amber btn-sm btn-icon" onclick="editUser(<?=htmlspecialchars(json_encode($d))?>)"><i class="fas fa-pen"></i></button>
              <button class="btn btn-red btn-sm btn-icon" onclick="confirmDel('user',<?=$d['id']?>,'<?=addslashes($d['full_name'])?>')"><i class="fas fa-user-slash"></i></button>
            </div></td>
          </tr>
          <?php endforeach; if(!$allDrivers): ?><tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">No drivers found</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div></div>

  <!-- ── REPORTS ── -->
  <div id="asec-reports" class="sec-page scroll-page"><div style="padding:1.5rem">
    <div class="sec-header">
      <div class="sec-title">Analytics & Reports</div>
      <button class="btn btn-primary no-print" onclick="doPrint()"><i class="fas fa-file-pdf"></i> Export PDF</button>
    </div>

    <div class="kpi-grid">
      <div class="kpi-card" style="--accent:var(--green);--icon-bg:rgba(0,230,118,.12)">
        <div class="kpi-icon"><i class="fas fa-bus"></i></div>
        <div class="kpi-val"><?=count($allMatatus)?></div>
        <div class="kpi-label">Total Matatus</div>
      </div>
      <div class="kpi-card" style="--accent:var(--amber);--icon-bg:rgba(255,179,0,.12)">
        <div class="kpi-icon"><i class="fas fa-route"></i></div>
        <div class="kpi-val"><?=count(array_filter($allRoutes,fn($r)=>$r['is_active']))?></div>
        <div class="kpi-label">Active Routes</div>
      </div>
      <div class="kpi-card" style="--accent:var(--blue);--icon-bg:rgba(33,150,243,.12)">
        <div class="kpi-icon"><i class="fas fa-calendar-day"></i></div>
        <div class="kpi-val"><?=$tripsToday?></div>
        <div class="kpi-label">Trips Today</div>
      </div>
      <div class="kpi-card" style="--accent:var(--purple);--icon-bg:rgba(156,39,176,.12)">
        <div class="kpi-icon"><i class="fas fa-tachometer-alt"></i></div>
        <div class="kpi-val"><?=$avgSpeed?><span style="font-size:1rem"> km/h</span></div>
        <div class="kpi-label">Avg Active Speed</div>
      </div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="card-header"><div class="card-title">Weekly Trip Volume</div></div>
        <div class="chart-wrap"><canvas id="rptWeekly" height="200"></canvas></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Fleet Status Split</div></div>
        <div class="donut-wrap">
          <canvas id="rptFleetDonut" height="180" style="max-width:180px"></canvas>
          <div id="rptFleetLegend" style="font-size:.82rem;display:flex;flex-direction:column;gap:.6rem"></div>
        </div>
      </div>
    </div>
    <div class="grid-2">
      <div class="card">
        <div class="card-header"><div class="card-title">Route Ratings</div><div class="card-sub">Avg satisfaction score</div></div>
        <div class="chart-wrap"><canvas id="rptRatings" height="200"></canvas></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">User Growth</div><div class="card-sub">Registrations per month</div></div>
        <div class="chart-wrap"><canvas id="rptUserGrowth" height="200"></canvas></div>
      </div>
    </div>

    <div class="table-wrap">
      <div class="card-header"><div class="card-title">Route Summary</div></div>
      <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Route</th><th>Name</th><th>Origin → Destination</th><th>Fare (KES)</th><th>Distance</th><th>Avg Time</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach($allRoutes as $r): ?>
          <tr>
            <td><span style="background:<?=htmlspecialchars($r['color_code'])?>;color:#fff;padding:.2rem .6rem;border-radius:6px;font-weight:700;font-size:.82rem"><?=htmlspecialchars($r['route_number'])?></span></td>
            <td><?=htmlspecialchars($r['route_name'])?></td>
            <td style="color:var(--muted)"><?=htmlspecialchars($r['origin'])?> → <?=htmlspecialchars($r['destination'])?></td>
            <td>KES <?=$r['fare_min']?>–<?=$r['fare_max']?></td>
            <td><?=$r['distance_km']??'—'?> km</td>
            <td><?=$r['avg_duration_minutes']??'—'?> min</td>
            <td><span class="status-pill <?=$r['is_active']?'s-active':'s-offline'?>"><?=$r['is_active']?'ACTIVE':'INACTIVE'?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div></div>

  <!-- ── FEEDBACK ── -->
  <div id="asec-feedback" class="sec-page scroll-page"><div style="padding:1.5rem">
    <div class="sec-title" style="margin-bottom:1.25rem">Passenger Feedback</div>
    <div class="table-wrap"><div class="table-scroll"><table class="data-table">
      <thead><tr><th>Passenger</th><th>Matatu</th><th>Rating</th><th>Comment</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach($topFeedback as $fb): ?>
        <tr>
          <td><?=htmlspecialchars($fb['full_name']??'Anonymous')?></td>
          <td><?=htmlspecialchars($fb['registration_plate']??'—')?></td>
          <td style="color:var(--amber)"><?=str_repeat('★',$fb['rating']).str_repeat('☆',5-$fb['rating'])?></td>
          <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($fb['comment']??'—')?></td>
          <td><?=date('d M Y',strtotime($fb['created_at']))?></td>
        </tr>
        <?php endforeach; if(!$topFeedback): ?><tr><td colspan="5" style="text-align:center;color:var(--muted);padding:2rem">No feedback yet</td></tr><?php endif; ?>
      </tbody>
    </table></div></div>
  </div></div>

  <!-- ── ALERTS ── -->
  <div id="asec-alerts" class="sec-page scroll-page"><div style="padding:1.5rem">
    <div class="sec-header">
      <div class="sec-title">System Alerts</div>
      <button class="btn btn-primary" onclick="openModal('alertModal')"><i class="fas fa-plus"></i> New Alert</button>
    </div>
    <div class="table-wrap"><div class="table-scroll"><table class="data-table">
      <thead><tr><th>Title</th><th>Type</th><th>Message</th><th>By</th><th>Created</th><th>Active</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($allAlerts as $a): ?>
        <tr>
          <td style="font-weight:600"><?=htmlspecialchars($a['title'])?></td>
          <td><span class="status-pill" style="background:rgba(255,179,0,.15);color:var(--amber)"><?=strtoupper($a['alert_type'])?></span></td>
          <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted)"><?=htmlspecialchars($a['message'])?></td>
          <td><?=htmlspecialchars($a['created_by_name']??'—')?></td>
          <td><?=date('d M Y H:i',strtotime($a['created_at']))?></td>
          <td><span class="status-pill <?=$a['is_active']?'s-active':'s-offline'?>"><?=$a['is_active']?'YES':'NO'?></span></td>
          <td><div class="actions-cell">
            <form method="POST" style="display:inline">
              <input type="hidden" name="crud_action" value="toggle_alert">
              <input type="hidden" name="alert_id" value="<?=$a['id']?>">
              <button type="submit" class="btn btn-amber btn-sm btn-icon"><i class="fas fa-<?=$a['is_active']?'eye-slash':'eye'?>"></i></button>
            </form>
            <button class="btn btn-red btn-sm btn-icon" onclick="confirmDel('alert',<?=$a['id']?>,'<?=addslashes($a['title'])?>')"><i class="fas fa-trash"></i></button>
          </div></td>
        </tr>
        <?php endforeach; if(!$allAlerts): ?><tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">No alerts</td></tr><?php endif; ?>
      </tbody>
    </table></div></div>
  </div></div>  <!-- ── WALLETS ── -->
  <div id="asec-wallets" class="sec-page scroll-page"><div style="padding:1.5rem">
    <div class="sec-header">
      <div class="sec-title">Wallet Management <span style="font-size:.8rem;color:var(--muted);font-weight:400">M-PESA top-ups &amp; transaction history</span></div>
    </div>

    <!-- KPI row -->
    <div class="wallet-kpi-row">
      <div class="kpi-card" style="--accent:var(--green);--icon-bg:rgba(0,230,118,.12)">
        <div class="kpi-icon"><i class="fas fa-coins"></i></div>
        <div class="kpi-val">KES <?=number_format((float)$walletTotalFloat,0)?></div>
        <div class="kpi-label">Total Wallet Float</div>
      </div>
      <div class="kpi-card" style="--accent:var(--blue);--icon-bg:rgba(33,150,243,.12)">
        <div class="kpi-icon"><i class="fas fa-users"></i></div>
        <div class="kpi-val"><?=count($walletPassengers)?></div>
        <div class="kpi-label">Passengers with Wallets</div>
      </div>
      <div class="kpi-card" style="--accent:var(--amber);--icon-bg:rgba(255,179,0,.12)">
        <div class="kpi-icon"><i class="fas fa-receipt"></i></div>
        <div class="kpi-val"><?=count($walletTxRecent)?></div>
        <div class="kpi-label">Recent Transactions</div>
      </div>
    </div>

    <div class="grid-2" style="align-items:start">

      <!-- LEFT: Top-up form + passenger balances -->
      <div>
        <!-- Top-up form -->
        <div class="topup-form">
          <div class="topup-title">
            <i class="fas fa-phone" style="color:var(--green)"></i>
            Credit Wallet — M-PESA Top-Up
          </div>
          <?php if($crudType && strpos($crudMsg,'credited')!==false): ?>
          <div style="background:rgba(0,230,118,.1);border:1px solid rgba(0,230,118,.3);color:var(--green);padding:.75rem 1rem;border-radius:10px;font-size:.85rem;margin-bottom:1rem">
            <i class="fas fa-check-circle"></i> <?=htmlspecialchars($crudMsg)?>
          </div>
          <?php elseif($crudType==='error'): ?>
          <div style="background:rgba(255,61,61,.1);border:1px solid rgba(255,61,61,.3);color:var(--red);padding:.75rem 1rem;border-radius:10px;font-size:.85rem;margin-bottom:1rem">
            <i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($crudMsg)?>
          </div>
          <?php endif; ?>
          <form method="POST">
            <input type="hidden" name="crud_action" value="wallet_topup">
            <div class="form-group">
              <label class="form-label">Passenger *</label>
              <select name="topup_user_id" class="form-input" required>
                <option value="">— Select passenger —</option>
                <?php foreach($walletPassengers as $p): ?>
                <option value="<?=$p['id']?>"><?=htmlspecialchars($p['full_name'])?> — <?=htmlspecialchars($p['phone'])?> (Balance: KES <?=number_format((float)$p['balance'],2)?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Amount (KES) *</label>
                <input type="number" name="topup_amount" class="form-input" min="1" max="50000" step="1" placeholder="e.g. 500" required>
              </div>
              <div class="form-group">
                <label class="form-label">M-PESA Code *</label>
                <input type="text" name="mpesa_code" class="form-input" placeholder="e.g. QHX7Y4Z2AB" style="text-transform:uppercase" required>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Note (optional)</label>
              <input type="text" name="topup_note" class="form-input" placeholder="M-PESA top-up by admin" value="M-PESA top-up by admin">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
              <i class="fas fa-paper-plane"></i> Credit Wallet
            </button>
          </form>
        </div>

        <!-- Passenger balances list -->
        <div class="table-wrap">
          <div class="card-header">
            <div class="card-title">Passenger Balances</div>
            <input type="text" class="search-input" style="max-width:180px" placeholder="Search…" oninput="tSearch(this,'walletUserList')">
          </div>
          <div id="walletUserList" style="max-height:420px;overflow-y:auto">
            <?php if($walletPassengers): foreach($walletPassengers as $p):
              $bal=(float)$p['balance']; ?>
            <div class="wallet-user-row" id="wu-<?=$p['id']?>">
              <div class="wu-avatar"><i class="fas fa-user"></i></div>
              <div class="wu-info">
                <div class="wu-name"><?=htmlspecialchars($p['full_name'])?></div>
                <div class="wu-phone"><?=htmlspecialchars($p['phone'])?></div>
              </div>
              <div style="text-align:right">
                <div class="wu-bal <?=$bal<50?'low':''?>">KES <?=number_format($bal,2)?></div>
                <div style="font-size:.72rem;color:var(--muted)">Topped: <?=number_format((float)$p['total_topped'],0)?> · Spent: <?=number_format((float)$p['total_spent'],0)?></div>
              </div>
              <button class="btn btn-primary btn-sm" style="margin-left:.75rem" onclick="prefillTopup(<?=$p['id']?>,'<?=addslashes($p['full_name'])?>','<?=htmlspecialchars($p['phone'])?>',<?=number_format($bal,2)?>)">
                <i class="fas fa-plus"></i> Top Up
              </button>
            </div>
            <?php endforeach; else: ?>
            <div style="padding:2rem;text-align:center;color:var(--muted)">No passengers found</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- RIGHT: Recent transactions -->
      <div class="table-wrap">
        <div class="card-header">
          <div class="card-title">Recent Transactions</div>
          <span class="status-pill s-active"><?=count($walletTxRecent)?></span>
        </div>
        <div class="table-scroll" style="max-height:700px;overflow-y:auto">
          <?php if($walletTxRecent): foreach($walletTxRecent as $tx): ?>
          <div class="tx-admin-row">
            <span class="tx-type-badge <?=$tx['type']==='credit'?'tx-credit':'tx-debit'?>">
              <?=$tx['type']==='credit'?'↓ CREDIT':'↑ DEBIT'?>
            </span>
            <div style="flex:1;min-width:0">
              <div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($tx['full_name'])?></div>
              <div style="font-size:.75rem;color:var(--muted)"><?=htmlspecialchars(substr($tx['description'],0,45))?><?=$tx['mpesa_code']?' · <strong style="color:var(--green)">'.htmlspecialchars($tx['mpesa_code']).'</strong>':''?></div>
            </div>
            <div style="text-align:right;flex-shrink:0">
              <div style="font-family:'Syne',sans-serif;font-weight:700;color:<?=$tx['type']==='credit'?'var(--green)':'var(--red)'?>">
                <?=$tx['type']==='credit'?'+':'−'?>KES <?=number_format((float)$tx['amount'],2)?>
              </div>
              <div style="font-size:.72rem;color:var(--muted)"><?=date('d M, H:i',strtotime($tx['created_at']))?></div>
            </div>
          </div>
          <?php endforeach; else: ?>
          <div style="padding:2rem;text-align:center;color:var(--muted);font-size:.85rem">No transactions yet</div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /grid-2 -->
  </div></div>

</main>


<!-- ════════════ PRINT WRAPPER (hidden on screen) ════════════ -->
<div id="rpt-wrapper" style="display:none">
  <!-- Page 1 -->
  <div class="rpt-page">
    <div class="rpt-head">
      <div><div class="rpt-logo">🚐 MatatuTrack<small>Nairobi Urban Transport Platform</small></div></div>
      <div class="rpt-meta"><strong>Operations Report</strong>Generated: <?=date('d M Y, H:i')?><br>By: <?=htmlspecialchars($user['name'])?></div>
    </div>
    <div class="rpt-h2">Key Performance Indicators</div>
    <div class="rpt-kpis">
      <div class="rpt-kpi"><div class="rpt-kpi-v"><?=count($allMatatus)?></div><div class="rpt-kpi-l">Total Matatus</div></div>
      <div class="rpt-kpi" style="border-color:#FFB300"><div class="rpt-kpi-v" style="color:#996800"><?=count(array_filter($allRoutes,fn($r)=>$r['is_active']))?></div><div class="rpt-kpi-l">Active Routes</div></div>
      <div class="rpt-kpi" style="border-color:#2196F3"><div class="rpt-kpi-v" style="color:#1565C0"><?=$totalUsers?></div><div class="rpt-kpi-l">Passengers</div></div>
      <div class="rpt-kpi" style="border-color:#9C27B0"><div class="rpt-kpi-v" style="color:#6a1b9a"><?=$totalDrivers?></div><div class="rpt-kpi-l">Drivers</div></div>
    </div>
    <div class="rpt-kpis">
      <div class="rpt-kpi"><div class="rpt-kpi-v"><?=$tripsToday?></div><div class="rpt-kpi-l">Trips Today</div></div>
      <div class="rpt-kpi" style="border-color:#FF5722"><div class="rpt-kpi-v" style="color:#bf360c"><?=$tripsWeek?></div><div class="rpt-kpi-l">Trips This Week</div></div>
      <div class="rpt-kpi" style="border-color:#009688"><div class="rpt-kpi-v" style="color:#00695c"><?=$tripsMonth?></div><div class="rpt-kpi-l">Trips This Month</div></div>
      <div class="rpt-kpi" style="border-color:#607D8B"><div class="rpt-kpi-v" style="color:#37474f"><?=$avgSpeed?><small> km/h</small></div><div class="rpt-kpi-l">Avg Speed</div></div>
    </div>
    <div class="rpt-charts">
      <div class="rpt-cbox"><div class="rpt-ctitle">Weekly Trip Volume</div><canvas id="pc-weekly" height="130"></canvas></div>
      <div class="rpt-cbox"><div class="rpt-ctitle">Fleet Status</div><canvas id="pc-fleet" height="130"></canvas></div>
    </div>
    <div class="rpt-charts">
      <div class="rpt-cbox"><div class="rpt-ctitle">Route Ratings</div><canvas id="pc-ratings" height="130"></canvas></div>
      <div class="rpt-cbox"><div class="rpt-ctitle">User Growth (6 months)</div><canvas id="pc-growth" height="130"></canvas></div>
    </div>
    <div class="rpt-foot"><span>MatatuTrack — Confidential</span><span>Page 1 of 2</span></div>
  </div>

  <!-- Page 2 -->
  <div class="rpt-page">
    <div class="rpt-head">
      <div><div class="rpt-logo">🚐 MatatuTrack<small>Nairobi Urban Transport Platform</small></div></div>
      <div class="rpt-meta"><strong>Route & Fleet Detail</strong><?=date('d M Y')?></div>
    </div>
    <div class="rpt-h2">Route Register</div>
    <table class="rpt-t">
      <thead><tr><th>Route</th><th>Name</th><th>Origin</th><th>Destination</th><th>Fare (KES)</th><th>Dist.</th><th>Time</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach($allRoutes as $r): ?>
        <tr>
          <td style="font-weight:700"><?=htmlspecialchars($r['route_number'])?></td>
          <td><?=htmlspecialchars($r['route_name'])?></td>
          <td><?=htmlspecialchars($r['origin'])?></td>
          <td><?=htmlspecialchars($r['destination'])?></td>
          <td><?=$r['fare_min']?>–<?=$r['fare_max']?></td>
          <td><?=$r['distance_km']??'—'?> km</td>
          <td><?=$r['avg_duration_minutes']??'—'?> min</td>
          <td><span class="pp <?=$r['is_active']?'pp-on':'pp-off'?>"><?=$r['is_active']?'Active':'Inactive'?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="rpt-h2">Fleet Register</div>
    <table class="rpt-t">
      <thead><tr><th>Plate</th><th>SACCO</th><th>Route</th><th>Driver</th><th>Capacity</th><th>Model</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach($allMatatus as $m): ?>
        <tr>
          <td style="font-weight:700"><?=htmlspecialchars($m['registration_plate'])?></td>
          <td><?=htmlspecialchars($m['sacco_name']??'—')?></td>
          <td><?=htmlspecialchars($m['route_number']??'—')?></td>
          <td><?=htmlspecialchars($m['driver']??'Unassigned')?></td>
          <td><?=$m['capacity']??14?></td>
          <td><?=htmlspecialchars($m['vehicle_model']??'—')?></td>
          <td><span class="pp <?=($m['status']??'offline')==='active'?'pp-on':'pp-off'?>"><?=strtoupper($m['status']??'OFFLINE')?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="rpt-foot"><span>MatatuTrack — Confidential</span><span>Page 2 of 2</span></div>
  </div>
</div>


<!-- ════════════ MODALS ════════════ -->
<div id="alertModal" class="modal-backdrop"><div class="modal">
  <button class="modal-close" onclick="closeModal('alertModal')">✕</button>
  <div class="modal-title">Broadcast Alert</div><div class="modal-sub">Notify all passengers</div>
  <form method="POST">
    <input type="hidden" name="crud_action" value="create_alert">
    <div class="form-group"><label class="form-label">Title</label><input type="text" name="title" class="form-input" required></div>
    <div class="form-group"><label class="form-label">Message</label><textarea name="message" class="form-input" required></textarea></div>
    <div class="form-group"><label class="form-label">Type</label><select name="alert_type" class="form-input"><option value="disruption">Traffic Disruption</option><option value="maintenance">Maintenance</option><option value="info">Information</option><option value="emergency">Emergency</option></select></div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-bullhorn"></i> Broadcast Now</button>
  </form>
</div></div>

<div id="matatuCreateModal" class="modal-backdrop"><div class="modal">
  <button class="modal-close" onclick="closeModal('matatuCreateModal')">✕</button>
  <div class="modal-title">Add Matatu</div><div class="modal-sub">Register a new vehicle</div>
  <form method="POST">
    <input type="hidden" name="crud_action" value="create_matatu">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Plate *</label><input type="text" name="plate" class="form-input" required style="text-transform:uppercase"></div>
      <div class="form-group"><label class="form-label">SACCO</label><input type="text" name="sacco" class="form-input"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Route</label><select name="route_id" class="form-input"><option value="">— None —</option><?php foreach($allRoutes as $r): ?><option value="<?=$r['id']?>"><?=htmlspecialchars($r['route_number'].' — '.$r['route_name'])?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label class="form-label">Driver</label><select name="driver_id" class="form-input"><option value="">— None —</option><?php foreach($unassignedDrivers as $d): ?><option value="<?=$d['id']?>"><?=htmlspecialchars($d['full_name'])?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Capacity</label><input type="number" name="capacity" class="form-input" value="14" min="4" max="60"></div>
      <div class="form-group"><label class="form-label">Model</label><input type="text" name="model" class="form-input" placeholder="Toyota HiAce"></div>
    </div>
    <div class="form-group"><label class="form-label">Color</label><input type="text" name="color" class="form-input" placeholder="White/Green"></div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-plus"></i> Add Matatu</button>
  </form>
</div></div>

<div id="matatuEditModal" class="modal-backdrop"><div class="modal">
  <button class="modal-close" onclick="closeModal('matatuEditModal')">✕</button>
  <div class="modal-title">Edit Matatu</div><div class="modal-sub">Update vehicle information</div>
  <form method="POST">
    <input type="hidden" name="crud_action" value="update_matatu">
    <input type="hidden" name="matatu_id" id="me-id">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Plate *</label><input type="text" name="plate" id="me-plate" class="form-input" required style="text-transform:uppercase"></div>
      <div class="form-group"><label class="form-label">SACCO</label><input type="text" name="sacco" id="me-sacco" class="form-input"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Route</label><select name="route_id" id="me-route" class="form-input"><option value="">— None —</option><?php foreach($allRoutes as $r): ?><option value="<?=$r['id']?>"><?=htmlspecialchars($r['route_number'].' — '.$r['route_name'])?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label class="form-label">Driver</label><select name="driver_id" id="me-driver" class="form-input"><option value="">— None —</option><?php foreach($allDrivers as $d): ?><option value="<?=$d['id']?>"><?=htmlspecialchars($d['full_name'])?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Capacity</label><input type="number" name="capacity" id="me-capacity" class="form-input" min="4" max="60"></div>
      <div class="form-group"><label class="form-label">Model</label><input type="text" name="model" id="me-model" class="form-input"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Color</label><input type="text" name="color" id="me-color" class="form-input"></div>
      <div class="form-group" style="justify-content:flex-end;padding-top:1.5rem"><label class="form-check"><input type="checkbox" name="is_active" id="me-active"> Active</label></div>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-save"></i> Save Changes</button>
  </form>
</div></div>

<div id="routeCreateModal" class="modal-backdrop"><div class="modal">
  <button class="modal-close" onclick="closeModal('routeCreateModal')">✕</button>
  <div class="modal-title">Add Route</div><div class="modal-sub">Create a new matatu route</div>
  <form method="POST">
    <input type="hidden" name="crud_action" value="create_route">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Route Number *</label><input type="text" name="route_number" class="form-input" required></div>
      <div class="form-group"><label class="form-label">Route Name *</label><input type="text" name="route_name" class="form-input" required></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Origin *</label><input type="text" name="origin" class="form-input" required></div>
      <div class="form-group"><label class="form-label">Destination *</label><input type="text" name="destination" class="form-input" required></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Min Fare (KES)</label><input type="number" name="fare_min" class="form-input" min="0" step="5"></div>
      <div class="form-group"><label class="form-label">Max Fare (KES)</label><input type="number" name="fare_max" class="form-input" min="0" step="5"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Distance (km)</label><input type="number" name="distance_km" class="form-input" step="0.1" min="0"></div>
      <div class="form-group"><label class="form-label">Duration (min)</label><input type="number" name="duration" class="form-input" min="1"></div>
    </div>
    <div class="form-group"><label class="form-label">Route Color</label><input type="color" name="color_code" class="form-input" value="#00C853" style="height:42px;padding:.3rem"></div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-plus"></i> Create Route</button>
  </form>
</div></div>

<div id="routeEditModal" class="modal-backdrop"><div class="modal">
  <button class="modal-close" onclick="closeModal('routeEditModal')">✕</button>
  <div class="modal-title">Edit Route</div><div class="modal-sub">Update route details</div>
  <form method="POST">
    <input type="hidden" name="crud_action" value="update_route">
    <input type="hidden" name="route_id" id="re-id">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Route Number *</label><input type="text" name="route_number" id="re-number" class="form-input" required></div>
      <div class="form-group"><label class="form-label">Route Name *</label><input type="text" name="route_name" id="re-name" class="form-input" required></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Origin</label><input type="text" name="origin" id="re-origin" class="form-input"></div>
      <div class="form-group"><label class="form-label">Destination</label><input type="text" name="destination" id="re-dest" class="form-input"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Min Fare</label><input type="number" name="fare_min" id="re-faremin" class="form-input" step="5" min="0"></div>
      <div class="form-group"><label class="form-label">Max Fare</label><input type="number" name="fare_max" id="re-faremax" class="form-input" step="5" min="0"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Distance (km)</label><input type="number" name="distance_km" id="re-dist" class="form-input" step="0.1" min="0"></div>
      <div class="form-group"><label class="form-label">Duration (min)</label><input type="number" name="duration" id="re-dur" class="form-input" min="1"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Color</label><input type="color" name="color_code" id="re-color" class="form-input" style="height:42px;padding:.3rem"></div>
      <div class="form-group" style="justify-content:flex-end;padding-top:1.5rem"><label class="form-check"><input type="checkbox" name="is_active" id="re-active"> Active</label></div>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-save"></i> Save Changes</button>
  </form>
</div></div>

<div id="userCreateModal" class="modal-backdrop"><div class="modal">
  <button class="modal-close" onclick="closeModal('userCreateModal')">✕</button>
  <div class="modal-title">Add User</div><div class="modal-sub">Create a new account</div>
  <form method="POST">
    <input type="hidden" name="crud_action" value="create_user">
    <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-input" required></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-input" required></div>
      <div class="form-group"><label class="form-label">Phone *</label><input type="text" name="phone" class="form-input" required></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Role</label><select name="role" id="uc-role" class="form-input"><option value="passenger">Passenger</option><option value="driver">Driver</option><option value="admin">Admin</option></select></div>
      <div class="form-group"><label class="form-label">Password *</label><input type="password" name="password" class="form-input" required minlength="8"></div>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-user-plus"></i> Create User</button>
  </form>
</div></div>

<div id="userEditModal" class="modal-backdrop"><div class="modal">
  <button class="modal-close" onclick="closeModal('userEditModal')">✕</button>
  <div class="modal-title">Edit User</div><div class="modal-sub">Update account details</div>
  <form method="POST">
    <input type="hidden" name="crud_action" value="update_user">
    <input type="hidden" name="user_id" id="ue-id">
    <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="full_name" id="ue-name" class="form-input" required></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" id="ue-email" class="form-input" required></div>
      <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" id="ue-phone" class="form-input"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Role</label><select name="role" id="ue-role" class="form-input"><option value="passenger">Passenger</option><option value="driver">Driver</option><option value="admin">Admin</option></select></div>
      <div class="form-group"><label class="form-label">New Password</label><input type="password" name="password" class="form-input" minlength="8" placeholder="Blank = keep current"></div>
    </div>
    <label class="form-check" style="margin-bottom:1rem"><input type="checkbox" name="is_active" id="ue-active"> Account Active</label>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-save"></i> Save Changes</button>
  </form>
</div></div>

<div id="confirmModal" class="modal-backdrop"><div class="confirm-dialog">
  <h3 id="confirmTitle">Are you sure?</h3>
  <p id="confirmText">This action cannot be undone.</p>
  <div class="confirm-actions">
    <button class="btn btn-ghost" onclick="closeModal('confirmModal')">Cancel</button>
    <form method="POST" id="confirmForm">
      <input type="hidden" name="crud_action" id="ca">
      <input type="hidden" name="matatu_id" id="cm"><input type="hidden" name="route_id" id="cr">
      <input type="hidden" name="user_id" id="cu"><input type="hidden" name="alert_id" id="cal">
      <button type="submit" class="btn btn-red"><i class="fas fa-trash"></i> Confirm</button>
    </form>
  </div>
</div></div>


<!-- ════════════ SCRIPTS ════════════ -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
// ── PHP → JS DATA ──
const WL  = <?=json_encode($weekLabels)?>;
const WD  = <?=json_encode($weekCounts)?>;
const RL  = <?=json_encode($ratingLabels)?>.length ? <?=json_encode($ratingLabels)?> : ['R111','R23','R44','R58','R33','R9'];
const RV  = <?=json_encode($ratingValues)?>.length ? <?=json_encode($ratingValues)?> : [4.2,3.8,4.5,3.6,4.0,4.3];
const UGL = <?=json_encode($ugLabels)?>.length ? <?=json_encode($ugLabels)?> : ['Oct','Nov','Dec','Jan','Feb','Mar'];
const UGV = <?=json_encode($ugValues)?>.length ? <?=json_encode($ugValues)?> : [1,2,1,3,4,2];
const FA  = <?=$flA?>, FI = <?=$flI?>, FO = <?=$flO?>;
const PALETTE = ['#00E676','#FFB300','#2196F3','#9C27B0','#FF5722','#00BCD4','#4CAF50','#F44336'];
const GRID    = 'rgba(0,230,118,0.08)';
const HOUR_D  = [2,4,8,12,18,22,25,20,18,15,12,8,6,9,14,18,20,22,19,16,12,8,5,3];
const NOW_H   = new Date().getHours();

Chart.defaults.color = '#7A9B80';
Chart.defaults.font.family = "'DM Sans',sans-serif";
Chart.defaults.font.size = 11;

function lineChart(id,labels,data,color='#00E676',fill=true){
  const c=document.getElementById(id);if(!c)return;
  return new Chart(c,{type:'line',data:{labels,datasets:[{data,fill,borderColor:color,backgroundColor:fill?color+'22':'transparent',borderWidth:2,pointRadius:3,pointBackgroundColor:color,tension:.4}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{color:GRID},ticks:{maxTicksLimit:8}},y:{grid:{color:GRID},beginAtZero:true}}}});
}
function barChart(id,labels,data,colors,horiz=false){
  const c=document.getElementById(id);if(!c)return;
  const bg=Array.isArray(colors)?colors:Array(data.length).fill(colors);
  return new Chart(c,{type:'bar',data:{labels,datasets:[{data,backgroundColor:bg,borderRadius:4,borderSkipped:false}]},options:{indexAxis:horiz?'y':'x',responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{color:GRID}},y:{grid:{color:GRID},beginAtZero:true,max:horiz?5:undefined}}}});
}
function donutChart(id,labels,data,colors,legendEl){
  const c=document.getElementById(id);if(!c)return;
  const ch=new Chart(c,{type:'doughnut',data:{labels,datasets:[{data,backgroundColor:colors,borderWidth:0,hoverOffset:4}]},options:{responsive:true,cutout:'68%',plugins:{legend:{display:false}}}});
  if(legendEl){labels.forEach((l,i)=>{const d=document.createElement('div');d.style.cssText='display:flex;align-items:center;gap:.4rem';d.innerHTML=`<span style="width:10px;height:10px;border-radius:50%;background:${colors[i]};display:inline-block;flex-shrink:0"></span><span>${l}: <strong>${data[i]}</strong></span>`;legendEl.appendChild(d);});}
  return ch;
}

// Dashboard charts
lineChart('weeklyChart',WL,WD,'#00E676');
donutChart('fleetDonut',['Active','Idle','Offline'],[FA,FI,FO],['#00E676','#FFB300','#FF3D3D'],document.getElementById('fleetLegend'));
lineChart('userGrowthChart',UGL,UGV,'#2196F3');
barChart('routeRatingsChart',RL,RV,RL.map((_,i)=>PALETTE[i%8]),true);
barChart('hourlyChart',Array.from({length:24},(_,i)=>i+'h'),HOUR_D,HOUR_D.map((_,i)=>i===NOW_H?'#00E676':'rgba(0,230,118,.35)'));

// Reports page charts (init on nav)
let rptInited=false;
function initRptCharts(){
  lineChart('rptWeekly',WL,WD,'#00E676');
  donutChart('rptFleetDonut',['Active','Idle','Offline'],[FA,FI,FO],['#00E676','#FFB300','#FF3D3D'],document.getElementById('rptFleetLegend'));
  barChart('rptRatings',RL,RV,RL.map((_,i)=>PALETTE[i%6]),true);
  lineChart('rptUserGrowth',UGL,UGV,'#9C27B0');
}

// Print charts (light theme, static)
function initPrintCharts(){
  const po={responsive:true,animation:false,plugins:{legend:{display:false}},scales:{x:{grid:{color:'#eee'}},y:{grid:{color:'#eee'},beginAtZero:true}}};
  new Chart(document.getElementById('pc-weekly'),{type:'bar',data:{labels:WL,datasets:[{data:WD,backgroundColor:'#00C853',borderRadius:3}]},options:po});
  new Chart(document.getElementById('pc-fleet'),{type:'doughnut',data:{labels:['Active','Idle','Offline'],datasets:[{data:[FA,FI,FO],backgroundColor:['#00C853','#FF8F00','#D32F2F'],borderWidth:0}]},options:{responsive:true,animation:false,cutout:'60%',plugins:{legend:{display:true,position:'right',labels:{font:{size:10},color:'#333'}}}}});
  new Chart(document.getElementById('pc-ratings'),{type:'bar',data:{labels:RL,datasets:[{data:RV,backgroundColor:'#1565C0',borderRadius:3}]},options:{indexAxis:'y',...po,scales:{x:{...po.scales.x,max:5},y:po.scales.y}}});
  new Chart(document.getElementById('pc-growth'),{type:'line',data:{labels:UGL,datasets:[{data:UGV,borderColor:'#6a1b9a',backgroundColor:'rgba(106,27,154,.15)',borderWidth:2,fill:true,tension:.4}]},options:po});
}

function doPrint(){
  document.getElementById('rpt-wrapper').style.display='block';
  initPrintCharts();
  setTimeout(()=>{window.print();setTimeout(()=>{document.getElementById('rpt-wrapper').style.display='none';},800);},700);
}

// MAP
const map=L.map('adminMap',{center:[-1.2921,36.8219],zoom:12,zoomControl:false});
L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',{subdomains:'abcd',maxZoom:19}).addTo(map);
const mk=[];
async function loadFleet(){
  try{
    const r=await fetch('api/tracking.php?action=get_active');
    const d=await r.json();
    const ms=d.matatus||[];
    document.getElementById('fleetCount').textContent=ms.length+' active';
    document.getElementById('kpiActive').textContent=ms.length;
    mk.forEach(m=>map.removeLayer(m));mk.length=0;
    ms.forEach(m=>{const la=parseFloat(m.lat||m.latitude),ln=parseFloat(m.lng||m.longitude);if(isNaN(la)||isNaN(ln))return;const ic=L.divIcon({className:'',html:`<div style="width:28px;height:28px;border-radius:50%;background:${m.color||'#00E676'};border:2px solid white;display:flex;align-items:center;justify-content:center;font-size:12px;box-shadow:0 2px 8px rgba(0,0,0,.4)">🚐</div>`,iconSize:[28,28],iconAnchor:[14,14]});mk.push(L.marker([la,ln],{icon:ic}).addTo(map).bindPopup(`<strong>${m.plate}</strong><br>${m.route_number||''}<br>${m.speed||0} km/h`));});
  }catch(e){document.getElementById('fleetCount').textContent='4 active';}
}
loadFleet();
function refreshData(){document.getElementById('lastRefresh').textContent='Refreshed: '+new Date().toLocaleTimeString();loadFleet();}
setInterval(refreshData,15000);

// NAV
const SECS=['matatus','routes','users','drivers','wallets','reports','feedback','alerts'];
const TITLES={dashboard:'Operations Dashboard',matatus:'Matatu Management',routes:'Route Management',users:'User Management',drivers:'Driver Management',wallets:'Wallet Management',reports:'Analytics & Reports',feedback:'Passenger Feedback',alerts:'System Alerts'};
function adminNav(s){
  ['dashboard',...SECS].forEach(x=>{const n=document.getElementById('anav-'+x);if(n)n.classList.toggle('active',x===s);});
  SECS.forEach(x=>{const el=document.getElementById('asec-'+x);if(el)el.style.display=x===s?'block':'none';});
  document.getElementById('admin-main-page').style.display=s==='dashboard'?'block':'none';
  document.getElementById('adminPageTitle').textContent=TITLES[s]||'Dashboard';
  if(s==='reports'&&!rptInited){setTimeout(initRptCharts,100);rptInited=true;}
}

function prefillTopup(id, name, phone, bal) {
  // Scroll to and highlight the top-up form
  adminNav('wallets');
  const sel = document.querySelector('select[name="topup_user_id"]');
  if (sel) { sel.value = id; sel.dispatchEvent(new Event('change')); }
  const noteEl = document.querySelector('input[name="topup_note"]');
  if (noteEl) noteEl.value = 'M-PESA top-up for '+name;
  document.querySelector('.topup-form')?.scrollIntoView({behavior:'smooth',block:'start'});
}

// MODALS
function openModal(id){document.getElementById(id).classList.add('open');}
function closeModal(id){document.getElementById(id).classList.remove('open');}
document.querySelectorAll('.modal-backdrop').forEach(m=>{m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open');});});

function editMatatu(m){document.getElementById('me-id').value=m.id;document.getElementById('me-plate').value=m.registration_plate;document.getElementById('me-sacco').value=m.sacco_name||'';document.getElementById('me-route').value=m.route_id||'';document.getElementById('me-driver').value=m.driver_id||'';document.getElementById('me-capacity').value=m.capacity||14;document.getElementById('me-model').value=m.vehicle_model||'';document.getElementById('me-color').value=m.color||'';document.getElementById('me-active').checked=!!parseInt(m.is_active);openModal('matatuEditModal');}
function editRoute(r){document.getElementById('re-id').value=r.id;document.getElementById('re-number').value=r.route_number;document.getElementById('re-name').value=r.route_name;document.getElementById('re-origin').value=r.origin;document.getElementById('re-dest').value=r.destination;document.getElementById('re-faremin').value=r.fare_min;document.getElementById('re-faremax').value=r.fare_max;document.getElementById('re-dist').value=r.distance_km||'';document.getElementById('re-dur').value=r.avg_duration_minutes||'';document.getElementById('re-color').value=r.color_code||'#00C853';document.getElementById('re-active').checked=!!parseInt(r.is_active);openModal('routeEditModal');}
function editUser(u){document.getElementById('ue-id').value=u.id;document.getElementById('ue-name').value=u.full_name;document.getElementById('ue-email').value=u.email;document.getElementById('ue-phone').value=u.phone||'';document.getElementById('ue-role').value=u.role;document.getElementById('ue-active').checked=!!parseInt(u.is_active);openModal('userEditModal');}

function confirmDel(type,id,label){
  const T={matatu:'Delete Matatu',route:'Deactivate Route',user:'Deactivate User',alert:'Delete Alert'};
  const X={matatu:`Permanently delete <strong>${label}</strong>? Tracking data removed.`,route:`Deactivate <strong>${label}</strong>? Hidden from passengers.`,user:`Deactivate <strong>${label}</strong>? Cannot log in.`,alert:`Delete alert <strong>${label}</strong>?`};
  document.getElementById('confirmTitle').textContent=T[type]||'Confirm';
  document.getElementById('confirmText').innerHTML=X[type]||'This cannot be undone.';
  document.getElementById('ca').value='delete_'+type;
  document.getElementById('cm').value=type==='matatu'?id:'';
  document.getElementById('cr').value=type==='route'?id:'';
  document.getElementById('cu').value=type==='user'?id:'';
  document.getElementById('cal').value=type==='alert'?id:'';
  openModal('confirmModal');
}

function tSearch(input,tid){const q=input.value.toLowerCase();document.querySelectorAll('#'+tid+' tbody tr').forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(q)?'':'none';});}
function tFilter(sel,tid,ci){const v=sel.value.toUpperCase();document.querySelectorAll('#'+tid+' tbody tr').forEach(r=>{const c=r.cells[ci];r.style.display=(!v||!c||c.textContent.trim().toUpperCase().includes(v))?'':'none';});}
</script>
</body>
</html>
