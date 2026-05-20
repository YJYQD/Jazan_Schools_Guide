<?php
require_once "db.php";
require_once "navbar.php";
require_once "security_helpers.php";
require_once "i18n.php";

$conn->set_charset('utf8mb4');
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') { header('Location: login.php'); exit; }

$settingsFile = __DIR__ . DIRECTORY_SEPARATOR . 'settings.json';
$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
      $message = t('security_verification_failed'); $message_type = 'error';
    } else {
        $site_title = trim($_POST['site_title'] ?? '');
        $primary_color = trim($_POST['primary_color'] ?? '');
        $data = ['site_title' => $site_title, 'primary_color' => $primary_color];
      if (@file_put_contents($settingsFile, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))) {
        $message = t('settings_saved'); $message_type = 'success';
      } else {
        $message = t('settings_save_error'); $message_type = 'error';
      }
    }
}

$existing = [];
if (file_exists($settingsFile)) {
    $raw = @file_get_contents($settingsFile);
    $decoded = @json_decode($raw, true);
    if (is_array($decoded)) $existing = $decoded;
}

$page_lang = function_exists('current_lang') ? current_lang() : 'ar';

?>
<!DOCTYPE html>
<?php $page_dir = $page_lang === 'en' ? 'ltr' : 'rtl'; ?>
<html lang="<?php echo htmlspecialchars($page_lang); ?>" dir="<?php echo htmlspecialchars($page_dir); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars(t('settings_page_title')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php render_navbar('settings', true, 'admin.php', t('back_home_short')); ?>
<div class="container-fluid py-4">
  <div class="row">
    <div class="col-md-3"><?php include 'admin_sidebar.php'; ?></div>
    <div class="col-md-9">
      <h2 class="mb-4">⚙️ <?php echo htmlspecialchars(t('system_settings')); ?></h2>
      <?php if($message): ?><div class="alert alert-<?= $message_type=='success'?'success':'danger' ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>

      <div class="card"><div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
          <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(t('site_title_label')); ?></label>
            <input name="site_title" class="form-control" value="<?= htmlspecialchars($existing['site_title'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(t('primary_color_label')); ?></label>
            <input type="color" name="primary_color" class="form-control form-control-color" value="<?= htmlspecialchars($existing['primary_color'] ?? '#003366') ?>">
          </div>
          <div class="mb-3">
            <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(t('save_settings_button')); ?></button>
          </div>
        </form>
      </div></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
