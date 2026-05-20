<?php
/**
 * rating_system.php
 * نظام التقييم بالنجوم (1-5 نجوم)
 * مع عرض متوسط التقييم والعدد الإجمالي
 */

session_start();
require_once "db.php";
require_once "security_helpers.php";

header('Content-Type: application/json; charset=utf-8');

// معالجة طلبات AJAX
$action = $_GET['action'] ?? '';

// ============================================
// 1. إضافة/تحديث التقييم
// ============================================
if ($action === 'add_rating' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من تسجيل الدخول
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => t('login_required')]);
        exit;
    }
    
    // التحقق من CSRF Token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        echo json_encode(['success' => false, 'message' => t('security_verification_failed')]);
        exit;
    }
    
    $school_id = intval($_POST['school_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $review_text = sanitizeInput($_POST['review_text'] ?? '');
    $user_id = $_SESSION['user_id'];
    
    // التحقق من صحة البيانات
    if ($school_id <= 0 || $rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => t('invalid_data')]);
        exit;
    }
    
    // التحقق من وجود المدرسة
    $stmt = $conn->prepare("SELECT School_ID FROM Schools WHERE School_ID = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => t('school_not_found')]);
        exit;
    }
    
    // الحذف والإدراج الجديد (لتحديث التقييم)
    $stmt = $conn->prepare("DELETE FROM School_Ratings WHERE School_ID = ? AND User_ID = ?");
    $stmt->bind_param("ii", $school_id, $user_id);
    $stmt->execute();
    
    // إدراج التقييم الجديد
    $stmt = $conn->prepare("
        INSERT INTO School_Ratings (School_ID, User_ID, Rating, Review_Text) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiis", $school_id, $user_id, $rating, $review_text);
    
    if ($stmt->execute()) {
        // تسجيل في سجل التدقيق
        logSecurityEvent('CREATE', "المستخدم أضاف تقييماً للمدرسة", [
            'school_id' => $school_id,
            'rating' => $rating,
            'user_id' => $user_id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => t('rating_saved_success')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => t('rating_save_error') . ': ' . $conn->error
        ]);
    }
    exit;
}

// ============================================
// 2. الحصول على معلومات التقييم
// ============================================
if ($action === 'get_rating') {
    $school_id = intval($_GET['school_id'] ?? 0);
    
    if ($school_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'معرف المدرسة غير صحيح']);
        exit;
    }
    
    // الحصول على متوسط التقييم والعدد الإجمالي
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_ratings,
            AVG(Rating) as average_rating,
            SUM(CASE WHEN Rating = 5 THEN 1 ELSE 0 END) as five_stars,
            SUM(CASE WHEN Rating = 4 THEN 1 ELSE 0 END) as four_stars,
            SUM(CASE WHEN Rating = 3 THEN 1 ELSE 0 END) as three_stars,
            SUM(CASE WHEN Rating = 2 THEN 1 ELSE 0 END) as two_stars,
            SUM(CASE WHEN Rating = 1 THEN 1 ELSE 0 END) as one_star
        FROM School_Ratings 
        WHERE School_ID = ?
    ");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    // حساب النسب المئوية
    $total = $result['total_ratings'] ?? 0;
    if ($total > 0) {
        $result['five_stars_percent'] = round(($result['five_stars'] / $total) * 100, 1);
        $result['four_stars_percent'] = round(($result['four_stars'] / $total) * 100, 1);
        $result['three_stars_percent'] = round(($result['three_stars'] / $total) * 100, 1);
        $result['two_stars_percent'] = round(($result['two_stars'] / $total) * 100, 1);
        $result['one_star_percent'] = round(($result['one_star'] / $total) * 100, 1);
    }
    
    $result['average_rating'] = round($result['average_rating'] ?? 0, 2);
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
    exit;
}

// ============================================
// 3. الحصول على تقييم المستخدم الحالي
// ============================================
if ($action === 'get_user_rating') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'data' => null]);
        exit;
    }
    
    $school_id = intval($_GET['school_id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    
    if ($school_id <= 0) {
        echo json_encode(['success' => false, 'data' => null]);
        exit;
    }
    
    $stmt = $conn->prepare("
        SELECT Rating, Review_Text, Created_Date 
        FROM School_Ratings 
        WHERE School_ID = ? AND User_ID = ?
    ");
    $stmt->bind_param("ii", $school_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
    exit;
}

// ============================================
// 4. الحصول على أفضل المدارس المقيمة
// ============================================
if ($action === 'top_rated_schools') {
    $limit = intval($_GET['limit'] ?? 5);
    
    $stmt = $conn->prepare("
        SELECT 
            s.School_ID,
            s.School_Name,
            s.Education_Level,
            COUNT(r.Rating_ID) as total_ratings,
            ROUND(AVG(r.Rating), 2) as average_rating
        FROM Schools s
        LEFT JOIN School_Ratings r ON s.School_ID = r.School_ID
        GROUP BY s.School_ID
        HAVING average_rating > 0
        ORDER BY average_rating DESC, total_ratings DESC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schools = [];
    while ($row = $result->fetch_assoc()) {
        $schools[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $schools
    ]);
    exit;
}

// ============================================
// 5. الحصول على أحدث التقييمات
// ============================================
if ($action === 'latest_ratings') {
    $limit = intval($_GET['limit'] ?? 10);
    
    $stmt = $conn->prepare("
        SELECT 
            r.Rating_ID,
            r.Rating,
            r.Review_Text,
            r.Created_Date,
            s.School_Name,
            u.Username
        FROM School_Ratings r
        JOIN Schools s ON r.School_ID = s.School_ID
        JOIN Users u ON r.User_ID = u.User_ID
        ORDER BY r.Created_Date DESC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ratings = [];
    while ($row = $result->fetch_assoc()) {
        $ratings[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $ratings
    ]);
    exit;
}

// ============================================
// 6. حذف التقييم (للمسؤول أو المستخدم نفسه)
// ============================================
if ($action === 'delete_rating' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => t('login_required')]);
        exit;
    }
    
    // التحقق من CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => t('security_verification_failed')]);
        exit;
    }
    
    $rating_id = intval($_POST['rating_id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    $is_admin = ($_SESSION['role'] ?? '') === 'admin';
    
    // التحقق من أن المستخدم هو المالك أو مسؤول
    $stmt = $conn->prepare("SELECT User_ID FROM School_Ratings WHERE Rating_ID = ?");
    $stmt->bind_param("i", $rating_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result || ($result['User_ID'] !== $user_id && !$is_admin)) {
        echo json_encode(['success' => false, 'message' => t('no_permission_delete')]);
        exit;
    }
    
    // حذف التقييم
    $stmt = $conn->prepare("DELETE FROM School_Ratings WHERE Rating_ID = ?");
    $stmt->bind_param("i", $rating_id);
    
    if ($stmt->execute()) {
        logSecurityEvent('DELETE', "تم حذف تقييم", [
            'rating_id' => $rating_id,
            'deleted_by' => $user_id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => t('rating_deleted_success')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => t('rating_delete_error')
        ]);
    }
    exit;
}

// إجراء غير معروف
echo json_encode(['success' => false, 'message' => t('unknown_action')]);
?>
