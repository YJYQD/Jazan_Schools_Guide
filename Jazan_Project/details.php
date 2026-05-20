<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
require_once 'security_helpers.php';
require_once 'i18n.php';
// ensure CSRF token exists (generate after includes so helpers are available)
if (empty($_SESSION['csrf_token'])) generate_csrf_token();

$details_lang = function_exists('current_lang') ? current_lang() : 'ar';

$school_id = intval($_GET['school_id'] ?? 0);
$school_map = null;
if ($school_id > 0) {
    $stmt = $conn->prepare("SELECT School_Name, Latitude, Longitude FROM Schools WHERE School_ID = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $school_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $lat = trim((string)($row['Latitude'] ?? ''));
            $lng = trim((string)($row['Longitude'] ?? ''));
            if ($lat !== '' && $lng !== '') {
                // Use localized name when available (fallback to translate_db_text)
                require_once 'i18n.php';
                if (function_exists('get_localized_field')) {
                    $localized = get_localized_field($conn, 'Schools', 'School_ID', $school_id, 'School_Name', $row['School_Name']);
                } else if (function_exists('translate_db_text')) {
                    $localized = (function_exists('current_lang') ? translate_db_text($row['School_Name'], current_lang()) : $row['School_Name']);
                } else {
                    $localized = $row['School_Name'];
                }
                $school_map = ['name' => $localized ?? $row['School_Name'], 'lat' => $lat, 'lng' => $lng];
            }
        }
        $stmt->close();
    }
}
?>

<?php if ($school_map !== null): ?>
    <div class="school-map" style="margin:12px 0;">
        <iframe src="https://www.google.com/maps?q=<?php echo rawurlencode($school_map['lat'] . ',' . $school_map['lng']); ?>&z=16&output=embed" style="width:100%;height:360px;border:0;" loading="lazy" allowfullscreen title="<?php echo htmlspecialchars(($details_lang === 'en' ? 'Location of ' : 'موقع ') . $school_map['name']); ?>"></iframe>
    </div>
<?php endif; ?>

<?php if(isset($_SESSION['user_id'])): ?>
    <div class="comment-box">
        <h4><?php echo htmlspecialchars(t('add_feedback')); ?></h4>

        <form action="add_review.php" method="POST">
            <textarea name="comment_text" required placeholder="<?php echo htmlspecialchars(t('write_comment_placeholder')); ?>"></textarea>

            <input type="hidden" name="school_id" value="<?php echo intval($school_id); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">

            <button type="submit"><?php echo htmlspecialchars(t('submit_comment')); ?></button>
        </form>
    </div>

<?php else: ?>
    <div class="alert alert-warning">
        <?php echo htmlspecialchars(t('login_to_comment')); ?>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>
