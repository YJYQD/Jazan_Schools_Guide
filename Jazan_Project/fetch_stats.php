<?php
/**
 * API - جلب الإحصائيات الحية (Stats API) - موحدة
 * محسّن بـ: Prepared Statements, Security Headers, دعم كامل للفلاتر
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once "db.php";
require_once "filters_helper.php";
require_once "security_helpers.php";

$conn->set_charset('utf8mb4');

// بناء شروط الفلاتر الموحدة
$baseSql = "FROM Schools
    LEFT JOIN Offices ON Schools.Office_ID = Offices.Office_ID
    LEFT JOIN Governorates ON Offices.Gov_ID = Governorates.Gov_ID
    WHERE 1=1";

$params = [];
$types = "";
$filterConditions = buildFilterConditions($params, $types);
$baseSql .= $filterConditions;

$bindIfNeeded = function ($stmt, string $bindTypes, array $bindParams) {
    if ($stmt && $bindTypes !== '' && !empty($bindParams)) {
        $stmt->bind_param($bindTypes, ...$bindParams);
    }
};

// الاستعلام الأول للمدارس والمراحل والمكاتب
$sql = "SELECT 
    COUNT(*) AS total_schools,
    COUNT(DISTINCT Offices.Office_ID) AS total_offices,
    SUM(CASE WHEN Schools.Education_Level = 'روضة' THEN 1 ELSE 0 END) AS kinder,
    SUM(CASE WHEN Schools.Education_Level = 'ابتدائي' THEN 1 ELSE 0 END) AS elementary,
    SUM(CASE WHEN Schools.Education_Level = 'متوسط' THEN 1 ELSE 0 END) AS middle,
    SUM(CASE WHEN Schools.Education_Level = 'ثانوي' THEN 1 ELSE 0 END) AS high,
    SUM(CASE WHEN Schools.Education_Level = 'مجمع' THEN 1 ELSE 0 END) AS complex
    " . $baseSql;

// تنفيذ الاستعلام الأول
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['error' => 'فشل تحضير الاستعلام الرئيسي', 'debug' => $conn->error], JSON_UNESCAPED_UNICODE);
    exit();
}

// ربط المعاملات
$bindIfNeeded($stmt, $types, $params);

// تنفيذ الاستعلام
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'فشل تنفيذ الاستعلام الرئيسي', 'debug' => $stmt->error], JSON_UNESCAPED_UNICODE);
    exit();
}

// جلب النتائج
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

// الاستعلام الثاني للتقييمات (بنفس الفلاتر)
$reviewSql = "SELECT COUNT(*) AS total_reviews FROM School_Reviews WHERE School_ID IN (SELECT School_ID " . $baseSql . ")";
$reviewStmt = $conn->prepare($reviewSql);

if ($reviewStmt === false) {
    $total_reviews = 0;
} else {
    $bindIfNeeded($reviewStmt, $types, $params);
    
    if ($reviewStmt->execute()) {
        $reviewResult = $reviewStmt->get_result();
        $reviewRow = $reviewResult->fetch_assoc();
        $total_reviews = intval($reviewRow['total_reviews'] ?? 0);
        $reviewStmt->close();
    } else {
        $total_reviews = 0;
        $reviewStmt->close();
    }
}

// التأكد من أن جميع القيم أعداد صحيحة وتعيين قيم افتراضية
$output = [
    'total_schools' => intval($row['total_schools'] ?? 0),
    'total_offices' => intval($row['total_offices'] ?? 0),
    'total_reviews' => $total_reviews,
    'kinder' => intval($row['kinder'] ?? 0),
    'elementary' => intval($row['elementary'] ?? 0),
    'middle' => intval($row['middle'] ?? 0),
    'high' => intval($row['high'] ?? 0),
    'complex' => intval($row['complex'] ?? 0),
    // للتوافق مع الكود الحالي
    'total' => intval($row['total_schools'] ?? 0)
];

// إرجاع النتائج بصيغة JSON
echo json_encode($output, JSON_UNESCAPED_UNICODE);
exit();
?>