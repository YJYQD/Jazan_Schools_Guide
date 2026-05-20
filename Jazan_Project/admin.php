<?php
// Note: previously there was a temporary redirect here to dashboard.php
// removed so the full admin dashboard is accessible again.

require_once "db.php";
require_once "navbar.php";
require_once "security_helpers.php";
require_once "i18n.php";

$conn->set_charset("utf8mb4");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit;
}

// توليد CSRF Token
initiateCsrfToken();

// إعدادات رفع الصور للمدارس
$allowed_image_roles = implode(', ', getAllowedSchoolImageUploadRoles());

// الحصول على الإحصائيات
$regions_count = db_fetch_count($conn, "SELECT COUNT(*) AS total FROM Regions");
$gov_count = db_fetch_count($conn, "SELECT COUNT(*) AS total FROM Governorates");
$offices_count = db_fetch_count($conn, "SELECT COUNT(*) AS total FROM Offices");
$schools_count = db_fetch_count($conn, "SELECT COUNT(*) AS total FROM Schools");
$principals_count = db_fetch_count($conn, "SELECT COUNT(*) AS total FROM Users WHERE Role = 'principal'");
$schools_with_images_count = db_fetch_count($conn, "SELECT COUNT(*) AS total FROM Schools WHERE School_Image IS NOT NULL AND School_Image <> ''");

$message = '';
$message_type = '';

// إضافة مدير مدرسة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_principal'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = t('security_verification_failed');
        $message_type = 'error';
    } else {
        $principal_username = trim($_POST['principal_username'] ?? '');
        $principal_password = $_POST['principal_password'] ?? '';
        $principal_password_confirm = $_POST['principal_password_confirm'] ?? '';

        if ($principal_username === '' || $principal_password === '' || $principal_password_confirm === '') {
            $message = t('fill_all_principal_fields');
            $message_type = 'error';
        } elseif (!isValidUsername($principal_username)) {
            $message = t('invalid_username');
            $message_type = 'error';
        } elseif (strlen($principal_password) < 8) {
            $message = t('password_too_short');
            $message_type = 'error';
        } elseif ($principal_password !== $principal_password_confirm) {
            $message = t('passwords_mismatch');
            $message_type = 'error';
        } else {
            $check_stmt = $conn->prepare("SELECT User_ID FROM Users WHERE Username = ? LIMIT 1");
            if ($check_stmt) {
                $check_stmt->bind_param('s', $principal_username);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                    if ($check_result && $check_result->num_rows > 0) {
                    $message = t('username_exists');
                    $message_type = 'error';
                } else {
                    $hashed_password = hashPassword($principal_password);
                    $role = 'principal';
                    $insert_stmt = $conn->prepare("INSERT INTO Users (Username, Password, Role) VALUES (?, ?, ?)");
                    if ($insert_stmt) {
                        $insert_stmt->bind_param('sss', $principal_username, $hashed_password, $role);
                        if ($insert_stmt->execute()) {
                            $message = t('principal_added_success');
                            $message_type = 'success';
                        } else {
                            $message = t('principal_add_error');
                            $message_type = 'error';
                        }
                        $insert_stmt->close();
                    } else {
                        $message = 'تعذر تحضير استعلام إضافة مدير المدرسة';
                        $message_type = 'error';
                    }
                }

                $check_stmt->close();
                    } else {
                        $message = t('check_username_error');
                        $message_type = 'error';
                    }
        }
    }
}

/* ================== ADD ================== */

// منطقة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_region'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = t('security_verification_failed');
        $message_type = 'error';
    } else {
        $name = trim($_POST['region_name'] ?? '');
        if ($name !== '') {
            $stmt = $conn->prepare("INSERT INTO Regions (Region_Name) VALUES (?)");
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) {
                $message = t('region_added_success');
                $message_type = 'success';
            } else {
                $message = t('region_add_error');
                $message_type = 'error';
            }
        }
    }
}

// محافظة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_gov'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'فشل التحقق الأمني.';
        $message_type = 'error';
    } else {
        $gov_name = trim($_POST['gov_name'] ?? '');
        $region_id = intval($_POST['region_id'] ?? 0);
        if ($gov_name !== '' && $region_id > 0) {
            $stmt = $conn->prepare("INSERT INTO Governorates (Gov_Name, Region_ID) VALUES (?,?)");
            $stmt->bind_param("si", $gov_name, $region_id);
            if ($stmt->execute()) {
                $message = t('governorate_added_success');
                $message_type = 'success';
            } else {
                $message = t('governorate_add_error');
                $message_type = 'error';
            }
        }
    }
}

// مكتب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_office'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'فشل التحقق الأمني.';
        $message_type = 'error';
    } else {
        $office_name = trim($_POST['office_name'] ?? '');
        $gov_id = intval($_POST['gov_id'] ?? 0);
        if ($office_name !== '' && $gov_id > 0) {
            $stmt = $conn->prepare("INSERT INTO Offices (Office_Name, Gov_ID) VALUES (?,?)");
            $stmt->bind_param("si", $office_name, $gov_id);
            if ($stmt->execute()) {
                $message = t('office_added_success');
                $message_type = 'success';
            } else {
                $message = t('office_add_error');
                $message_type = 'error';
            }
        }
    }
}

// مدرسة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_school'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'فشل التحقق الأمني.';
        $message_type = 'error';
    } else {
        $school_id = intval($_POST['school_id'] ?? 0);
        $school_name = trim($_POST['school_name'] ?? '');
        $level = trim($_POST['level'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $office_id = intval($_POST['office_id'] ?? 0);
        $city_text = trim($_POST['city_text'] ?? '');
        $gender = trim($_POST['gender'] ?? '');

        if ($school_id > 0 && $school_name !== '' && $office_id > 0) {
            $stmt = $conn->prepare("
                INSERT INTO Schools 
                (School_ID, School_Name, Education_Level, School_Type, Office_ID, City, Gender)
                VALUES (?,?,?,?,?,?,?)
            ");

            $stmt->bind_param(
                "isssiss",
                $school_id,
                $school_name,
                $level,
                $type,
                $office_id,
                $city_text,
                $gender
            );

            if ($stmt->execute()) {
                $message = t('school_added_success');
                $message_type = 'success';
            } else {
                $message = t('school_add_error');
                $message_type = 'error';
            }
        }
    }
}

/* ================== DELETE (POST + CSRF) ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // must be admin (already checked) and have valid CSRF
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        $message = t('security_verification_failed');
        $message_type = 'error';
    } else {
        if (isset($_POST['del_region'])) {
            $region_id = intval($_POST['del_region']);
            $stmt = $conn->prepare("DELETE FROM Regions WHERE Region_ID=?");
            $stmt->bind_param("i", $region_id);
            if ($stmt->execute()) { $message = t('region_deleted_success'); $message_type = 'success'; }
        }
        if (isset($_POST['del_gov'])) {
            $gov_id = intval($_POST['del_gov']);
            $stmt = $conn->prepare("DELETE FROM Governorates WHERE Gov_ID=?");
            $stmt->bind_param("i", $gov_id);
            if ($stmt->execute()) { $message = t('governorate_deleted_success'); $message_type = 'success'; }
        }
        if (isset($_POST['del_office'])) {
            $office_id = intval($_POST['del_office']);
            $stmt = $conn->prepare("DELETE FROM Offices WHERE Office_ID=?");
            $stmt->bind_param("i", $office_id);
            if ($stmt->execute()) { $message = t('office_deleted_success'); $message_type = 'success'; }
        }
        if (isset($_POST['del_school'])) {
            $school_id = intval($_POST['del_school']);
            $stmt = $conn->prepare("DELETE FROM Schools WHERE School_ID=?");
            $stmt->bind_param("i", $school_id);
            if ($stmt->execute()) { $message = t('school_deleted_success'); $message_type = 'success'; }
        }
    }
}
?>
<?php $page_lang = function_exists('current_lang') ? current_lang() : 'ar'; $page_dir = $page_lang === 'en' ? 'ltr' : 'rtl'; ?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($page_lang); ?>" dir="<?php echo htmlspecialchars($page_dir); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - دليل مدارس المملكة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet" integrity="sha384-+qdLaWm0B1QZb+3m1Q9gqQ5Y5b6Z5Y5G1Z6Z6Z6Z6Z" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&display=swap" rel="stylesheet">
<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }
    
    .stat-box {
        background: white;
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        text-align: center;
        border-top: 4px solid var(--primary);
        transition: all 0.3s ease;
    }
    
    .stat-box:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 51, 102, 0.15);
    }
    
    .stat-box h3 {
        margin: 0;
        font-size: 2.5rem;
        color: var(--primary);
    }
    
    .stat-box p {
        margin: 10px 0 0;
        color: var(--text-light);
    }

    .card {
        background: white;
        padding: 25px;
        border-radius: 15px;
        margin-bottom: 25px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        border-right: 4px solid var(--primary);
    }

    .card h3 {
        margin-top: 0;
        color: var(--primary);
        font-size: 1.3rem;
    }

    form {
        display: grid;
        gap: 12px;
    }

    .form-group {
        display: grid;
        gap: 8px;
    }

    .form-group label {
        font-weight: 600;
        color: var(--text-dark);
    }

    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    table a {
        text-decoration: none;
        margin-left: 5px;
        transition: all 0.3s ease;
    }

    table a:hover {
        opacity: 0.8;
    }

    .alert {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border-left: 4px solid #10b981;
    }

    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border-left: 4px solid #ef4444;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
    }

    .feature-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 12px;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
    }

    .feature-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }

    .feature-card small {
        display: block;
        font-size: 0.85rem;
        opacity: 0.9;
        margin-top: 8px;
    }

    .image-gallery {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-top: 10px;
    }

    .image-card {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
    }

    .image-card img {
        width: 100%;
        height: 160px;
        object-fit: cover;
        display: block;
    }

    .image-card-body {
        padding: 12px 14px;
        display: grid;
        gap: 4px;
    }

    .image-badge {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.75rem;
        background: #e0f2fe;
        color: #075985;
        font-weight: 700;
    }

    @media (max-width: 768px) {
        .card {
            padding: 15px;
            margin-bottom: 15px;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .table-responsive {
            font-size: 0.9rem;
        }

        table {
            min-width: 500px;
        }

        th, td {
            padding: 10px;
        }

        .form-group {
            grid-template-columns: 1fr;
        }

        .features-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .features-grid {
            grid-template-columns: 1fr;
        }

        .stat-box h3 {
            font-size: 2rem;
        }
    }
</style>
</head>

<body>

    <?php render_navbar('admin', true, 'index.php', '⬅️ ' . t('back_to_home')); ?>

    <div class="container py-4" style="padding-top: 20px;">
        <!-- الرسائل -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= $message_type == 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <h2 style="color: var(--primary); text-align: center; margin-bottom: 30px;"><?php echo htmlspecialchars('⚙️ ' . t('admin_panel')); ?></h2>

        <!-- الإحصائيات -->
        <div class="stats-grid">
            <div class="stat-box">
                <h3>📍</h3>
                <h3><?= $regions_count ?></h3>
                <p><?php echo htmlspecialchars(t('regions')); ?></p>
            </div>
            <div class="stat-box">
                <h3>🏙️</h3>
                <h3><?= $gov_count ?></h3>
                <p><?php echo htmlspecialchars(t('governorates_plural')); ?></p>
            </div>
            <div class="stat-box">
                <h3>🏛️</h3>
                <h3><?= $offices_count ?></h3>
                <p><?php echo htmlspecialchars(t('offices_plural')); ?></p>
            </div>
            <div class="stat-box">
                <h3>🏫</h3>
                <h3><?= $schools_count ?></h3>
                <p><?php echo htmlspecialchars(t('schools_plural')); ?></p>
            </div>
            <div class="stat-box">
                <h3>👨‍🏫</h3>
                <h3><?= $principals_count ?></h3>
                <p><?php echo htmlspecialchars(t('school_principals')); ?></p>
            </div>
            <div class="stat-box">
                <h3>🖼️</h3>
                <h3><?= $schools_with_images_count ?></h3>
                <p><?php echo htmlspecialchars(t('school_images')); ?></p>
            </div>
        </div>

        <!-- نماذج الإضافة -->
        <div class="card">
            <h3><?php echo htmlspecialchars(t('add_region')); ?></h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <div class="form-group">
                    <label><?php echo htmlspecialchars(t('name')); ?></label>
                    <input type="text" name="region_name" placeholder="<?php echo htmlspecialchars(t('enter_region_name')); ?>" required>
                </div>
                <button type="submit" name="add_region" class="btn btn-primary"><?php echo htmlspecialchars(t('add_region')); ?></button>
            </form>
        </div>

        <div class="card">
            <h3><?php echo htmlspecialchars(t('add_governorate')); ?></h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <div class="form-group">
                    <label><?php echo htmlspecialchars(t('region')); ?></label>
                    <select name="region_id" required>
                        <?php
                        $r = $conn->query("SELECT * FROM Regions ORDER BY Region_Name");
                        while ($row = $r->fetch_assoc()) {
                            $label = (function_exists('translate_db_text') ? translate_db_text($row['Region_Name'], function_exists('current_lang')? current_lang() : 'ar') : $row['Region_Name']);
                            echo "<option value='" . intval($row['Region_ID']) . "'>" . htmlspecialchars($label) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?php echo htmlspecialchars(t('name')); ?></label>
                    <input type="text" name="gov_name" placeholder="<?php echo htmlspecialchars(t('enter_gov_name')); ?>" required>
                </div>
                <button type="submit" name="add_gov" class="btn btn-primary"><?php echo htmlspecialchars(t('add_governorate')); ?></button>
            </form>
        </div>

        <div class="card">
            <h3><?php echo htmlspecialchars(t('add_office')); ?></h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <div class="form-group">
                    <label><?php echo htmlspecialchars(t('governorate')); ?></label>
                    <select name="gov_id" required>
                        <?php
                        $g = $conn->query("SELECT * FROM Governorates ORDER BY Gov_Name");
                        while ($row = $g->fetch_assoc()) {
                            $label = (function_exists('translate_db_text') ? translate_db_text($row['Gov_Name'], function_exists('current_lang')? current_lang() : 'ar') : $row['Gov_Name']);
                            echo "<option value='" . intval($row['Gov_ID']) . "'>" . htmlspecialchars($label) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?php echo htmlspecialchars(t('name')); ?></label>
                    <input type="text" name="office_name" placeholder="<?php echo htmlspecialchars(t('enter_office_name')); ?>" required>
                </div>
                <button type="submit" name="add_office" class="btn btn-primary"><?php echo htmlspecialchars(t('add_office')); ?></button>
            </form>
        </div>

        <div class="card">
            <h3><?php echo htmlspecialchars(t('add_school')); ?></h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <div class="form-group">
                    <label><?php echo htmlspecialchars(t('name')); ?> / ID</label>
                    <input type="number" name="school_id" placeholder="<?php echo htmlspecialchars(t('enter_region_name')); ?>" required>
                </div>
                <div class="form-group">
                    <label><?php echo htmlspecialchars(t('name')); ?></label>
                    <input type="text" name="school_name" placeholder="<?php echo htmlspecialchars(t('enter_region_name')); ?>" required>
                </div>
                <div class="form-group">
                    <label>المدينة</label>
                    <input type="text" name="city_text" placeholder="أدخل اسم المدينة" required>
                </div>
                <div class="form-group">
                    <label>المكتب</label>
                    <select name="office_id" required>
                        <?php
                        $o = $conn->query("SELECT o.Office_ID, o.Office_Name, g.Gov_Name FROM Offices o LEFT JOIN Governorates g ON o.Gov_ID = g.Gov_ID ORDER BY g.Gov_Name, o.Office_Name");
                        while ($row = $o->fetch_assoc()) {
                            echo "<option value='" . intval($row['Office_ID']) . "'>" . htmlspecialchars($row['Gov_Name']) . " - " . htmlspecialchars($row['Office_Name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>المرحلة التعليمية</label>
                    <select name="level" required>
                        <option value="ابتدائي">ابتدائي</option>
                        <option value="متوسط">متوسط</option>
                        <option value="ثانوي">ثانوي</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>نوع المدرسة</label>
                    <select name="type" required>
                        <option value="حكومي">حكومي</option>
                        <option value="أهلي">أهلي</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>النوع</label>
                    <select name="gender" required>
                        <option value="بنين">بنين</option>
                        <option value="بنات">بنات</option>
                        <option value="مختلط">مختلط</option>
                    </select>
                </div>
                <button type="submit" name="add_school" class="btn btn-primary"><?php echo htmlspecialchars(t('add_school')); ?></button>
            </form>
        </div>

        <div class="card">
            <h3><?php echo htmlspecialchars(t('add_principal')); ?></h3>
            <p style="margin-top: 0; color: var(--text-light);">سيتم إنشاء الحساب بدور <strong>principal</strong> ليتمكن من رفع صور المدرسة فقط.</p>
                    <form method="POST" style="max-width: 700px;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <div class="form-group">
                    <label><?php echo htmlspecialchars(t('username_placeholder')); ?></label>
                    <input type="text" name="principal_username" placeholder="<?php echo htmlspecialchars(t('username_placeholder')); ?>" required>
                </div>
                <div class="form-group">
                    <label><?php echo htmlspecialchars(t('password_placeholder')); ?></label>
                    <input type="password" name="principal_password" placeholder="<?php echo htmlspecialchars(t('password_placeholder')); ?>" required>
                </div>
                <div class="form-group">
                    <label><?php echo htmlspecialchars(t('confirm_password_placeholder')); ?></label>
                    <input type="password" name="principal_password_confirm" placeholder="<?php echo htmlspecialchars(t('confirm_password_placeholder')); ?>" required>
                </div>
                <button type="submit" name="add_principal" class="btn btn-primary"><?php echo htmlspecialchars(t('add_principal')); ?></button>
            </form>
        </div>

        <!-- عرض البيانات - الجداول -->
        <div class="card">
            <h3>📍 المناطق</h3>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered align-middle">
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars(t('name')); ?></th>
                            <th><?php echo htmlspecialchars(t('actions')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $res = $conn->query("SELECT * FROM Regions ORDER BY Region_Name");
                        if ($res && $res->num_rows > 0) {
                            while ($r = $res->fetch_assoc()) {
                                echo "<tr>
                                    <td>" . htmlspecialchars($r['Region_Name']) . "</td>
                                    <td>
                                        <form method='POST' style='display:inline;'>
                                            <input type='hidden' name='del_region' value='" . intval($r['Region_ID']) . "'>
                                            <input type='hidden' name='csrf_token' value='" . htmlspecialchars(getCsrfToken()) . "'>
                                            <button type='submit' onclick=\"return confirm('" . htmlspecialchars(t('confirm_delete_region')) . "')\" class='btn btn-sm btn-danger' style='display: inline-block; width: auto;'>❌ " . htmlspecialchars(t('delete')) . "</button>
                                        </form>
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='2' style='text-align: center; color: #999;'>لا توجد مناطق</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h3>🏙️ المحافظات</h3>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered align-middle">
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars(t('name')); ?></th>
                            <th><?php echo htmlspecialchars(t('region')); ?></th>
                            <th><?php echo htmlspecialchars(t('actions')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $res = $conn->query("
                            SELECT g.*, r.Region_Name 
                            FROM Governorates g
                            LEFT JOIN Regions r ON r.Region_ID = g.Region_ID
                            ORDER BY r.Region_Name, g.Gov_Name
                        ");
                        if ($res && $res->num_rows > 0) {
                            while ($g = $res->fetch_assoc()) {
                                echo "<tr>
                                    <td>" . htmlspecialchars($g['Gov_Name']) . "</td>
                                    <td>" . htmlspecialchars($g['Region_Name']) . "</td>
                                    <td>
                                        <form method='POST' style='display:inline;'>
                                            <input type='hidden' name='del_gov' value='" . intval($g['Gov_ID']) . "'>
                                            <input type='hidden' name='csrf_token' value='" . htmlspecialchars(getCsrfToken()) . "'>
                                            <button type='submit' onclick=\"return confirm('" . htmlspecialchars(t('confirm_delete_gov')) . "')\" class='btn btn-sm btn-danger' style='display: inline-block; width: auto;'>❌ " . htmlspecialchars(t('delete')) . "</button>
                                        </form>
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='3' style='text-align: center; color: #999;'>لا توجد محافظات</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h3>🏛️ المكاتب</h3>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered align-middle">
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars(t('name')); ?></th>
                            <th><?php echo htmlspecialchars(t('governorate')); ?></th>
                            <th><?php echo htmlspecialchars(t('actions')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $res = $conn->query("
                            SELECT o.*, g.Gov_Name 
                            FROM Offices o
                            LEFT JOIN Governorates g ON g.Gov_ID = o.Gov_ID
                            ORDER BY g.Gov_Name, o.Office_Name
                        ");
                        if ($res && $res->num_rows > 0) {
                            while ($o = $res->fetch_assoc()) {
                                echo "<tr>
                                    <td>" . htmlspecialchars($o['Office_Name']) . "</td>
                                    <td>" . htmlspecialchars($o['Gov_Name']) . "</td>
                                    <td>
                                        <form method='POST' style='display:inline;'>
                                            <input type='hidden' name='del_office' value='" . intval($o['Office_ID']) . "'>
                                            <input type='hidden' name='csrf_token' value='" . htmlspecialchars(getCsrfToken()) . "'>
                                            <button type='submit' onclick=\"return confirm('" . htmlspecialchars(t('confirm_delete_office')) . "')\" class='btn btn-sm btn-danger' style='display: inline-block; width: auto;'>❌ " . htmlspecialchars(t('delete')) . "</button>
                                        </form>
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='3' style='text-align: center; color: #999;'>لا توجد مكاتب</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h3>🏫 المدارس</h3>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>المكتب</th>
                            <th class="hide-mobile">المرحلة</th>
                            <th class="hide-mobile">النوع</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $res = $conn->query("
                            SELECT s.*, o.Office_Name 
                            FROM Schools s
                            LEFT JOIN Offices o ON o.Office_ID = s.Office_ID
                            ORDER BY s.School_Name
                        ");
                        if ($res && $res->num_rows > 0) {
                            while ($s = $res->fetch_assoc()) {
                                $displayName = $s['School_Name'];
                                if (function_exists('get_localized_field')) {
                                    $displayName = get_localized_field($conn, 'Schools', 'School_ID', intval($s['School_ID']), 'School_Name', (function_exists('translate_db_text') ? translate_db_text($s['School_Name'], function_exists('current_lang')? current_lang() : 'ar') : $s['School_Name']));
                                } else if (function_exists('translate_db_text')) {
                                    $displayName = translate_db_text($s['School_Name'], function_exists('current_lang')? current_lang() : 'ar');
                                }

                                $officeName = (function_exists('translate_db_text') ? translate_db_text($s['Office_Name'], function_exists('current_lang')? current_lang() : 'ar') : $s['Office_Name']);
                                $level = (function_exists('translate_term') ? translate_term('education_level', $s['Education_Level']) : $s['Education_Level']);
                                $type = (function_exists('translate_term') ? translate_term('school_type', $s['School_Type']) : $s['School_Type']);

                                echo "<tr>
                                    <td><strong>" . htmlspecialchars($displayName) . "</strong></td>
                                    <td>" . htmlspecialchars($officeName) . "</td>
                                    <td class='hide-mobile'>" . htmlspecialchars($level) . "</td>
                                    <td class='hide-mobile'>" . htmlspecialchars($type) . "</td>
                                    <td>
                                        <a href='edit_school.php?id=" . intval($s['School_ID']) . "' class='btn btn-sm btn-info' style='display: inline-block; width: auto; margin-left: 5px;'>✏️ " . htmlspecialchars(t('edit')) . "</a>
                                        <form method='POST' style='display:inline;'>
                                            <input type='hidden' name='del_school' value='" . intval($s['School_ID']) . "'>
                                            <input type='hidden' name='csrf_token' value='" . htmlspecialchars(getCsrfToken()) . "'>
                                            <button type='submit' onclick=\"return confirm('" . htmlspecialchars(t('confirm_delete_school')) . "')\" class='btn btn-sm btn-danger' style='display: inline-block; width: auto;'>❌ " . htmlspecialchars(t('delete')) . "</button>
                                        </form>
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' style='text-align: center; color: #999;'>" . htmlspecialchars(t('no_schools_found') ?? 'لا توجد مدارس') . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- الميزات المتقدمة -->
        <div class="card">
            <h3>📊 الميزات المتقدمة</h3>
            <div class="features-grid">
                <a href="stats_dashboard.php" class="feature-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    📈 لوحة الإحصائيات
                    <small>رسوم بيانية ومؤشرات متقدمة</small>
                </a>
                <a href="audit_trail.php" class="feature-card" style="background: linear-gradient(135deg, #764ba2 0%, #f093fb 100%);">
                    📋 سجل العمليات
                    <small>تتبع جميع التغييرات</small>
                </a>
                <a href="admin_import.php" class="feature-card" style="background: linear-gradient(135deg, #ff9800 0%, #ff5722 100%);">
                    📥 استيراد CSV
                    <small>رفع بيانات المدارس دفعة واحدة</small>
                </a>
                <a href="backup_system.php" class="feature-card" style="background: linear-gradient(135deg, #00bcd4 0%, #0097a7 100%);">
                    💾 النسخ الاحتياطية
                    <small>حفظ البيانات بأمان</small>
                </a>
                <a href="map_integration.php" class="feature-card" style="background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%);">
                    🗺️ خريطة المدارس
                    <small>عرض المواقع على الخريطة</small>
                </a>
            </div>
        </div>

        <!-- إعدادات الصور -->
        <div class="card">
            <h3>🖼️ إعدادات رفع صور المدارس</h3>
            <p style="margin-top: 0; color: var(--text-light);">
                الرفع مقصور على <strong>مدراء المدارس</strong> فقط، ولا يمكن لغيرهم إضافة الصور.
            </p>
            <div style="display: inline-block; background: #eef6ff; color: #0b4f8a; padding: 10px 14px; border-radius: 999px; font-weight: 700;">
                الدور المسموح: <?= htmlspecialchars($allowed_image_roles) ?>
            </div>
        </div>

        <!-- صور المدارس -->
        <div class="card">
            <h3>🖼️ صور المدارس</h3>
            <p style="margin-top: 0; color: var(--text-light);">جميع الصور المرفوعة تظهر هنا داخل لوحة التحكم.</p>
            <div class="image-gallery">
                <?php
                $images_result = $conn->query("SELECT School_ID, School_Name, School_Image, School_Logo FROM Schools WHERE (School_Image IS NOT NULL AND School_Image <> '') OR (School_Logo IS NOT NULL AND School_Logo <> '') ORDER BY School_Name");
                if ($images_result && $images_result->num_rows > 0) {
                    while ($image_row = $images_result->fetch_assoc()) {
                        $schoolId = intval($image_row['School_ID']);
                        $schoolName = htmlspecialchars($image_row['School_Name']);
                        $buildingImage = trim((string) ($image_row['School_Image'] ?? ''));
                        $logoImage = trim((string) ($image_row['School_Logo'] ?? ''));

                        $rendered = false;
                        foreach ([['value' => $buildingImage, 'label' => 'صورة المدرسة'], ['value' => $logoImage, 'label' => 'الشعار']] as $imageItem) {
                            if ($imageItem['value'] === '') {
                                continue;
                            }

                            $rawPath = $imageItem['value'];
                            $imagePath = $rawPath;
                            if (!preg_match('/^https?:\/\//i', $rawPath) && strpos($rawPath, 'uploads/') !== 0) {
                                $imagePath = 'uploads/schools/' . ltrim($rawPath, '/\\');
                            }

                            $safePath = htmlspecialchars($imagePath);
                            $safeLabel = htmlspecialchars($imageItem['label']);
                            echo "<div class='image-card'>
                                    <img src='" . $safePath . "' alt='" . $schoolName . " - " . $safeLabel . "' loading='lazy'>
                                    <div class='image-card-body'>
                                        <span class='image-badge'>" . $safeLabel . "</span>
                                        <strong>" . $schoolName . "</strong>
                                        <small>رقم المدرسة: " . $schoolId . "</small>
                                    </div>
                                  </div>";
                            $rendered = true;
                        }

                        if (!$rendered) {
                            echo "<div class='image-card'><div class='image-card-body'><strong>" . $schoolName . "</strong><small>لا توجد صورة</small></div></div>";
                        }
                    }
                } else {
                    echo "<div style='color:#777;'>لا توجد صور مدارس مرفوعة حتى الآن.</div>";
                }
                ?>
            </div>
        </div>

        <!-- مدراء المدارس -->
        <div class="card">
            <h3>👨‍🏫 مدراء المدارس</h3>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم المستخدم</th>
                            <th>الدور</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $principals_result = $conn->query("SELECT User_ID, Username, Role FROM Users WHERE Role = 'principal' ORDER BY User_ID DESC");
                        if ($principals_result && $principals_result->num_rows > 0) {
                            while ($principal = $principals_result->fetch_assoc()) {
                                echo "<tr>
                                        <td>" . intval($principal['User_ID']) . "</td>
                                        <td>" . htmlspecialchars($principal['Username']) . "</td>
                                        <td>" . htmlspecialchars($principal['Role']) . "</td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='3' style='text-align: center; color: #999;'>لا يوجد مدراء مدارس حتى الآن</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- التصدير -->
        <div class="card">
            <h3>📤 تصدير البيانات</h3>
            <div class="features-grid" style="margin-top: 15px;">
                <a href="export_pdf.php?output=pdf" target="_blank" class="feature-card" style="background: linear-gradient(135deg, #f44336 0%, #e91e63 100%); text-decoration: none; font-weight: bold;">
                    📄 تصدير PDF
                </a>
                <a href="export_excel.php?format=csv" target="_blank" class="feature-card" style="background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); text-decoration: none; font-weight: bold;">
                    📊 تصدير CSV
                </a>
                <a href="export_excel.php?format=xlsx" target="_blank" class="feature-card" style="background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%); text-decoration: none; font-weight: bold;">
                    📊 تصدير Excel
                </a>
            </div>
        </div>

        <!-- معلومات النظام -->
        <div class="card">
            <h3>ℹ️ معلومات النظام</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <div>
                    <strong>🔒 إصدار الأمان:</strong>
                    <p>محسّن بـ CSRF Protection + Prepared Statements</p>
                </div>
                <div>
                    <strong>📊 إجمالي المدارس:</strong>
                    <p><?= intval($schools_count) ?> مدرسة</p>
                </div>
                <div>
                    <strong>📍 إجمالي المناطق:</strong>
                    <p><?= intval($regions_count) ?> منطقة</p>
                </div>
                <div>
                    <strong>🏙️ إجمالي المحافظات:</strong>
                    <p><?= intval($gov_count) ?> محافظة</p>
                </div>
            </div>
            <p style="color:#999; font-size:12px; margin-top: 15px;">
                ⏰ آخر تحديث: <?php echo date('Y-m-d H:i:s', time()); ?>
                <br>
                💾 حالة قاعدة البيانات: متصلة وتعمل بشكل طبيعي
            </p>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
