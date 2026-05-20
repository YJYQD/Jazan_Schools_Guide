<?php
/**
 * audit_trail.php
 * لوحة تحكم سجل العمليات
 * تعرض جميع العمليات: إضافة، تعديل، حذف المدارس والمستخدمين
 */

// Ensure session is started safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "db.php";
require_once "security_helpers.php";

// detect actual audit timestamp column
$audit_ts_col = get_audit_timestamp_column();
// Verify the column actually exists; if not, fallback to a safe ordering column
$time_col_ref = "";
$colExists = false;
$dbName = defined('DB_NAME') ? DB_NAME : '';
if ($dbName !== '' && $audit_ts_col) {
    $chkSql = "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'Audit_Trail' AND COLUMN_NAME = ?";
    if ($chkStmt = $conn->prepare($chkSql)) {
        $chkStmt->bind_param('ss', $dbName, $audit_ts_col);
        if ($chkStmt->execute()) {
            $cres = $chkStmt->get_result();
            if ($crow = $cres->fetch_assoc()) {
                if (!empty($crow['cnt'])) {
                    $colExists = true;
                    $time_col_ref = "at.`" . $audit_ts_col . "`";
                }
            }
        }
        $chkStmt->close();
    }
}
if (!$colExists) {
    // fallback to a non-timestamp ordering column to keep queries safe
    $time_col_ref = 'at.Action_ID';
}

// التحقق من صلاحيات المسؤول
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// الفلاترات
$action_type = $_GET['action'] ?? 'ALL';
$table_name = $_GET['table'] ?? 'ALL';
$date_from = $_GET['from'] ?? '';
$date_to = $_GET['to'] ?? '';
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// بناء الاستعلام الديناميكي
$where_conditions = [];
$params = [];
$param_types = '';

// فلتر نوع العملية
if ($action_type !== 'ALL') {
    $where_conditions[] = "Action_Type = ?";
    $params[] = $action_type;
    $param_types .= 's';
}

// فلتر اسم الجدول
if ($table_name !== 'ALL') {
    $where_conditions[] = "Table_Name = ?";
    $params[] = $table_name;
    $param_types .= 's';
}

// فلتر التاريخ من
if ($date_from) {
    $where_conditions[] = $time_col_ref . " >= ?";
    $params[] = $date_from . " 00:00:00";
    $param_types .= 's';
}

// فلتر التاريخ إلى
if ($date_to) {
    $where_conditions[] = $time_col_ref . " <= ?";
    $params[] = $date_to . " 23:59:59";
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// الحصول على العدد الكلي
$count_query = "SELECT COUNT(*) as total FROM Audit_Trail $where_clause";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$count_result = $stmt->get_result()->fetch_assoc();
$total_records = $count_result['total'] ?? 0;
$total_pages = ceil($total_records / $per_page);

// الحصول على السجلات
$query = "
    SELECT 
        at.*,
        u.Username as User_Name
    FROM Audit_Trail at
    LEFT JOIN Users u ON at.User_ID = u.User_ID
    " . $where_clause . "
    ORDER BY " . $time_col_ref . " DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
$limit_type = $param_types . 'ii';
$params[] = $per_page;
$params[] = $offset;
$stmt->bind_param($limit_type, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$audit_records = [];
while ($row = $result->fetch_assoc()) {
    $audit_records[] = $row;
}

// حساب الإحصائيات
$stats_query = "
    SELECT 
        Action_Type,
        COUNT(*) as count
    FROM Audit_Trail
    GROUP BY Action_Type
";
$stmt = $conn->prepare($stats_query);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = [];
while ($row = $stats_result->fetch_assoc()) {
    $stats[$row['Action_Type']] = $row['count'];
}
?>
<?php $page_lang = function_exists('current_lang') ? current_lang() : 'ar'; $page_dir = $page_lang === 'en' ? 'ltr' : 'rtl'; ?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($page_lang); ?>" dir="<?php echo htmlspecialchars($page_dir); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سجل العمليات</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .audit-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        
        .stat-card p {
            margin: 5px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .filters {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Tajawal', sans-serif;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-family: 'Tajawal', sans-serif;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #1e88e5;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1565c0;
        }
        
        .btn-secondary {
            background: #f44336;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #da190b;
        }
        
        .audit-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .audit-table thead {
            background: #f5f5f5;
            border-bottom: 2px solid #ddd;
        }
        
        .audit-table th {
            padding: 15px;
            text-align: right;
            font-weight: bold;
            color: #333;
        }
        
        .audit-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .audit-table tbody tr:hover {
            background: #f9f9f9;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }
        
        .badge-create {
            background: #4caf50;
            color: white;
        }
        
        .badge-update {
            background: #2196f3;
            color: white;
        }
        
        .badge-delete {
            background: #f44336;
            color: white;
        }
        
        .badge-login {
            background: #9c27b0;
            color: white;
        }
        
        .badge-export {
            background: #ff9800;
            color: white;
        }
        
        .badge-backup {
            background: #00bcd4;
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 30px;
        }
        
        .pagination a,
        .pagination span {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #1e88e5;
            cursor: pointer;
        }
        
        .pagination a:hover {
            background: #1e88e5;
            color: white;
        }
        
        .pagination .active {
            background: #1e88e5;
            color: white;
            border-color: #1e88e5;
        }
        
        .details-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .details-modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            left: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .modal-close:hover {
            color: #333;
        }
        
        .json-block {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin: 10px 0;
        }
        
        @media (max-width: 768px) {
            .filters {
                grid-template-columns: 1fr;
            }
            
            .audit-table {
                font-size: 12px;
            }
            
            .audit-table th,
            .audit-table td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div style="padding:12px;">
        <a href="admin.php" class="back-button">⬅️ العودة للإدارة</a>
    </div>
    <button id="theme-toggle" class="btn-icon" type="button" title="تبديل الوضع" aria-label="تبديل الوضع" style="font-size:1.05rem;">🌙</button>

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
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            applyTheme(prefersDark ? 'dark' : 'light');
        }

        toggle.addEventListener('click', function() {
            const nextTheme = document.body.classList.contains('dark') ? 'light' : 'dark';
            applyTheme(nextTheme);
            localStorage.setItem('jazan_theme', nextTheme);
        });
    })();
    </script>

    <div class="audit-container">
        <h1>📊 سجل العمليات (Audit Trail)</h1>
        
        <!-- الإحصائيات -->
        <div class="stats-cards">
            <div class="stat-card">
                <h3><?php echo $stats['CREATE'] ?? 0; ?></h3>
                <p>عمليات إنشاء</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['UPDATE'] ?? 0; ?></h3>
                <p>عمليات تعديل</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['DELETE'] ?? 0; ?></h3>
                <p>عمليات حذف</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_records; ?></h3>
                <p>إجمالي العمليات</p>
            </div>
        </div>
        
        <!-- الفلاترات -->
        <form method="GET" class="filters">
            <div class="filter-group">
                <label>نوع العملية</label>
                <select name="action">
                    <option value="ALL">جميع العمليات</option>
                    <option value="CREATE" <?php echo ($action_type === 'CREATE') ? 'selected' : ''; ?>>إنشاء</option>
                    <option value="UPDATE" <?php echo ($action_type === 'UPDATE') ? 'selected' : ''; ?>>تعديل</option>
                    <option value="DELETE" <?php echo ($action_type === 'DELETE') ? 'selected' : ''; ?>>حذف</option>
                    <option value="LOGIN" <?php echo ($action_type === 'LOGIN') ? 'selected' : ''; ?>>تسجيل دخول</option>
                    <option value="EXPORT" <?php echo ($action_type === 'EXPORT') ? 'selected' : ''; ?>>تصدير</option>
                    <option value="BACKUP" <?php echo ($action_type === 'BACKUP') ? 'selected' : ''; ?>>نسخ احتياطي</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>الجدول</label>
                <select name="table">
                    <option value="ALL">جميع الجداول</option>
                    <option value="Schools" <?php echo ($table_name === 'Schools') ? 'selected' : ''; ?>>المدارس</option>
                    <option value="Users" <?php echo ($table_name === 'Users') ? 'selected' : ''; ?>>المستخدمون</option>
                    <option value="Reviews" <?php echo ($table_name === 'Reviews') ? 'selected' : ''; ?>>التقييمات</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>من التاريخ</label>
                <input type="date" name="from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            
            <div class="filter-group">
                <label>إلى التاريخ</label>
                <input type="date" name="to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            
            <div class="filter-buttons">
                <button type="submit" class="btn btn-primary">🔍 تطبيق الفلاترات</button>
                <a href="audit_trail.php" class="btn btn-secondary">❌ إعادة تعيين</a>
            </div>
        </form>
        
        <!-- جدول السجلات -->
        <table class="audit-table">
            <thead>
                <tr>
                    <th>التاريخ والوقت</th>
                    <th>المستخدم</th>
                    <th>نوع العملية</th>
                    <th>الجدول</th>
                    <th>الوصف</th>
                    <th>العنوان IP</th>
                    <th>التفاصيل</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($audit_records as $record): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record[$audit_ts_col]); ?></td>
                        <td><?php echo htmlspecialchars($record['User_Name'] ?? 'نظام'); ?></td>
                        <td>
                            <span class="badge badge-<?php echo strtolower($record['Action_Type']); ?>">
                                <?php echo htmlspecialchars($record['Action_Type']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($record['Table_Name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($record['Action_Details'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($record['IP_Address'] ?? '-'); ?></td>
                        <td>
                            <button class="btn btn-primary" onclick="showDetails(<?php echo htmlspecialchars(json_encode($record)); ?>)">
                                عرض
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- التصفح -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1&action=<?php echo $action_type; ?>&table=<?php echo $table_name; ?>">« الأولى</a>
                    <a href="?page=<?php echo $page - 1; ?>&action=<?php echo $action_type; ?>&table=<?php echo $table_name; ?>">« السابقة</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&action=<?php echo $action_type; ?>&table=<?php echo $table_name; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&action=<?php echo $action_type; ?>&table=<?php echo $table_name; ?>">التالية »</a>
                    <a href="?page=<?php echo $total_pages; ?>&action=<?php echo $action_type; ?>&table=<?php echo $table_name; ?>">الأخيرة »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- نافذة التفاصيل -->
    <div id="detailsModal" class="details-modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeDetails()">✕</span>
            <h2>تفاصيل العملية</h2>
            <div id="modalBody"></div>
        </div>
    </div>
    
    <script>
        function showDetails(record) {
            let html = `
                <p><strong>المستخدم:</strong> ${record.User_Name || 'نظام'}</p>
                <p><strong>نوع العملية:</strong> ${record.Action_Type}</p>
                <p><strong>الجدول:</strong> ${record.Table_Name || '-'}</p>
                <p><strong>معرف السجل:</strong> ${record.Record_ID || '-'}</p>
                <p><strong>التاريخ والوقت:</strong> ${record['<?php echo $audit_ts_col; ?>']}</p>
                <p><strong>عنوان IP:</strong> ${record.IP_Address || '-'}</p>
                <p><strong>الوصف:</strong></p>
                <p>${record.Action_Details || '-'}</p>
            `;
            
            if (record.Old_Value) {
                html += `<p><strong>القيمة القديمة:</strong></p><div class="json-block">${JSON.stringify(JSON.parse(record.Old_Value), null, 2)}</div>`;
            }
            
            if (record.New_Value) {
                html += `<p><strong>القيمة الجديدة:</strong></p><div class="json-block">${JSON.stringify(JSON.parse(record.New_Value), null, 2)}</div>`;
            }
            
            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('detailsModal').classList.add('active');
        }
        
        function closeDetails() {
            document.getElementById('detailsModal').classList.remove('active');
        }
    </script>
</body>
</html>
