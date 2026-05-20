<?php
/**
 * backup_system.php
 * نظام النسخ الاحتياطية الآلي والدليلي لقاعدة البيانات
 */

// Ensure session is started safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "db.php";
require_once "security_helpers.php";
require_once "i18n.php";

// التحقق من صلاحيات المسؤول
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// إعدادات النسخ الاحتياطية
$backup_dir = 'backups/';
$max_backups = 10;
$page_lang = function_exists('current_lang') ? current_lang() : 'ar';

// إنشاء مجلد النسخ الاحتياطية
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// ============================================
// معالجة طلبات AJAX
// ============================================
$action = $_GET['action'] ?? '';

if ($action === 'create_backup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // إنشاء اسم ملف النسخة الاحتياطية
    $backup_file = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';

    // استخدام طريقة PHP (آمنة) دائماً بدلاً من وضع كلمات المرور في سطر الأوامر
    createManualBackup($conn, $backup_file);

    if (file_exists($backup_file)) {
        $file_size = filesize($backup_file);

        // تسجيل النسخة
        $user_id = $_SESSION['user_id'];
        $size_str = formatBytes($file_size);
        $notes = t('backup_notes_manual');
        $status = "SUCCESS";

        $stmt = $conn->prepare("INSERT INTO Backup_History (Backup_File, Backup_Size, Status, Created_By, Notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssis", basename($backup_file), $size_str, $status, $user_id, $notes);
        $stmt->execute();

        logSecurityEvent('BACKUP', "تم إنشاء النسخة الاحتياطية (طريقة PHP)", [
            'backup_file' => basename($backup_file),
            'file_size' => $size_str
        ]);

        cleanOldBackups($backup_dir, $max_backups);

        echo json_encode([
            'success' => true,
            'message' => t('backup_created_success'),
            'file_name' => basename($backup_file),
            'file_size' => $size_str
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => t('backup_failed')]);
    }
    exit;
}

// ============================================
// تحميل النسخة الاحتياطية
// ============================================
if ($action === 'download_backup') {
    $file_name = basename($_GET['file'] ?? '');
    $file_path = $backup_dir . $file_name;
    
    if (!file_exists($file_path) || strpos($file_name, '..') !== false) {
        die(t('file_not_found'));
    }
    
    // تسجيل التحميل
    logSecurityEvent('EXPORT', "تم تحميل نسخة احتياطية", [
        'backup_file' => $file_name
    ]);
    
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . filesize($file_path));
    
    readfile($file_path);
    exit;
}

// ============================================
// حذف النسخة الاحتياطية
// ============================================
if ($action === 'delete_backup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => t('security_verification_failed')]);
        exit;
    }
    
    $file_name = basename($_POST['file'] ?? '');
    $file_path = $backup_dir . $file_name;
    
    if (!file_exists($file_path) || strpos($file_name, '..') !== false) {
        echo json_encode(['success' => false, 'message' => t('file_not_found')]);
        exit;
    }
    
    if (unlink($file_path)) {
        // تحديث قاعدة البيانات
        $stmt = $conn->prepare("UPDATE Backup_History SET Status = 'DELETED' WHERE Backup_File = ?");
        $stmt->bind_param("s", $file_name);
        $stmt->execute();
        
        logSecurityEvent('DELETE', "تم حذف نسخة احتياطية", [
            'backup_file' => $file_name
        ]);
        
        echo json_encode(['success' => true, 'message' => t('backup_deleted')]);
    } else {
        echo json_encode(['success' => false, 'message' => t('failed_delete_file')]);
    }
    exit;
}

// ============================================
// عرض النسخ الاحتياطية
// ============================================
$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $file_path = $backup_dir . $file;
            $backups[] = [
                'name' => $file,
                'size' => filesize($file_path),
                'date' => date('Y-m-d H:i:s', filemtime($file_path)),
                'size_formatted' => formatBytes(filesize($file_path))
            ];
        }
    }
}

// الحصول على سجل النسخ الاحتياطية من قاعدة البيانات
$stmt = $conn->prepare("
    SELECT * FROM Backup_History
    ORDER BY Backup_Date DESC
    LIMIT 20
");
$stmt->execute();
$result = $stmt->get_result();
$backup_history = [];
while ($row = $result->fetch_assoc()) {
    $backup_history[] = $row;
}

generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($page_lang); ?>" dir="<?php echo $page_lang === 'en' ? 'ltr' : 'rtl'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('backup_system_title')); ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .backup-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .backup-controls {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-family: 'Tajawal', sans-serif;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #4caf50;
            color: white;
        }
        
        .btn-primary:hover {
            background: #45a049;
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-danger:hover {
            background: #da190b;
        }
        
        .btn-info {
            background: #2196f3;
            color: white;
        }
        
        .backup-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .backup-table thead {
            background: #f5f5f5;
            border-bottom: 2px solid #ddd;
        }
        
        .backup-table th {
            padding: 15px;
            text-align: right;
            font-weight: bold;
            color: #333;
        }
        
        .backup-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .backup-table tbody tr:hover {
            background: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-success {
            background: #4caf50;
            color: white;
        }
        
        .status-failed {
            background: #f44336;
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0;
            font-size: 28px;
        }
        
        .stat-card p {
            margin: 5px 0 0 0;
        }
        
        .progress-bar {
            width: 100%;
            height: 25px;
            background: #eee;
            border-radius: 5px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4caf50, #45a049);
            width: 0;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .backup-controls {
                flex-direction: column;
            }
            
            .backup-table {
                font-size: 12px;
            }
            
            .backup-table th,
            .backup-table td {
                padding: 10px;
            }
        }

        html[data-theme='dark'] body,
        body.dark {
            background: #020617;
            background-image: radial-gradient(circle at 50% 50%, rgba(14, 165, 233, 0.15) 0%, transparent 80%);
            color: #f8fafc;
        }

        html[data-theme='dark'] .backup-container,
        body.dark .backup-container {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #f8fafc;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
        }

        html[data-theme='dark'] .backup-table,
        html[data-theme='dark'] .backup-table th,
        html[data-theme='dark'] .backup-table td,
        body.dark .backup-table,
        body.dark .backup-table th,
        body.dark .backup-table td {
            background: rgba(15, 23, 42, 0.9);
            color: #e5e7eb;
            border-color: rgba(255, 255, 255, 0.12);
        }
    </style>
</head>
<body>
    <button id="theme-toggle" class="btn-icon" type="button" title="<?php echo htmlspecialchars(t('theme_toggle')); ?>" aria-label="<?php echo htmlspecialchars(t('theme_toggle')); ?>" style="font-size:1.05rem;">🌙</button>

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

    <div class="backup-container">
        <h1>💾 <?php echo htmlspecialchars(t('backup_system_title')); ?></h1>
        
        <!-- الإحصائيات -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo count($backups); ?></h3>
                <p><?php echo htmlspecialchars(t('available_backups')); ?></p>
            </div>
            <div class="stat-card">
                <h3><?php echo formatBytes(array_sum(array_map(function($b) { return $b['size']; }, $backups))); ?></h3>
                <p><?php echo htmlspecialchars(t('total_backup_size')); ?></p>
            </div>
            <div class="stat-card">
                <h3><?php echo count($backup_history); ?></h3>
                <p><?php echo htmlspecialchars(t('logged_operations')); ?></p>
            </div>
        </div>
        
        <!-- أزرار التحكم -->
        <div class="backup-controls">
            <button class="btn btn-primary" onclick="createBackup()">
                ➕ <?php echo htmlspecialchars(t('create_backup_now')); ?>
            </button>
            <a href="admin.php" class="btn btn-info"><?php echo htmlspecialchars(t('back_to_dashboard')); ?></a>
        </div>
        
            <div class="loading" id="loading">
            <div class="spinner"></div>
            <p><?php echo htmlspecialchars(t('creating_backup_message')); ?></p>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill" style="width: 0%">0%</div>
            </div>
        </div>
        
        <!-- جدول النسخ الاحتياطية -->
        <h2 style="margin-top: 40px;">📋 <?php echo htmlspecialchars(t('available_backups')); ?></h2>
        
        <?php if (!empty($backups)): ?>
            <table class="backup-table">
                <thead>
                    <tr>
                        <th><?php echo htmlspecialchars(t('file_name')); ?></th>
                        <th><?php echo htmlspecialchars(t('date_time')); ?></th>
                        <th><?php echo htmlspecialchars(t('size_label')); ?></th>
                        <th><?php echo htmlspecialchars(t('actions')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($backup['name']); ?></td>
                            <td><?php echo htmlspecialchars($backup['date']); ?></td>
                            <td><?php echo htmlspecialchars($backup['size_formatted']); ?></td>
                            <td>
                                <a href="?action=download_backup&file=<?php echo urlencode($backup['name']); ?>" 
                                   class="btn btn-info" style="padding: 8px 15px; display: inline-block;">
                                   ⬇️ <?php echo htmlspecialchars(t('download')); ?>
                                </a>
                                <button class="btn btn-danger" 
                                        onclick="deleteBackup('<?php echo htmlspecialchars($backup['name']); ?>')"
                                        style="padding: 8px 15px;">
                                   🗑️ <?php echo htmlspecialchars(t('delete')); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; color: #999; padding: 20px;"><?php echo htmlspecialchars(t('no_backups_available')); ?></p>
        <?php endif; ?>
        
        <!-- سجل العمليات -->
        <h2 style="margin-top: 40px;">📝 <?php echo htmlspecialchars(t('backup_activity_log')); ?></h2>
        
        <table class="backup-table">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars(t('backup_file')); ?></th>
                    <th><?php echo htmlspecialchars(t('date')); ?></th>
                    <th><?php echo htmlspecialchars(t('size_label')); ?></th>
                    <th><?php echo htmlspecialchars(t('status')); ?></th>
                    <th><?php echo htmlspecialchars(t('owner')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($backup_history as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['Backup_File'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($item['Backup_Date']); ?></td>
                        <td><?php echo htmlspecialchars($item['Backup_Size'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($item['Status']); ?>">
                                <?php echo htmlspecialchars($item['Status']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($item['Created_By'] ?? 'N/A'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <script>
        function createBackup() {
            if (!confirm(<?php echo json_encode(t('create_backup_confirm')); ?>)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            
            const loading = document.getElementById('loading');
            loading.style.display = 'block';
            
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += Math.random() * 20;
                if (progress > 90) progress = 90;
                document.getElementById('progressFill').style.width = progress + '%';
                document.getElementById('progressFill').textContent = Math.floor(progress) + '%';
            }, 500);
            
            fetch('?action=create_backup', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                clearInterval(progressInterval);
                document.getElementById('progressFill').style.width = '100%';
                document.getElementById('progressFill').textContent = '100%';
                
                setTimeout(() => {
                    loading.style.display = 'none';
                    if (data.success) {
                        alert(<?php echo json_encode(t('backup_created_success') . "\n"); ?> + data.message);
                        location.reload();
                    } else {
                        alert(<?php echo json_encode(t('error_prefix')); ?> + data.message);
                    }
                }, 1000);
            })
            .catch(error => {
                clearInterval(progressInterval);
                loading.style.display = 'none';
                alert(<?php echo json_encode(t('error_prefix')); ?> + error.message);
            });
        }
        
        function deleteBackup(filename) {
            if (!confirm(<?php echo json_encode(t('delete_backup_confirm')); ?>)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
            formData.append('file', filename);
            
            fetch('?action=delete_backup', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(<?php echo json_encode(t('error_prefix')); ?> + data.message);
                }
            })
            .catch(error => alert('خطأ: ' + error.message));
        }
    </script>
</body>
</html>

<?php
// ============================================
// دوال مساعدة
// ============================================

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function cleanOldBackups($backup_dir, $max_backups) {
    $files = array_filter(scandir($backup_dir, SCANDIR_SORT_DESCENDING), 
        function($f) { return pathinfo($f, PATHINFO_EXTENSION) === 'sql'; }
    );
    
    while (count($files) > $max_backups) {
        $old_file = array_pop($files);
        @unlink($backup_dir . $old_file);
    }
}

function createManualBackup($conn, $backup_file) {
    $output = "-- Jazan Schools Database Backup\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- Encoding: utf8mb4\n\n";
    $output .= "SET NAMES utf8mb4;\n";
    $output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n\n";
    
    // الحصول على قائمة الجداول
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    // حفظ هيكل وبيانات كل جدول
    foreach ($tables as $table) {
        $output .= "-- Table structure for table `$table`\n";
        $create_result = $conn->query("SHOW CREATE TABLE $table");
        $create_row = $create_result->fetch_row();
        $output .= $create_row[1] . ";\n\n";
        
        // البيانات
        $output .= "-- Data for table `$table`\n";
        $data_result = $conn->query("SELECT * FROM $table");
        
        while ($data_row = $data_result->fetch_assoc()) {
            $values = array_map(function($v) use ($conn) {
                return $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
            }, $data_row);
            
            $output .= "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
        }
        $output .= "\n";
    }
    
    file_put_contents($backup_file, $output);
}
?>
