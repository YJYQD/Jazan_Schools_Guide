<?php
/**
 * image_upload.php
 * نظام رفع الصور والشعارات للمدارس
 * مع التحقق الأمني والتخزين الآمن
 */

session_start();
require_once "db.php";
require_once "security_helpers.php";

header('Content-Type: application/json; charset=utf-8');

// معالجة طلبات AJAX
$action = $_GET['action'] ?? '';

if ($action !== 'get_image' && !canManageSchoolImages()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => t('insufficient_permissions')]);
    exit;
}

// ============================================
// إعدادات التحميل
// ============================================
$upload_dir = 'uploads/schools/';
$max_file_size = 5 * 1024 * 1024; // 5 MB
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// إنشاء مجلد التحميل إذا لم يكن موجوداً
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ============================================
// 1. تحميل صورة المدرسة
// ============================================
if ($action === 'upload_image' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => t('security_verification_failed')]);
        exit;
    }
    
    $school_id = intval($_POST['school_id'] ?? 0);

    if ($school_id <= 0) {
        echo json_encode(['success' => false, 'message' => t('invalid_request_data')]);
        exit;
    }
    
    // التحقق من وجود المدرسة
    $stmt = $conn->prepare("SELECT School_ID FROM Schools WHERE School_ID = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => t('school_not_found')]);
        exit;
    }
    
    // التحقق من وجود الملف/الملفات
    if (!isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'message' => t('no_file_selected')]);
        exit;
    }

    $files = $_FILES['image'];
    $fileItems = [];

    if (is_array($files['name'])) {
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $fileItems[] = [
                'name' => $files['name'][$i] ?? '',
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];
        }
    } else {
        $fileItems[] = [
            'name' => $files['name'] ?? '',
            'type' => $files['type'] ?? '',
            'tmp_name' => $files['tmp_name'] ?? '',
            'error' => $files['error'] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'] ?? 0,
        ];
    }

    if (empty($fileItems)) {
        echo json_encode(['success' => false, 'message' => t('no_file_selected')]);
        exit;
    }

    // حد أعلى منطقي لعدد الصور في الطلب الواحد
    if (count($fileItems) > 12) {
        echo json_encode(['success' => false, 'message' => t('max_upload_exceeded')]);
        exit;
    }

    $uploadedNames = [];
    $uploadedPaths = [];
    $cleanupUploadedFiles = function () use (&$uploadedPaths) {
        foreach ($uploadedPaths as $path) {
            @unlink($path);
        }
    };
    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    foreach ($fileItems as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            finfo_close($finfo);
            $cleanupUploadedFiles();
            echo json_encode(['success' => false, 'message' => t('file_error')]);
            exit;
        }

        $file_size = (int) ($file['size'] ?? 0);
        $file_type = (string) ($file['type'] ?? '');
        $file_tmp = (string) ($file['tmp_name'] ?? '');
        $file_name = (string) ($file['name'] ?? '');

        if ($file_size <= 0 || $file_size > $max_file_size) {
            finfo_close($finfo);
            $cleanupUploadedFiles();
            echo json_encode(['success' => false, 'message' => t('file_too_large')]);
            exit;
        }

        if (!in_array($file_type, $allowed_types, true)) {
            finfo_close($finfo);
            $cleanupUploadedFiles();
            echo json_encode(['success' => false, 'message' => t('file_type_not_supported')]);
            exit;
        }

        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (!in_array($file_ext, $allowed_extensions, true)) {
            finfo_close($finfo);
            $cleanupUploadedFiles();
            echo json_encode(['success' => false, 'message' => t('file_ext_not_allowed')]);
            exit;
        }

        $real_type = finfo_file($finfo, $file_tmp);
        if (!in_array($real_type, $allowed_types, true)) {
            finfo_close($finfo);
            $cleanupUploadedFiles();
            echo json_encode(['success' => false, 'message' => t('not_valid_image')]);
            exit;
        }

        $uploaderId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $unique_name = uniqid('school_' . $school_id . '_u' . $uploaderId . '_building_', true) . '.' . $file_ext;
        $upload_path = $upload_dir . $unique_name;

        if (!move_uploaded_file($file_tmp, $upload_path)) {
            finfo_close($finfo);
            $cleanupUploadedFiles();
            echo json_encode(['success' => false, 'message' => t('file_save_failed')]);
            exit;
        }

        $uploadedNames[] = $unique_name;
        $uploadedPaths[] = $upload_path;
    }
    finfo_close($finfo);

    // تحديث قاعدة البيانات
    $db_column = 'School_Image';

    // جلب الصور القديمة
    $stmt = $conn->prepare("SELECT $db_column FROM Schools WHERE School_ID = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    $existingRaw = trim((string) ($result[$db_column] ?? ''));
    $existingParts = preg_split('/[,;|\n\r]+/u', $existingRaw) ?: [];
    $existingNames = [];
    foreach ($existingParts as $part) {
        $name = trim((string) $part);
        if ($name !== '') {
            $existingNames[] = basename($name);
        }
    }
    $merged = array_values(array_unique(array_merge($existingNames, $uploadedNames)));
    $newDbValue = implode(',', $merged);

    // تحديث السجل
    $stmt = $conn->prepare("UPDATE Schools SET $db_column = ? WHERE School_ID = ?");
    $stmt->bind_param("si", $newDbValue, $school_id);

        if ($stmt->execute()) {
        logSecurityEvent('UPDATE', "تم رفع صورة للمدرسة", [
            'school_id' => $school_id,
            'uploaded_count' => count($uploadedNames),
            'uploaded_by' => $_SESSION['user_id'] ?? 'UNKNOWN'
        ]);
        echo json_encode([
            'success' => true,
            'message' => (count($uploadedNames) > 1 ? t('image_upload_success_multiple') : t('image_upload_success_single')),
            'uploaded_count' => count($uploadedNames),
            'file_names' => $uploadedNames
        ]);
    } else {
        // حذف الملفات الجديدة إذا فشل التحديث
        foreach ($uploadedPaths as $path) {
            @unlink($path);
        }
        echo json_encode(['success' => false, 'message' => t('db_update_error')]);
    }
    exit;
}

// ============================================
// 2. الحصول على الصورة
// ============================================
if ($action === 'get_image') {
    $school_id = intval($_GET['school_id'] ?? 0);
    $image_type = sanitizeInput($_GET['type'] ?? 'building');
    
    if ($school_id <= 0) {
        echo json_encode(['success' => false, 'message' => t('invalid_school_id')]);
        exit;
    }
    
    $db_column = ($image_type === 'building') ? 'School_Image' : 'School_Logo';
    
    $stmt = $conn->prepare("SELECT $db_column FROM Schools WHERE School_ID = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result && !empty($result[$db_column])) {
        echo json_encode([
            'success' => true,
            'image_path' => $upload_dir . $result[$db_column],
            'image_name' => $result[$db_column]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => t('no_image')]);
    }
    exit;
}

// ============================================
// 3. حذف الصورة
// ============================================
if ($action === 'delete_image' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => t('security_verification_failed')]);
        exit;
    }
    
    $school_id = intval($_POST['school_id'] ?? 0);
    $image_name = trim((string) ($_POST['image_name'] ?? ''));
    $delete_all = filter_var($_POST['delete_all'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
    if ($school_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'معرف المدرسة غير صحيح']);
        exit;
    }
    
    $db_column = 'School_Image';
    
    // الحصول على اسم الملف
    $stmt = $conn->prepare("SELECT $db_column FROM Schools WHERE School_ID = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result && !empty($result[$db_column])) {
        $existingRaw = trim((string) $result[$db_column]);
        $parts = preg_split('/[,;|\n\r]+/u', $existingRaw) ?: [];
        $existingNames = [];
        foreach ($parts as $part) {
            $name = trim((string) $part);
            if ($name !== '') {
                $existingNames[] = basename($name);
            }
        }

            if (empty($existingNames)) {
            echo json_encode(['success' => false, 'message' => t('image_not_found')]);
            exit;
        }

        $toDelete = [];
        if ($delete_all) {
            $toDelete = $existingNames;
            $remaining = [];
        } else {
            if ($image_name === '') {
                echo json_encode(['success' => false, 'message' => t('image_name_required')]);
                exit;
            }

            $target = basename($image_name);
            $remaining = [];
            foreach ($existingNames as $name) {
                if ($name === $target) {
                    $toDelete[] = $name;
                } else {
                    $remaining[] = $name;
                }
            }

            if (empty($toDelete)) {
                echo json_encode(['success' => false, 'message' => t('image_not_found')]);
                exit;
            }
        }

        $userRole = $_SESSION['role'] ?? '';
        $currentUser = intval($_SESSION['user_id'] ?? 0);

        // If delete_all requested and user is not admin, only delete images uploaded by current user
        $finalToDelete = [];
        $finalRemaining = $remaining;

        foreach ($toDelete as $fileName) {
            $allowed = false;
            if ($userRole === 'admin') {
                $allowed = true;
            } else {
                // try to parse uploader id from filename pattern _u{user}_
                if (preg_match('/_u(\d+)_/u', $fileName, $m)) {
                    $ownerId = intval($m[1]);
                    if ($ownerId === $currentUser) {
                        $allowed = true;
                    }
                }
            }

            if ($allowed) {
                $path = $upload_dir . $fileName;
                if (file_exists($path)) {
                    @unlink($path);
                }
                $finalToDelete[] = $fileName;
                // remove from remaining if present
                $idx = array_search($fileName, $finalRemaining, true);
                if ($idx !== false) { unset($finalRemaining[$idx]); }
            }
        }

        $newValue = implode(',', array_values(array_unique($finalRemaining)));
        $stmt = $conn->prepare("UPDATE Schools SET $db_column = ? WHERE School_ID = ?");
        $stmt->bind_param("si", $newValue, $school_id);

        if ($stmt->execute()) {
            logSecurityEvent('DELETE', "تم حذف صورة من المدرسة", [
                'school_id' => $school_id,
                'deleted_count' => count($finalToDelete),
                'requested_delete_count' => count($toDelete),
                'delete_all' => $delete_all,
                'performed_by' => $currentUser
            ]);

            echo json_encode([
                'success' => true,
                'message' => count($finalToDelete) > 0 ? (count($finalToDelete) . ' صورة محذوفة') : 'لا توجد صلاحية لحذف الصور المطلوبة',
                'deleted_count' => count($finalToDelete)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطأ في حذف الصورة']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'الصورة غير موجودة']);
    }
    exit;
}

// ============================================
// 4. تحميل الصورة من URL خارجي
// ============================================
if ($action === 'upload_from_url' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => t('security_verification_failed')]);
        exit;
    }
    
    $school_id = intval($_POST['school_id'] ?? 0);
    $image_url = filter_var($_POST['url'] ?? '', FILTER_VALIDATE_URL);
    $image_type = sanitizeInput($_POST['image_type'] ?? 'building');
    
    if ($school_id <= 0 || !$image_url) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
        exit;
    }
    
    // تحميل الصورة من URL
    $image_data = @file_get_contents($image_url);
    if ($image_data === false) {
        echo json_encode(['success' => false, 'message' => 'فشل تحميل الصورة من URL']);
        exit;
    }
    
    // التحقق من نوع المحتوى
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $temp_file = tempnam(sys_get_temp_dir(), 'upload_');
    file_put_contents($temp_file, $image_data);
    $file_type = finfo_file($finfo, $temp_file);
    finfo_close($finfo);
    
    if (!in_array($file_type, $allowed_types)) {
        unlink($temp_file);
        echo json_encode(['success' => false, 'message' => 'نوع الصورة غير مدعوم']);
        exit;
    }
    
    // الحصول على امتداد الملف من URL أو Content-Type
    $ext = 'jpg';
    if (preg_match('/\.(\w+)$/', parse_url($image_url, PHP_URL_PATH), $matches)) {
        $ext = strtolower($matches[1]);
    }
    
    if (!in_array($ext, $allowed_extensions)) {
        $ext = 'jpg';
    }
    
    // إنشاء اسم فريد
    $unique_name = uniqid('school_' . $school_id . '_' . $image_type . '_', true) . '.' . $ext;
    $upload_path = $upload_dir . $unique_name;
    
    // نقل الملف المؤقت
    if (!rename($temp_file, $upload_path)) {
        unlink($temp_file);
        echo json_encode(['success' => false, 'message' => 'فشل حفظ الملف']);
        exit;
    }
    
    // تحديث قاعدة البيانات (نفس العملية أعلاه)
    $db_column = ($image_type === 'building') ? 'School_Image' : 'School_Logo';
    $stmt = $conn->prepare("UPDATE Schools SET $db_column = ? WHERE School_ID = ?");
    $stmt->bind_param("si", $unique_name, $school_id);
    
    if ($stmt->execute()) {
        logSecurityEvent('UPDATE', "تم رفع صورة من URL للمدرسة", [
            'school_id' => $school_id,
            'image_type' => $image_type,
            'url' => $image_url
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'تم تحميل الصورة بنجاح',
            'file_name' => $unique_name
        ]);
    } else {
        @unlink($upload_path);
        echo json_encode(['success' => false, 'message' => 'خطأ في تحديث قاعدة البيانات']);
    }
    exit;
}

// إجراء غير معروف
echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
?>
