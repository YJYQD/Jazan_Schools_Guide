<?php
/**
 * صفحة تسجيل الدخول الموحدة - مشروع المملكة
 * إعداد المطور: يحيى مكرشي
 * 🔐 محسّنة بـ: Prepared Statements, Password Hashing, Rate Limiting, CSRF Protection
 */
// Ensure session is started safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "db.php";
require_once "security_helpers.php";
require_once "i18n.php";

$conn->set_charset("utf8mb4");
$login_lang = function_exists('current_lang') ? current_lang() : 'ar';

// تأمين الجلسة
if (!secureSession()) {
    header("Location: login.php?timeout=1");
    exit();
}

// توليد CSRF Token
generate_csrf_token();

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // التحقق من CSRF Token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        logSecurityEvent('warning', 'CSRF token validation failed');
        $error = t('login_security_error');
    } else {
        $username = isset($_POST['username']) ? sanitizeInput($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        // التحقق من صحة المدخلات
        if (empty($username) || empty($password)) {
            $error = t('login_missing_fields');
        } elseif (!isValidUsername($username)) {
            $error = t('login_username_invalid');
        } else {
            // مصادقة المستخدم عبر قاعدة البيانات فقط (لا توجد طرق احتياطية)

            // فحص حد معدل الطلبات (5 محاولات كل 5 دقائق)
            $rate_limit_id = "login_" . $_SERVER['REMOTE_ADDR'];
            
            if (!checkRateLimit($rate_limit_id, 5, 50)) {
                logSecurityEvent('warning', 'Rate limit exceeded for login', ['username' => $username]);
                $error = t('login_rate_limited');
            } else {
                // استعلام آمن باستخدام Prepared Statement
                $stmt = $conn->prepare("SELECT User_ID, Username, Password, Role FROM Users WHERE Username = ?");
                if (!$stmt) {
                    logSecurityEvent('error', 'Database prepare error', ['error' => $conn->error]);
                    $error = handleDbError($conn->error, 'Failed to prepare login query');
                } else {
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result && $result->num_rows > 0) {
                        $row = $result->fetch_assoc();

                        // التحقق من كلمة المرور المحفوظة (هاش)
                        if (verifyPassword($password, $row['Password'])) {
                            // تسجيل الدخول الناجح
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = $row['User_ID'];
                            $_SESSION['username'] = $row['Username'];
                            $_SESSION['role'] = $row['Role'];
                            $_SESSION['login_time'] = time();

                            // مسح سجل حد معدل الطلبات
                            clearRateLimit($rate_limit_id);

                            // تسجيل الحدث
                            logSecurityEvent('info', 'User logged in successfully', 
                                ['user_id' => $row['User_ID'], 'username' => $row['Username']]);

                            // إعادة توجيه للرئيسية
                            header("Location: index.php");
                            exit();
                        } else {
                            // كلمة مرور خاطئة
                            logSecurityEvent('warning', 'Failed login attempt - wrong password', ['username' => $username]);
                            $error = t('login_invalid_credentials');
                        }
                    } else {
                        // اسم المستخدم غير موجود
                        logSecurityEvent('warning', 'Failed login attempt - user not found', ['username' => $username]);
                        $error = t('login_invalid_credentials');
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// تحقق من timeout
if (isset($_GET['timeout'])) {
    $error = t('login_timeout');
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($login_lang); ?>" dir="<?php echo $login_lang === 'en' ? 'ltr' : 'rtl'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('login_page_title')); ?></title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --page-bg: #f8fafc;
            --page-glow: rgba(14, 165, 233, 0.08);
            --glass: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(15, 23, 42, 0.12);
            --neon-blue: #0284c7;
            --text-main: #0f172a;
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
            --neon-blue: #00f2fe;
            --text-main: #f8fafc;
            --input-bg: rgba(255, 255, 255, 0.05);
            --input-border: rgba(255, 255, 255, 0.15);
            --placeholder: rgba(255, 255, 255, 0.5);
        }

        body {
            margin: 0; padding: 0;
            background: var(--page-bg);
            background-image: radial-gradient(circle at 50% 50%, var(--page-glow) 0%, transparent 80%);
            font-family: 'Tajawal', sans-serif;
            display: flex; justify-content: center; align-items: center;
            height: 100vh; color: var(--text-main);
        }

        .login-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 40px;
            border-radius: 30px;
            width: 100%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
        }

        h2 { color: var(--neon-blue); margin-bottom: 30px; font-size: 24px; }

        .error { background: rgba(239, 68, 68, 0.2); color: #fca5a5; padding: 12px; border-radius: 10px; margin-bottom: 15px; border: 1px solid #f87171; }

        input {
            width: 100%;
            padding: 15px;
            margin: 12px 0;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 15px;
            color: var(--text-main);
            outline: none;
            box-sizing: border-box;
            font-family: 'Tajawal';
        }

        input:focus { border-color: var(--neon-blue); box-shadow: 0 0 0 3px rgba(0, 242, 254, 0.1); }

        input::placeholder { color: var(--placeholder); }

        button {
            width: 100%;
            padding: 15px;
            margin-top: 10px;
            background: linear-gradient(135deg, #10b981 0%, #0ea5e9 100%);
            border: none;
            border-radius: 15px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3); }

        a { color: var(--neon-blue); text-decoration: none; font-size: 13px; }
        a:hover { text-decoration: underline; }

        .form-group { margin: 15px 0; }

        #theme-toggle {
            z-index: 5000 !important;
        }
    </style>
</head>
<body>
    <button id="theme-toggle" class="btn-icon" type="button" title="<?php echo htmlspecialchars(t('theme_toggle')); ?>" aria-label="<?php echo htmlspecialchars(t('theme_toggle')); ?>" style="font-size:1.05rem;">🌙</button>

    <div class="login-card">
        <div style="display:flex; gap:8px; justify-content:center; margin-bottom:18px;">
            <button id="tab-login" class="tab-btn active"><?php echo htmlspecialchars(t('login_tab')); ?></button>
            <button id="tab-signup" class="tab-btn"><?php echo htmlspecialchars(t('signup_tab')); ?></button>
        </div>

        <div id="panel-login">
            <h2><?php echo htmlspecialchars(t('signin_heading')); ?></h2>
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <input type="text" name="username" placeholder="<?php echo htmlspecialchars(t('username_placeholder')); ?>" required autofocus>
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="<?php echo htmlspecialchars(t('password_placeholder')); ?>" required>
                </div>

                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">

                <button type="submit"><?php echo htmlspecialchars(t('login_button')); ?></button>
            </form>

            <p style="font-size: 13px; margin-top: 12px; opacity: 0.8;">
                <a href="forgot_password.php"><?php echo htmlspecialchars(t('forgot_password')); ?></a>
            </p>
        </div>

        <div id="panel-signup" style="display:none;">
            <h2><?php echo htmlspecialchars(t('new_account_heading')); ?></h2>
            <form method="POST" action="signup.php">
                <div class="form-group">
                    <input type="text" name="username" placeholder="<?php echo htmlspecialchars(t('username_placeholder')); ?>" required>
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="<?php echo htmlspecialchars(t('password_placeholder')); ?>" required>
                </div>
                <div class="form-group">
                    <input type="password" name="password_confirm" placeholder="<?php echo htmlspecialchars(t('confirm_password_placeholder')); ?>" required>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                <button type="submit"><?php echo htmlspecialchars(t('create_account_button')); ?></button>
            </form>
            <p style="font-size: 13px; margin-top: 12px; opacity: 0.8;"><?php echo htmlspecialchars(t('have_account')); ?> <a href="#" onclick="showLoginTab();return false;"><?php echo htmlspecialchars(t('switch_login')); ?></a></p>
        </div>

        <a href="index.php" style="display:block; margin-top:14px;"><?php echo htmlspecialchars(t('back_home_short')); ?></a>
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
            applyTheme('dark');
        }

        toggle.addEventListener('click', function() {
            const nextTheme = document.body.classList.contains('dark') ? 'light' : 'dark';
            applyTheme(nextTheme);
            localStorage.setItem('jazan_theme', nextTheme);
        });
    })();
        // Tabs for login/signup
        document.addEventListener('DOMContentLoaded', function(){
            const tabLogin = document.getElementById('tab-login');
            const tabSignup = document.getElementById('tab-signup');
            const panelLogin = document.getElementById('panel-login');
            const panelSignup = document.getElementById('panel-signup');

            function activate(tab){
                if(tab === 'login'){
                    tabLogin.classList.add('active'); tabSignup.classList.remove('active');
                    panelLogin.style.display = ''; panelSignup.style.display = 'none';
                } else {
                    tabSignup.classList.add('active'); tabLogin.classList.remove('active');
                    panelSignup.style.display = ''; panelLogin.style.display = 'none';
                }
            }

            tabLogin.addEventListener('click', function(){ activate('login'); });
            tabSignup.addEventListener('click', function(){ activate('signup'); });

            window.showLoginTab = function(){ activate('login'); };
            // respect anchor
            if(location.hash === '#signup') activate('signup');
        });
    </script>
</body>
</html>