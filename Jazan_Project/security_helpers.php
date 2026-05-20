<?php
/**
 * ملف المساعدات الأمنية - دليل مدارس المملكة
 * يحتوي على دوال: CSRF Protection, Logging, Rate Limiting
 */

// ============ 1. CSRF TOKEN MANAGEMENT ============

/**
 * توليد وتخزين CSRF Token
 */
if (!function_exists('initiateCsrfToken')) {
    function initiateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * التحقق من صحة CSRF Token
 */
if (!function_exists('validateCsrfToken')) {
    function validateCsrfToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

/**
 * إرجاع CSRF Token الحالي
 */
if (!function_exists('getCsrfToken')) {
    function getCsrfToken() {
        return isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    }
}

// ============ 2. LOGGING SYSTEM ============

define('LOG_FILE', __DIR__ . '/logs/security.log');
define('LOG_MAX_SIZE', 5 * 1024 * 1024); // 5MB

/**
 * تسجيل العمليات الأمنية
 * @param $level - debug, info, warning, error, critical
 * @param $message - الرسالة
 * @param $data - بيانات إضافية (مثل user_id, IP, الحدث)
 */
function logSecurityEvent($level, $message, $data = []) {
    // تحقق من حجم السجل وأعد تعيينه إذا لزم الأمر
    if (file_exists(LOG_FILE) && filesize(LOG_FILE) > LOG_MAX_SIZE) {
        rename(LOG_FILE, LOG_FILE . '.' . date('Y-m-d-His'));
    }

    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $user = isset($_SESSION['username']) ? $_SESSION['username'] : 'ANONYMOUS';
    
    $logEntry = [
        'timestamp' => $timestamp,
        'level' => strtoupper($level),
        'message' => $message,
        'user' => $user,
        'ip' => $ip,
        'data' => json_encode($data, JSON_UNESCAPED_UNICODE)
    ];

    $logLine = "[" . $logEntry['timestamp'] . "] [" . $logEntry['level'] . "] " .
               "User: " . $logEntry['user'] . " | IP: " . $logEntry['ip'] . " | " .
               $logEntry['message'] . " | Data: " . $logEntry['data'] . PHP_EOL;

    error_log($logLine, 3, LOG_FILE);
}

// أمثلة على الاستخدام:
// logSecurityEvent('warning', 'Failed login attempt', ['username' => $user]);
// logSecurityEvent('info', 'User logged in successfully', ['user_id' => $uid]);
// logSecurityEvent('error', 'SQL Error', ['query' => $sql]);

// ============ 3. RATE LIMITING ============

/**
 * فحص حد معدل الطلبات (Rate Limiting)
 * @param $identifier - معرف فريد (مثل IP أو username)
 * @param $max_attempts - عدد المحاولات المسموحة
 * @param $time_window - النافذة الزمنية بالثواني (مثل 300 = 5 دقائق)
 * @return true إذا كان الطلب مسموحاً، false إذا تجاوز الحد
 */
function checkRateLimit($identifier, $max_attempts = 5, $time_window = 300) {
    $lockFile = sys_get_temp_dir() . '/ratelimit_' . md5($identifier) . '.lock';
    
    // إنشئ السجل إذا لم يكن موجوداً
    if (!file_exists($lockFile)) {
        file_put_contents($lockFile, json_encode(['count' => 0, 'first_attempt' => time()]));
        return true;
    }

    $data = json_decode(file_get_contents($lockFile), true);
    $elapsed = time() - $data['first_attempt'];

    // إعادة تعيين إذا مضت النافذة الزمنية
    if ($elapsed > $time_window) {
        file_put_contents($lockFile, json_encode(['count' => 1, 'first_attempt' => time()]));
        return true;
    }

    // زيادة العداد
    $data['count']++;
    file_put_contents($lockFile, json_encode($data));

    // تحقق من تجاوز الحد
    return $data['count'] <= $max_attempts;
}

/**
 * مسح سجل حد معدل الطلبات (عند النجاح)
 */
function clearRateLimit($identifier) {
    $lockFile = sys_get_temp_dir() . '/ratelimit_' . md5($identifier) . '.lock';
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}

// ============ 4. PASSWORD SECURITY ============

/**
 * تشفير كلمة المرور
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
}

/**
 * التحقق من كلمة المرور
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * التحقق من قوة كلمة المرور
 */
function isStrongPassword($password) {
    // على الأقل 8 أحرف، حرف كبير، حرف صغير، رقم
    return strlen($password) >= 8 &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[a-z]/', $password) &&
           preg_match('/[0-9]/', $password);
}

// ============ 5. INPUT VALIDATION ============

/**
 * التحقق من صحة اسم المستخدم
 */
function isValidUsername($username) {
    // يجب أن يكون من 3-20 حرف، ألفانمريك و underscore فقط
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

/**
 * التحقق من صحة البريد الإلكتروني
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * تنظيف المدخل النصي (ولا تزيل الأحرف المهمة)
 */
function sanitizeInput($input) {
    return trim(strip_tags($input));
}

/**
 * إرجاع الأدوار المسموح لها برفع صور المدارس
 */
function getAllowedSchoolImageUploadRoles() {
    return ['principal', 'admin'];
}

/**
 * التحقق من صلاحية رفع صور المدارس
 */
function canManageSchoolImages() {
    $role = $_SESSION['role'] ?? '';
    return in_array($role, ['principal', 'admin'], true);
}

// ============ 6. ERROR HANDLING ============

/**
 * معالجة الأخطاء بأمان (بدون كشف تفاصيل النظام)
 */
function handleDbError($error, $log_message = '') {
    // سجل الخطأ الفعلي
    logSecurityEvent('error', $log_message ?: 'Database error occurred', ['error' => $error]);
    
    // أظهر رسالة عامة للمستخدم
    return "حدث خطأ تقني. يرجى المحاولة لاحقاً.";
}

// ============ 7. SESSION SECURITY ============

/**
 * تأمين الجلسة
 */
function secureSession() {
    // منع session fixation
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }

    // تحقق من timeout (30 دقيقة)
    if (isset($_SESSION['last_activity'])) {
        $idle_timeout = 1800; // 30 دقيقة
        if ((time() - $_SESSION['last_activity']) > $idle_timeout) {
            session_unset();
            session_destroy();
            return false;
        }
    }

    $_SESSION['last_activity'] = time();
    return true;
}

// ============ 8. IP WHITELIST/BLACKLIST (اختياري) ============

/**
 * التحقق من IP المسموحة (اختياري للعمليات الحساسة)
 */
function isAllowedIP($ip = null) {
    $ip = $ip ?? $_SERVER['REMOTE_ADDR'];
    $allowed_ips = []; // أضف IPs المسموحة هنا إذا لزم الأمر
    
    // إذا كانت القائمة فارغة، اسمح بالجميع
    if (empty($allowed_ips)) {
        return true;
    }

    return in_array($ip, $allowed_ips);
}

?>
