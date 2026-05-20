<?php
/**
 * navbar.php
 * شريط التنقل الموحد لجميع صفحات النظام
 * يوفر دالة يمكن إعادة استخدامها في كل صفحة
 */

/**
 * دالة عرض شريط التنقل
 * 
 * @param string $current_page - اسم الصفحة الحالية للتمييز بينها
 * @param bool $show_back - إظهار زر العودة
 * @param string $back_url - رابط العودة (افتراضياً admin.php)
 * @param string $back_text - نص زر العودة
 */
function render_navbar($current_page = '', $show_back = true, $back_url = 'admin.php', $back_text = '⬅️ العودة') {
    // make translations available
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'i18n.php';
    // Language switch handling (set via GET param ?set_lang=ar|en)
    if (isset($_GET['set_lang'])) {
        $lang = ($_GET['set_lang'] === 'en') ? 'en' : 'ar';
        // store preference in session if available
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['lang'] = $lang;
        } else {
            // fallback: set a cookie for clients without active session
            setcookie('jazan_lang', $lang, time() + 30*24*3600, '/');
        }
        // client-side redirect (safer when headers/output may already be sent)
        $redirect = htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'));
        echo "<script>try{window.location.replace('{$redirect}');}catch(e){window.location.href='{$redirect}';}</script>";
        return;
    }

    $is_admin = isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
    $is_user = isset($_SESSION['role']) && $_SESSION['role'] == 'user';
    $is_logged_in = isset($_SESSION['user_id']);
    // centralized translator
    $lang = current_lang();
    $L = [
        'home' => t('home'),
        'admin' => t('admin'),
        'stats' => t('stats'),
        'map' => t('map'),
        'search' => t('search'),
        'login' => t('login'),
        'signup' => t('signup'),
        'logout' => t('logout'),
        'beta' => t('beta'),
        'theme_toggle' => t('theme_toggle'),
        'theme_light_label' => t('theme_light_label'),
        'theme_dark_label' => t('theme_dark_label'),
        'back' => t('back'),
        'user_default' => t('user_default'),
        'lang_ar' => t('lang_ar'),
        'lang_en' => t('lang_en')
    ];
    // try to load runtime settings (site title, primary color) from settings.json
    $settings = [];
    $settingsFile = __DIR__ . DIRECTORY_SEPARATOR . 'settings.json';
    if (file_exists($settingsFile)) {
        $raw = @file_get_contents($settingsFile);
        $decoded = @json_decode($raw, true);
        if (is_array($decoded)) $settings = $decoded;
    }
    $site_title = $settings['site_title'] ?? 'دليل مدارس المملكة';
    $primary_color = $settings['primary_color'] ?? null;
    if ($primary_color) {
        echo "<style>:root{ --primary: " . htmlspecialchars($primary_color) . "; --primary-light: " . htmlspecialchars($primary_color) . "; }</style>";
    }
    // prepare localized back text
    $back_text_local = $back_text;
    if ($back_text === '⬅️ العودة' || $back_text === '' || $back_text === null) {
        $back_text_local = $L['back'];
    }
    // determine if back_url points to admin
    $parsedPath = parse_url($back_url, PHP_URL_PATH) ?: $back_url;
    $back_basename = basename($parsedPath);
    $back_is_admin = strtolower($back_basename) === 'admin.php';
    ?>

    <nav class="navbar">
        <div class="navbar-container">
            <!-- زر تبديل الوضع (جهة اليسار) -->
            <button id="theme-toggle" class="btn-icon" type="button" title="<?php echo htmlspecialchars($L['theme_toggle']); ?>" aria-label="<?php echo htmlspecialchars($L['theme_toggle']); ?>" style="font-size:1.05rem;">🌙</button>
            <!-- الجزء الأيسر: الشعار والعنوان -->
            <div class="navbar-brand" style="display:flex; align-items:center; gap:8px;">
                <?php
                // Resolve logo from multiple possible locations, including parent /img folder
                $candidates = [
                    __DIR__ . '/assets/images/jazan_logo.png',
                    __DIR__ . '/uploads/jazan_logo.png',
                    __DIR__ . '/../img/jazan_logo.png',
                    __DIR__ . '/../img/logo.png',
                ];
                $found = null;
                foreach ($candidates as $p) {
                    if (file_exists($p)) { $found = realpath($p); break; }
                }
                if (!$found) {
                    $imgDir = realpath(__DIR__ . '/../img');
                    if ($imgDir && is_dir($imgDir)) {
                        $files = glob($imgDir . '/*.{png,jpg,jpeg,gif,svg}', GLOB_BRACE);
                        if (!empty($files)) {
                            $found = realpath($files[0]);
                        }
                    }
                }

                if ($found) {
                    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? 'C:/xampp/htdocs');
                    if ($docRoot && strpos($found, $docRoot) === 0) {
                        $logo_web = '/' . ltrim(str_replace('\\', '/', substr($found, strlen($docRoot) + 1)), '/');
                    } else {
                        // fallback: make relative to project dir
                        $logo_web = ltrim(str_replace('\\', '/', str_replace(realpath(__DIR__) . DIRECTORY_SEPARATOR, '', $found)), '/');
                    }
                } else {
                    $logo_web = 'uploads/jazan_logo.png';
                }
                ?>
                <img src="<?php echo htmlspecialchars($logo_web); ?>" alt="<?php echo htmlspecialchars(t('jazan_logo_alt')); ?>" style="height:56px; width:auto; object-fit:contain;" />
                <div>
                    <div class="navbar-title"><?php echo htmlspecialchars($site_title); ?></div>
                        <small style="font-size: 0.9rem; opacity: 0.8;"><?php echo htmlspecialchars($L['beta']); ?></small>
                </div>
            </div>

            <!-- قائمة التنقل المركزية -->
            <ul class="navbar-nav">
                <?php if ($current_page !== 'home'): ?>
                    <li class="navbar-item">
                        <a href="index.php" class="navbar-link">
                            <span>🏠</span> <?php echo htmlspecialchars($L['home']); ?>
                        </a>
                    </li>

                    <?php if ($is_admin): ?>
                        <li class="navbar-item">
                            <a href="admin.php" class="navbar-link <?= $current_page == 'admin' ? 'active' : '' ?>">
                                <span>⚙️</span> <?php echo htmlspecialchars($L['admin']); ?>
                            </a>
                        </li>
                        <li class="navbar-item">
                            <a href="stats_dashboard.php" class="navbar-link <?= $current_page == 'stats' ? 'active' : '' ?>">
                                <span>📊</span> <?php echo htmlspecialchars($L['stats']); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- الخريطة متاحة للجميع الآن -->
                <li class="navbar-item">
                    <a href="map_integration.php" class="navbar-link <?= $current_page == 'map' ? 'active' : '' ?>">
                        <span>🗺️</span> <?php echo htmlspecialchars($L['map']); ?>
                    </a>
                </li>

                <?php if ($is_user): ?>
                    <li class="navbar-item">
                        <a href="details.php" class="navbar-link <?= $current_page == 'details' ? 'active' : '' ?>">
                            <span>🔍</span> <?php echo htmlspecialchars($L['search']); ?>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- الجزء الأيمن: معلومات المستخدم والتسجيل -->
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <!-- Language toggle -->
                <div style="display:flex; gap:6px; align-items:center;">
                    <a href="?set_lang=ar" class="btn btn-sm <?php echo $lang==='ar' ? 'btn-light' : 'btn-outline-secondary'; ?>" style="padding:6px 8px; font-weight:700;"><?php echo htmlspecialchars($L['lang_ar']); ?></a>
                    <a href="?set_lang=en" class="btn btn-sm <?php echo $lang==='en' ? 'btn-light' : 'btn-outline-secondary'; ?>" style="padding:6px 8px; font-weight:700;"><?php echo htmlspecialchars($L['lang_en']); ?></a>
                </div>
                <?php if ($is_logged_in): ?>
                    <span style="color: white; font-size: 0.9rem; padding: 0 10px;">
                        👤 <?= htmlspecialchars($_SESSION['username'] ?? $L['user_default']) ?>
                    </span>
                    <a href="logout.php" class="back-button" style="width: auto;">
                        🚪 <?php echo htmlspecialchars($L['logout']); ?>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="back-button" style="width: auto;">
                        🔐 <?php echo htmlspecialchars($L['login']); ?>
                    </a>
                    <a href="signup.php" class="back-button" style="width: auto; background: var(--accent); color: var(--primary);">
                        ✍️ <?php echo htmlspecialchars($L['signup']); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- زر العودة (إذا كان مطلوباً) -->
        <?php if ($show_back && ($is_admin || !$back_is_admin)): ?>
            <div style="padding: 10px 20px; background: rgba(255, 255, 255, 0.05); border-top: 1px solid rgba(255, 255, 255, 0.1);">
                <a href="<?= htmlspecialchars($back_url) ?>" class="back-button">
                    <?= htmlspecialchars($back_text_local) ?>
                </a>
            </div>
        <?php endif; ?>
    </nav>

    <script>
    // localized theme labels from PHP
    var jazan_theme_light_label = <?php echo json_encode($L['theme_light_label'], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
    var jazan_theme_dark_label = <?php echo json_encode($L['theme_dark_label'], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
    (function(){
        const toggle = document.getElementById('theme-toggle');
        if (!toggle) return;

        const applyTheme = function(mode) {
            const isDark = mode === 'dark';
            document.body.classList.toggle('dark', isDark);
            document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
            toggle.textContent = isDark ? '☀️' : '🌙';
            toggle.setAttribute('aria-label', isDark ? jazan_theme_light_label : jazan_theme_dark_label);
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

    <!-- debug UI removed -->

    <?php
}

/**
 * دالة عرض رسالة النجاح مع الخيارات
 */
function show_success_message($message, $back_url = 'admin.php', $back_text = '⬅️ العودة') {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'i18n.php';
    ?>
    <div style="direction: rtl; text-align: center; min-height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 20px;">
        <div style="background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1); max-width: 500px; width: 100%;">
            <div style="font-size: 4rem; margin-bottom: 20px;">✅</div>
            <h2 style="color: var(--primary); margin: 20px 0; font-size: 1.5rem;"><?php echo htmlspecialchars(t('success_title')); ?></h2>
            <p style="color: var(--text-light); font-size: 1.1rem; margin: 20px 0;">
                <?= htmlspecialchars($message) ?>
            </p>
            <div style="margin-top: 30px; display: grid; gap: 10px;">
                <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-primary" style="width: 100%; text-decoration: none; display: flex; justify-content: center; align-items: center;">
                    <?= htmlspecialchars($back_text) ?>
                </a>
                <a href="index.php" class="btn btn-secondary" style="width: 100%; text-decoration: none; display: flex; justify-content: center; align-items: center;">
                    🏠 <?php echo htmlspecialchars(t('home')); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}

/**
 * دالة عرض رسالة الخطأ
 */
function show_error_message($message, $back_url = 'admin.php', $back_text = '⬅️ العودة') {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'i18n.php';
    ?>
    <div style="direction: rtl; text-align: center; min-height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 20px;">
        <div style="background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1); max-width: 500px; width: 100%;">
            <div style="font-size: 4rem; margin-bottom: 20px;">❌</div>
            <h2 style="color: var(--danger); margin: 20px 0; font-size: 1.5rem;"><?php echo htmlspecialchars(t('error_title')); ?></h2>
            <p style="color: var(--text-light); font-size: 1.1rem; margin: 20px 0;">
                <?= htmlspecialchars($message) ?>
            </p>
            <div style="margin-top: 30px; display: grid; gap: 10px;">
                <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-primary" style="width: 100%; text-decoration: none; display: flex; justify-content: center; align-items: center;">
                    <?= htmlspecialchars($back_text) ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}

/**
 * دالة عرض رسالة التأكيد
 */
function show_confirmation_page($title, $message, $action_url, $action_button_text = 'تأكيد', $back_url = 'admin.php') {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'i18n.php';
    ?>
    <div style="direction: rtl; text-align: center; min-height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 20px;">
        <div style="background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1); max-width: 500px; width: 100%;">
            <div style="font-size: 3rem; margin-bottom: 20px;">⚠️</div>
            <h2 style="color: var(--warning); margin: 20px 0; font-size: 1.5rem;"><?= htmlspecialchars($title) ?></h2>
            <p style="color: var(--text-light); font-size: 1.1rem; margin: 20px 0;">
                <?= htmlspecialchars($message) ?>
            </p>
            <div style="margin-top: 30px; display: grid; gap: 10px; grid-template-columns: 1fr 1fr;">
                <a href="<?= htmlspecialchars($action_url) ?>" class="btn btn-danger" style="text-decoration: none; display: flex; justify-content: center; align-items: center;">
                    <?= htmlspecialchars($action_button_text) ?>
                </a>
                <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-secondary" style="text-decoration: none; display: flex; justify-content: center; align-items: center;">
                    <?php echo htmlspecialchars(t('cancel')); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}
?>
