<?php
// ============================================================
// api/mpesa_callback.php  — Daraja STK Push callback
//
// Safaricom calls this URL after the customer pays (or cancels).
// It MUST return HTTP 200 quickly — do not do heavy work here.
// This endpoint is public (no session auth) — Safaricom hits it.
// ============================================================
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// ── Read and log raw payload ────────────────────────────────
$raw = file_get_contents('php://input');
error_log('[Daraja Callback] ' . $raw);

$data = json_decode($raw, true);
if (!$data) {
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

// ── Parse the callback structure ───────────────────────────
// Safaricom structure: Body.stkCallback
$callback = $data['Body']['stkCallback'] ?? null;
if (!$callback) {
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

$merchantRequestId = $callback['MerchantRequestID'] ?? '';
$checkoutRequestId = $callback['CheckoutRequestID'] ?? '';
$resultCode        = (int)($callback['ResultCode'] ?? -1);
$resultDesc        = $callback['ResultDesc'] ?? '';

$db = Database::getConnection();

// ── Find the matching pending transaction ───────────────────
$stmt = $db->prepare(
    "SELECT * FROM mpesa_transactions WHERE checkout_request_id = ? LIMIT 1"
);
$stmt->execute([$checkoutRequestId]);
$tx = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tx) {
    // Unknown transaction — acknowledge anyway
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

// Ignore duplicate callbacks
if ($tx['status'] !== 'pending') {
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

// ── Payment SUCCESSFUL (ResultCode 0) ──────────────────────
if ($resultCode === 0) {
    // Extract metadata items from CallbackMetadata
    $items = [];
    foreach (($callback['CallbackMetadata']['Item'] ?? []) as $item) {
        $items[$item['Name']] = $item['Value'] ?? null;
    }

    $mpesaCode = $items['MpesaReceiptNumber'] ?? null; // e.g. QHX4KXXXXXXXXX
    $paidAmt   = (float)($items['Amount'] ?? $tx['amount']);
    $phone     = $items['PhoneNumber'] ?? $tx['phone'];

    try {
        $db->beginTransaction();

        // 1. Mark mpesa_transaction as complete
        $db->prepare(
            "UPDATE mpesa_transactions
             SET status = 'complete', mpesa_code = ?, amount_paid = ?,
                 callback_payload = ?, completed_at = NOW()
             WHERE id = ?"
        )->execute([
            $mpesaCode,
            $paidAmt,
            $raw,
            $tx['id'],
        ]);

        $userId = (int)$tx['user_id'];

        // 2. Ensure wallet exists
        $db->prepare(
            "INSERT IGNORE INTO wallets (user_id, balance, total_topped, total_spent)
             VALUES (?, 0.00, 0.00, 0.00)"
        )->execute([$userId]);

        // 3. Credit the wallet
        $db->prepare(
            "UPDATE wallets
             SET balance = balance + ?, total_topped = total_topped + ?, updated_at = NOW()
             WHERE user_id = ?"
        )->execute([$paidAmt, $paidAmt, $userId]);

        // 4. Fetch new balance
        $walletStmt = $db->prepare("SELECT * FROM wallets WHERE user_id = ?");
        $walletStmt->execute([$userId]);
        $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);

        // 5. Record wallet transaction (reuses existing wallet_transactions table)
        $db->prepare(
            "INSERT INTO wallet_transactions
                (wallet_id, user_id, type, amount, balance_after, description, mpesa_code, performed_by)
             VALUES (?, ?, 'credit', ?, ?, ?, ?, NULL)"
        )->execute([
            (int)$wallet['id'],
            $userId,
            $paidAmt,
            $wallet['balance'],
            'M-PESA STK Push Top-Up',
            $mpesaCode,
        ]);

        $db->commit();

        error_log("[Daraja Callback] SUCCESS — User $userId credited KES $paidAmt. Code: $mpesaCode");

    } catch (Exception $e) {
        $db->rollBack();
        error_log('[Daraja Callback] DB error: ' . $e->getMessage());
    }

} else {
    // ── Payment FAILED / CANCELLED ──────────────────────────
    $db->prepare(
        "UPDATE mpesa_transactions
         SET status = 'failed', result_desc = ?, callback_payload = ?, completed_at = NOW()
         WHERE id = ?"
    )->execute([$resultDesc, $raw, $tx['id']]);

    error_log("[Daraja Callback] FAILED — checkout $checkoutRequestId: $resultDesc");
}

// Always acknowledge Safaricom with HTTP 200
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
exit;
