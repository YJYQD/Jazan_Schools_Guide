<?php
require_once "db.php";
require_once "navbar.php";
require_once "security_helpers.php";
require_once "i18n.php";

$conn->set_charset("utf8mb4");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit;
}

initiateCsrfToken();

$message = '';
$message_type = '';

// تعديل المنطقة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_region'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) { $message = t('security_verification_failed'); $message_type = 'error'; }
    else {
        $rid = intval($_POST['region_id'] ?? 0);
        $rname = trim($_POST['region_name'] ?? '');
        if ($rid > 0 && $rname !== '') {
            $stmt = $conn->prepare("UPDATE Regions SET Region_Name = ? WHERE Region_ID = ?");
            $stmt->bind_param('si', $rname, $rid);
            if ($stmt->execute()) { $message = t('region_update_success'); $message_type = 'success'; }
            else { $message = t('region_update_error'); $message_type = 'error'; }
        }
    }
}

// معالجة الإضافة
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

// معالجة الحذف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['del_region'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = t('security_verification_failed');
        $message_type = 'error';
    } else {
        $region_id = intval($_POST['del_region']);
        $stmt = $conn->prepare("DELETE FROM Regions WHERE Region_ID=?");
        $stmt->bind_param("i", $region_id);
        if ($stmt->execute()) { $message = t('region_deleted_success'); $message_type = 'success'; }
    }
}

// جلب المناطق
$regions = $conn->query("SELECT * FROM Regions ORDER BY Region_Name");
$page_lang = function_exists('current_lang') ? current_lang() : 'ar';
// DataTables language file based on current language
$dt_lang_url = ($page_lang === 'en') ? 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/en-GB.json' : 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json';

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($page_lang); ?>" dir="<?php echo $page_lang === 'en' ? 'ltr' : 'rtl'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('regions') . ' - ' . t('admin')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <?php render_navbar('admin', true, 'index.php', t('back_home_short')); ?>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-md-3">
                <?php include 'admin_sidebar.php'; ?>
            </div>
            <div class="col-md-9">
                <h2 class="mb-4">📍 <?php echo htmlspecialchars(t('regions')); ?></h2>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= $message_type == 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0"><?php echo htmlspecialchars(t('regions_list')); ?></h5>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRegionModal"><?php echo htmlspecialchars(t('add_region')); ?></button>
                                </div>

                                <!-- Add Region Modal -->
                                <div class="modal fade" id="addRegionModal" tabindex="-1" aria-labelledby="addRegionModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-sm modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="addRegionModalLabel"><?php echo htmlspecialchars(t('add_region')); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST">
                                            <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                                                        <div class="mb-2">
                                                                <label class="form-label"><?php echo htmlspecialchars(t('name')); ?></label>
                                                                <input type="text" name="region_name" class="form-control" placeholder="<?php echo htmlspecialchars(t('enter_region_name')); ?>" required>
                                                        </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo htmlspecialchars(t('close')); ?></button>
                                                <button type="submit" name="add_region" class="btn btn-primary"><?php echo htmlspecialchars(t('save')); ?></button>
                                            </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                <div class="card">
                    <div class="card-body table-responsive">
                        <table id="regionsTable" class="table table-striped table-hover table-bordered align-middle">
                            <thead>
                                <tr><th><?php echo htmlspecialchars(t('name')); ?></th><th><?php echo htmlspecialchars(t('actions')); ?></th></tr>
                            </thead>
                            <tbody>
                                <?php
                                // re-run query to ensure fresh result set in case it was consumed earlier
                                $regions = $conn->query("SELECT * FROM Regions ORDER BY Region_Name");
                                ?>
                                <?php
                                if ($regions && $regions->num_rows > 0) {
                                    while ($r = $regions->fetch_assoc()) {
                                        echo "<tr>",
                                             "<td>" . htmlspecialchars($r['Region_Name']) . "</td>",
                                             "<td>",
                                                "<button type='button' class='btn btn-sm btn-info btn-edit-region' data-id='".intval($r['Region_ID'])."' data-name='".htmlspecialchars($r['Region_Name'], ENT_QUOTES)."'>✏️ " . htmlspecialchars(t('edit')) . "</button>",
                                                " ",
                                                "<form method='POST' style='display:inline;margin-left:6px;'>",
                                                    "<input type='hidden' name='del_region' value='" . intval($r['Region_ID']) . "'>",
                                                    "<input type='hidden' name='csrf_token' value='" . htmlspecialchars(getCsrfToken()) . "'>",
                                                    "<button type='submit' onclick=\"return confirm('" . htmlspecialchars(t('confirm_delete_region')) . "')\" class='btn btn-sm btn-danger'>❌ " . htmlspecialchars(t('delete')) . "</button>",
                                                "</form>",
                                             "</td>",
                                        "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='2' class='text-center text-muted'>" . htmlspecialchars(t('no_matching_schools')) . "</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                                <!-- Edit Region Modal -->
                                <div class="modal fade" id="editRegionModal" tabindex="-1" aria-labelledby="editRegionModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-sm modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editRegionModalLabel"><?php echo htmlspecialchars(t('edit_region')); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST">
                                            <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                                                        <input type="hidden" name="region_id" id="edit_region_id" value="">
                                                        <div class="mb-2">
                                                                    <label class="form-label"><?php echo htmlspecialchars(t('name')); ?></label>
                                                                <input type="text" name="region_name" id="edit_region_name" class="form-control" required>
                                                        </div>
                                            </div>
                                            <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo htmlspecialchars(t('close')); ?></button>
                                                            <button type="submit" name="edit_region" class="btn btn-primary"><?php echo htmlspecialchars(t('save')); ?></button>
                                            </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function(){
            try {
                $('#regionsTable').DataTable({
                    "order": [],
                    "language": { "url": "<?php echo $dt_lang_url; ?>" }
                });
            } catch (e) {
                console.warn('DataTables init failed', e);
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // handle edit button clicks
        document.addEventListener('DOMContentLoaded', function(){
            document.querySelectorAll('.btn-edit-region').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    var id = this.getAttribute('data-id');
                    var name = this.getAttribute('data-name');
                    document.getElementById('edit_region_id').value = id;
                    document.getElementById('edit_region_name').value = name;
                    var modal = new bootstrap.Modal(document.getElementById('editRegionModal'));
                    modal.show();
                });
            });
        });
    </script>
</body>
</html>
