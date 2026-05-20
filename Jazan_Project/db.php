<?php
/**
 * db.php
 * Database Connection with Session Management and CSRF Protection
 */

require_once __DIR__ . '/config.php';

// ============ SESSION MANAGEMENT ============
// Configure secure session cookie parameters
// determine secure flag if using HTTPS
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
$secureFlag = SESSION_COOKIE_SECURE || $isHttps;
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        @session_set_cookie_params([
            'lifetime' => SESSION_COOKIE_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => $secureFlag,
            'httponly' => SESSION_COOKIE_HTTPONLY,
            'samesite' => SESSION_COOKIE_SAMESITE,
        ]);
    }
    session_start();
}

// ============ DATABASE CONNECTION ============
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

/**
 * Execute a count query safely and always return an integer.
 */
function db_fetch_count(mysqli $conn, string $sql, string $types = '', array $params = []): int {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($row)) {
        return 0;
    }

    if (array_key_exists('total', $row)) {
        return (int) $row['total'];
    }

    if (array_key_exists('count', $row)) {
        return (int) $row['count'];
    }

    return 0;
}

// ============ CSRF TOKEN FUNCTIONS ============

/**
 * Generate and return CSRF token
 * يوليد وإرجاع CSRF token
 */
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Verify CSRF token
 * التحقق من صحة CSRF token
 */
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

/**
 * Get current CSRF token
 * الحصول على CSRF token الحالي
 */
if (!function_exists('get_csrf_token')) {
    function get_csrf_token() {
        return generate_csrf_token();
    }
}

/**
 * Detect actual timestamp column name in Audit_Trail table.
 * Returns a safe column name string (falls back to Action_Timestamp).
 */
function get_audit_timestamp_column() {
    global $conn;

    $candidates = ['Action_Timestamp', 'timestamp', 'created_at', 'action_date', 'createdAt'];
    $databaseName = defined('DB_NAME') ? DB_NAME : '';

    if ($databaseName === '') {
        return 'Action_Timestamp';
    }

    // Build parameterized query to check INFORMATION_SCHEMA
    $placeholders = implode("','", $candidates);
    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'Audit_Trail' AND COLUMN_NAME IN ('" . $placeholders . "') LIMIT 1";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('s', $databaseName);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $col = $row['COLUMN_NAME'];
            $stmt->close();
            return $col;
        }
        $stmt->close();
    }

    // Default fallback
    return 'Action_Timestamp';
}

// Note: CSRF helpers are provided in security_helpers.php. Avoid declaring
// duplicate wrapper functions here to prevent redeclare errors.
?>
