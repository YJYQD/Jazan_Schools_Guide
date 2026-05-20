<?php
/**
 * Request password reset - shows reset link (for local testing) and stores token.
 */
session_start();
require_once "db.php";
require_once "security_helpers.php";

$conn->set_charset("utf8mb4");

initiateCsrfToken();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $message = '❌ خطأ أمني: رمز جلسة غير صحيح';
    } else {
        $username = isset($_POST['username']) ? sanitizeInput($_POST['username']) : '';
        if (empty($username) || !isValidUsername($username)) {
            $message = '❌ اسم المستخدم غير صالح';
        } else {
            $stmt = $conn->prepare("SELECT User_ID, Username FROM Users WHERE Username = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $user = $res->fetch_assoc();
                    $user_id = $user['User_ID'];

                    // create table if not exists
                    $conn->query("CREATE TABLE IF NOT EXISTS Password_Resets (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        token VARCHAR(128) NOT NULL,
                        expires_at DATETIME NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

                    $ins = $conn->prepare("INSERT INTO Password_Resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                    if ($ins) {
                        $ins->bind_param('iss', $user_id, $token, $expires);
                        if ($ins->execute()) {
                            // For local/dev: show reset link instead of email
                            $reset_link = sprintf("%s/reset_password.php?token=%s", rtrim(dirname($_SERVER['REQUEST_URI']), '/'), $token);
                            $message = "✅ تم إنشاء رابط إعادة تعيين. استخدم الرابط التالي (اختباري): <br><a href=\"reset_password.php?token=$token\">فتح رابط إعادة التعيين</a>";
                        } else {
                            $message = '❌ فشل إنشاء رمز إعادة التعيين';
                        }
                        $ins->close();
                    } else {
                        $message = '❌ خطأ في قاعدة البيانات عند إنشاء الرمز';
                    }
                } else {
                    // Do not reveal that user doesn't exist
                    $message = '✅ إذا كان اسم المستخدم موجوداً، فسيتم إنشاء رابط إعادة التعيين.';
                }
                $stmt->close();
            } else {
                $message = '❌ خطأ في قاعدة البيانات';
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
    <title>نسيت كلمة المرور</title>
    <link rel="stylesheet" href="styles.css">
    <style>body{font-family: Tajawal, sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0} .card{padding:28px;border-radius:16px;background:var(--glass);backdrop-filter:blur(10px);max-width:420px;width:100%}</style>
    </head>
<body>
<div class="card">
    <h2>نسيت كلمة المرور</h2>
    <?php if (!empty($message)): ?>
        <div style="margin:10px 0; padding:10px; border-radius:8px; background:rgba(0,0,0,0.05);"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>اسم المستخدم</label>
        <input type="text" name="username" required placeholder="أدخل اسم المستخدم">
        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
        <button type="submit" style="margin-top:10px;padding:10px 14px;border-radius:10px;border:none;background:#0ea5e9;color:#fff;">إنشاء رابط إعادة التعيين</button>
    </form>

    <p style="margin-top:12px;"><a href="login.php">العودة لتسجيل الدخول</a></p>
</div>
</body>
</html>
