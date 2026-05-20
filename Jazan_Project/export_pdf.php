<?php
/**
 * export_pdf.php
 * تصدير بيانات المدارس إلى PDF
 * استخدام مكتبة TCPDF أو بديل HTML-to-PDF
 */

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
logSecurityEvent('EXPORT', "تم تصدير بيانات المدارس إلى PDF", [
    'gov_id' => $gov_id,
    'education_level' => $education_level,
    'school_type' => $school_type,
    'schools_count' => count($schools),
    'exported_by' => $_SESSION['user_id']
]);

// تحديد نوع المخرجات
$output_type = $_GET['output'] ?? 'pdf';

if ($output_type === 'html') {
    // عرض بصيغة HTML للطباعة
    $page_lang = function_exists('current_lang') ? current_lang() : 'ar';
    $page_dir = $page_lang === 'en' ? 'ltr' : 'rtl';
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo htmlspecialchars($page_lang); ?>" dir="<?php echo htmlspecialchars($page_dir); ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>تقرير المدارس</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Tajawal', Arial, sans-serif;
                direction: rtl;
                background: white;
            }
            
            .header {
                text-align: center;
                border-bottom: 3px solid #333;
                padding: 20px 0;
                margin-bottom: 30px;
            }
            
            .header h1 {
                font-size: 28px;
                margin-bottom: 10px;
                color: #1e88e5;
            }
            
            .header p {
                color: #666;
                font-size: 14px;
            }
            
            .report-info {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 20px;
                margin-bottom: 30px;
                padding: 15px;
                background: #f5f5f5;
                border-radius: 5px;
            }
            
            .info-item {
                text-align: center;
            }
            
            .info-item strong {
                display: block;
                color: #1e88e5;
                margin-bottom: 5px;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 30px;
            }
            
            thead {
                background: #1e88e5;
                color: white;
            }
            
            th {
                padding: 12px;
                text-align: right;
                font-weight: bold;
                border: 1px solid #ddd;
            }
            
            td {
                padding: 12px;
                border: 1px solid #ddd;
            }
            
            tbody tr:nth-child(odd) {
                background: #f9f9f9;
            }
            
            tbody tr:hover {
                background: #f0f0f0;
            }
            
            .footer {
                margin-top: 50px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
                text-align: center;
                color: #666;
                font-size: 12px;
            }
            
            .print-button {
                display: block;
                margin: 20px auto;
                padding: 12px 30px;
                background: #1e88e5;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                font-family: 'Tajawal', Arial, sans-serif;
            }
            
            .print-button:hover {
                background: #1565c0;
            }
            
            @media print {
                .print-button {
                    display: none;
                }
                
                body {
                    margin: 0;
                    padding: 0;
                }
            }
            
            .rating {
                color: #ff9800;
                font-weight: bold;
            }

            #theme-toggle {
                position: fixed;
                left: 12px;
                top: 12px;
                z-index: 4000;
                width: 44px;
                height: 44px;
                border-radius: 50%;
                border: 1px solid #cbd5e1;
                background: #ffffff;
                cursor: pointer;
                box-shadow: 0 6px 18px rgba(2, 6, 23, 0.35);
                font-size: 1.1rem;
            }

            html[data-theme='dark'] body,
            body.dark {
                background: #020617;
                color: #f8fafc;
            }

            html[data-theme='dark'] .header,
            html[data-theme='dark'] .report-info,
            html[data-theme='dark'] table,
            html[data-theme='dark'] td,
            html[data-theme='dark'] th,
            html[data-theme='dark'] .footer,
            body.dark .header,
            body.dark .report-info,
            body.dark table,
            body.dark td,
            body.dark th,
            body.dark .footer {
                background: #0f172a;
                color: #e5e7eb;
                border-color: rgba(255, 255, 255, 0.16);
            }

            html[data-theme='dark'] tbody tr:nth-child(odd),
            body.dark tbody tr:nth-child(odd) {
                background: #111827;
            }

            html[data-theme='dark'] tbody tr:hover,
            body.dark tbody tr:hover {
                background: #1f2937;
            }
        </style>
    </head>
    <body>
        <div style="padding:12px;">
            <a href="admin.php" class="back-button"><?php echo htmlspecialchars(t('back_to_admin')); ?></a>
        </div>
        <button id="theme-toggle" type="button" title="تبديل الوضع" aria-label="تبديل الوضع">🌙</button>

        <div class="header">
            <h1>📊 <?php echo htmlspecialchars(t('schools_report_title')); ?></h1>
            <p><?php echo htmlspecialchars(t('report_region')); ?></p>
        </div>
        
        <button class="print-button" onclick="window.print()"><?php echo htmlspecialchars('🖨️ ' . t('print_button')); ?></button>
        
        <div class="report-info">
            <div class="info-item">
                <strong>إجمالي المدارس</strong>
                <span><?php echo count($schools); ?></span>
            </div>
            <div class="info-item">
                <strong>تاريخ التقرير</strong>
                <span><?php echo date('Y-m-d H:i'); ?></span>
            </div>
            <div class="info-item">
                <strong>المصدر</strong>
                <span>نظام إدارة المدارس</span>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <?php if ($page_lang === 'en'): ?>
                        <th>School Name</th>
                        <th>Type</th>
                        <th>Education Level</th>
                        <th>Governorate</th>
                        <th>Office</th>
                        <th>City</th>
                        <th>Ministerial Rating</th>
                        <th>Average Rating</th>
                    <?php else: ?>
                        <th>اسم المدرسة</th>
                        <th>النوع</th>
                        <th>المرحلة التعليمية</th>
                        <th>المحافظة</th>
                        <th>المكتب التعليمي</th>
                        <th>المدينة</th>
                        <th>التقييم الوزاري</th>
                        <th>متوسط التقييم</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schools as $index => $school): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($school['School_Name']); ?></td>
                        <td><?php echo htmlspecialchars( (function_exists('translate_term') && $page_lang === 'en') ? translate_term('school_type', $school['School_Type']) : $school['School_Type']); ?></td>
                        <td><?php echo htmlspecialchars( (function_exists('translate_term') && $page_lang === 'en') ? translate_term('education_level', $school['Education_Level']) : $school['Education_Level']); ?></td>
                        <td><?php echo htmlspecialchars($school['Gov_Name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($school['Office_Name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($school['City'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($school['Ministerial_Rating'] ?? '-'); ?></td>
                        <td>
                            <?php if ($school['Avg_Rating']): ?>
                                <span class="rating">⭐ <?php echo htmlspecialchars($school['Avg_Rating']); ?>/5</span>
                            <?php else: ?>
                                <span>-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p><?php echo htmlspecialchars(t('report_generated_by')); ?></p>
            <p><?php echo date('Y-m-d H:i:s'); ?></p>
        </div>

        <script>
        (function(){
            const toggle = document.getElementById('theme-toggle');
            if (!toggle) return;

            const applyTheme = function(mode) {
                const isDark = mode === 'dark';
                document.body.classList.toggle('dark', isDark);
                document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
                toggle.textContent = isDark ? '☀️' : '🌙';
                toggle.setAttribute('aria-label', isDark ? 'تفعيل الوضع الفاتح' : 'تفعيل الوضع الداكن');
            };

            const storedTheme = localStorage.getItem('jazan_theme');
            if (storedTheme === 'dark' || storedTheme === 'light') {
                applyTheme(storedTheme);
            } else {
                applyTheme('light');
            }

            toggle.addEventListener('click', function() {
                const nextTheme = document.body.classList.contains('dark') ? 'light' : 'dark';
                applyTheme(nextTheme);
                localStorage.setItem('jazan_theme', nextTheme);
            });
        })();
        </script>
    </body>
    </html>
    <?php
} else if ($output_type === 'pdf') {
    // محاولة استخدام مكتبة TCPDF إن وجدت
    if (file_exists('includes/TCPDF/tcpdf.php')) {
        require_once('includes/TCPDF/tcpdf.php');
        
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->AddPage();
        
        // العنوان
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->Cell(0, 10, 'تقرير المدارس', 0, 1, 'C');
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(0, 10, 'منطقة المملكة التعليمية', 0, 1, 'C');
        $pdf->Ln(10);
        
        // جدول البيانات
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->SetFillColor(30, 136, 229);
        $pdf->SetTextColor(255);
        
        $pdf->Cell(10, 7, '#', 1, 0, 'C', true);
        $pdf->Cell(40, 7, 'المدرسة', 1, 0, 'R', true);
        $pdf->Cell(20, 7, 'النوع', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'المرحلة', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'المحافظة', 1, 0, 'R', true);
        $pdf->Cell(20, 7, 'التقييم', 1, 1, 'C', true);
        
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->SetTextColor(0);
        
        foreach ($schools as $index => $school) {
            $pdf->Cell(10, 6, $index + 1, 1, 0, 'C');
            $pdf->Cell(40, 6, substr($school['School_Name'], 0, 20), 1, 0, 'R');
            $pdf->Cell(20, 6, substr($school['School_Type'], 0, 10), 1, 0, 'C');
            $pdf->Cell(25, 6, substr($school['Education_Level'], 0, 10), 1, 0, 'C');
            $pdf->Cell(25, 6, substr($school['Gov_Name'] ?? '', 0, 15), 1, 0, 'R');
            $pdf->Cell(20, 6, $school['Ministerial_Rating'] ?? '-', 1, 1, 'C');
        }
        
        $pdf->Output('schools_report_' . date('Y-m-d') . '.pdf', 'D');
    } else {
        // إذا لم تكن TCPDF متاحة، عرض HTML مع تعليمات الطباعة
        header('Content-Type: text/html; charset=utf-8');
        $_GET['output'] = 'html';
        include __FILE__;
    }
}
?>
