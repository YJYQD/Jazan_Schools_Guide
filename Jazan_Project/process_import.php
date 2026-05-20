<?php
session_start();
require_once "db.php";
require_once "security_helpers.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_import.php');
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['import_flash'] = [
        'type' => 'error',
        'message' => t('security_verification_failed') . ' أعد المحاولة.'
    ];
    header('Location: admin_import.php');
    exit;
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['import_flash'] = [
        'type' => 'error',
        'message' => 'لم يتم رفع ملف CSV بشكل صحيح.'
    ];
    header('Location: admin_import.php');
    exit;
}

set_time_limit(0);
ignore_user_abort(true);
ini_set('memory_limit', '512M');
$conn->set_charset('utf8mb4');

$file_name = $_FILES['csv_file']['name'];
$file_tmp = $_FILES['csv_file']['tmp_name'];
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

if ($file_ext !== 'csv') {
    $_SESSION['import_flash'] = [
        'type' => 'error',
        'message' => 'الملف المرفوع يجب أن يكون بصيغة CSV.'
    ];
    header('Location: admin_import.php');
    exit;
}

function normalizeImportHeader($header) {
    $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
    $header = mb_strtolower(trim($header), 'UTF-8');
    $header = preg_replace('/[\s\-_\.\/\\\(\)]+/u', '', $header);
    return $header;
}

function detectCsvDelimiter($filePath) {
    $handle = fopen($filePath, 'r');
    $line = '';
    if ($handle) {
        while (($candidateLine = fgets($handle)) !== false) {
            if (trim($candidateLine) !== '') {
                $line = $candidateLine;
                break;
            }
        }
        fclose($handle);
    }

    $candidates = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
    arsort($candidates);
    return array_key_first($candidates) ?: ',';
}

function rowHasData(array $row) {
    foreach ($row as $value) {
        if (trim((string) $value) !== '') {
            return true;
        }
    }
    return false;
}

function ensureImportAuditType(mysqli $conn) {
    $result = $conn->query("SHOW COLUMNS FROM Audit_Trail LIKE 'Action_Type'");
    if ($result && ($row = $result->fetch_assoc())) {
        if (strpos($row['Type'], 'IMPORT') === false) {
            @$conn->query("ALTER TABLE Audit_Trail MODIFY COLUMN Action_Type ENUM('CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'EXPORT', 'BACKUP', 'IMPORT') NOT NULL");
        }
    }
}

$delimiter = detectCsvDelimiter($file_tmp);
$handle = fopen($file_tmp, 'r');

if (!$handle) {
    $_SESSION['import_flash'] = [
        'type' => 'error',
        'message' => 'تعذر فتح ملف CSV للقراءة.'
    ];
    header('Location: admin_import.php');
    exit;
}

$headers = null;
while (($headerRow = fgetcsv($handle, 0, $delimiter)) !== false) {
    if (rowHasData($headerRow)) {
        $headers = $headerRow;
        break;
    }
}

if (!$headers) {
    fclose($handle);
    $_SESSION['import_flash'] = [
        'type' => 'error',
        'message' => 'ملف CSV فارغ أو لا يحتوي على رأس أعمدة.'
    ];
    header('Location: admin_import.php');
    exit;
}

$headerMap = [];
foreach ($headers as $index => $header) {
    $headerMap[normalizeImportHeader($header)] = $index;
}

$aliases = [
    'school_id' => ['schoolid', 'معرفالمدرسة', 'رقمالمدرسة', 'id'],
    'school_name' => ['schoolname', 'اسمالمدرسة', 'اسم'],
    'school_type' => ['schooltype', 'النوع', 'type'],
    'education_level' => ['educationlevel', 'المرحلةالتعليمية', 'level'],
    'office_id' => ['officeid', 'المكتبالتعليمي', 'المكتب', 'office'],
    'city' => ['city', 'المدينة'],
    'gender' => ['gender', 'الجنس'],
    'school_website' => ['schoolwebsite', 'website', 'url', 'الموقعالإلكتروني'],
    'ministerial_rating' => ['ministerialrating', 'التقييمالوزاري', 'rating'],
    'latitude' => ['latitude', 'خطالعرض'],
    'longitude' => ['longitude', 'خطالطول'],
];

$requiredFields = ['school_id', 'school_name', 'school_type', 'education_level', 'office_id'];
$fieldIndexes = [];

foreach ($aliases as $canonical => $choices) {
    $fieldIndexes[$canonical] = null;
    foreach (array_merge([$canonical], $choices) as $name) {
        $normalized = normalizeImportHeader($name);
        if (array_key_exists($normalized, $headerMap)) {
            $fieldIndexes[$canonical] = $headerMap[$normalized];
            break;
        }
    }
}

foreach ($requiredFields as $field) {
    if ($fieldIndexes[$field] === null) {
        fclose($handle);
        $_SESSION['import_flash'] = [
            'type' => 'error',
            'message' => 'ملف CSV يجب أن يحتوي على الأعمدة الأساسية: School_ID, School_Name, School_Type, Education_Level, Office_ID.'
        ];
        header('Location: admin_import.php');
        exit;
    }
}

$insertSql = "
    INSERT INTO Schools (
        School_ID,
        School_Name,
        School_Type,
        Education_Level,
        Office_ID,
        City,
        Gender,
        School_Website,
        Ministerial_Rating,
        Latitude,
        Longitude
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        School_Name = VALUES(School_Name),
        School_Type = VALUES(School_Type),
        Education_Level = VALUES(Education_Level),
        Office_ID = VALUES(Office_ID),
        City = VALUES(City),
        Gender = VALUES(Gender),
        School_Website = VALUES(School_Website),
        Ministerial_Rating = VALUES(Ministerial_Rating),
        Latitude = VALUES(Latitude),
        Longitude = VALUES(Longitude)
";

$stmt = $conn->prepare($insertSql);
if (!$stmt) {
    fclose($handle);
    $_SESSION['import_flash'] = [
        'type' => 'error',
        'message' => 'تعذر تجهيز استعلام التحديث: ' . $conn->error
    ];
    header('Location: admin_import.php');
    exit;
}

$conn->begin_transaction();

$processed = 0;
$inserted = 0;
$updated = 0;
$skipped = 0;
$failed = 0;
$lineNumber = 1;
$batchSize = 500;
$batchCounter = 0;
$errorSamples = [];

$school_id = 0;
$school_name = '';
$school_type = '';
$education_level = '';
$office_id = 0;
$city = '';
$gender = '';
$school_website = '';
$ministerial_rating = 0.0;
$latitude = 0.0;
$longitude = 0.0;

$stmt->bind_param(
    'isssisssddd',
    $school_id,
    $school_name,
    $school_type,
    $education_level,
    $office_id,
    $city,
    $gender,
    $school_website,
    $ministerial_rating,
    $latitude,
    $longitude
);

while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
    $lineNumber++;

    if (!rowHasData($row)) {
        $skipped++;
        continue;
    }

    $processed++;

    $school_id = intval(trim((string) ($row[$fieldIndexes['school_id']] ?? '0')));
    $school_name = sanitizeInput($row[$fieldIndexes['school_name']] ?? '');
    $school_type = sanitizeInput($row[$fieldIndexes['school_type']] ?? '');
    $education_level = sanitizeInput($row[$fieldIndexes['education_level']] ?? '');
    $office_id = intval(trim((string) ($row[$fieldIndexes['office_id']] ?? '0')));
    $city = sanitizeInput($row[$fieldIndexes['city']] ?? '');
    $gender = sanitizeInput($row[$fieldIndexes['gender']] ?? '');
    $school_website = sanitizeInput($row[$fieldIndexes['school_website']] ?? '');
    $ministerial_rating = is_numeric($row[$fieldIndexes['ministerial_rating']] ?? null) ? floatval($row[$fieldIndexes['ministerial_rating']]) : 0;
    $latitude = is_numeric($row[$fieldIndexes['latitude']] ?? null) ? floatval($row[$fieldIndexes['latitude']]) : 0;
    $longitude = is_numeric($row[$fieldIndexes['longitude']] ?? null) ? floatval($row[$fieldIndexes['longitude']]) : 0;

    if ($school_id <= 0 || $school_name === '' || $school_type === '' || $education_level === '' || $office_id <= 0) {
        $failed++;
        if (count($errorSamples) < 10) {
            $errorSamples[] = "السطر {$lineNumber}: حقول إلزامية ناقصة أو غير صحيحة.";
        }
        continue;
    }

    if (!$stmt->execute()) {
        $failed++;
        if (count($errorSamples) < 10) {
            $errorSamples[] = "السطر {$lineNumber}: " . $stmt->error;
        }
        continue;
    }

    $affectedRows = $stmt->affected_rows;
    if ($affectedRows === 1) {
        $inserted++;
    } elseif ($affectedRows === 2) {
        $updated++;
    } else {
        $skipped++;
    }
    $batchCounter++;

    if ($batchCounter >= $batchSize) {
        $conn->commit();
        $conn->begin_transaction();
        $batchCounter = 0;
    }
}

$conn->commit();
fclose($handle);

ensureImportAuditType($conn);

$auditDetails = sprintf(
    'Imported CSV file %s | processed=%d | inserted=%d | updated=%d | skipped=%d | failed=%d',
    $file_name,
    $processed,
    $inserted,
    $updated,
    $skipped,
    $failed
);
$auditPayload = json_encode([
    'file_name' => $file_name,
    'processed' => $processed,
    'inserted' => $inserted,
    'updated' => $updated,
    'skipped' => $skipped,
    'failed' => $failed,
    'delimiter' => $delimiter,
], JSON_UNESCAPED_UNICODE);

$auditStmt = $conn->prepare("INSERT INTO Audit_Trail (User_ID, Action_Type, Table_Name, Record_ID, Action_Details, IP_Address, User_Agent, New_Value) VALUES (?, 'IMPORT', 'Schools', NULL, ?, ?, ?, ?)");
if ($auditStmt) {
    $userId = intval($_SESSION['user_id']);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $auditStmt->bind_param('issss', $userId, $auditDetails, $ipAddress, $userAgent, $auditPayload);
    @$auditStmt->execute();
    $auditStmt->close();
}

logSecurityEvent('info', 'Bulk school import completed', [
    'user_id' => $_SESSION['user_id'],
    'file_name' => $file_name,
    'processed' => $processed,
    'inserted' => $inserted,
    'updated' => $updated,
    'skipped' => $skipped,
    'failed' => $failed,
]);

$summaryMessage = "تم استيراد الملف بنجاح. تمت معالجة {$processed} سجل، وإضافة {$inserted}، وتحديث {$updated}، وتجاوز {$skipped}، مع {$failed} صفاً غير صالح.";
if (!empty($errorSamples)) {
    $summaryMessage .= ' أول الأخطاء: ' . implode(' | ', $errorSamples);
}

$_SESSION['import_flash'] = [
    'type' => $failed > 0 ? 'error' : 'success',
    'message' => $summaryMessage
];

header('Location: admin_import.php');
exit;
