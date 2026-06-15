<?php
// ============================================================
// Database Configuration
// Real-Time Matatu Tracking System
// ============================================================

define('DB_HOST', 'localhost');
define('DB_PORT', '3310');
define('DB_NAME', 'matatu_tracker');
define('DB_USER', 'root');         // Change to your MySQL username
define('DB_PASS', '');             // Change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'MatatuTrack');
define('APP_VERSION', '2.0');
define('APP_URL', 'http://localhost/matatu-tracker'); // ← the folder name here
define('SESSION_LIFETIME', 3600 * 8); // 8 hours

// Google Maps API Key (replace with your actual key)
define('GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_API_KEY');

// JWT Secret for API tokens
define('JWT_SECRET', 'matatu_track_secret_key_2024_nairobi');

// ── Safaricom Daraja API credentials ─────────────────────────
// Get these from https://developer.safaricom.co.ke after creating an app.
// For sandbox testing use the sandbox app credentials.

define('MPESA_SANDBOX',        true);   // ← set false in production

// Sandbox credentials (from Daraja portal "sandbox" app):
define('MPESA_CONSUMER_KEY',    'lgxvyA4m6IPdg5tGSyiUrJTG7uC189FcpoRmYEhuPMfG0hG1');
define('MPESA_CONSUMER_SECRET', 'GbFoakbGXAjcLB5CBC6rAiUVjeaY0fZATGnkBBS9d8RzGNQldixMCm25r5RgzPwu');

// For sandbox STK Push use the test shortcode 174379
define('MPESA_SHORTCODE',       '174379');
define('MPESA_PASSKEY',         'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'); // sandbox passkey

// Your publicly reachable callback URL — MUST be HTTPS in production.
// For local dev use ngrok: https://YOUR_TUNNEL.ngrok.io/matatu-tracker/api/mpesa_callback.php
define('MPESA_CALLBACK_URL', 'https://pursuant-marital-approval.ngrok-free.dev/matatu-tracker/api/mpesa_callback.php');

// ============================================================
// Database Connection (Singleton PDO)
// ============================================================
class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                die(json_encode([
                    'error' => true,
                    'message' => 'Database connection failed',
                    'detail' => $e->getMessage()
                ]));
            }
        }
        return self::$instance;
    }

    private function __construct() {}
    private function __clone() {}
}

// ============================================================
// Session Helper
// ============================================================
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        session_start();
    }
}

function isLoggedIn(): bool {
    startSecureSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['user_role'] !== $role && $_SESSION['user_role'] !== 'admin') {
        header('Location: dashboard.php?error=unauthorized');
        exit;
    }
}

function getCurrentUser(): array {
    startSecureSession();
    return [
        'id'    => $_SESSION['user_id'] ?? null,
        'name'  => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['user_role'] ?? '',
        'phone' => $_SESSION['user_phone'] ?? ''
    ];
}

// ============================================================
// Response Helpers
// ============================================================
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken(): string {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken(string $token): bool {
    startSecureSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
