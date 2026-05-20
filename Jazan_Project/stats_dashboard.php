<?php
/**
 * stats_dashboard.php
 * لوحة الإحصائيات المتقدمة مع رسوم بيانية
 * محسّنة ومتجاوبة - استخدام Chart.js
 */

// Ensure session is started safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "db.php";
require_once "security_helpers.php";
require_once "navbar.php";

// detect actual audit timestamp column
$audit_ts_col = get_audit_timestamp_column();

// التحقق من صلاحيات المسؤول
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// ============================================
// الإحصائيات الأساسية
// ============================================
$stats = [];

$executePreparedQuery = function (string $sql) use ($conn) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }

    $result = $stmt->get_result();
    $stmt->close();

    return $result;
};

// 1. عدد المدارس الإجمالي
$result = $executePreparedQuery("SELECT COUNT(*) as total FROM Schools");
$stats['total_schools'] = $result ? (int)($result->fetch_assoc()['total'] ?? 0) : 0;

// 2. توزيع المدارس حسب المرحلة التعليمية
$result = $executePreparedQuery("
    SELECT Education_Level, COUNT(*) as count
    FROM Schools
    GROUP BY Education_Level
    ORDER BY Education_Level
");
$stats['by_level'] = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stats['by_level'][$row['Education_Level']] = $row['count'];
    }
}

// 3. توزيع المدارس حسب النوع
$result = $executePreparedQuery("
    SELECT School_Type, COUNT(*) as count
    FROM Schools
    GROUP BY School_Type
");
$stats['by_type'] = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stats['by_type'][$row['School_Type']] = $row['count'];
    }
}

// 4. توزيع المدارس حسب المحافظة
$result = $executePreparedQuery("
    SELECT g.Gov_Name, COUNT(s.School_ID) as count
    FROM Schools s
    LEFT JOIN Offices o ON s.Office_ID = o.Office_ID
    LEFT JOIN Governorates g ON o.Gov_ID = g.Gov_ID
    GROUP BY g.Gov_ID, g.Gov_Name
    ORDER BY count DESC
");
$stats['by_gov'] = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stats['by_gov'][$row['Gov_Name'] ?? 'غير محدد'] = $row['count'];
    }
}

// 5. إحصائيات التقييمات
$result = $executePreparedQuery("
    SELECT 
        COUNT(*) as total_ratings,
        ROUND(AVG(Rating), 2) as avg_rating,
        COUNT(DISTINCT School_ID) as schools_with_ratings,
        COUNT(DISTINCT User_ID) as users_with_ratings
    FROM School_Ratings
");
$rating_stats = $result ? $result->fetch_assoc() : [];
$stats['ratings'] = $rating_stats;

// 6. توزيع التقييمات
$result = $executePreparedQuery("
    SELECT Rating, COUNT(*) as count
    FROM School_Ratings
    GROUP BY Rating
    ORDER BY Rating DESC
");
$stats['rating_distribution'] = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stats['rating_distribution'][$row['Rating']] = $row['count'];
    }
}

// 7. إحصائيات المستخدمين
$result = $executePreparedQuery("SELECT COUNT(*) as total FROM Users WHERE Role = 'user'");
$stats['total_users'] = $result ? (int)($result->fetch_assoc()['total'] ?? 0) : 0;

$result = $executePreparedQuery("SELECT COUNT(*) as total FROM School_Reviews");
$stats['total_reviews'] = $result ? (int)($result->fetch_assoc()['total'] ?? 0) : 0;

// 8. أحدث الأنشطة
// 8. أحدث الأنشطة (تفقد عمود الطابع الزمني أولاً للحماية)
$stats['recent_activities'] = [];
if (!empty($audit_ts_col)) {
    // تحقق من وجود العمود في INFORMATION_SCHEMA
    $checkSql = "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'Audit_Trail' AND COLUMN_NAME = ?";
    if ($checkStmt = $conn->prepare($checkSql)) {
        $dbName = defined('DB_NAME') ? DB_NAME : '';
        $checkStmt->bind_param('ss', $dbName, $audit_ts_col);
        if ($checkStmt->execute()) {
            $cres = $checkStmt->get_result();
            if ($crow = $cres->fetch_assoc()) {
                if (!empty($crow['cnt'])) {
                    $query_recent = "SELECT Action_Type, COUNT(*) as count FROM Audit_Trail WHERE `" . $audit_ts_col . "` > DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY Action_Type ORDER BY count DESC";
                    $result = $executePreparedQuery($query_recent);
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $stats['recent_activities'][$row['Action_Type']] = $row['count'];
                        }
                    }
                }
            }
        }
        $checkStmt->close();
    }
}

// إعداد البيانات للرسوم البيانية (JSON)
$chart_data = [
    'by_level' => [
        'labels' => array_keys($stats['by_level']),
        'data' => array_values($stats['by_level'])
    ],
    'by_type' => [
        'labels' => array_keys($stats['by_type']),
        'data' => array_values($stats['by_type'])
    ],
    'by_gov' => [
        'labels' => array_keys($stats['by_gov']),
        'data' => array_values($stats['by_gov'])
    ],
    'ratings' => [
        'labels' => array_keys($stats['rating_distribution']),
        'data' => array_values($stats['rating_distribution'])
    ],
    'activities' => [
        'labels' => array_keys($stats['recent_activities']),
        'data' => array_values($stats['recent_activities'])
    ]
];
?>
<?php $page_lang = function_exists('current_lang') ? current_lang() : 'ar'; $page_dir = $page_lang === 'en' ? 'ltr' : 'rtl'; ?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($page_lang); ?>" dir="<?php echo htmlspecialchars($page_dir); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة الإحصائيات</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .stats-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .stat-card .value {
            font-size: 36px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-card .subtext {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .chart-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .chart-section h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 15px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-card .value {
                font-size: 28px;
            }
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Tajawal', sans-serif;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #764ba2;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .export-button {
            padding: 12px 25px;
            background: #4caf50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Tajawal', sans-serif;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .export-button:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <?php render_navbar('stats', true, 'admin.php', '⬅️ العودة للإدارة'); ?>
    <div class="stats-container">
        <h1>📊 لوحة الإحصائيات المتقدمة</h1>
        
        <!-- الإحصائيات الأساسية -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>🏫 إجمالي المدارس</h3>
                <div class="value"><?php echo $stats['total_schools']; ?></div>
                <div class="subtext">جميع المدارس المسجلة</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <h3>⭐ متوسط التقييم</h3>
                <div class="value"><?php echo $stats['ratings']['avg_rating'] ?? 0; ?></div>
                <div class="subtext"><?php echo $stats['ratings']['total_ratings'] ?? 0; ?> تقييم</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <h3>👥 المستخدمين</h3>
                <div class="value"><?php echo $stats['total_users']; ?></div>
                <div class="subtext">مستخدمون نشطون</div>
            </div>
            
            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <h3>💬 المراجعات</h3>
                <div class="value"><?php echo $stats['total_reviews']; ?></div>
                <div class="subtext">إجمالي المراجعات</div>
            </div>
        </div>
        
        <!-- أزرار التصدير -->
        <div class="actions">
            <a href="export_pdf.php?output=pdf" class="export-button">📄 تصدير PDF</a>
            <a href="export_excel.php?format=csv" class="export-button">📊 تصدير CSV</a>
            <a href="export_excel.php?format=xlsx" class="export-button">📊 تصدير Excel</a>
            <a href="audit_trail.php" class="export-button">📋 سجل العمليات</a>
            <a href="admin.php" class="export-button" style="background: #2196f3;">← العودة</a>
        </div>
        
        <!-- الرسوم البيانية -->
        <div class="charts-grid">
            <!-- المرحلة التعليمية -->
            <div class="chart-section">
                <h2>توزيع المدارس حسب المرحلة التعليمية</h2>
                <div class="chart-container">
                    <canvas id="levelChart"></canvas>
                </div>
            </div>
            
            <!-- نوع المدرسة -->
            <div class="chart-section">
                <h2>توزيع المدارس حسب النوع</h2>
                <div class="chart-container">
                    <canvas id="typeChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- رسم بياني كبير: المحافظات -->
        <div class="chart-section">
            <h2>توزيع المدارس حسب المحافظة</h2>
            <div class="chart-container" style="height: 400px;">
                <canvas id="govChart"></canvas>
            </div>
        </div>
        
        <!-- توزيع التقييمات -->
        <div class="charts-grid">
            <div class="chart-section">
                <h2>توزيع النجوم (التقييمات)</h2>
                <div class="chart-container">
                    <canvas id="ratingsChart"></canvas>
                </div>
            </div>
            
            <!-- الأنشطة -->
            <div class="chart-section">
                <h2>الأنشطة الأخيرة (30 يوم)</h2>
                <div class="chart-container">
                    <canvas id="activitiesChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // الألوان
        const colors = {
            primary: 'rgba(102, 126, 234, 1)',
            secondary: 'rgba(245, 87, 108, 1)',
            success: 'rgba(76, 175, 80, 1)',
            info: 'rgba(33, 150, 243, 1)',
            warning: 'rgba(255, 152, 0, 1)',
            danger: 'rgba(244, 67, 54, 1)'
        };
        
        // بيانات الرسوم البيانية
        const chartData = <?php echo json_encode($chart_data, JSON_UNESCAPED_UNICODE); ?>;
        
        // ============================================
        // 1. رسم بياني المرحلة التعليمية (Pie)
        // ============================================
        new Chart(document.getElementById('levelChart'), {
            type: 'doughnut',
            data: {
                labels: chartData.by_level.labels,
                datasets: [{
                    data: chartData.by_level.data,
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(245, 87, 108, 0.8)',
                        'rgba(76, 175, 80, 0.8)'
                    ],
                    borderColor: ['#fff', '#fff', '#fff'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { family: "'Tajawal', sans-serif", size: 14 },
                            padding: 15
                        }
                    }
                }
            }
        });
        
        // ============================================
        // 2. رسم بياني نوع المدرسة (Bar)
        // ============================================
        new Chart(document.getElementById('typeChart'), {
            type: 'bar',
            data: {
                labels: chartData.by_type.labels,
                datasets: [{
                    label: 'عدد المدارس',
                    data: chartData.by_type.data,
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(245, 87, 108, 0.8)'
                    ],
                    borderColor: [
                        'rgba(102, 126, 234, 1)',
                        'rgba(245, 87, 108, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { beginAtZero: true }
                }
            }
        });
        
        // ============================================
        // 3. رسم بياني المحافظات (Horizontal Bar)
        // ============================================
        new Chart(document.getElementById('govChart'), {
            type: 'bar',
            data: {
                labels: chartData.by_gov.labels,
                datasets: [{
                    label: 'عدد المدارس',
                    data: chartData.by_gov.data,
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { beginAtZero: true }
                }
            }
        });
        
        // ============================================
        // 4. رسم بياني التقييمات (Bar)
        // ============================================
        new Chart(document.getElementById('ratingsChart'), {
            type: 'bar',
            data: {
                labels: chartData.ratings.labels.map(l => '⭐ ' + l + ' نجوم'),
                datasets: [{
                    label: 'عدد التقييمات',
                    data: chartData.ratings.data,
                    backgroundColor: 'rgba(255, 152, 0, 0.8)',
                    borderColor: 'rgba(255, 152, 0, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
        
        // ============================================
        // 5. رسم بياني الأنشطة (Pie)
        // ============================================
        new Chart(document.getElementById('activitiesChart'), {
            type: 'doughnut',
            data: {
                labels: chartData.activities.labels,
                datasets: [{
                    data: chartData.activities.data,
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(245, 87, 108, 0.8)',
                        'rgba(76, 175, 80, 0.8)',
                        'rgba(33, 150, 243, 0.8)',
                        'rgba(255, 152, 0, 0.8)',
                        'rgba(244, 67, 54, 0.8)'
                    ],
                    borderColor: ['#fff', '#fff', '#fff', '#fff', '#fff', '#fff'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { family: "'Tajawal', sans-serif", size: 12 },
                            padding: 10
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
