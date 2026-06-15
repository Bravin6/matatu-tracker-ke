<?php
// ============================================================
// api/mpesa_stk.php  — Initiate STK Push for wallet top-up
//
// POST body (JSON):
//   { "phone": "0712345678", "amount": 500 }
//
// Response (success):
//   { "success": true, "checkout_request_id": "ws_CO_...", "message": "..." }
// Response (error):
//   { "error": true, "message": "..." }
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mpesa.php';

header('Content-Type: application/json');
startSecureSession();

if (!isLoggedIn()) {
    jsonResponse(['error' => true, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => true, 'message' => 'Method not allowed'], 405);
}

$db    = Database::getConnection();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$me    = (int)$_SESSION['user_id'];

// ── Validate input ─────────────────────────────────────────
$phone  = trim($input['phone'] ?? ($_SESSION['user_phone'] ?? ''));
$amount = (int)round((float)($input['amount'] ?? 0));

if (empty($phone)) {
    jsonResponse(['error' => true, 'message' => 'Phone number is required'], 400);
}
if (!preg_match('/^(?:\+?254|0)[17]\d{8}$/', $phone)) {
    jsonResponse(['error' => true, 'message' => 'Enter a valid Kenyan phone number (07xx or 01xx)'], 400);
}
if ($amount < 10) {
    jsonResponse(['error' => true, 'message' => 'Minimum top-up is KES 10'], 400);
}
if ($amount > 150000) {
    jsonResponse(['error' => true, 'message' => 'Maximum top-up is KES 150,000'], 400);
}

// ── Prevent duplicate pending requests ─────────────────────
$pending = $db->prepare(
    "SELECT id FROM mpesa_transactions
     WHERE user_id = ? AND status = 'pending'
       AND initiated_at > DATE_SUB(NOW(), INTERVAL 3 MINUTE)
     LIMIT 1"
);
$pending->execute([$me]);
if ($pending->fetch()) {
    jsonResponse(['error' => true, 'message' => 'You have a pending payment. Please wait a moment before trying again.'], 429);
}

// ── Fire STK Push ───────────────────────────────────────────
try {
    $mpesa = new Mpesa();
    $result = $mpesa->stkPush(
        phone:       $phone,
        amount:      $amount,
        accountRef:  'MatatuTrack',
        description: 'Wallet Top-Up'
    );

    // Daraja returns ResponseCode "0" on success
    if (($result['ResponseCode'] ?? '') !== '0') {
        $errMsg = $result['errorMessage'] ?? ($result['ResponseDescription'] ?? 'STK Push failed');
        jsonResponse(['error' => true, 'message' => $errMsg]);
    }

    $checkoutId = $result['CheckoutRequestID'];
    $merchantId = $result['MerchantRequestID'];

    // ── Record pending transaction ──────────────────────────
    $db->prepare(
        "INSERT INTO mpesa_transactions
            (user_id, phone, amount, checkout_request_id, merchant_request_id, status, initiated_at)
         VALUES (?, ?, ?, ?, ?, 'pending', NOW())"
    )->execute([$me, $phone, $amount, $checkoutId, $merchantId]);

    jsonResponse([
        'success'             => true,
        'checkout_request_id' => $checkoutId,
        'message'             => 'Check your phone (' . $phone . ') and enter your M-PESA PIN to complete the payment.',
    ]);

} catch (Exception $e) {
    error_log('[Daraja STK] ' . $e->getMessage());
    jsonResponse(['error' => true, 'message' => 'Could not initiate payment. Please try again.'], 500);
}
