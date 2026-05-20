<?php
/**
 * Reset password page - verifies token and allows setting new password
 */
session_start();
require_once "db.php";
require_once "security_helpers.php";

$conn->set_charset("utf8mb4");

initiateCsrfToken();

$message = '';
$show_form = false;
$token = isset($_GET['token']) ? $_GET['token'] : (isset($_POST['token']) ? $_POST['token'] : '');

if (!$token) {
    $message = '❌ رمز غير صحيح';
} else {
    // validate token
    $stmt = $conn->prepare("SELECT pr.id, pr.user_id, pr.expires_at, u.Username FROM Password_Resets pr JOIN Users u ON pr.user_id = u.User_ID WHERE pr.token = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            if (strtotime($row['expires_at']) < time()) {
                $message = '❌ رمز إعادة التعيين منتهي الصلاحية.';
            } else {
                $show_form = true;
                $user_id = $row['user_id'];
                $username = $row['Username'];
            }
        } else {
            $message = '❌ رمز غير صالح';
        }
        $stmt->close();
    } else {
        $message = '❌ خطأ في قاعدة البيانات';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form) {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $message = '❌ خطأ أمني: رمز جلسة غير صحيح';
    } else {
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
        if (empty($password) || strlen($password) < 8) {
            $message = '❌ كلمة المرور يجب أن تكون 8 أحرف على الأقل';
        } elseif ($password !== $password_confirm) {
            $message = '❌ كلمات المرور غير متطابقة';
        } else {
            $hashed = hashPassword($password);
            $upd = $conn->prepare("UPDATE Users SET Password = ? WHERE User_ID = ?");
            if ($upd) {
                $upd->bind_param('si', $hashed, $user_id);
                if ($upd->execute()) {
                    // delete all tokens for user
                    $del = $conn->prepare("DELETE FROM Password_Resets WHERE user_id = ?");
                    if ($del) { $del->bind_param('i', $user_id); $del->execute(); $del->close(); }
                    $message = '✅ تم تحديث كلمة المرور. يمكنك الآن تسجيل الدخول.';
                    $show_form = false;
                } else {
                    $message = '❌ فشل تحديث كلمة المرور';
                }
                $upd->close();
            } else {
                $message = '❌ خطأ في قاعدة البيانات أثناء التحديث';
            }
        }
    }
}
?>
<?php $page_lang = function_exists('current_lang') ? current_lang() : 'ar'; $page_dir = $page_lang === 'en' ? 'ltr' : 'rtl'; ?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($page_lang); ?>" dir="<?php echo htmlspecialchars($page_dir); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>إعادة تعيين كلمة المرور</title>
    <link rel="stylesheet" href="styles.css">
    <style>body{font-family: Tajawal, sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0} .card{padding:28px;border-radius:16px;background:var(--glass);backdrop-filter:blur(10px);max-width:420px;width:100%}</style>
    </head>
<body>
<div class="card">
    <h2>إعادة تعيين كلمة المرور</h2>
    <?php if (!empty($message)): ?>
        <div style="margin:10px 0; padding:10px; border-radius:8px; background:rgba(0,0,0,0.05);"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($show_form): ?>
        <p>المستخدم: <strong><?php echo htmlspecialchars($username); ?></strong></p>
        <form method="POST">
            <input type="password" name="password" placeholder="كلمة المرور الجديدة" required>
            <input type="password" name="password_confirm" placeholder="تأكيد كلمة المرور" required>
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
            <button type="submit" style="margin-top:10px;padding:10px 14px;border-radius:10px;border:none;background:#10b981;color:#fff;">تحديث كلمة المرور</button>
        </form>
    <?php else: ?>
        <p><a href="login.php">العودة لتسجيل الدخول</a></p>
    <?php endif; ?>
</div>
</body>
</html>
