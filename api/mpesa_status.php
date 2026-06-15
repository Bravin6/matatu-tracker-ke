<?php
// ============================================================
// api/mpesa_status.php  — Poll STK Push payment status
//
// GET ?checkout_request_id=ws_CO_xxxxx
//
// Returns:
//   { "status": "pending"|"complete"|"failed", "message": "..." }
// ============================================================
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
startSecureSession();

if (!isLoggedIn()) {
    jsonResponse(['error' => true, 'message' => 'Unauthorized'], 401);
}

$db             = Database::getConnection();
$me             = (int)$_SESSION['user_id'];
$checkoutId     = trim($_GET['checkout_request_id'] ?? '');

if (empty($checkoutId)) {
    jsonResponse(['error' => true, 'message' => 'Missing checkout_request_id'], 400);
}

$stmt = $db->prepare(
    "SELECT * FROM mpesa_transactions WHERE checkout_request_id = ? AND user_id = ? LIMIT 1"
);
$stmt->execute([$checkoutId, $me]);
$tx = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tx) {
    jsonResponse(['error' => true, 'message' => 'Transaction not found'], 404);
}

switch ($tx['status']) {
    case 'complete':
        // Also return new balance so the UI can update instantly
        $walletStmt = $db->prepare("SELECT balance FROM wallets WHERE user_id = ?");
        $walletStmt->execute([$me]);
        $balance = (float)($walletStmt->fetchColumn() ?? 0);

        jsonResponse([
            'status'      => 'complete',
            'message'     => 'KES ' . number_format((float)$tx['amount_paid'], 2) . ' added to your wallet!',
            'mpesa_code'  => $tx['mpesa_code'],
            'new_balance' => number_format($balance, 2),
        ]);
        break;

    case 'failed':
        jsonResponse([
            'status'  => 'failed',
            'message' => $tx['result_desc'] ?: 'Payment was cancelled or failed.',
        ]);
        break;

    default: // pending
        // Optionally query Daraja directly if older than 30s and still pending
        $age = time() - strtotime($tx['initiated_at']);

        if ($age > 90) {
            // Transaction is stale — query Daraja for definitive status
            try {
                require_once __DIR__ . '/../includes/mpesa.php';
                $mpesa  = new Mpesa();
                $result = $mpesa->stkQuery($checkoutId);

                if (isset($result['ResultCode']) && (int)$result['ResultCode'] === 0) {
                    // Safaricom says it's complete but callback may not have arrived yet
                    jsonResponse([
                        'status'  => 'pending',
                        'message' => 'Payment confirmed — crediting your wallet…',
                    ]);
                } elseif (isset($result['ResultCode'])) {
                    // Definitive failure from Daraja query
                    $db->prepare(
                        "UPDATE mpesa_transactions SET status='failed', result_desc=? WHERE id=?"
                    )->execute([$result['ResultDesc'] ?? 'Timeout', $tx['id']]);

                    jsonResponse([
                        'status'  => 'failed',
                        'message' => $result['ResultDesc'] ?? 'Payment timed out. Please try again.',
                    ]);
                }
            } catch (Exception $e) {
                // Daraja query failed — stay in pending state
            }
        }

        jsonResponse([
            'status'  => 'pending',
            'message' => 'Waiting for M-PESA confirmation…',
        ]);
}
