<?php
// ============================================================
// api/wallet.php  — Wallet system
// Actions: balance | topup | debit | history | all_balances
// ============================================================
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

startSecureSession();
if (!isLoggedIn()) { jsonResponse(['error'=>true,'message'=>'Unauthorized'], 401); }

$db     = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Read php://input ONCE — it is a one-time stream
$rawInput = file_get_contents('php://input');
$input    = json_decode($rawInput, true) ?? [];

// Action: URL param takes priority, then JSON body
$action = $_GET['action'] ?? ($input['action'] ?? '');

$me   = (int)$_SESSION['user_id'];
$role = $_SESSION['user_role'];

// ── helpers ───────────────────────────────────────────────────
function ensureWallet($db, $userId) {
    $db->prepare("INSERT IGNORE INTO wallets (user_id, balance, total_topped, total_spent) VALUES (?, 0.00, 0.00, 0.00)")
       ->execute([$userId]);
}

function getWallet($db, $userId) {
    ensureWallet($db, $userId);
    $s = $db->prepare("SELECT * FROM wallets WHERE user_id = ?");
    $s->execute([$userId]);
    return $s->fetch(PDO::FETCH_ASSOC);
}

// ============================================================
// GET: balance
// ============================================================
if ($method === 'GET' && $action === 'balance') {
    $uid = ($role === 'admin' && isset($_GET['user_id'])) ? (int)$_GET['user_id'] : $me;
    $w   = getWallet($db, $uid);
    jsonResponse(['success' => true, 'data' => [
        'balance'      => number_format((float)$w['balance'],      2, '.', ''),
        'total_topped' => number_format((float)$w['total_topped'], 2, '.', ''),
        'total_spent'  => number_format((float)$w['total_spent'],  2, '.', ''),
        'updated_at'   => $w['updated_at'],
    ]]);
}

// ============================================================
// GET: history
// ============================================================
if ($method === 'GET' && $action === 'history') {
    $uid   = ($role === 'admin' && isset($_GET['user_id'])) ? (int)$_GET['user_id'] : $me;
    $limit = min((int)($_GET['limit'] ?? 50), 200);
    $s = $db->prepare(
        "SELECT wt.*, u.full_name AS performed_by_name
         FROM wallet_transactions wt
         LEFT JOIN users u ON wt.performed_by = u.id
         WHERE wt.user_id = ?
         ORDER BY wt.created_at DESC
         LIMIT ?"
    );
    $s->bindValue(1, $uid,   PDO::PARAM_INT);
    $s->bindValue(2, $limit, PDO::PARAM_INT);
    $s->execute();
    jsonResponse(['success' => true, 'data' => $s->fetchAll(PDO::FETCH_ASSOC)]);
}

// ============================================================
// GET: all_balances — admin only
// ============================================================
if ($method === 'GET' && $action === 'all_balances') {
    if ($role !== 'admin') jsonResponse(['error'=>true,'message'=>'Forbidden'], 403);
    $rows = $db->query(
        "SELECT u.id, u.full_name, u.email, u.phone,
                COALESCE(w.balance,0)      AS balance,
                COALESCE(w.total_topped,0) AS total_topped,
                COALESCE(w.total_spent,0)  AS total_spent,
                w.updated_at
         FROM users u
         LEFT JOIN wallets w ON u.id = w.user_id
         WHERE u.role = 'passenger'
         ORDER BY u.full_name"
    )->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(['success' => true, 'data' => $rows]);
}

// ============================================================
// POST: topup — admin only
// ============================================================
if ($method === 'POST' && $action === 'topup') {
    if ($role !== 'admin') jsonResponse(['error'=>true,'message'=>'Forbidden'], 403);

    $uid        = (int)($input['user_id'] ?? 0);
    $amount     = round((float)($input['amount'] ?? 0), 2);
    $mpesa_code = sanitize($input['mpesa_code'] ?? '');
    $note       = sanitize($input['note'] ?? 'M-PESA top-up');

    if (!$uid || $amount <= 0) jsonResponse(['error'=>true,'message'=>'Invalid user or amount'], 400);
    if (empty($mpesa_code))    jsonResponse(['error'=>true,'message'=>'M-PESA code is required'], 400);
    if ($amount > 50000)       jsonResponse(['error'=>true,'message'=>'Max single top-up is KES 50,000'], 400);

    $dup = $db->prepare("SELECT id FROM wallet_transactions WHERE mpesa_code = ? LIMIT 1");
    $dup->execute([$mpesa_code]);
    if ($dup->fetch()) jsonResponse(['error'=>true,'message'=>'This M-PESA code has already been used'], 400);

    try {
        $db->beginTransaction();
        ensureWallet($db, $uid);
        $db->prepare("UPDATE wallets SET balance=balance+?, total_topped=total_topped+?, updated_at=NOW() WHERE user_id=?")
           ->execute([$amount, $amount, $uid]);
        $w = getWallet($db, $uid);
        $db->prepare(
            "INSERT INTO wallet_transactions
                (wallet_id, user_id, type, amount, balance_after, description, mpesa_code, performed_by)
             VALUES (?, ?, 'credit', ?, ?, ?, ?, ?)"
        )->execute([(int)$w['id'], $uid, $amount, $w['balance'], $note, $mpesa_code, $me]);
        $db->commit();
        jsonResponse([
            'success'     => true,
            'message'     => 'KES '.number_format($amount,2).' credited to wallet',
            'new_balance' => number_format((float)$w['balance'], 2),
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error'=>true,'message'=>$e->getMessage()], 500);
    }
}

// ============================================================
// POST: debit — passenger pays fare
// ============================================================
if ($method === 'POST' && $action === 'debit') {

    $uid    = (int)($input['user_id'] ?? $me);
    $amount = round((float)($input['amount'] ?? 0), 2);
    $ref    = sanitize($input['reference'] ?? '');

    // Strip any non-ASCII characters from description to prevent
    // PDO parameter parser issues with multibyte chars (e.g. · U+00B7)
    $rawDesc = $input['description'] ?? 'Trip fare';
    $desc    = sanitize(preg_replace('/[^\x00-\x7F]/', '-', $rawDesc));

    if ($role === 'passenger' && $uid !== $me)
        jsonResponse(['error'=>true,'message'=>'Forbidden'], 403);

    if ($amount <= 0)
        jsonResponse(['error'=>true,'message'=>'Invalid amount'], 400);

    ensureWallet($db, $uid);
    $w = getWallet($db, $uid);
    if ((float)$w['balance'] < $amount) {
        jsonResponse([
            'error'     => true,
            'message'   => 'Insufficient balance. You need KES '.number_format($amount-(float)$w['balance'],2).' more.',
            'shortfall' => round($amount-(float)$w['balance'],2),
        ], 400);
    }

    try {
        $db->beginTransaction();

        $db->prepare("UPDATE wallets SET balance=balance-?, total_spent=total_spent+?, updated_at=NOW() WHERE user_id=?")
           ->execute([$amount, $amount, $uid]);

        $s = $db->prepare("SELECT * FROM wallets WHERE user_id = ?");
        $s->execute([$uid]);
        $w = $s->fetch(PDO::FETCH_ASSOC);

        // Use positional ? placeholders — avoids ALL named-param parsing issues
        // with multibyte characters, special chars in values, or PDO version quirks.
        // Nullable columns (reference, trip_id) are passed as null — positional
        // placeholders handle null correctly without PDO::PARAM_NULL.
        $stmt = $db->prepare(
            "INSERT INTO wallet_transactions
                (wallet_id, user_id, type, amount, balance_after, description, reference, trip_id)
             VALUES (?, ?, 'debit', ?, ?, ?, ?, NULL)"
        );
        $stmt->execute([
            (int)$w['id'],
            $uid,
            $amount,
            $w['balance'],
            $desc,
            $ref ?: null,
        ]);

        $db->commit();
        jsonResponse([
            'success'     => true,
            'message'     => 'Fare deducted',
            'new_balance' => number_format((float)$w['balance'], 2),
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error'=>true,'message'=>$e->getMessage()], 500);
    }
}

jsonResponse(['error'=>true,'message'=>'Unknown action'], 400);