<?php
// ============================================================
// Authentication Handler
// ============================================================
require_once __DIR__ . '/includes/config.php';

startSecureSession();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ============================================================
// LOGOUT
// ============================================================
if ($action === 'logout') {
    session_destroy();
    header('Location: index.php?msg=logged_out');
    exit;
}

// ============================================================
// LOGIN
// ============================================================
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = sanitize($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $remember   = isset($_POST['remember']);

    if (empty($identifier) || empty($password)) {
        header('Location: index.php?error=empty_fields');
        exit;
    }

    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT id, full_name, email, phone, password_hash, role, is_active
            FROM users
            WHERE (email = :email OR phone = :phone) AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute(['email' => $identifier, 'phone' => $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Update last login
            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id")
               ->execute(['id' => $user['id']]);

            // Set session
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_phone'] = $user['phone'];
            $_SESSION['user_role']  = $user['role'];
            $_SESSION['login_time'] = time();

            // If driver, get their matatu
            if ($user['role'] === 'driver') {
                $mStmt = $db->prepare("SELECT id, registration_plate, route_id FROM matatus WHERE driver_id = :did LIMIT 1");
                $mStmt->execute(['did' => $user['id']]);
                $matatu = $mStmt->fetch();
                if ($matatu) {
                    $_SESSION['matatu_id']    = $matatu['id'];
                    $_SESSION['matatu_plate'] = $matatu['registration_plate'];
                    $_SESSION['route_id']     = $matatu['route_id'];
                }
            }

            // Redirect by role
            if ($user['role'] === 'driver') {
                header('Location: driver-dashboard.php');
            } elseif ($user['role'] === 'admin') {
                header('Location: admin-dashboard.php');
            } else {
                header('Location: passenger-dashboard.php');
            }
            exit;
        } else {
            header('Location: index.php?error=invalid_credentials');
            exit;
        }
    } catch (Exception $e) {
        header('Location: index.php?error=server_error');
        exit;
    }
}

// ============================================================
// REGISTER
// ============================================================
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name'] ?? '');
    $email     = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $phone     = sanitize($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';
    $role      = in_array($_POST['role'] ?? '', ['passenger', 'driver']) ? $_POST['role'] : 'passenger';

    $errors = [];
    if (empty($full_name)) $errors[] = 'Full name is required';
    if (!$email) $errors[] = 'Valid email is required';
    if (strlen($phone) < 10) $errors[] = 'Valid phone number is required';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
    if ($password !== $confirm) $errors[] = 'Passwords do not match';

    if (!empty($errors)) {
        $errStr = urlencode(implode('|', $errors));
        header("Location: index.php?error=validation&msg={$errStr}");
        exit;
    }

    try {
        $db = Database::getConnection();

        // Check duplicates
        $check = $db->prepare("SELECT id FROM users WHERE email = :e OR phone = :p LIMIT 1");
        $check->execute(['e' => $email, 'p' => $phone]);
        if ($check->fetch()) {
            header('Location: index.php?error=duplicate_user');
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $ins  = $db->prepare("INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (:n,:e,:p,:h,:r)");
        $ins->execute(['n' => $full_name, 'e' => $email, 'p' => $phone, 'h' => $hash, 'r' => $role]);

        header('Location: index.php?success=registered');
        exit;
    } catch (Exception $e) {
        header('Location: index.php?error=register_failed');
        exit;
    }
}

// Default redirect
header('Location: index.php');
exit;
