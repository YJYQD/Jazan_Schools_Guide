<?php
require_once "db.php";
require_once "navbar.php";
require_once "security_helpers.php";

$conn->set_charset("utf8mb4");
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') { header('Location: login.php'); exit; }
initiateCsrfToken();

$message=''; $message_type='';
// حذف الصور (تهيئة الحقول) - مسموح للإدمن فقط (المدراء يحذفون صورهم عبر image_upload.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_images'])) {
  if (!validateCsrfToken($_POST['csrf_token'] ?? '')) { $message = t('security_verification_failed'); $message_type='error'; }
  else {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
      $message = 'لا تملك صلاحية مسح كل صور المدرسة.';
      $message_type = 'error';
    } else {
      $school_id = intval($_POST['clear_images']);
      $stmt = $conn->prepare("UPDATE Schools SET School_Image = NULL, School_Logo = NULL WHERE School_ID = ?");
      $stmt->bind_param('i',$school_id);
      if ($stmt->execute()) { $message='تم مسح الصور لهذه المدرسة'; $message_type='success'; }
    }
  }
}

$images_result = $conn->query("SELECT School_ID, School_Name, School_Image, School_Logo FROM Schools ORDER BY School_Name");

?>
<?php $page_lang = function_exists('current_lang') ? current_lang() : 'ar'; $page_dir = $page_lang === 'en' ? 'ltr' : 'rtl'; ?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($page_lang); ?>" dir="<?php echo htmlspecialchars($page_dir); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>صور المدارس - لوحة التحكم</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php render_navbar('admin', true, 'index.php', '⬅️ العودة للرئيسية'); ?>
<div class="container-fluid py-4">
  <div class="row">
    <div class="col-md-3"><?php include 'admin_sidebar.php'; ?></div>
    <div class="col-md-9">
      <h2 class="mb-4">🖼️ صور المدارس</h2>
      <?php if(!empty($message)): ?><div class="alert alert-<?= $message_type=='success'?'success':'danger' ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>

      <div class="row row-cols-1 row-cols-md-3 g-3">
        <?php
        if ($images_result && $images_result->num_rows>0) {
            while($row = $images_result->fetch_assoc()) {
                $schoolId = intval($row['School_ID']);
                $rawName = $row['School_Name'];
                if (function_exists('get_localized_field')) {
                  $localized = get_localized_field($conn, 'Schools', 'School_ID', $schoolId, 'School_Name', (function_exists('translate_db_text') ? translate_db_text($rawName, function_exists('current_lang')? current_lang() : 'ar') : $rawName));
                } else if (function_exists('translate_db_text')) {
                  $localized = translate_db_text($rawName, function_exists('current_lang')? current_lang() : 'ar');
                } else {
                  $localized = $rawName;
                }
                $schoolName = htmlspecialchars($localized);
                $building = trim((string)($row['School_Image'] ?? ''));
                $logo = trim((string)($row['School_Logo'] ?? ''));

                if ($building === '' && $logo === '') {
                    echo "<div class='col'><div class='card p-3 text-center'><div class='card-body'><strong>$schoolName</strong><p class='text-muted'>لا توجد صور</p></div></div></div>";
                    continue;
                }

                $imgTag = '';
                if ($building !== '') {
                  $path = preg_match('/^https?:\/\//i',$building) ? $building : 'uploads/schools/'.ltrim($building,'/\\');
                  $imgTag .= "<img src='" . htmlspecialchars($path) . "' alt='" . htmlspecialchars($schoolName) . " - صورة المدرسة' style='width:100%;height:150px;object-fit:cover;' loading='lazy'>";
                }
                if ($logo !== '') {
                  $path2 = preg_match('/^https?:\/\//i',$logo) ? $logo : 'uploads/schools/'.ltrim($logo,'/\\');
                  $imgTag .= "<div style='padding:8px;'><img src='" . htmlspecialchars($path2) . "' alt='" . htmlspecialchars($schoolName) . " - شعار المدرسة' style='height:60px;object-fit:contain;' loading='lazy'></div>";
                }

                echo "<div class='col'><div class='card image-card'>".$imgTag.
                     "<div class='card-body'><strong>$schoolName</strong><div class='mt-2'>".
                     "<form method='POST' style='display:inline;'>".
                     "<input type='hidden' name='csrf_token' value='".htmlspecialchars(getCsrfToken())."'>".
                     "<button type='submit' name='clear_images' value='".$schoolId."' onclick=\"return confirm('مسح صور هذه المدرسة؟')\" class='btn btn-sm btn-danger'>🧹 مسح الصور</button>".
                     "</form></div></div></div></div>";
            }
        } else { echo "<div class='col-12 text-muted'>لا توجد صور مدارس مرفوعة حتى الآن.</div>"; }
        ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
