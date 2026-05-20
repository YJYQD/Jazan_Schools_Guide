<?php
/**
 * دليل مدارس المملكة الذكي - النسخة الاحترافية الكاملة
 * إعداد المطور: يحيى مكرشي
 * 🔐 محسّن بـ: CSRF Protection, Security Headers
 */
session_start();
require_once "db.php";
require_once "filters_helper.php";
require_once "security_helpers.php"; 
require_once "navbar.php";
// Ensure translation helper is available (navbar usually loads it, but be explicit)
require_once __DIR__ . '/i18n.php';

$current_lang = current_lang();
$lang_dir = ($current_lang === 'en') ? 'ltr' : 'rtl';
$lang_font = ($current_lang === 'en') ? 'Times New Roman, serif' : 'Tajawal, sans-serif';

generate_csrf_token();
// Initialize filter/search variables to safe defaults to avoid undefined warnings
$q = $_GET['q'] ?? '';
$rid = $_GET['region_id'] ?? '';
$gid = $_GET['gov_id'] ?? '';
$oid = $_GET['office_id'] ?? '';
$lvl = $_GET['level'] ?? '';
$type = $_GET['school_type'] ?? '';
$gen = $_GET['gender'] ?? '';
$kinder_n = $elem_n = $mid_n = $high_n = $comp_n = 0;
// العدادات الأولية (بدون فلاتر)
$count_schools = db_fetch_count($conn, "SELECT COUNT(*) AS total FROM Schools");
$count_offices = db_fetch_count($conn, "SELECT COUNT(*) AS total FROM Offices");
$count_reviews = db_fetch_count($conn, "SELECT COUNT(*) AS total FROM School_Reviews");
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>" dir="<?php echo htmlspecialchars($lang_dir); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('site_title')); ?></title>
    <link rel="stylesheet" href="styles.css?t=<?php echo filemtime('styles.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        .school-card { background: white; border-radius: 25px; margin-bottom: 35px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; transition: 0.3s; }
        .school-card:hover { box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .card-header { padding: 30px; display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; background: linear-gradient(to left, #ffffff, #fafafa); }
        .badge { padding: 8px 16px; border-radius: 12px; font-size: 0.9rem; font-weight: bold; display: inline-flex; align-items: center; gap: 5px; }
        .btn-map { background: #10b981; color: white; text-decoration: none; padding: 12px 25px; border-radius: 12px; font-weight: bold; transition: 0.3s; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2); }
        .btn-map:hover { background: #059669; transform: scale(1.05); }
        .school-media { width: 100%; flex-shrink: 0; display: flex; flex-direction: column; gap: 12px; }
        .school-gallery { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
        .school-thumb-item { position: relative; height: 78px; }
        .school-thumb-btn, .school-thumb-more { border: 0; padding: 0; border-radius: 12px; overflow: hidden; cursor: pointer; position: relative; height: 78px; width: 100%; }
        .school-photo-thumb { width: 100%; height: 100%; object-fit: cover; background: #f8fafc; border: 1px solid #e2e8f0; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08); transition: transform 0.2s ease; }
        .school-thumb-btn:hover .school-photo-thumb { transform: scale(1.06); }
        .school-thumb-more { background: linear-gradient(135deg, #0ea5e9, #1d4ed8); color: #fff; font-weight: 800; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; }
        .school-thumb-delete { position: absolute; top: 6px; left: 6px; width: 24px; height: 24px; border: 0; border-radius: 50%; background: rgba(239,68,68,0.95); color: #fff; font-weight: 900; cursor: pointer; z-index: 2; }
        .school-gallery-actions { margin-top: 8px; display: flex; justify-content: flex-end; }
        .school-delete-all-btn { border: 1px solid #ef4444; background: #fff5f5; color: #b91c1c; border-radius: 10px; padding: 6px 10px; font-size: 0.85rem; cursor: pointer; }
        .school-photo, .school-photo-empty { width: 100%; height: 180px; border-radius: 18px; object-fit: cover; background: #f8fafc; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08); }
        .school-photo-empty { display: flex; align-items: center; justify-content: center; color: #64748b; font-weight: bold; text-align: center; padding: 18px; }
        .school-image-form { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 18px; padding: 14px; }
        .school-image-form label { display: block; margin-bottom: 8px; font-weight: bold; color: #0f172a; font-size: 0.95rem; }
        .school-image-form input[type="file"] { width: 100%; font-family: 'Tajawal', sans-serif; }
        .school-image-form button { width: 100%; margin-top: 10px; border: none; border-radius: 12px; padding: 10px 14px; background: #003366; color: white; font-weight: bold; cursor: pointer; }
        .school-image-form button:hover { background: #00509d; }
        .school-upload-note { margin-top: 8px; color: #64748b; font-size: 0.8rem; line-height: 1.6; }

        /* قسم التعليقات (صدى الميدان) */
        .reviews-section { background: #f8fafc; padding: 30px; border-top: 1px solid #f1f5f9; }
        .review-item { background: white; padding: 20px; border-radius: 20px; margin-bottom: 15px; border: 1px solid #e2e8f0; position: relative; }
        .add-review-form { display: grid; grid-template-columns: 180px 1fr auto; gap: 15px; margin-top: 25px; }

        .lightbox-overlay { position: fixed; inset: 0; background: rgba(2, 6, 23, 0.84); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 20px; }
        .lightbox-overlay.open { display: flex; }
        .lightbox-content { position: relative; width: min(92vw, 980px); }
        .lightbox-image-wrap { background: #0f172a; border-radius: 18px; padding: 12px; box-shadow: 0 20px 40px rgba(0,0,0,0.45); }
        .lightbox-image { width: 100%; max-height: 78vh; object-fit: contain; border-radius: 12px; display: block; }
        .lightbox-close, .lightbox-nav { position: absolute; border: 0; cursor: pointer; background: rgba(255,255,255,0.22); color: #fff; backdrop-filter: blur(6px); }
        .lightbox-close { top: -12px; left: -12px; width: 42px; height: 42px; border-radius: 50%; font-size: 1.2rem; }
        .lightbox-nav { top: 50%; transform: translateY(-50%); width: 42px; height: 42px; border-radius: 50%; font-size: 1.3rem; }
        .lightbox-prev { right: -14px; }
        .lightbox-next { left: -14px; }
        .lightbox-caption { color: #f8fafc; text-align: center; margin-top: 10px; font-weight: 700; }

        @media (max-width: 768px) {
            .card-header { flex-direction: column; }
            .school-media { width: 100%; }
            .school-gallery { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
    <script>
    const defaultGovernorateOption = <?php echo json_encode('📍 ' . t('governorate')); ?>;
    const defaultOfficeOption = <?php echo json_encode('🏢 ' . t('education_office')); ?>;

    function updateHierarchy(type, id) {
        let target = (type === 'region') ? 'gov_id' : 'office_id';
        const targetEl = document.getElementById(target);
        if (targetEl) {
            targetEl.innerHTML = `<option value="">${type === 'region' ? defaultGovernorateOption : defaultOfficeOption}</option>`;
            targetEl.value = '';
        }

        if (type === 'region') {
            const officeEl = document.getElementById('office_id');
            if (officeEl) {
                officeEl.innerHTML = `<option value="">${defaultOfficeOption}</option>`;
                officeEl.value = '';
            }
        }

        const params = (type === 'region') ? { region_id: id } : { gov_id: id };
        params.jazan_lang = document.getElementById('jazan_lang')?.value || '';
        fetch('fetch_data.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams(params).toString()
            }).then(res => res.text()).then(html => {
                console.log('fetch_data response:', html);
                if (targetEl) targetEl.innerHTML = html;
                const payload = gatherFilterParams();
                payload.page = 1;
                console.log('updateHierarchy - payload:', payload);
                submitSearch(payload);
            });
    }

    function toggleAdvanced() {
        const advancedDiv = document.getElementById('advancedFilters');
        const toggle = document.getElementById('advancedToggle').querySelector('button');
        if (advancedDiv.style.display === 'none') {
            advancedDiv.style.display = 'block';
            toggle.textContent = <?php echo json_encode('➖ ' . t('hide_options'), JSON_UNESCAPED_UNICODE); ?>;
        } else {
            advancedDiv.style.display = 'none';
            toggle.textContent = <?php echo json_encode('➕ ' . t('advanced_options'), JSON_UNESCAPED_UNICODE); ?>;
        }
    }
    </script>
</head>
<body style="font-family: <?php echo htmlspecialchars($lang_font); ?>;">

<?php render_navbar('home', false); ?>

<!-- theme toggle handled by navbar.php -->

<!-- محرك البحث الهرمي المتدرج -->
<div class="container" style="margin-top:18px;">
    <div class="search-container">
        <form class="search-grid" id="searchForm">
            <!-- صف البحث الرئيسي -->
            <input type="text" id="q" name="q" placeholder="🔍 <?php echo htmlspecialchars(t('search_placeholder')); ?>" value="<?php echo htmlspecialchars($q); ?>" style="grid-column: span 4;">
            
            <!-- الهرمية: منطقة → محافظة → مكتب -->
            <select id="region_id" name="region_id" onchange="updateHierarchy('region', this.value)" style="font-weight: bold;">
                <option value="">🗺️ <?php echo htmlspecialchars(t('region')); ?></option>
                <?php
                $regs = $conn->query("SELECT DISTINCT Region_Name, Region_ID FROM Regions GROUP BY Region_Name ORDER BY Region_Name ASC");
                while($r = $regs->fetch_assoc()) {
                    $sel = ($rid == $r['Region_ID']) ? 'selected' : '';
                    echo "<option value='".$r['Region_ID']."' $sel>".htmlspecialchars(translate_db_text($r['Region_Name'], $current_lang))."</option>";
                }
                ?>
            </select>

            <select id="gov_id" name="gov_id" onchange="updateHierarchy('gov', this.value)" style="font-weight: bold;">
                <option value="">📍 <?php echo htmlspecialchars(t('governorate')); ?></option>
            </select>

            <select id="office_id" name="office_id" style="font-weight: bold;">
                <option value="">🏢 <?php echo htmlspecialchars(t('education_office')); ?></option>
            </select>

            <!-- CSRF Token -->
            <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
            <!-- current language for AJAX -->
            <input type="hidden" id="jazan_lang" name="jazan_lang" value="<?php echo htmlspecialchars(current_lang()); ?>">

            <button type="submit" class="btn-search" style="padding: 15px 30px; font-weight: bold;"><?php echo htmlspecialchars(t('quick_search')); ?></button>

            <!-- الخيارات الإضافية (مخفية في البداية) -->
            <div id="advancedToggle" style="grid-column: span 8; text-align: center; margin-top: 15px;">
                <button type="button" onclick="toggleAdvanced()" style="background: none; border: none; color: #003366; cursor: pointer; font-size: 0.95rem; text-decoration: underline;">
                    ➕ <?php echo htmlspecialchars(t('advanced_options')); ?>
                </button>
            </div>

            <div id="advancedFilters" style="display: none; grid-column: span 8;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                    <select id="level" name="level">
                        <option value="">📚 <?php echo htmlspecialchars(t('all_levels')); ?></option>
                        <option value="روضة" <?php echo ($lvl == 'روضة') ? 'selected' : ''; ?>>🧸 <?php echo htmlspecialchars(t('kindergarten')); ?></option>
                        <option value="ابتدائي" <?php echo ($lvl == 'ابتدائي') ? 'selected' : ''; ?>>📖 <?php echo htmlspecialchars(t('elementary')); ?></option>
                        <option value="متوسط" <?php echo ($lvl == 'متوسط') ? 'selected' : ''; ?>>📔 <?php echo htmlspecialchars(t('middle')); ?></option>
                        <option value="ثانوي" <?php echo ($lvl == 'ثانوي') ? 'selected' : ''; ?>>🎓 <?php echo htmlspecialchars(t('high_school')); ?></option>
                        <option value="مجمع" <?php echo ($lvl == 'مجمع') ? 'selected' : ''; ?>>🏫 <?php echo htmlspecialchars(t('complex')); ?></option>
                    </select>

                    <select id="school_type" name="school_type">
                        <option value="">🏛️ <?php echo htmlspecialchars(t('school_type')); ?></option>
                        <option value="حكومي" <?php echo ($type == 'حكومي') ? 'selected' : ''; ?>>🏛️ <?php echo htmlspecialchars(t('public')); ?></option>
                        <option value="أهلي" <?php echo ($type == 'أهلي') ? 'selected' : ''; ?>>💎 <?php echo htmlspecialchars(t('private')); ?></option>
                    </select>

                    <select id="gender" name="gender">
                        <option value="">👥 <?php echo htmlspecialchars(t('gender')); ?></option>
                        <option value="بنين" <?php echo ($gen == 'بنين') ? 'selected' : ''; ?>>👦 <?php echo htmlspecialchars(t('boys')); ?></option>
                        <option value="بنات" <?php echo ($gen == 'بنات') ? 'selected' : ''; ?>>👧 <?php echo htmlspecialchars(t('girls')); ?></option>
                    </select>
                </div>
            </div>
        </form>
    </div>
</div>

<header>
    <div class="container">
        <h1 class="logo-title"><?php echo htmlspecialchars(t('site_title')); ?></h1>
        <p class="sub-title"><?php echo htmlspecialchars(t('site_subtitle')); ?></p>
    </div>
</header>

    <div class="container" style="margin-top:18px;">
        <div class="stats-grid">
            <div class="stat-box">
                <h2 id="total-schools"><?php echo $count_schools; ?></h2>
                <p><?php echo htmlspecialchars(t('total_schools')); ?></p>
            </div>
            <div class="stat-box">
                <h2 id="total-offices"><?php echo $count_offices; ?></h2>
                <p><?php echo htmlspecialchars(t('total_offices')); ?></p>
            </div>
            <div class="stat-box">
                <h2 id="total-reviews"><?php echo $count_reviews; ?></h2>
                <p><?php echo htmlspecialchars(t('total_reviews')); ?></p>
            </div>
        </div>
    </div>

<main class="container">

    <!-- تم نقل صندوق البحث أعلى الصفحة -->

    <!-- فلاتر المراحل السريعة -->
    <div class="levels-bar">
        <a href="javascript:void(0);" onclick="setLevelFilter('روضة')" class="level-pill" id="level-pill-روضة">🧸 <?php echo htmlspecialchars(t('level_prefix_kindergarten')); ?>: <span id="kinder-count"><?php echo $kinder_n; ?></span></a>
        <a href="javascript:void(0);" onclick="setLevelFilter('ابتدائي')" class="level-pill" id="level-pill-ابتدائي">📚 <?php echo htmlspecialchars(t('level_prefix_elementary')); ?>: <span id="elementary-count"><?php echo $elem_n; ?></span></a>
        <a href="javascript:void(0);" onclick="setLevelFilter('متوسط')" class="level-pill" id="level-pill-متوسط">📖 <?php echo htmlspecialchars(t('level_prefix_middle')); ?>: <span id="middle-count"><?php echo $mid_n; ?></span></a>
        <a href="javascript:void(0);" onclick="setLevelFilter('ثانوي')" class="level-pill" id="level-pill-ثانوي">🎓 <?php echo htmlspecialchars(t('level_prefix_high')); ?>: <span id="high-count"><?php echo $high_n; ?></span></a>
        <a href="javascript:void(0);" onclick="setLevelFilter('مجمع')" class="level-pill" id="level-pill-مجمع">🏫 <?php echo htmlspecialchars(t('level_prefix_complex')); ?>: <span id="complex-count"><?php echo $comp_n; ?></span></a>
    </div>

    <!-- عرض نتائج المدارس -->
    <div class="results-wrapper" id="results">
        <?php
            // Build prepared statement for schools with pagination
            $baseSql = "FROM Schools 
                    LEFT JOIN Offices ON Schools.Office_ID = Offices.Office_ID 
                    LEFT JOIN Governorates ON Offices.Gov_ID = Governorates.Gov_ID WHERE 1=1";

            $params = [];
            $types = '';

            if ($q) {
                $baseSql .= " AND (School_Name LIKE ? OR City LIKE ?)";
                $like = "%$q%";
                $params[] = $like; $params[] = $like; $types .= 'ss';
            }
            if ($rid) { $baseSql .= " AND Governorates.Region_ID = ?"; $params[] = $rid; $types .= 'i'; }
            if ($gid) { $baseSql .= " AND Offices.Gov_ID = ?"; $params[] = $gid; $types .= 'i'; }
            if ($oid) { $baseSql .= " AND Schools.Office_ID = ?"; $params[] = $oid; $types .= 'i'; }
            if ($lvl) { $baseSql .= " AND Education_Level = ?"; $params[] = $lvl; $types .= 's'; }
            if ($type) { $baseSql .= " AND School_Type = ?"; $params[] = $type; $types .= 's'; }
            if ($gen) { $baseSql .= " AND Gender = ?"; $params[] = $gen; $types .= 's'; }

            // Count total
            $countSql = "SELECT COUNT(*) as total " . $baseSql;
            $total = db_fetch_count($conn, $countSql, $types, $params);

            $perPage = defined('SCHOOLS_PER_PAGE') ? SCHOOLS_PER_PAGE : 10;
            $page = max(1, intval($_GET['page'] ?? 1));
            $offset = ($page - 1) * $perPage;

            // Select paginated rows
            $selectSql = "SELECT Schools.*, Offices.Office_Name, Governorates.Gov_Name " . $baseSql . " ORDER BY Schools.School_Name ASC LIMIT ? OFFSET ?";
            $selectStmt = $conn->prepare($selectSql);
            if ($selectStmt) {
                // bind params + two ints for limit/offset
                $bindParams = array_merge($params, [$perPage, $offset]);
                if ($types === '') {
                    $selectStmt->bind_param('ii', $perPage, $offset);
                } else {
                    $selectStmt->bind_param($types . 'ii', ...$bindParams);
                }
                $selectStmt->execute();
                $res = $selectStmt->get_result();
            } else {
                // prepare failed — surface an inline error (helps debugging) and attempt a safe fallback
                error_log('index.php: prepare failed for selectSql: ' . $conn->error);
                echo "<div class='alert alert-danger'>خطأ في تحميل قائمة المدارس (تحقق من سجلات الخادم).</div>";
                $res = null;
            }

            $schools = [];
            while($row = $res->fetch_assoc()) {
                $schools[] = $row;
            }

            // Batch fetch reviews for displayed schools to avoid N+1 queries
            $schoolIds = array_column($schools, 'School_ID');
            $reviewsBySchool = [];
            if (!empty($schoolIds)) {
                $placeholders = implode(',', array_fill(0, count($schoolIds), '?'));
                $revSql = "SELECT Review_ID, School_ID, Visitor_Name, Comment, Review_Date FROM School_Reviews WHERE School_ID IN ($placeholders) ORDER BY Review_Date DESC";
                $revStmt = $conn->prepare($revSql);
                // bind ints dynamically
                $revTypes = str_repeat('i', count($schoolIds));
                $revStmt->bind_param($revTypes, ...$schoolIds);
                $revStmt->execute();
                $revRes = $revStmt->get_result();
                while ($r = $revRes->fetch_assoc()) {
                    $reviewsBySchool[$r['School_ID']][] = $r;
                }
                $revStmt->close();
            }

            if (count($schools) > 0) {
                foreach ($schools as $row) {
                    $sid = $row['School_ID'];
                $city_raw = $row['City'] ?? '';
                $gov_raw  = $row['Gov_Name'] ?? 'المملكة';
                $city_val = htmlspecialchars(translate_db_text($city_raw, $current_lang));
                $gov_val  = htmlspecialchars(translate_db_text(get_localized_field($conn, 'Governorates', 'Gov_ID', intval($row['Gov_ID'] ?? 0), 'Gov_Name', $gov_raw), $current_lang));
                $full_loc = (trim($city_val) == trim($gov_val)) ? $gov_val : $city_val . "، " . $gov_val;
                $t_color = ($row['School_Type'] == 'حكومي') ? '#0369a1' : '#16a34a';
        ?>
        <div class="school-card">
            <div class="card-header">
                <div>
                    <h3 style="margin:0 0 15px 0; color:var(--primary); font-size:1.7rem;">
                        <?php echo htmlspecialchars(get_localized_field($conn, 'Schools', 'School_ID', intval($row['School_ID']), 'School_Name', translate_db_text($row['School_Name'], $current_lang))); ?>
                    </h3>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <span class="badge" style="background:#f0f9ff; color:<?php echo $t_color; ?>; border:1px solid <?php echo $t_color; ?>;">🏛️ <?php echo htmlspecialchars(translate_term('school_type', $row['School_Type'])); ?></span>
                        <span class="badge" style="background:#e0f2fe; color:#0369a1;">🎓 <?php echo htmlspecialchars(translate_term('education_level', $row['Education_Level'])); ?></span>
                        <span class="badge" style="background:#fff7ed; color:#c2410c; border:1px solid #ffedd5;">📍 <?php echo $full_loc; ?></span>
                    </div>
                    <p style="margin-top:15px; font-size:1rem; color:#64748b;">
                        🏢 <?php echo htmlspecialchars(t('office_label')); ?>: <b><?php echo htmlspecialchars(translate_db_text(get_localized_field($conn, 'Offices', 'Office_ID', intval($row['Office_ID'] ?? 0), 'Office_Name', $row['Office_Name'] ?? ''), $current_lang)); ?></b> | 
                        🏅 <?php echo htmlspecialchars(t('rating_label')); ?>: <span style="color:#fbbf24; font-weight:bold; font-size:1.1rem;"><?php echo htmlspecialchars(translate_db_text($row['Ministerial_Rating'] ?? '', $current_lang)); ?> ⭐</span>
                    </p>
                </div>
                <?php
                $has_coordinates = !empty($row['Latitude']) && !empty($row['Longitude']);
                if ($has_coordinates) {
                    echo '<a href="map_integration.php?view=school&school=' . intval($sid) . '" class="btn-map">' . htmlspecialchars(t('school_location')) . '</a>';
                } else {
                    $website = trim($row['School_Website'] ?? '');
                    $website = filter_var($website, FILTER_SANITIZE_URL);
                    if (!empty($website)) {
                        echo '<a href="' . htmlspecialchars($website) . '" target="_blank" rel="noopener noreferrer" class="btn-map">' . htmlspecialchars(t('website_link')) . '</a>';
                    } else {
                        echo '<span class="btn-map" style="opacity:0.6; cursor:default;">' . htmlspecialchars(t('no_link')) . '</span>';
                    }
                }
                ?>
            </div>

            <!-- قسم الصور -->
            <div class="school-media" style="padding: 20px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                <h4 style="margin: 0 0 15px 0; color: #1e293b; font-size: 1.1rem;">📸 <?php echo htmlspecialchars(t('school_images')); ?></h4>
                
                <!-- عرض الصورة الحالية -->
                <?php
                $schoolImageRaw = trim((string) ($row['School_Image'] ?? ''));
                $imageParts = preg_split('/[,;|\n\r]+/u', $schoolImageRaw) ?: [];
                $imageItems = [];
                foreach ($imageParts as $imgPart) {
                    $imgName = trim((string) $imgPart);
                    if ($imgName === '') {
                        continue;
                    }
                    if (preg_match('/^https?:\/\//i', $imgName)) {
                        $imageItems[] = ['name' => $imgName, 'url' => $imgName];
                    } else {
                        $cleanName = basename($imgName);
                        $imageItems[] = ['name' => $cleanName, 'url' => 'uploads/schools/' . $cleanName];
                    }
                }
                $seenNames = [];
                $dedupedItems = [];
                foreach ($imageItems as $item) {
                    $key = (string) $item['name'];
                    if ($key === '' || isset($seenNames[$key])) {
                        continue;
                    }
                    $seenNames[$key] = true;
                    $dedupedItems[] = $item;
                }
                if (!empty($dedupedItems)) {
                    $visibleImages = array_slice($dedupedItems, 0, 4);
                    $hiddenCount = max(0, count($dedupedItems) - count($visibleImages));
                    $jsonImages = htmlspecialchars(json_encode(array_column($dedupedItems, 'url'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="school-gallery" data-images="<?php echo $jsonImages; ?>" data-school="<?php echo htmlspecialchars(get_localized_field($conn, 'Schools', 'School_ID', intval($row['School_ID']), 'School_Name', translate_db_text($row['School_Name'], $current_lang))); ?>" data-school-id="<?php echo intval($sid); ?>" data-csrf-token="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                        <?php foreach ($visibleImages as $imgIndex => $imgItem): ?>
                            <div class="school-thumb-item">
                                <button type="button" class="school-thumb-btn" onclick="openSchoolGallery(this.closest('.school-gallery'), <?php echo intval($imgIndex); ?>)">
                                    <img src="<?php echo htmlspecialchars($imgItem['url']); ?>" alt="<?php echo htmlspecialchars(get_localized_field($conn, 'Schools', 'School_ID', intval($row['School_ID']), 'School_Name', $row['School_Name'])); ?>" class="school-photo-thumb" loading="lazy">
                                </button>
                                <?php if(isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'principal'], true)): ?>
                                    <button type="button" class="school-thumb-delete" title="<?php echo htmlspecialchars($current_lang === 'en' ? 'Delete image' : 'حذف الصورة'); ?>" onclick="deleteSchoolImage(event, <?php echo intval($sid); ?>, '<?php echo htmlspecialchars($imgItem['name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>')">×</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($hiddenCount > 0): ?>
                            <button type="button" class="school-thumb-more" onclick="openSchoolGallery(this.closest('.school-gallery'), 4)">+<?php echo intval($hiddenCount); ?></button>
                        <?php endif; ?>
                    </div>
                    <?php if(isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'principal'], true)): ?>
                        <div class="school-gallery-actions">
                            <button type="button" class="school-delete-all-btn" onclick="deleteAllSchoolImages(<?php echo intval($sid); ?>, '<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>')"><?php echo htmlspecialchars($current_lang === 'en' ? 'Delete all images' : 'حذف كل الصور'); ?></button>
                        </div>
                    <?php endif; ?>
                    <?php
                } else {
                    echo '<div class="school-photo-empty">📷 ' . htmlspecialchars(t('no_current_image')) . '</div>';
                }
                ?>
                
                <!-- نموذج الرفع (للمسؤولين والمديرين فقط) -->
                <?php if(isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'principal'], true)): ?>
                    <form onsubmit="return uploadSchoolImage(event, this)" class="school-image-form">
                        <input type="hidden" name="school_id" value="<?php echo intval($sid); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                        
                        <label for="image_<?php echo intval($sid); ?>"><?php echo htmlspecialchars(t('choose_image')); ?></label>
                        <input type="file" id="image_<?php echo intval($sid); ?>" class="auto-image-upload" name="image[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple data-school-id="<?php echo intval($sid); ?>" data-csrf="<?php echo getCsrfToken(); ?>">
                        <button type="submit"><?php echo htmlspecialchars(t('upload_image')); ?></button>
                        <div class="school-upload-icons">
                            <span class="upload-icon" title="<?php echo htmlspecialchars(t('max_image_size')); ?>" role="img" aria-label="<?php echo htmlspecialchars(t('max_image_size')); ?>">
                                <!-- file size SVG -->
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M14 2H6C4.895 2 4 2.895 4 4V20C4 21.105 4.895 22 6 22H18C19.105 22 20 21.105 20 20V8L14 2Z" stroke="#64748b" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 2V8H20" stroke="#64748b" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 12H16" stroke="#64748b" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 16H14" stroke="#64748b" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <span class="sr-only"><?php echo htmlspecialchars(t('max_image_size')); ?></span>
                            </span>
                            <span class="upload-icon" title="<?php echo htmlspecialchars(t('multiple_images')); ?>" role="img" aria-label="<?php echo htmlspecialchars(t('multiple_images')); ?>">
                                <!-- multiple files SVG -->
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M21 15V6a2 2 0 0 0-2-2H8l-2 2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h11" stroke="#64748b" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><rect x="7" y="12" width="11" height="8" rx="2" stroke="#64748b" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <span class="sr-only"><?php echo htmlspecialchars(t('multiple_images')); ?></span>
                            </span>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- صدى الميدان -->
            <div class="reviews-section">
                <h4 style="margin:0 0 20px 0; color:#1e293b; font-size:1.2rem; display:flex; align-items:center; gap:10px;"><?php echo htmlspecialchars(t('feedback_title')); ?></h4>
                <div class="reviews-list">
                    <?php
                    $revs = $reviewsBySchool[$sid] ?? [];
                    foreach ($revs as $rv): ?>
                        <div class="review-item">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <b style="color:var(--primary); font-size:1.1rem;">@<?php echo htmlspecialchars($rv['Visitor_Name']); ?></b>
                                <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                                    <form method="POST" action="delete_review.php" style="display:inline;">
                                        <input type="hidden" name="id" value="<?php echo $rv['Review_ID']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                                        <button type="submit" onclick="return confirm('<?php echo htmlspecialchars(t('delete_comment')); ?>')" style="background:none;border:1px solid #ef4444;color:#ef4444;padding:3px 10px;border-radius:8px;cursor:pointer;">🗑️ حذف</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                                    <p style="margin:0; font-size:1.05rem; color:#475569; line-height:1.7;">
                                        <?php echo htmlspecialchars(get_localized_field($conn, 'School_Reviews', 'Review_ID', intval($rv['Review_ID']), 'Comment', $rv['Comment'])); ?>
                                    </p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if(isset($_SESSION['username'])): ?>
                    <form method="POST" action="add_review.php" class="add-review-form">
                        <input type="hidden" name="school_id" value="<?php echo $sid; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                        <input type="text" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" readonly style="background:#f1f5f9; font-weight:bold; color:var(--primary);">
                        <textarea name="comment_text" placeholder="<?php echo htmlspecialchars(t('write_experience')); ?>" required style="height:55px; resize:none; padding:12px; border-radius:12px; border:1px solid #cbd5e1;"></textarea>
                        <button type="submit" name="submit_comment" class="btn-search" style="padding:0 35px;"><?php echo htmlspecialchars(t('publish_experience')); ?></button>
                    </form>
                <?php else: ?>
                    <p style="text-align:center; padding:15px; background:#fffbeb; color:#92400e; border-radius:15px; font-size:0.95rem; border:1px solid #fef3c7;"><?php echo htmlspecialchars(t('login_to_participate')); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php } ?>
        <?php if (count($schools) === 0) { ?>
            <div style="text-align:center; padding:100px; background:white; border-radius:30px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border:1px solid #edf2f7;"><p style="color:#64748b; font-size:1.4rem;"><?php echo htmlspecialchars(t('no_matching_schools')); ?></p></div>
        <?php } ?>

        <?php } ?>

        <?php
        // Pagination UI
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($totalPages > 1):
            // build base query preserving filters
            $qs = [];
            if ($q !== '') $qs['q'] = $q;
            if ($rid) $qs['region_id'] = $rid;
            if ($gid) $qs['gov_id'] = $gid;
            if ($oid) $qs['office_id'] = $oid;
            if ($lvl !== '') $qs['level'] = $lvl;
            if ($type !== '') $qs['school_type'] = $type;
            if ($gen !== '') $qs['gender'] = $gen;

            echo '<div style="display:flex; gap:8px; justify-content:center; margin:30px 0;">';
            // Previous
            if ($page > 1) {
                $qs['page'] = $page - 1;
                echo '<a class="level-pill" href="?' . htmlspecialchars(http_build_query($qs)) . '">' . htmlspecialchars(t('previous')) . '</a>';
            }

            // Page numbers (limit to nearby pages)
            $start = max(1, $page - 3);
            $end = min($totalPages, $page + 3);
            for ($p = $start; $p <= $end; $p++) {
                $qs['page'] = $p;
                $cls = ($p == $page) ? 'active-pill' : '';
                echo '<a class="level-pill ' . $cls . '" href="?' . htmlspecialchars(http_build_query($qs)) . '">' . $p . '</a>';
            }

            // Next
            if ($page < $totalPages) {
                $qs['page'] = $page + 1;
                echo '<a class="level-pill" href="?' . htmlspecialchars(http_build_query($qs)) . '">' . htmlspecialchars(t('next')) . '</a>';
            }

            echo '</div>';
        endif;
        ?>
    </div>
</main>

<div id="schoolLightbox" class="lightbox-overlay" onclick="handleLightboxBackdrop(event)">
    <div class="lightbox-content">
        <button type="button" class="lightbox-close" onclick="closeSchoolLightbox()">✕</button>
        <button type="button" class="lightbox-nav lightbox-prev" onclick="prevSchoolImage()">❯</button>
        <button type="button" class="lightbox-nav lightbox-next" onclick="nextSchoolImage()">❮</button>
        <div class="lightbox-image-wrap">
            <img id="schoolLightboxImage" class="lightbox-image" src="" alt="صورة المدرسة">
        </div>
        <div id="schoolLightboxCaption" class="lightbox-caption"></div>
    </div>
</div>

    <script>
let currentSchoolGalleryImages = [];
let currentSchoolGalleryIndex = 0;
let currentSchoolGalleryName = '';

function openSchoolGallery(galleryElement, startIndex = 0) {
    if (!galleryElement) return;

    let images = [];
    try {
        images = JSON.parse(galleryElement.dataset.images || '[]');
    } catch (error) {
        images = [];
    }

    if (!Array.isArray(images) || images.length === 0) return;

    currentSchoolGalleryImages = images;
    currentSchoolGalleryName = galleryElement.dataset.school || <?php echo json_encode(t('school_images'), JSON_UNESCAPED_UNICODE); ?>;
    currentSchoolGalleryIndex = Math.max(0, Math.min(Number(startIndex) || 0, images.length - 1));

    const lightbox = document.getElementById('schoolLightbox');
    if (!lightbox) return;
    lightbox.classList.add('open');
    document.body.style.overflow = 'hidden';
    renderSchoolLightboxImage();
}

function renderSchoolLightboxImage() {
    const imageElement = document.getElementById('schoolLightboxImage');
    const captionElement = document.getElementById('schoolLightboxCaption');
    if (!imageElement || !captionElement || currentSchoolGalleryImages.length === 0) return;

    imageElement.src = currentSchoolGalleryImages[currentSchoolGalleryIndex];
    captionElement.textContent = `${currentSchoolGalleryName} • ${currentSchoolGalleryIndex + 1} / ${currentSchoolGalleryImages.length}`;
}

function closeSchoolLightbox() {
    const lightbox = document.getElementById('schoolLightbox');
    if (!lightbox) return;
    lightbox.classList.remove('open');
    document.body.style.overflow = '';
}

function nextSchoolImage() {
    if (currentSchoolGalleryImages.length === 0) return;
    currentSchoolGalleryIndex = (currentSchoolGalleryIndex + 1) % currentSchoolGalleryImages.length;
    renderSchoolLightboxImage();
}

function prevSchoolImage() {
    if (currentSchoolGalleryImages.length === 0) return;
    currentSchoolGalleryIndex = (currentSchoolGalleryIndex - 1 + currentSchoolGalleryImages.length) % currentSchoolGalleryImages.length;
    renderSchoolLightboxImage();
}

function handleLightboxBackdrop(event) {
    if (event.target && event.target.id === 'schoolLightbox') {
        closeSchoolLightbox();
    }
}

document.addEventListener('keydown', function (event) {
    const lightbox = document.getElementById('schoolLightbox');
    if (!lightbox || !lightbox.classList.contains('open')) return;

    if (event.key === 'Escape') closeSchoolLightbox();
    if (event.key === 'ArrowRight') prevSchoolImage();
    if (event.key === 'ArrowLeft') nextSchoolImage();
});

function animateValue(el, start, end, duration = 600) {
    console.log(`⏱️ animateValue - Starting animation: ${start} → ${end}`);
    start = Number(start);
    end = Number(end);
    const range = end - start;
    const startTime = performance.now();
    
    function step(now) {
        const progress = Math.min((now - startTime) / duration, 1);
        const value = Math.floor(start + range * progress);
        el.textContent = value;
        el.style.transform = `scale(${1 + 0.06 * (1 - progress)})`;
        if (progress < 1) requestAnimationFrame(step);
        else {
            el.style.transform = '';
            console.log(`✨ animateValue - Animation completed: final value = ${value}`);
        }
    }
    requestAnimationFrame(step);
}

function fetchStats(payload = {}) {
    const finalPayload = Object.assign({}, payload || {});
    finalPayload.csrf_token = document.getElementById('csrf_token')?.value || '';
    const body = new URLSearchParams(finalPayload);
    console.log('📊 fetchStats - Sending payload:', finalPayload);
    
    fetch('fetch_stats.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: body.toString()
    })
    .then(res => {
        console.log('✅ fetchStats - Response status:', res.status);
        if (!res.ok) throw new Error(`HTTP Error: ${res.status}`);
        return res.json();
    })
    .then(data => {
        console.log('📊 fetchStats - Response data:', data);
        
        if (!data) {
            console.error('❌ fetchStats - No data received');
            return;
        }
        
        // خريطة مطابقة مفاتيح API إلى معرّفات عناصر HTML
        const map = {
            total_schools: 'total-schools',
            total_offices: 'total-offices',
            total_reviews: 'total-reviews',
            kinder: 'kinder-count',
            elementary: 'elementary-count',
            middle: 'middle-count',
            high: 'high-count',
            complex: 'complex-count'
        };
        
        Object.keys(map).forEach(key => {
            const id = map[key];
            const el = document.getElementById(id);
            if (!el || typeof data[key] === 'undefined') {
                console.warn(`⚠️ fetchStats - Missing element or data: ${id} / ${key}`);
                return;
            }
            
            const current = parseInt(el.textContent.replace(/[^0-9-]/g, '')) || 0;
            const target = parseInt(data[key]) || 0;
            if (current === target) {
                console.log(`ℹ️ fetchStats - No change needed: ${id} = ${target}`);
                return;
            }
            
            console.log(`🔄 Updating ${id}: ${current} → ${target}`);
            animateValue(el, current, target, 600);
        });
    })
    .catch(err => {
        console.error('❌ fetchStats error:', err);
    });
}

document.getElementById("searchForm").addEventListener("submit", function(e){
    e.preventDefault();
    console.log('📤 Form submitted manually');
    submitSearch({page: 1});
});

function gatherFilterParams() {
    return {
        q: document.getElementById('q')?.value || '',
        region_id: document.getElementById('region_id')?.value || '',
        gov_id: document.getElementById('gov_id')?.value || '',
        office_id: document.getElementById('office_id')?.value || '',
        school_type: document.getElementById('school_type')?.value || '',
        gender: document.getElementById('gender')?.value || '',
        level: document.getElementById('level')?.value || '',
        jazan_lang: document.getElementById('jazan_lang')?.value || ''
    };
}

function updateSearchResults(payload = {}) {
    // payload expected to include filters and optionally page
    const finalPayload = Object.assign({}, gatherFilterParams(), payload || {});
    finalPayload.page = finalPayload.page || 1;
    finalPayload.csrf_token = document.getElementById('csrf_token')?.value || '';

    console.log('updateSearchResults - payload before fetch:', finalPayload);
    const body = new URLSearchParams(finalPayload);

    fetch("fetch_search.php", {
        method: "POST",
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: body.toString()
    })
    .then(res => res.text())
    .then(html => {
        console.log('updateSearchResults - Response received, updating UI');
        document.getElementById("results").innerHTML = html;
    })
    .catch(err => {
        console.error('updateSearchResults error:', err);
        document.getElementById("results").innerHTML = '<p style="text-align:center; color:red;">❌ <?php echo htmlspecialchars(t('error_title')); ?> في جلب النتائج</p>';
    });
}

function submitSearch(pageParams = {}) {
    console.log('submitSearch called with pageParams:', pageParams);

    const sharedPayload = gatherFilterParams();
    if (pageParams.page) sharedPayload.page = pageParams.page;

    console.log('submitSearch - Shared payload:', sharedPayload);
    updateSearchResults(sharedPayload);
    fetchStats(sharedPayload);
}

document.addEventListener('DOMContentLoaded', function(){
    console.log('=== Page loaded - Initializing filters ===');
    
    // جمع جميع عناصر الفلاتر
    const filterNames = ['office_id', 'level', 'school_type', 'gender'];

    // إضافة listeners لجميع الـ selects
    filterNames.forEach(name => {
        const el = document.getElementById(name);
        if (el) {
            el.addEventListener('change', function(){
                console.log(`🔄 Filter changed: ${name} = "${this.value}"`);

                if (window.filterUpdateTimeout) clearTimeout(window.filterUpdateTimeout);

                window.filterUpdateTimeout = setTimeout(() => {
                    console.log(`⏱️ Executing delayed update for filter: ${name}`);
                    const payload = gatherFilterParams();
                    payload.page = 1;
                    console.log('Delayed update - payload:', payload);
                    updateSearchResults(payload); // إعادة تعيين الصفحة إلى 1
                    fetchStats(payload);
                }, 200);
            });
        }
    });

    // إضافة listener للبحث النصي
    const qInputEl = document.getElementById('q');
    if (qInputEl) {
        qInputEl.addEventListener('input', function(){
            console.log(`🔍 Search input changed: "${this.value}"`);

            if (window.filterUpdateTimeout) clearTimeout(window.filterUpdateTimeout);

            window.filterUpdateTimeout = setTimeout(() => {
                console.log('⏱️ Executing delayed search');
                const payload = gatherFilterParams();
                payload.page = 1;
                console.log('Delayed search - payload:', payload);
                updateSearchResults(payload);
                fetchStats(payload);
            }, 500);
        });
    }
    
    // تحديث أولي للنتائج والعدادات
    console.log('📊 Initial load - fetching results and stats');
    const initialPayload = gatherFilterParams();
    initialPayload.page = 1;
    console.log('Initial payload:', initialPayload);
    updateSearchResults(initialPayload);
    fetchStats(initialPayload);
});

function toggleMapIframe(id) {
    var el = document.getElementById('map_iframe_' + id);
    if (!el) return;
    if (el.style.display === 'none' || el.style.display === '') el.style.display = 'block';
    else el.style.display = 'none';
}

function setLevelFilter(level) {
    console.log(`🎯 setLevelFilter called: "${level}"`);
    
    // ميزة toggle: إذا كانت نفس المرحلة مختارة، ألغ الفلتر
    const levelSel = document.getElementById('level');
    if (levelSel) {
        const currentLevel = levelSel.value;
        if (currentLevel === level) {
            // toggle off: إلغاء الفلتر
            levelSel.value = '';
            console.log(`❌ Cancelled filter for level: "${level}"`);
        } else {
            // toggle on: تطبيق الفلتر
            levelSel.value = level;
            console.log(`✅ Applied filter for level: "${level}"`);
        }
    }

    // تحديث النتائج والعدادات باستخدام نفس payload
    const payload = gatherFilterParams();
    payload.page = 1;
    updateSearchResults(payload);
    fetchStats(payload);
}

function uploadSchoolImage(event, form) {
    event.preventDefault();

    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton ? submitButton.textContent : '';
    const formData = new FormData(form);

    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = <?php echo json_encode($current_lang === 'en' ? 'Uploading...' : 'جارٍ الرفع...'); ?>;
    }

    fetch('image_upload.php?action=upload_image', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(res => res.json())
    .then(data => {
        const count = Number(data.uploaded_count || 0);
        const doneMessage = count > 1 ? <?php echo json_encode($current_lang === 'en' ? 'Uploaded ' : 'تم رفع '); ?> + count + <?php echo json_encode($current_lang === 'en' ? ' images successfully' : ' صور بنجاح'); ?> : (data.message || <?php echo json_encode($current_lang === 'en' ? 'Done' : 'تمت العملية'); ?>);
        alert(doneMessage);
        if (data.success) {
            window.location.reload();
        }
    })
    .catch(() => {
        alert(<?php echo json_encode($current_lang === 'en' ? 'An error occurred while uploading the image' : 'حدث خطأ أثناء رفع الصورة'); ?>);
    })
    .finally(() => {
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
    });

    return false;
}

function deleteSchoolImage(event, schoolId, imageName, csrfToken) {
    event.preventDefault();
    event.stopPropagation();
    if (!confirm(<?php echo json_encode($current_lang === 'en' ? 'Delete this image?' : 'متأكد من حذف هذه الصورة؟'); ?>)) return;

    const formData = new FormData();
    formData.append('school_id', String(schoolId));
    formData.append('image_name', imageName);
    formData.append('csrf_token', csrfToken);

    fetch('image_upload.php?action=delete_image', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message || <?php echo json_encode($current_lang === 'en' ? 'Operation completed' : 'تم تنفيذ العملية'); ?>);
        if (data.success) {
            window.location.reload();
        }
    })
    .catch(() => alert(<?php echo json_encode($current_lang === 'en' ? 'An error occurred while deleting the image' : 'حدث خطأ أثناء حذف الصورة'); ?>));
}

function deleteAllSchoolImages(schoolId, csrfToken) {
    if (!confirm(<?php echo json_encode($current_lang === 'en' ? 'Delete all school images?' : 'متأكد من حذف كل صور المدرسة؟'); ?>)) return;

    const formData = new FormData();
    formData.append('school_id', String(schoolId));
    formData.append('delete_all', '1');
    formData.append('csrf_token', csrfToken);

    fetch('image_upload.php?action=delete_image', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message || <?php echo json_encode($current_lang === 'en' ? 'Operation completed' : 'تم تنفيذ العملية'); ?>);
        if (data.success) {
            window.location.reload();
        }
    })
    .catch(() => alert('حدث خطأ أثناء حذف الصور'));
}

</script>

<?php include 'footer.php'; ?>
</body>

<script>
// Auto-upload images: send only image files (client-side filter) with CSRF and school_id
(() => {
    const allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
    const maxSize = 5 * 1024 * 1024; // 5MB

    function showMessage(msg) {
        try { alert(msg); } catch (e) { console.log(msg); }
    }

    async function uploadImages(files, schoolId, csrf) {
        const fd = new FormData();
        files.forEach(f => fd.append('image[]', f));
        fd.append('school_id', schoolId);
        fd.append('csrf_token', csrf);

        try {
            const res = await fetch('image_upload.php?action=upload_image', { method: 'POST', body: fd });
            const data = await res.json();
            if (data && data.success) {
                showMessage(data.message || 'تم رفع الصور بنجاح');
                // Optionally refresh thumbnails or the page
                // location.reload();
            } else {
                showMessage(data.message || 'فشل رفع الصور');
            }
        } catch (err) {
            console.error(err);
            showMessage('خطأ في رفع الصور');
        }
    }

    function handleFileInput(e) {
        const input = e.currentTarget;
        const allFiles = Array.from(input.files || []);
        const schoolId = input.dataset.schoolId || input.id.split('_').pop();
        const csrf = input.dataset.csrf || (document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '');

        const imageFiles = allFiles.filter(f => allowedTypes.includes(f.type) && f.size > 0 && f.size <= maxSize);
        if (imageFiles.length === 0) {
            showMessage('الرجاء اختيار صور فقط وبحجم لا يتجاوز 5MB لكل صورة');
            input.value = '';
            return;
        }

        uploadImages(imageFiles, schoolId, csrf);
        input.value = '';
    }

    function bindInputs(root=document) {
        root.querySelectorAll('.auto-image-upload').forEach(inp => {
            if (!inp.dataset.bound) {
                inp.addEventListener('change', handleFileInput);
                inp.dataset.bound = '1';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => bindInputs(document));

    // Observe DOM for dynamic inserts (e.g., AJAX-loaded results)
    const mo = new MutationObserver(muts => {
        for (const m of muts) {
            for (const node of Array.from(m.addedNodes || [])) {
                if (node.nodeType === 1) bindInputs(node);
            }
        }
    });
    mo.observe(document.body, { childList: true, subtree: true });
})();
</script>
</html>