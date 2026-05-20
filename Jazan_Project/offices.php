<?php
require_once "db.php";
require_once "navbar.php";
require_once "security_helpers.php";
require_once "i18n.php";

$conn->set_charset("utf8mb4");
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') { header('Location: login.php'); exit; }
initiateCsrfToken();

$message=''; $message_type='';
// إضافة مكتب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_office'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) { $message = t('security_verification_failed'); $message_type='error'; }
    else {
        $office_name = trim($_POST['office_name'] ?? '');
        $gov_id = intval($_POST['gov_id'] ?? 0);
        if ($office_name !== '' && $gov_id>0) {
            $stmt = $conn->prepare("INSERT INTO Offices (Office_Name, Gov_ID) VALUES (?,?)");
            $stmt->bind_param('si',$office_name,$gov_id);
        if ($stmt->execute()) { $message = t('office_added_success'); $message_type='success'; }
        else { $message = t('office_add_error'); $message_type='error'; }
        } else { $message = t('fill_required_fields_client'); $message_type='error'; }
    }
}
// حذف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['del_office'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) { $message = t('security_verification_failed'); $message_type='error'; }
    else { $office_id = intval($_POST['del_office']); $stmt = $conn->prepare("DELETE FROM Offices WHERE Office_ID=?"); $stmt->bind_param('i',$office_id); if ($stmt->execute()) { $message = t('office_deleted_success'); $message_type='success'; } }
}

$regions = $conn->query("SELECT * FROM Regions ORDER BY Region_Name");
$offices = $conn->query("SELECT o.*, g.Gov_Name FROM Offices o LEFT JOIN Governorates g ON g.Gov_ID = o.Gov_ID ORDER BY o.Office_Name");
$page_lang = function_exists('current_lang') ? current_lang() : 'ar';

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($page_lang); ?>" dir="<?php echo $page_lang === 'en' ? 'ltr' : 'rtl'; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo htmlspecialchars(t('offices_plural') . ' - ' . t('admin')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php render_navbar('admin', true, 'index.php', t('back_home_short')); ?>
<div class="container-fluid py-4">
  <div class="row">
    <div class="col-md-3"><?php include 'admin_sidebar.php'; ?></div>
    <div class="col-md-9">
      <h2 class="mb-4">🏛️ <?php echo htmlspecialchars(t('offices_plural')); ?></h2>
      <?php if(!empty($message)): ?><div class="alert alert-<?= $message_type=='success'?'success':'danger' ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><?php echo htmlspecialchars(t('offices_list')); ?></h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOfficeModal"><?php echo htmlspecialchars(t('add_office')); ?></button>
      </div>

      <!-- Modal -->
      <div class="modal fade" id="addOfficeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?php echo htmlspecialchars(t('add_office')); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
            <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
              <div class="mb-2"><label class="form-label"><?php echo htmlspecialchars(t('governorate')); ?></label>
                <select name="gov_id" class="form-select" required>
                  <option value=""><?php echo htmlspecialchars(t('choose_governorate')); ?></option>
                  <?php
                    $rg = $conn->query("SELECT g.Gov_ID, g.Gov_Name, r.Region_Name FROM Governorates g LEFT JOIN Regions r ON r.Region_ID = g.Region_ID ORDER BY r.Region_Name, g.Gov_Name");
                    while($row = $rg->fetch_assoc()) {
                        echo "<option value='".intval($row['Gov_ID'])."'>".htmlspecialchars($row['Region_Name'])." - ".htmlspecialchars($row['Gov_Name'])."</option>";
                    }
                  ?>
                </select>
              </div>
              <div class="mb-2"><label class="form-label"><?php echo htmlspecialchars(t('name')); ?></label><input name="office_name" class="form-control" placeholder="<?php echo htmlspecialchars(t('enter_office_name')); ?>" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo htmlspecialchars(t('close')); ?></button><button type="submit" name="add_office" class="btn btn-primary"><?php echo htmlspecialchars(t('save')); ?></button></div>
            </form>
          </div>
        </div>
      </div>

      <div class="card"><div class="card-body table-responsive">
        <table id="officesTable" class="table table-striped table-hover table-bordered align-middle">
          <thead><tr><th><?php echo htmlspecialchars(t('name')); ?></th><th><?php echo htmlspecialchars(t('governorate')); ?></th><th><?php echo htmlspecialchars(t('actions')); ?></th></tr></thead>
          <tbody>
            <?php
            // refresh offices result set in case it was used earlier
            $offices = $conn->query("SELECT o.*, g.Gov_Name FROM Offices o LEFT JOIN Governorates g ON g.Gov_ID = o.Gov_ID ORDER BY o.Office_Name");
            if($offices && $offices->num_rows>0) {
                while($o=$offices->fetch_assoc()){
                    echo "<tr><td>".htmlspecialchars($o['Office_Name'])."</td><td>".htmlspecialchars($o['Gov_Name'])."</td><td>".
                         "<form method='POST' style='display:inline;'>".
                         "<input type='hidden' name='del_office' value='".intval($o['Office_ID'])."'>".
                         "<input type='hidden' name='csrf_token' value='".htmlspecialchars(getCsrfToken())."'>".
                         "<button class='btn btn-sm btn-danger' onclick=\"return confirm('" . htmlspecialchars(t('confirm_delete_office')) . "')\">❌ " . htmlspecialchars(t('delete')) . "</button>".
                         "</form>".
                         "</td></tr>";
                }
            } else {
              echo "<tr><td colspan='3' class='text-center text-muted'>" . htmlspecialchars(t('no_offices')) . "</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div></div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <?php $page_lang = function_exists('current_lang') ? current_lang() : 'ar'; $dt_lang_url = ($page_lang === 'en') ? 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/en-GB.json' : 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'; ?>
  <script>$(function(){ try{ $('#officesTable').DataTable({"order":[],"language":{"url":"<?php echo $dt_lang_url; ?>"}}); }catch(e){console.warn(e);} });</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
