<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input    = json_decode(file_get_contents('php://input'), true);
    $user_id  = intval($input['user_id'] ?? 0);
    $matatu_id= intval($input['matatu_id'] ?? 0);
    $rating   = intval($input['rating'] ?? 0);
    $comment  = sanitize($input['comment'] ?? '');

    if (!$matatu_id || $rating < 1 || $rating > 5) {
        jsonResponse(['error' => true, 'message' => 'Invalid input'], 400);
    }
    try {
        $db = Database::getConnection();
        $db->prepare("INSERT INTO feedback (user_id, matatu_id, rating, comment, trip_date) VALUES (:u,:m,:r,:c,CURDATE())")
           ->execute([':u'=>$user_id ?: null, ':m'=>$matatu_id, ':r'=>$rating, ':c'=>$comment]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
    }
}
jsonResponse(['error' => true, 'message' => 'Invalid request'], 400);
