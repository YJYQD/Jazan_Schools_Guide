<?php
require_once "db.php";
require_once "navbar.php";
require_once "security_helpers.php";
require_once "i18n.php";

$conn->set_charset("utf8mb4");
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') { header('Location: login.php'); exit; }
initiateCsrfToken();

$message=''; $message_type='';
// إضافة مدير مدرسة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_principal'])) {
  if (!validateCsrfToken($_POST['csrf_token'] ?? '')) { $message = t('security_verification_failed'); $message_type='error'; }
  else {
    $principal_username = trim($_POST['principal_username'] ?? '');
    $principal_password = $_POST['principal_password'] ?? '';
    $principal_password_confirm = $_POST['principal_password_confirm'] ?? '';
    if ($principal_username === '' || $principal_password === '' || $principal_password_confirm === '') { $message = t('fill_all_principal_fields'); $message_type='error'; }
    elseif (!isValidUsername($principal_username)) { $message = t('invalid_username'); $message_type='error'; }
    elseif (strlen($principal_password) < 8) { $message = t('password_too_short'); $message_type='error'; }
    elseif ($principal_password !== $principal_password_confirm) { $message = t('passwords_mismatch'); $message_type='error'; }
    else {
      $check = $conn->prepare("SELECT User_ID FROM Users WHERE Username=? LIMIT 1"); $check->bind_param('s',$principal_username); $check->execute(); $res = $check->get_result();
      if ($res && $res->num_rows>0) { $message = t('username_exists'); $message_type='error'; }
      else {
        $hashed = hashPassword($principal_password);
        $role = 'principal';
        $ins = $conn->prepare("INSERT INTO Users (Username, Password, Role) VALUES (?,?,?)"); $ins->bind_param('sss',$principal_username,$hashed,$role);
        if ($ins->execute()) { $message = t('principal_added_success'); $message_type='success'; } else { $message = t('principal_add_error'); $message_type='error'; }
      }
    }
  }
}

$principals = $conn->query("SELECT User_ID, Username, Role FROM Users WHERE Role='principal' ORDER BY User_ID DESC");
$page_lang = function_exists('current_lang') ? current_lang() : 'ar';

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($page_lang); ?>" dir="<?php echo $page_lang === 'en' ? 'ltr' : 'rtl'; ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars(t('school_principals') . ' - ' . t('admin')); ?></title>
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
      <h2 class="mb-4">👨‍🏫 <?php echo htmlspecialchars(t('school_principals')); ?></h2>
      <?php if(!empty($message)): ?><div class="alert alert-<?= $message_type=='success'?'success':'danger' ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><?php echo htmlspecialchars(t('school_principals')); ?></h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPrincipalModal"><?php echo htmlspecialchars(t('add_principal')); ?></button>
      </div>

      <!-- Modal -->
      <div class="modal fade" id="addPrincipalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?php echo htmlspecialchars(t('add_principal')); ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
            <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
              <div class="mb-2"><label class="form-label"><?php echo htmlspecialchars(t('username_placeholder')); ?></label><input name="principal_username" class="form-control" required></div>
              <div class="mb-2"><label class="form-label"><?php echo htmlspecialchars(t('password_placeholder')); ?></label><input type="password" name="principal_password" class="form-control" required></div>
              <div class="mb-2"><label class="form-label"><?php echo htmlspecialchars(t('confirm_password_placeholder')); ?></label><input type="password" name="principal_password_confirm" class="form-control" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo htmlspecialchars(t('close')); ?></button><button type="submit" name="add_principal" class="btn btn-primary"><?php echo htmlspecialchars(t('save')); ?></button></div>
            </form>
          </div>
        </div>
      </div>

      <div class="card"><div class="card-body table-responsive">
        <table id="principalsTable" class="table table-striped table-hover table-bordered align-middle">
          <thead><tr><th>#</th><th><?php echo htmlspecialchars(t('username_placeholder')); ?></th><th><?php echo htmlspecialchars(t('user_default')); ?></th></tr></thead>
          <tbody>
            <?php if($principals && $principals->num_rows>0) { while($p=$principals->fetch_assoc()){
              echo "<tr><td>".intval($p['User_ID'])."</td><td>".htmlspecialchars($p['Username'])."</td><td>".htmlspecialchars($p['Role'])."</td></tr>";
            }} else { echo "<tr><td colspan='3' class='text-center text-muted'>" . htmlspecialchars(t('no_principals')) . "</td></tr>"; } ?>
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
$(function(){
  try{
    $('#principalsTable').DataTable({
      "order":[],
      <?php $page_lang = function_exists('current_lang') ? current_lang() : 'ar'; $dt_lang_url = ($page_lang === 'en') ? 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/en-GB.json' : 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'; ?>
      "language":{ "url":"<?php echo $dt_lang_url; ?>" },
      "columns": [ null, null, null ]
    });
  }catch(e){console.warn(e);} 
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
