<?php
require_once "db.php";
require_once "navbar.php";
require_once "security_helpers.php";
require_once "i18n.php";
require_once "filters_helper.php";

$conn->set_charset('utf8mb4');
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') { header('Location: login.php'); exit; }
initiateCsrfToken();

$message=''; $message_type='';
// Add school
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_school'])) {
  if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $message = t('security_verification_failed'); $message_type='error';
  } else {
    $school_id = intval($_POST['school_id'] ?? 0);
    $school_name = trim($_POST['school_name'] ?? '');
    $office_id = intval($_POST['office_id'] ?? 0);
    $city = trim($_POST['city_text'] ?? '');
    $level = trim($_POST['level'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $gender = trim($_POST['gender'] ?? '');

    // Server-side validation
    if ($school_id <= 0) {
      $message = t('invalid_school_id'); $message_type = 'error';
    } elseif ($school_name === '') {
      $message = t('school_name_required'); $message_type = 'error';
    } elseif ($office_id <= 0) {
      $message = t('choose_office_required'); $message_type = 'error';
    } else {
      $stmt = $conn->prepare("INSERT INTO Schools (School_ID, School_Name, Education_Level, School_Type, Office_ID, City, Gender) VALUES (?,?,?,?,?,?,?)");
      if (!$stmt) {
        $message = t('prepare_add_query_error'); $message_type = 'error';
      } else {
        $bindOk = $stmt->bind_param('isssiss', $school_id, $school_name, $level, $type, $office_id, $city, $gender);
        if ($bindOk === false) {
          $message = t('internal_data_error'); $message_type = 'error';
        } else {
          if ($stmt->execute()) {
            $message = t('school_added_success'); $message_type = 'success';
          } else {
            $message = t('school_add_exists_error'); $message_type = 'error';
          }
        }
        $stmt->close();
      }
    }
  }
}

// Delete school
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['del_school'])) {
  if (!validateCsrfToken($_POST['csrf_token'] ?? '')) { $message = t('security_verification_failed'); $message_type='error'; }
  else { $school_id = intval($_POST['del_school']); $stmt = $conn->prepare("DELETE FROM Schools WHERE School_ID=?"); $stmt->bind_param('i',$school_id); if ($stmt->execute()) { $message = t('delete_school_success'); $message_type='success'; } }
}

$schools = $conn->query("SELECT s.*, o.Office_Name, g.Gov_Name FROM Schools s LEFT JOIN Offices o ON o.Office_ID = s.Office_ID LEFT JOIN Governorates g ON g.GOV_ID = o.Gov_ID ORDER BY s.School_Name");
$offices = $conn->query("SELECT o.Office_ID, o.Office_Name, g.Gov_Name FROM Offices o LEFT JOIN Governorates g ON g.Gov_ID = o.Gov_ID ORDER BY g.Gov_Name, o.Office_Name");

$page_lang = function_exists('current_lang') ? current_lang() : 'ar';

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($page_lang); ?>" dir="<?php echo $page_lang === 'en' ? 'ltr' : 'rtl'; ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars(t('schools_plural') . ' - ' . t('admin')); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php render_navbar('admin', true, 'admin.php', t('back_to_dashboard')); ?>
<div class="container-fluid py-4">
  <div class="row">
    <div class="col-md-3"><?php include 'admin_sidebar.php'; ?></div>
    <div class="col-md-9">
      <h2 class="mb-4">🏫 <?php echo htmlspecialchars(t('schools_plural')); ?></h2>
      <?php if($message): ?><div class="alert alert-<?= $message_type=='success'?'success':'danger' ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>

      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addSchoolModal"><?php echo htmlspecialchars(t('add_school')); ?></button>
          <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal"><?php echo htmlspecialchars(t('import_csv')); ?></button>
        </div>
        <div>
          <a href="export_excel.php?format=csv" class="btn btn-success">📤 تصدير CSV</a>
        </div>
      </div>

      <!-- Add School Modal -->
      <div class="modal fade" id="addSchoolModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?php echo htmlspecialchars(t('add_school')); ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" id="addSchoolForm">
                <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
              <div class="row g-2">
                <div class="col-md-4"><label class="form-label"><?php echo htmlspecialchars(t('school_id_label') ?? 'معرف المدرسة'); ?></label><input name="school_id" class="form-control" type="number" required></div>
                <div class="col-md-8"><label class="form-label"><?php echo htmlspecialchars(t('name')); ?></label><input name="school_name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label"><?php echo htmlspecialchars(t('city')); ?></label><input name="city_text" class="form-control"></div>
                  <div class="col-md-6"><label class="form-label"><?php echo htmlspecialchars(t('office_label')); ?></label><select name="office_id" class="form-select" required><?php while($o=$offices->fetch_assoc()){ echo "<option value='".intval($o['Office_ID'])."'>".htmlspecialchars($o['Gov_Name'])." - ".htmlspecialchars($o['Office_Name'])."</option>"; } ?></select></div>
                  <div class="col-md-4"><label class="form-label"><?php echo htmlspecialchars(t('education_level_label') ?? 'المرحلة'); ?></label><select name="level" class="form-select"><option><?php echo htmlspecialchars(t('elementary')); ?></option><option><?php echo htmlspecialchars(t('middle')); ?></option><option><?php echo htmlspecialchars(t('high_school')); ?></option></select></div>
                  <div class="col-md-4"><label class="form-label"><?php echo htmlspecialchars(t('school_type') ?? 'النوع'); ?></label><select name="type" class="form-select"><option><?php echo htmlspecialchars(t('public')); ?></option><option><?php echo htmlspecialchars(t('private')); ?></option></select></div>
                  <div class="col-md-4"><label class="form-label"><?php echo htmlspecialchars(t('gender_label') ?? 'النوع'); ?></label><select name="gender" class="form-select"><option><?php echo htmlspecialchars(t('boys')); ?></option><option><?php echo htmlspecialchars(t('girls')); ?></option><option><?php echo htmlspecialchars(t('mixed')); ?></option></select></div>
              </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo htmlspecialchars(t('close')); ?></button><button type="submit" name="add_school" class="btn btn-primary"><?php echo htmlspecialchars(t('save')); ?></button></div>
            </form>
          </div>
        </div>
      </div>

      <!-- Import Modal -->
      <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?php echo htmlspecialchars(t('import_csv')); ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="process_import.php" enctype="multipart/form-data">
            <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
              <div class="mb-2"><label class="form-label">ملف CSV</label><input type="file" name="csv_file" accept=".csv" class="form-control" required></div>
              <p class="text-muted small">تحميل CSV يحتوي على الأعمدة الأساسية (School_ID, School_Name, School_Type, Education_Level, Office_ID).</p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo htmlspecialchars(t('close')); ?></button><button type="submit" class="btn btn-primary"><?php echo htmlspecialchars(t('save')); ?></button></div>
            </form>
          </div>
        </div>
      </div>

      <div class="card"><div class="card-body table-responsive">
        <table id="schoolsTable" class="table table-striped table-hover table-bordered align-middle">
          <thead><tr><th><?php echo htmlspecialchars(t('name')); ?></th><th><?php echo htmlspecialchars(t('office_label')); ?></th><th><?php echo htmlspecialchars(t('level_prefix_elementary')); ?></th><th><?php echo htmlspecialchars(t('school_type')); ?></th><th><?php echo htmlspecialchars(t('actions')); ?></th></tr></thead>
          <tbody>
            <?php if($schools && $schools->num_rows>0){ while($s=$schools->fetch_assoc()){ 
                $displayName = $s['School_Name'];
                $lang = function_exists('current_lang') ? current_lang() : (_jazan_get_lang());
                if (function_exists('translate_db_text')) {
                    $displayName = translate_db_text($s['School_Name'], $lang);
                }
                    echo "<tr><td><strong>".htmlspecialchars($displayName)."</strong><br><small class='text-muted'>ID: ".intval($s['School_ID'])."</small></td><td>".htmlspecialchars($s['Office_Name'])."</td><td>".htmlspecialchars($s['Education_Level'])."</td><td>".htmlspecialchars($s['School_Type'])."</td><td><a href='edit_school.php?id=".intval($s['School_ID'])."' class='btn btn-sm btn-info me-1'>✏️ " . htmlspecialchars(t('edit')) . "</a><form method='POST' style='display:inline;'><input type='hidden' name='del_school' value='".intval($s['School_ID'])."'><input type='hidden' name='csrf_token' value='".htmlspecialchars(getCsrfToken())."'><button class='btn btn-sm btn-danger' onclick=\"return confirm('" . htmlspecialchars(t('confirm_delete_school')) . "')\">❌ " . htmlspecialchars(t('delete')) . "</button></form></td></tr>"; } } else { echo "<tr><td colspan='5' class='text-center text-muted'>" . htmlspecialchars(t('no_matching_schools')) . "</td></tr>"; } ?>
          </tbody>
        </table>
      </div></div>

    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
</script>
<script>
<?php $page_lang = function_exists('current_lang') ? current_lang() : 'ar'; $dt_lang_url = ($page_lang === 'en') ? 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/en-GB.json' : 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'; ?>
$(function(){ try{ $('#schoolsTable').DataTable({"order":[],"language":{"url":"<?php echo $dt_lang_url; ?>"}}); }catch(e){console.warn(e);} });

// Client-side safeguard: prevent empty submit and show message
document.addEventListener('DOMContentLoaded', function(){
  var form = document.getElementById('addSchoolForm');
  if (!form) return;
  form.addEventListener('submit', function(e){
    var id = form.querySelector('input[name="school_id"]').value.trim();
    var name = form.querySelector('input[name="school_name"]').value.trim();
    var office = form.querySelector('select[name="office_id"]').value;
    if (!id || !name || !office) {
      e.preventDefault();
      // show inline alert
      var container = document.querySelector('.col-md-9');
      if (container) {
        var existing = document.getElementById('addSchoolAlert');
        if (existing) existing.remove();
        var alert = document.createElement('div');
        alert.id = 'addSchoolAlert';
        alert.className = 'alert alert-danger';
        alert.textContent = '<?php echo htmlspecialchars(t('fill_required_fields_client')); ?>';
        container.insertBefore(alert, container.firstChild);
        setTimeout(function(){ if (alert) alert.remove(); }, 5000);
      } else {
        alert('<?php echo htmlspecialchars(t('fill_required_fields_client')); ?>');
      }
      return false;
    }
    return true;
  });
});
</script>
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
