<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Ensure timezone for consistent "last update" timestamp
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Asia/Riyadh');
}

$last_update = date('Y-m-d H:i:s');
$footer_lang = function_exists('current_lang') ? current_lang() : 'ar';
// Try to get last DB update from Audit_Trail if available
try {
    if (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
        if (function_exists('get_audit_timestamp_column')) {
            $col = get_audit_timestamp_column();
            // Verify the column actually exists in the database to avoid SQL errors
            $checkSql = "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'Audit_Trail' AND COLUMN_NAME = ?";
            if ($checkStmt = $conn->prepare($checkSql)) {
                $dbName = defined('DB_NAME') ? DB_NAME : '';
                $checkStmt->bind_param('ss', $dbName, $col);
                if ($checkStmt->execute()) {
                    $cres = $checkStmt->get_result();
                    if ($crow = $cres->fetch_assoc()) {
                        if (!empty($crow['cnt'])) {
                            $sql = "SELECT MAX(`" . $col . "`) AS last_ts FROM Audit_Trail";
                            if ($stmt = $conn->prepare($sql)) {
                                if ($stmt->execute()) {
                                    $res = $stmt->get_result();
                                    if ($row = $res->fetch_assoc()) {
                                        if (!empty($row['last_ts'])) {
                                            $last_update = date('Y-m-d H:i:s', strtotime($row['last_ts']));
                                        }
                                    }
                                }
                                $stmt->close();
                            }
                        }
                    }
                }
                $checkStmt->close();
            }
        }
    }
} catch (Throwable $e) {
    // fallback to server time already set
}
?>
<footer style="text-align:center; padding:18px 12px; opacity:0.95; font-size:0.95rem; background:#ffffff; border-top:1px solid #e6eef6; margin-top:40px; color:#334155;">
    <div style="max-width:980px; margin:0 auto; direction:<?php echo $footer_lang === 'en' ? 'ltr' : 'rtl'; ?>;">
        <p style="margin:6px 0; font-weight:700;"><?php echo htmlspecialchars(t('site_footer_updated')); ?> <?php echo htmlspecialchars($last_update); ?></p>
        <p style="margin:6px 0;"><?php echo htmlspecialchars(t('site_footer_address')); ?></p>
        <p style="margin:6px 0;"><?php echo htmlspecialchars(t('site_footer_phone')); ?> <a href="mailto:info@saudi-schools.edu.sa">info@saudi-schools.edu.sa</a></p>
        <p style="margin:8px 0 0 0; color:#64748b; font-size:0.9rem;"><?php echo htmlspecialchars(t('site_footer_rights')); ?> <?php echo date('Y'); ?> | <?php echo htmlspecialchars(t('site_title')); ?></p>
    </div>
</footer>
<?php
