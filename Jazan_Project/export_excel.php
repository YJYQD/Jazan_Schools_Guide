<?php
/**
 * export_excel.php
 * تصدير بيانات المدارس إلى Excel/CSV
 */

// Use statements for PhpSpreadsheet must be at the top
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Ensure session is started safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";
require_once "security_helpers.php";

// التحقق من صلاحيات المسؤول
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// الفلاترات
$gov_id = intval($_GET['gov'] ?? 0);
$education_level = sanitizeInput($_GET['level'] ?? 'ALL');
$school_type = sanitizeInput($_GET['type'] ?? 'ALL');
$format = sanitizeInput($_GET['format'] ?? 'csv'); // csv أو xlsx

// بناء الاستعلام الديناميكي
$where_conditions = [];
$params = [];
$param_types = '';

if ($gov_id > 0) {
    $where_conditions[] = "o.Gov_ID = ?";
    $params[] = $gov_id;
    $param_types .= 'i';
}

if ($education_level !== 'ALL') {
    $where_conditions[] = "s.Education_Level = ?";
    $params[] = $education_level;
    $param_types .= 's';
}

if ($school_type !== 'ALL') {
    $where_conditions[] = "s.School_Type = ?";
    $params[] = $school_type;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// الحصول على البيانات
$query = "
    SELECT 
        s.School_ID,
        s.School_Name,
        s.School_Type,
        s.Education_Level,
        s.City,
        o.Office_Name,
        g.Gov_Name,
        s.School_Website,
        s.Ministerial_Rating,
        (SELECT COUNT(*) FROM School_Reviews WHERE School_ID = s.School_ID) AS Reviews_Count,
        (SELECT ROUND(AVG(Rating), 2) FROM School_Ratings WHERE School_ID = s.School_ID) AS Avg_Rating
    FROM Schools s
    LEFT JOIN Offices o ON s.Office_ID = o.Office_ID
    LEFT JOIN Governorates g ON o.Gov_ID = g.Gov_ID
    $where_clause
    ORDER BY g.Gov_Name, o.Office_Name, s.School_Name
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$schools = [];
while ($row = $result->fetch_assoc()) {
    $schools[] = $row;
}

// Localize DB fields when English is active
$page_lang = function_exists('current_lang') ? current_lang() : 'ar';
if ($page_lang === 'en' && count($schools) > 0) {
    foreach ($schools as &$r) {
        $sid = intval($r['School_ID'] ?? 0);
        $rawName = $r['School_Name'] ?? '';
        if (function_exists('get_localized_field')) {
            $r['School_Name'] = get_localized_field($conn, 'Schools', 'School_ID', $sid, 'School_Name', (function_exists('translate_db_text') ? translate_db_text($rawName, $page_lang) : $rawName));
        } else if (function_exists('translate_db_text')) {
            $r['School_Name'] = translate_db_text($rawName, $page_lang);
        }
        if (isset($r['Office_Name'])) $r['Office_Name'] = function_exists('translate_db_text') ? translate_db_text($r['Office_Name'], $page_lang) : $r['Office_Name'];
        if (isset($r['Gov_Name'])) $r['Gov_Name'] = function_exists('translate_db_text') ? translate_db_text($r['Gov_Name'], $page_lang) : $r['Gov_Name'];
        if (isset($r['City'])) $r['City'] = function_exists('translate_db_text') ? translate_db_text($r['City'], $page_lang) : $r['City'];
        if (isset($r['Education_Level'])) $r['Education_Level'] = function_exists('translate_term') ? translate_term('education_level', $r['Education_Level']) : $r['Education_Level'];
        if (isset($r['School_Type'])) $r['School_Type'] = function_exists('translate_term') ? translate_term('school_type', $r['School_Type']) : $r['School_Type'];
    }
    unset($r);
}

// تسجيل عملية التصدير
logSecurityEvent('EXPORT', t('exported_to') . strtoupper($format), [
    'gov_id' => $gov_id,
    'education_level' => $education_level,
    'school_type' => $school_type,
    'schools_count' => count($schools),
    'format' => $format,
    'exported_by' => $_SESSION['user_id']
]);

// ============================================
// تصدير CSV
// ============================================
if ($format === 'csv') {
    $filename = 'schools_report_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // BOM لدعم الأحرف العربية
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // رؤوس الأعمدة
    if ($page_lang === 'en') {
        $headers = [
            'School ID',
            'School Name',
            'Type',
            'Education Level',
            'City',
            'Office',
            'Governorate',
            'Website',
            'Ministerial Rating',
            'Reviews Count',
            'Average Rating'
        ];
    } else {
        $headers = [
            'معرف المدرسة',
            'اسم المدرسة',
            'النوع',
            'المرحلة التعليمية',
            'المدينة',
            'المكتب التعليمي',
            'المحافظة',
            'الموقع الإلكتروني',
            'التقييم الوزاري',
            'عدد المراجعات',
            'متوسط التقييم'
        ];
    }
    
    fputcsv($output, $headers, ',', '"');
    
    // البيانات
    foreach ($schools as $school) {
        $row = [
            $school['School_ID'],
            $school['School_Name'],
            (function_exists('translate_term') && $page_lang === 'en') ? translate_term('school_type', $school['School_Type']) : $school['School_Type'],
            (function_exists('translate_term') && $page_lang === 'en') ? translate_term('education_level', $school['Education_Level']) : $school['Education_Level'],
            $school['City'] ?? '-',
            $school['Office_Name'] ?? '-',
            $school['Gov_Name'] ?? '-',
            $school['School_Website'] ?? '-',
            $school['Ministerial_Rating'] ?? '-',
            $school['Reviews_Count'] ?? 0,
            $school['Avg_Rating'] ?? '-'
        ];
        fputcsv($output, $row, ',', '"');
    }
    
    fclose($output);
    exit;
}

// ============================================
// تصدير XLSX (Excel)
// ============================================
else if ($format === 'xlsx') {
    // محاولة استخدام PhpSpreadsheet إن كانت متاحة
    if (file_exists('vendor/autoload.php')) {
        require 'vendor/autoload.php';
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // تنسيق الرؤوس
        $headers = [
            'معرف المدرسة',
            'اسم المدرسة',
            'النوع',
            'المرحلة التعليمية',
            'المدينة',
            'المكتب التعليمي',
            'المحافظة',
            'الموقع الإلكتروني',
            'التقييم الوزاري',
            'عدد المراجعات',
            'متوسط التقييم'
        ];
        
        foreach ($headers as $col => $header) {
            $sheet->setCellValue(chr(65 + $col) . '1', $header);
        }
        
        // تنسيق رؤوس الأعمدة
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFE6E6E6');
        
        // إضافة البيانات
        $row = 2;
        foreach ($schools as $school) {
            $sheet->setCellValue('A' . $row, $school['School_ID']);
            $sheet->setCellValue('B' . $row, $school['School_Name']);
            $sheet->setCellValue('C' . $row, $school['School_Type']);
            $sheet->setCellValue('D' . $row, $school['Education_Level']);
            $sheet->setCellValue('E' . $row, $school['City'] ?? '-');
            $sheet->setCellValue('F' . $row, $school['Office_Name'] ?? '-');
            $sheet->setCellValue('G' . $row, $school['Gov_Name'] ?? '-');
            $sheet->setCellValue('H' . $row, $school['School_Website'] ?? '-');
            $sheet->setCellValue('I' . $row, $school['Ministerial_Rating'] ?? '-');
            $sheet->setCellValue('J' . $row, $school['Reviews_Count'] ?? 0);
            $sheet->setCellValue('K' . $row, $school['Avg_Rating'] ?? '-');
            $row++;
        }
        
        // ضبط عرض الأعمدة
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(25);
        $sheet->getColumnDimension('I')->setWidth(15);
        $sheet->getColumnDimension('J')->setWidth(15);
        $sheet->getColumnDimension('K')->setWidth(15);
        
        // حفظ الملف
        $writer = new Xlsx($spreadsheet);
        $filename = 'schools_report_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $writer->save('php://output');
    } else {
        // استخدام CSV كبديل
        $_GET['format'] = 'csv';
        include __FILE__;
    }
    exit;
}
?>
