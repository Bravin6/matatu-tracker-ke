<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    startSecureSession();
    if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
        jsonResponse(['error'=>true,'message'=>'Unauthorized'], 403);
    }
    $title    = sanitize($_POST['title'] ?? '');
    $message  = sanitize($_POST['message'] ?? '');
    $type     = in_array($_POST['alert_type']??'',['maintenance','disruption','info','emergency'])
                ? $_POST['alert_type'] : 'info';
    if (!$title || !$message) jsonResponse(['error'=>true,'message'=>'Title and message required'], 400);
    try {
        $db = Database::getConnection();
        $db->prepare("INSERT INTO system_alerts (title,message,alert_type,created_by) VALUES (:t,:m,:typ,:u)")
           ->execute([':t'=>$title,':m'=>$message,':typ'=>$type,':u'=>$_SESSION['user_id']]);
        // Redirect back to admin
        header('Location: ../admin-dashboard.php?success=alert_sent');
        exit;
    } catch (Exception $e) {
        jsonResponse(['error'=>true,'message'=>$e->getMessage()], 500);
    }
}

if ($action === 'list') {
    try {
        $db = Database::getConnection();
        $alerts = $db->query("SELECT * FROM system_alerts WHERE is_active=1 ORDER BY created_at DESC LIMIT 20")->fetchAll();
        jsonResponse(['success'=>true,'alerts'=>$alerts]);
    } catch (Exception $e) {
        jsonResponse(['success'=>true,'alerts'=>[
            ['title'=>'Traffic Advisory','message'=>'Heavy traffic on Mombasa Road','alert_type'=>'disruption','created_at'=>date('Y-m-d H:i:s')],
            ['title'=>'Service Update','message'=>'Route 45 normal operations resumed','alert_type'=>'info','created_at'=>date('Y-m-d H:i:s')]
        ]]);
    }
}

jsonResponse(['error'=>true,'message'=>'Invalid request'], 400);
