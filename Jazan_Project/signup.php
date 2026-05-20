<?php
/**
 * صفحة إنشاء حساب جديد - مشروع المملكة
 * معدّل: تنظيف ومعالجة CSRF
 */
session_start();
require_once "db.php";
require_once "security_helpers.php";
require_once "config.php";
require_once "i18n.php";

$conn->set_charset("utf8mb4");
$page_lang = function_exists('current_lang') ? current_lang() : 'ar';

// توليد CSRF Token
initiateCsrfToken();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        logSecurityEvent('warning', 'CSRF token validation failed during signup');
        $message = t('signup_csrf_error');
        $message_type = 'error';
    } else {
        $username = isset($_POST['username']) ? sanitizeInput($_POST['username']) : '';
        $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

        $validation_errors = [];
        if (empty($username) || !isValidUsername($username)) $validation_errors[] = t('signup_username_invalid');
        if (empty($password) || strlen($password) < 8) $validation_errors[] = t('signup_password_short');
        if ($password !== $password_confirm) $validation_errors[] = t('signup_password_mismatch');
        if (empty($email) || !isValidEmail($email)) $validation_errors[] = t('signup_email_invalid');

        if (!empty($validation_errors)) {
            $message = '❌ ' . implode('<br>❌ ', $validation_errors);
            $message_type = 'error';
        } else {
            // ensure Users table has Email and is_verified columns
            $colRes = $conn->query("SHOW COLUMNS FROM Users LIKE 'Email'");
            if (!$colRes || $colRes->num_rows === 0) {
                $conn->query("ALTER TABLE Users ADD COLUMN Email VARCHAR(255) DEFAULT NULL, ADD COLUMN is_verified TINYINT(1) DEFAULT 0");
            }

            // التحقق من وجود اسم المستخدم أو البريد باستخدام Prepared Statement
            $check_stmt = $conn->prepare("SELECT User_ID FROM Users WHERE Username = ? OR Email = ?");
            if (!$check_stmt) {
                logSecurityEvent('error', 'Database prepare error during signup check', ['error' => $conn->error]);
                $message = handleDbError($conn->error, 'Failed to prepare user check query');
                $message_type = 'error';
            } else {
                $check_stmt->bind_param("ss", $username, $email);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result && $check_result->num_rows > 0) {
                    $message = '❌ ' . t('signup_username_exists');
                    $message_type = 'error';
                    logSecurityEvent('warning', 'Signup attempt with existing username', ['username' => $username]);
                } else {
                    // تشفير كلمة المرور
                    $hashedPassword = hashPassword($password);

                    // إدراج المستخدم الجديد مع البريد وتمكين الحساب مباشرة (بدون تحقق عبر البريد)
                    $insert_stmt = $conn->prepare("INSERT INTO Users (Username, Password, Role, Email, is_verified) VALUES (?, ?, ?, ?, 1)");
                    if (!$insert_stmt) {
                        logSecurityEvent('error', 'Database prepare error during signup insert', ['error' => $conn->error]);
                        $message = handleDbError($conn->error, 'Failed to prepare user insert query');
                        $message_type = 'error';
                    } else {
                        $role = 'user'; // الدور الافتراضي
                        $insert_stmt->bind_param("ssss", $username, $hashedPassword, $role, $email);

                        if ($insert_stmt->execute()) {
                            $new_user_id = $conn->insert_id;
                            $message = t('signup_success');
                            $message_type = 'success';
                            logSecurityEvent('info', 'New user registered (auto-verified)', ['username' => $username, 'email' => $email]);
                        } else {
                            logSecurityEvent('error', 'Database insert error during signup', ['error' => $insert_stmt->error]);
                            $message = handleDbError($insert_stmt->error, 'Failed to insert new user');
                            $message_type = 'error';
                        }
                        $insert_stmt->close();
                    }
                }
                $check_stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($page_lang); ?>" dir="<?php echo $page_lang === 'en' ? 'ltr' : 'rtl'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('signup_page_title')); ?></title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --page-bg: #f8fafc;
            --page-glow: rgba(14, 165, 233, 0.08);
            --glass: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(15, 23, 42, 0.12);
            --neon: #0284c7;
            --text: #0f172a;
            --input-bg: rgba(255, 255, 255, 0.85);
            --input-border: rgba(15, 23, 42, 0.16);
            --placeholder: rgba(15, 23, 42, 0.5);
        }

        html[data-theme='dark'],
        body.dark {
            --page-bg: #020617;
            --page-glow: rgba(14, 165, 233, 0.15);
            --glass: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.15);
            --neon: #00f2fe;
            --text: #fff;
            --input-bg: rgba(255, 255, 255, 0.05);
            --input-border: rgba(255, 255, 255, 0.1);
            --placeholder: rgba(255, 255, 255, 0.5);
        }

        body {
            margin: 0;
            background: var(--page-bg);
            background-image: radial-gradient(circle at 50% 50%, var(--page-glow) 0%, transparent 80%);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-family: 'Tajawal', sans-serif;
            color: var(--text);
        }

        .card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 40px;
            border-radius: 30px;
            width: 100%;
            max-width: 450px;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
        }

        h2 {
            color: var(--neon);
            margin-bottom: 20px;
            font-size: 24px;
        }

        .message {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 0.9rem;
            border: 1px solid;
        }

        .message.success {
            background: rgba(34, 197, 94, 0.2);
            color: #86efac;
            border-color: #22c55e;
        }

        .message.error {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border-color: #f87171;
        }

        input {
            width: 100%;
            padding: 15px;
            margin: 10px 0;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 15px;
            color: var(--text);
            outline: none;
            box-sizing: border-box;
            font-family: 'Tajawal';
            font-size: 1rem;
        }

        input:focus {
            border-color: var(--neon);
            box-shadow: 0 0 0 3px rgba(0, 242, 254, 0.1);
        }

        input::placeholder {
            color: var(--placeholder);
        }

        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #10b981, #0ea5e9);
            border: none;
            border-radius: 15px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            transition: 0.3s;
            font-family: 'Tajawal';
            font-size: 1rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .form-group {
            margin: 15px 0;
            text-align: right;
        }

        .form-group label {
            display: block;
            font-size: 0.9rem;
            margin-bottom: 5px;
            opacity: 0.8;
        }

        .password-strength {
            font-size: 0.85rem;
            margin-top: 5px;
            padding: 8px;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.2);
        }

        .strength-weak { color: #fca5a5; }
        .strength-medium { color: #fbbf24; }
        .strength-strong { color: #86efac; }

        a {
            color: var(--neon);
            text-decoration: none;
            font-size: 13px;
        }

        a:hover {
            text-decoration: underline;
        }

        .links {
            font-size: 13px;
            margin-top: 20px;
            opacity: 0.7;
        }

        #theme-toggle {
            z-index: 5000 !important;
        }
    </style>

    <script>
    function checkPasswordStrength() {
        const password = document.getElementById('password').value;
        const strength = document.getElementById('passwordStrength');
        
        if (!password) {
            strength.textContent = '';
            return;
        }

        let score = 0;
        if (password.length >= 8) score++;
        if (password.length >= 12) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;

        let level = '';
        let className = '';
        if (score <= 2) {
            level = signupTexts.veryWeak;
            className = 'strength-weak';
        } else if (score <= 3) {
            level = signupTexts.weak;
            className = 'strength-weak';
        } else if (score <= 4) {
            level = signupTexts.medium;
            className = 'strength-medium';
        } else if (score <= 5) {
            level = signupTexts.strong;
            className = 'strength-strong';
        } else {
            level = signupTexts.veryStrong;
            className = 'strength-strong';
        }

        strength.textContent = signupTexts.strengthLabel + ' ' + level;
        strength.className = 'password-strength ' + className;
    }
    </script>
</head>

<body>
    <button id="theme-toggle" class="btn-icon" type="button" title="<?php echo htmlspecialchars(t('theme_toggle')); ?>" aria-label="<?php echo htmlspecialchars(t('theme_toggle')); ?>" style="font-size:1.05rem;">🌙</button>

    <div class="card">
        <h2>📝 <?php echo htmlspecialchars(t('signup_heading')); ?></h2>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username"><?php echo htmlspecialchars(t('username_label')); ?></label>
                <input type="text" id="username" name="username" placeholder="<?php echo htmlspecialchars(t('username_signup_placeholder')); ?>" required>
                <small style="opacity: 0.6;"><?php echo htmlspecialchars(t('username_signup_note')); ?></small>
            </div>

            <div class="form-group">
                <label for="email"><?php echo htmlspecialchars(t('email_label')); ?></label>
                <input type="email" id="email" name="email" placeholder="<?php echo htmlspecialchars(t('email_placeholder')); ?>" required>
            </div>

            <div class="form-group">
                <label for="password"><?php echo htmlspecialchars(t('password_label')); ?></label>
                <input type="password" id="password" name="password" placeholder="<?php echo htmlspecialchars(t('password_min_placeholder')); ?>" required onkeyup="checkPasswordStrength()">
                <div id="passwordStrength" class="password-strength"></div>
            </div>

            <div class="form-group">
                <label for="password_confirm"><?php echo htmlspecialchars(t('confirm_password_label')); ?></label>
                <input type="password" id="password_confirm" name="password_confirm" placeholder="<?php echo htmlspecialchars(t('confirm_password_signup_placeholder')); ?>" required>
            </div>

            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">

            <button type="submit" class="btn"><?php echo htmlspecialchars(t('signup_create_account_button')); ?></button>
        </form>

        <div class="links">
            <p><?php echo htmlspecialchars(t('already_have_account')); ?> <a href="login.php"><?php echo htmlspecialchars(t('sign_in')); ?></a></p>
            <a href="index.php"><?php echo htmlspecialchars(t('back_to_home')); ?></a>
        </div>
    </div>

    <script>
    const signupTexts = <?php echo json_encode([
        'strengthLabel' => t('password_strength_label'),
        'veryWeak' => t('password_strength_very_weak'),
        'weak' => t('password_strength_weak'),
        'medium' => t('password_strength_medium'),
        'strong' => t('password_strength_strong'),
        'veryStrong' => t('password_strength_very_strong'),
        'toggleDark' => t('theme_toggle_dark'),
        'toggleLight' => t('theme_toggle_light'),
    ], JSON_UNESCAPED_UNICODE); ?>;

    (function(){
        const toggle = document.getElementById('theme-toggle');
        if (!toggle) return;

        const applyTheme = function(mode) {
            const isDark = mode === 'dark';
            document.body.classList.toggle('dark', isDark);
            document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
            toggle.textContent = isDark ? '☀️' : '🌙';
            toggle.setAttribute('aria-label', isDark ? signupTexts.toggleLight : signupTexts.toggleDark);
        };

        const storedTheme = localStorage.getItem('jazan_theme');
        if (storedTheme === 'dark' || storedTheme === 'light') {
            applyTheme(storedTheme);
        } else {
            applyTheme('dark');
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

<!-- legacy duplicate block removed to avoid a second Arabic-only signup UI -->