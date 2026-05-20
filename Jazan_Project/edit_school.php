<?php
/**
 * edit_school.php
 * نظام تعديل بيانات المدارس
 * مع حماية CSRF وتسجيل العمليات وتصميم متجاوب
 */

// Ensure session is started safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "db.php";
require_once "security_helpers.php";
require_once "navbar.php";
require_once "i18n.php";

// التحقق من صلاحيات المسؤول
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$school_id = $_GET['id'] ?? null;
$school = null;
$page_lang = function_exists('current_lang') ? current_lang() : 'ar';

// الحصول على بيانات المدرسة
if ($school_id) {
    $stmt = $conn->prepare("SELECT * FROM Schools WHERE School_ID = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $school = $result->fetch_assoc();
    
    if (!$school) {
        header('Location: admin.php');
        exit;
    }
}

// معالجة تحديث المدرسة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = t('csrf_failed_try_again');
    } else {
        $school_id = $_POST['school_id'] ?? null;
        $school_name = sanitizeInput($_POST['school_name'] ?? '');
        $school_type = sanitizeInput($_POST['school_type'] ?? '');
        $education_level = sanitizeInput($_POST['education_level'] ?? '');
        $office_id = intval($_POST['office_id'] ?? 0);
        $city = sanitizeInput($_POST['city'] ?? '');
        $website = sanitizeInput($_POST['website'] ?? '');
        $ministerial_rating = floatval($_POST['ministerial_rating'] ?? 0);
        $latitude = floatval($_POST['latitude'] ?? 0);
        $longitude = floatval($_POST['longitude'] ?? 0);
        
        // التحقق من البيانات المدخلة
        if (!$school_id || !$school_name || !$school_type || !$education_level || $office_id <= 0) {
            $error = t('required_fields_missing');
        } else if (strlen($school_name) > 200) {
            $error = t('school_name_too_long');
        } else {
            // تحديث بيانات المدرسة
            $stmt = $conn->prepare("
                UPDATE Schools 
                SET 
                    School_Name = ?,
                    School_Type = ?,
                    Education_Level = ?,
                    Office_ID = ?,
                    City = ?,
                    School_Website = ?,
                    Ministerial_Rating = ?,
                    Latitude = ?,
                    Longitude = ?
                WHERE School_ID = ?
            ");
            
            if ($stmt) {
                $stmt->bind_param(
                    "sssissdddi",
                    $school_name,
                    $school_type,
                    $education_level,
                    $office_id,
                    $city,
                    $website,
                    $ministerial_rating,
                    $latitude,
                    $longitude,
                    $school_id
                );
                
                if ($stmt->execute()) {
                    $success = t('school_update_success');
                    
                    // تسجيل العملية في سجل التدقيق
                    logSecurityEvent('UPDATE', "تم تعديل المدرسة: $school_name", [
                        'school_id' => $school_id,
                        'edited_by' => $_SESSION['user_id']
                    ]);
                    
                    // إعادة تحميل بيانات المدرسة
                    $stmt = $conn->prepare("SELECT * FROM Schools WHERE School_ID = ?");
                    $stmt->bind_param("i", $school_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $school = $result->fetch_assoc();
                } else {
                    $error = t('school_update_db_error') . $conn->error;
                }
            } else {
                $error = t('statement_prepare_error') . $conn->error;
            }
        }
    }
}

// الحصول على قائمة المكاتب
$offices = [];
$stmt = $conn->prepare("
    SELECT o.Office_ID, o.Office_Name, g.Gov_Name 
    FROM Offices o
    LEFT JOIN Governorates g ON o.Gov_ID = g.Gov_ID
    ORDER BY g.Gov_Name, o.Office_Name
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $offices[] = $row;
}

// Csrf Token
generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($page_lang); ?>" dir="<?php echo $page_lang === 'en' ? 'ltr' : 'rtl'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('edit_school_title')); ?></title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        .edit-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 51, 102, 0.1);
            border-right: 4px solid var(--primary);
        }

        .edit-container h1 {
            color: var(--primary);
            font-size: 2rem;
            margin-top: 0;
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 15px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 1rem;
            font-family: 'Tajawal', sans-serif;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(0, 242, 254, 0.1);
            background: #fdfdfd;
        }

        .form-group input::placeholder {
            color: #cbd5e1;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid var(--border);
            flex-wrap: wrap;
        }

        .form-actions .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Tajawal', sans-serif;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 10px rgba(0, 51, 102, 0.2);
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 51, 102, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: var(--text-light);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--bg);
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        .form-section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            margin-top: 30px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--accent);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: var(--accent);
            transform: translateX(3px);
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .edit-container {
                padding: 20px;
                margin: 10px auto;
            }

            .edit-container h1 {
                font-size: 1.5rem;
            }

            .form-actions {
                flex-direction: column;
                gap: 10px;
            }

            .form-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .edit-container {
                padding: 15px;
                margin: 5px auto;
            }

            .edit-container h1 {
                font-size: 1.2rem;
            }

            .form-section-title {
                font-size: 1.1rem;
            }

            .form-group label {
                font-size: 0.9rem;
            }

            .form-group input,
            .form-group select {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <?php render_navbar('admin', true, 'admin.php', t('back_to_dashboard')); ?>

    <div class="container" style="padding-top: 20px;">
        <a href="admin.php" class="back-link"><?php echo htmlspecialchars(t('back_to_dashboard')); ?></a>

        <div class="edit-container">
            <h1>✏️ <?php echo htmlspecialchars(t('edit_school_heading')); ?></h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($school): ?>
                <form method="POST" class="edit-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="school_id" value="<?php echo htmlspecialchars($school['School_ID']); ?>">
                    
                    <!-- المعلومات الأساسية -->
                    <div class="form-section-title">📋 <?php echo htmlspecialchars(t('basic_information')); ?></div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="school_name"><?php echo htmlspecialchars(t('school_name_label')); ?></label>
                            <input type="text" id="school_name" name="school_name" 
                                   value="<?php echo htmlspecialchars($school['School_Name']); ?>" 
                                placeholder="<?php echo htmlspecialchars(t('school_name_placeholder')); ?>"
                                   required maxlength="200">
                        </div>
                        
                        <div class="form-group">
                            <label for="school_type"><?php echo htmlspecialchars(t('school_type')); ?> *</label>
                            <select id="school_type" name="school_type" required>
                                <option value=""><?php echo htmlspecialchars(t('select_option')); ?></option>
                                <option value="حكومي" <?php echo ($school['School_Type'] === 'حكومي') ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('public')); ?></option>
                                <option value="أهلي" <?php echo ($school['School_Type'] === 'أهلي') ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('private')); ?></option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="education_level"><?php echo htmlspecialchars(t('education_level_label')); ?></label>
                            <select id="education_level" name="education_level" required>
                                <option value=""><?php echo htmlspecialchars(t('select_option')); ?></option>
                                <option value="روضة" <?php echo ($school['Education_Level'] === 'روضة') ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('kindergarten')); ?></option>
                                <option value="ابتدائي" <?php echo ($school['Education_Level'] === 'ابتدائي') ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('elementary')); ?></option>
                                <option value="متوسط" <?php echo ($school['Education_Level'] === 'متوسط') ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('middle')); ?></option>
                                <option value="ثانوي" <?php echo ($school['Education_Level'] === 'ثانوي') ? 'selected' : ''; ?>><?php echo htmlspecialchars(t('high_school')); ?></option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="office_id"><?php echo htmlspecialchars(t('education_office_label')); ?></label>
                            <select id="office_id" name="office_id" required>
                                <option value=""><?php echo htmlspecialchars(t('select_option')); ?></option>
                                <?php foreach ($offices as $office): ?>
                                    <option value="<?php echo $office['Office_ID']; ?>" 
                                        <?php echo ($school['Office_ID'] === $office['Office_ID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($office['Gov_Name'] . ' - ' . $office['Office_Name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="city"><?php echo htmlspecialchars(t('city')); ?></label>
                            <input type="text" id="city" name="city" 
                                    placeholder="<?php echo htmlspecialchars(t('city_placeholder')); ?>"
                                   value="<?php echo htmlspecialchars($school['City'] ?? ''); ?>"
                                   maxlength="100">
                        </div>
                        
                        <div class="form-group">
                                <label for="ministerial_rating"><?php echo htmlspecialchars(t('ministerial_rating_label')); ?></label>
                            <input type="number" id="ministerial_rating" name="ministerial_rating" 
                                   value="<?php echo htmlspecialchars($school['Ministerial_Rating'] ?? 0); ?>"
                                   min="0" max="5" step="0.1"
                                    placeholder="<?php echo htmlspecialchars(t('enter_rating')); ?>">
                        </div>
                    </div>
                    
                    <!-- معلومات الاتصال والموقع -->
                    <div class="form-section-title">🌐 <?php echo htmlspecialchars(t('contact_location')); ?></div>
                    
                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="website"><?php echo htmlspecialchars(t('website_link_label')); ?></label>
                            <input type="url" id="website" name="website" 
                                   value="<?php echo htmlspecialchars($school['School_Website'] ?? ''); ?>"
                                   placeholder="https://example.com">
                        </div>
                        
                        <div class="form-group">
                            <label for="latitude"><?php echo htmlspecialchars(t('latitude_label')); ?></label>
                            <input type="number" id="latitude" name="latitude" 
                                   value="<?php echo htmlspecialchars($school['Latitude'] ?? 0); ?>"
                                   step="0.00001" placeholder="21.5433">
                        </div>
                        
                        <div class="form-group">
                            <label for="longitude"><?php echo htmlspecialchars(t('longitude_label')); ?></label>
                            <input type="number" id="longitude" name="longitude" 
                                   value="<?php echo htmlspecialchars($school['Longitude'] ?? 0); ?>"
                                   step="0.00001" placeholder="55.2075">
                        </div>
                    </div>
                    
                    <!-- أزرار التحكم -->
                    <div class="form-actions">
                        <a href="admin.php" class="btn btn-secondary">❌ <?php echo htmlspecialchars(t('cancel')); ?></a>
                        <button type="submit" class="btn btn-primary">✅ <?php echo htmlspecialchars(t('save_changes')); ?></button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-error">❌ <?php echo htmlspecialchars(t('school_not_found')); ?></div>
                <a href="admin.php" class="btn btn-secondary" style="display: inline-block;"><?php echo htmlspecialchars(t('back')); ?></a>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
