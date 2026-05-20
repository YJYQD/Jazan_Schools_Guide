<?php
/**
 * صفحة إضافة مدرسة جديدة - مشروع المملكة
 * محسّنة بـ: Prepared Statements, CSRF Protection, Input Validation
 */
session_start();
require_once "db.php";
require_once "security_helpers.php";
require_once "i18n.php";

// 1. حماية الصفحة: لا يدخل إلا الأدمن
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    logSecurityEvent('warning', 'Unauthorized access attempt to add_school.php');
    die("⚠️ عذراً، هذه الصفحة مخصصة لمدراء النظام فقط.");
}

$conn->set_charset("utf8mb4");

// توليد CSRF Token
initiateCsrfToken();

$message = "";
$message_type = "";
$page_lang = function_exists('current_lang') ? current_lang() : 'ar';

// 2. معالجة إرسال النموذج (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من CSRF Token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        logSecurityEvent('warning', 'CSRF token validation failed in add_school');
        $message = "❌ خطأ أمني: رمز جلسة غير صحيح";
        $message_type = "error";
    } else {
        // استقبال المدخلات بأمان
        $name    = isset($_POST['school_name']) ? sanitizeInput($_POST['school_name']) : '';
        $type    = isset($_POST['school_type']) ? sanitizeInput($_POST['school_type']) : '';
        $level   = isset($_POST['education_level']) ? sanitizeInput($_POST['education_level']) : '';
        $oid     = isset($_POST['office_id']) ? intval($_POST['office_id']) : 0;
        $city    = isset($_POST['city']) ? sanitizeInput($_POST['city']) : '';
        $website = isset($_POST['school_website']) ? sanitizeInput($_POST['school_website']) : '';
        $rating  = isset($_POST['rating']) ? sanitizeInput($_POST['rating']) : '';
        $gender  = isset($_POST['gender']) ? sanitizeInput($_POST['gender']) : '';

        // التحقق من صحة المدخلات
        $validation_errors = [];

        if (empty($name)) {
            $validation_errors[] = t('school_name_required');
        } elseif (strlen($name) > 200) {
            $validation_errors[] = t('school_name_too_long');
        }

        if ($oid <= 0) {
            $validation_errors[] = t('choose_office_required');
        }

        if (empty($type) || !in_array($type, ['حكومي', 'أهلي'])) {
            $validation_errors[] = t('invalid_sector_type');
        }

        if (empty($level) || !in_array($level, ['روضة', 'ابتدائي', 'متوسط', 'ثانوي', 'مجمع'])) {
            $validation_errors[] = t('invalid_education_level');
        }

        if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
            $validation_errors[] = t('invalid_website');
        }

        if (!empty($validation_errors)) {
            $message = "❌ " . implode("<br>❌ ", $validation_errors);
            $message_type = "error";
        } else {
            // التحقق من وجود المكتب التعليمي
            $check_stmt = $conn->prepare("SELECT Office_ID FROM Offices WHERE Office_ID = ?");
            $check_stmt->bind_param("i", $oid);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows === 0) {
                $message = t('office_not_found');
                $message_type = "error";
                logSecurityEvent('warning', 'Invalid office_id in add_school', ['office_id' => $oid]);
            } else {
                // إدراج المدرسة باستخدام Prepared Statement
                $stmt = $conn->prepare(
                    "INSERT INTO Schools 
                    (School_Name, School_Type, Education_Level, Office_ID, City, School_Website, Ministerial_Rating, Gender)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );

                if (!$stmt) {
                    logSecurityEvent('error', 'Database prepare error in add_school', ['error' => $conn->error]);
                    $message = handleDbError($conn->error, 'Failed to prepare school insert query');
                    $message_type = "error";
                } else {
                    $stmt->bind_param(
                        "sssissss",
                        $name,
                        $type,
                        $level,
                        $oid,
                        $city,
                        $website,
                        $rating,
                        $gender
                    );

                    if ($stmt->execute()) {
                        $message = t('school_added_success');
                        $message_type = "success";
                        logSecurityEvent('info', 'New school added successfully', 
                            ['school_name' => $name, 'office_id' => $oid, 'added_by' => $_SESSION['username']]);

                        // Reset the form after a short delay
                        echo "<script>setTimeout(() => { document.querySelector('form').reset(); }, 2000);</script>";
                    } else {
                        logSecurityEvent('error', 'Database insert error in add_school', ['error' => $stmt->error]);
                        $message = handleDbError($stmt->error, 'Failed to insert new school');
                        $message_type = "error";
                    }
                    $stmt->close();
                }
            }
            $check_stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($page_lang); ?>" dir="<?php echo $page_lang === 'en' ? 'ltr' : 'rtl'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('add_school_page_title')); ?></title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        body { background: #0f172a; color: white; font-family: 'Tajawal', sans-serif; }
        .container { max-width: 700px; margin: 50px auto; background: #1e293b; padding: 40px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        h2 { text-align: center; color: #4ade80; margin-bottom: 30px; }
        .message { padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 1px solid; text-align: center; }
        .message.success { background: rgba(34, 197, 94, 0.2); color: #86efac; border-color: #22c55e; }
        .message.error { background: rgba(239, 68, 68, 0.2); color: #fca5a5; border-color: #f87171; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #cbd5e1; }
        input, select { width: 100%; padding: 12px; background: #0f172a; border: 1px solid #334155; border-radius: 10px; color: white; font-family: 'Tajawal'; outline: none; }
        input:focus, select:focus { border-color: #00f2fe; box-shadow: 0 0 0 2px rgba(0, 242, 254, 0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        button { background: #4ade80; color: #064e3b; padding: 12px; border: none; border-radius: 10px; font-weight: bold; cursor: pointer; width: 100%; transition: 0.3s; margin-top: 20px; }
        button:hover { background: #22c55e; }
        a { text-align: center; display: block; color: #94a3b8; text-decoration: none; margin-top: 15px; transition: 0.3s; }
        a:hover { color: #cbd5e1; }
    </style>

    <script>
        const defaultGovernorateOption = <?php echo json_encode(t('all_governorates')); ?>;
        const defaultOfficeOption = <?php echo json_encode(t('all_offices')); ?>;

    function updateDropdowns(type, id) {
        let target = (type === 'region') ? 'gov_id' : 'office_id';
            let data = new URLSearchParams(type === 'region' ? { region_id: id } : { gov_id: id }).toString();

        const targetEl = document.getElementsByName(target)[0];
        if (targetEl) {
                targetEl.innerHTML = `<option value="">${type === 'region' ? defaultGovernorateOption : defaultOfficeOption}</option>`;
            targetEl.value = '';
        }

        if (type === 'region') {
            const officeEl = document.getElementsByName('office_id')[0];
            if (officeEl) {
                    officeEl.innerHTML = `<option value="">${defaultOfficeOption}</option>`;
                officeEl.value = '';
            }
        }

        fetch('fetch_data.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: data
        })
        .then(response => response.text())
        .then(html => {
            if (targetEl) targetEl.innerHTML = html;
        })
        .catch(err => console.error('Error:', err));
    }
    </script>
</head>
<body>

(function(){
    // Provide localized labels for this page (fallback if navbar didn't initialize them)
    var jazan_theme_light_label = <?php echo json_encode(t('theme_light_label'), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
    var jazan_theme_dark_label = <?php echo json_encode(t('theme_dark_label'), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
})();
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

<div style="padding:12px;">
    <a href="admin.php" class="back-button"><?php echo htmlspecialchars(t('back_to_dashboard')); ?></a>
</div>

<div class="container">
    <h2><?php echo htmlspecialchars(t('add_school_heading')); ?></h2>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label><?php echo htmlspecialchars(t('school_name_label')); ?></label>
            <input type="text" name="school_name" placeholder="<?php echo htmlspecialchars(t('school_name_placeholder')); ?>" required maxlength="200">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><?php echo htmlspecialchars(t('sector_type_label')); ?></label>
                <select name="school_type" required>
                    <option value=""><?php echo htmlspecialchars(t('select_option')); ?></option>
                    <option value="حكومي">🏛️ <?php echo htmlspecialchars(t('public')); ?></option>
                    <option value="أهلي">💎 <?php echo htmlspecialchars(t('private')); ?></option>
                </select>
            </div>
            <div class="form-group">
                <label><?php echo htmlspecialchars(t('education_level_label')); ?></label>
                <select name="education_level" required>
                    <option value=""><?php echo htmlspecialchars(t('select_option')); ?></option>
                    <option value="روضة">🧸 <?php echo htmlspecialchars(t('kindergarten')); ?></option>
                    <option value="ابتدائي">📚 <?php echo htmlspecialchars(t('elementary')); ?></option>
                    <option value="متوسط">📖 <?php echo htmlspecialchars(t('middle')); ?></option>
                    <option value="ثانوي">🎓 <?php echo htmlspecialchars(t('high_school')); ?></option>
                    <option value="مجمع">🏫 <?php echo htmlspecialchars(t('complex')); ?></option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label><?php echo htmlspecialchars(t('administrative_region_label')); ?></label>
            <select name="region_id" onchange="updateDropdowns('region', this.value)" required>
                <option value=""><?php echo htmlspecialchars(t('choose_region')); ?></option>
                <?php
                $regs = $conn->query("SELECT Region_ID, Region_Name FROM Regions ORDER BY Region_Name");
                while($r = $regs->fetch_assoc()) {
                    echo "<option value='".$r['Region_ID']."'>".htmlspecialchars($r['Region_Name'])."</option>";
                }
                ?>
            </select>
        </div>

        <div class="form-group">
            <label><?php echo htmlspecialchars(t('governorate')); ?></label>
            <select name="gov_id" onchange="updateDropdowns('gov', this.value)" required>
                <option value=""><?php echo htmlspecialchars(t('select_region_first')); ?></option>
            </select>
        </div>

        <div class="form-group">
            <label><?php echo htmlspecialchars(t('education_office_label')); ?></label>
            <select name="office_id" required>
                <option value=""><?php echo htmlspecialchars(t('select_governorate_first')); ?></option>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><?php echo htmlspecialchars(t('city_neighborhood_label')); ?></label>
                <input type="text" name="city" placeholder="<?php echo htmlspecialchars(t('city_placeholder')); ?>" maxlength="100">
            </div>
            <div class="form-group">
                <label><?php echo htmlspecialchars(t('gender_label')); ?></label>
                <select name="gender">
                    <option value=""><?php echo htmlspecialchars(t('select_option')); ?></option>
                    <option value="بنين">👦 <?php echo htmlspecialchars(t('boys')); ?></option>
                    <option value="بنات">👧 <?php echo htmlspecialchars(t('girls')); ?></option>
                    <option value="مختلط">👥 <?php echo htmlspecialchars(t('mixed') ?? 'مختلط'); ?></option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label><?php echo htmlspecialchars(t('website_link_label')); ?></label>
            <input type="url" name="school_website" placeholder="https://maps.google.com/..." maxlength="300">
        </div>

        <div class="form-group">
            <label><?php echo htmlspecialchars(t('ministerial_rating_label')); ?></label>
            <select name="rating" required>
                <option value=""><?php echo htmlspecialchars(t('select_option')); ?></option>
                <option value="ممتاز">⭐⭐⭐⭐⭐ <?php echo htmlspecialchars(t('excellent')); ?></option>
                <option value="جيد جداً">⭐⭐⭐⭐ <?php echo htmlspecialchars(t('very_good')); ?></option>
                <option value="جيد">⭐⭐⭐ <?php echo htmlspecialchars(t('good')); ?></option>
                <option value="مقبول">⭐⭐ <?php echo htmlspecialchars(t('acceptable')); ?></option>
            </select>
        </div>

        <!-- CSRF Token -->
        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">

        <button type="submit">💾 <?php echo htmlspecialchars(t('save_school_to_db')); ?></button>
    </form>

    <a href="admin.php"><?php echo htmlspecialchars(t('back_to_dashboard')); ?></a>
    <a href="index.php"><?php echo htmlspecialchars(t('back_to_home')); ?></a>
</div>

</body>
</html>

<!-- legacy duplicate block removed to keep the translated add-school UI only -->

