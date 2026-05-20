<?php
require_once "db.php";
require_once "navbar.php";
require_once "security_helpers.php";
require_once "i18n.php";

$conn->set_charset("utf8mb4");
$page_lang = function_exists('current_lang') ? current_lang() : 'ar';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_gov'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = t('security_verification_failed');
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
        } else {
            $message = t('fill_required_fields_client');
            $message_type = 'error';
        }
    }
}

// تعديل محافظة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_gov'])) {
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) { $message = t('security_verification_failed'); $message_type='error'; }
    else {
        $gov_id = intval($_POST['gov_id'] ?? 0);
        $gov_name = trim($_POST['gov_name'] ?? '');
        $region_id = intval($_POST['region_id'] ?? 0);
        if ($gov_id > 0 && $gov_name !== '' && $region_id > 0) {
            $stmt = $conn->prepare("UPDATE Governorates SET Gov_Name = ?, Region_ID = ? WHERE Gov_ID = ?");
            $stmt->bind_param('sii', $gov_name, $region_id, $gov_id);
            if ($stmt->execute()) { $message = t('governorate_update_success'); $message_type='success'; }
            else { $message = t('governorate_update_error'); $message_type='error'; }
        }
    }
}

// حذف محافظة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['del_gov'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = t('security_verification_failed');
        $message_type = 'error';
    } else {
        $gov_id = intval($_POST['del_gov']);
        $stmt = $conn->prepare("DELETE FROM Governorates WHERE Gov_ID=?");
        $stmt->bind_param("i", $gov_id);
        if ($stmt->execute()) { $message = t('governorate_deleted_success'); $message_type = 'success'; }
    }
}

// جلب بيانات المناطق والقوائم
$regions_list = $conn->query("SELECT * FROM Regions ORDER BY Region_Name");
$govs = $conn->query("SELECT g.*, r.Region_Name FROM Governorates g LEFT JOIN Regions r ON r.Region_ID = g.Region_ID ORDER BY r.Region_Name, g.Gov_Name");

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($page_lang); ?>" dir="<?php echo $page_lang === 'en' ? 'ltr' : 'rtl'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('governorates_plural') . ' - ' . t('admin')); ?></title>
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
                <h2 class="mb-4">🏙️ <?php echo htmlspecialchars(t('governorates_plural')); ?></h2>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= $message_type == 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0"><?php echo htmlspecialchars(t('governorates_list')); ?></h5>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGovModal"><?php echo htmlspecialchars(t('add_governorate')); ?></button>
                </div>

                <!-- Add Governorate Modal -->
                <div class="modal fade" id="addGovModal" tabindex="-1" aria-labelledby="addGovModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-sm modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                                                <h5 class="modal-title" id="addGovModalLabel"><?php echo htmlspecialchars(t('add_governorate')); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <form method="POST">
                      <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                            <div class="mb-2">
                                <label class="form-label"><?php echo htmlspecialchars(t('region')); ?></label>
                                <select name="region_id" class="form-select" required>
                                    <option value=""><?php echo htmlspecialchars(t('choose_region')); ?></option>
                                    <?php
                                    if ($regions_list && $regions_list->num_rows > 0) {
                                        while ($rr = $regions_list->fetch_assoc()) {
                                            echo "<option value='" . intval($rr['Region_ID']) . "'>" . htmlspecialchars($rr['Region_Name']) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label"><?php echo htmlspecialchars(t('name')); ?></label>
                                <input type="text" name="gov_name" class="form-control" placeholder="<?php echo htmlspecialchars(t('enter_gov_name')); ?>" required>
                            </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo htmlspecialchars(t('close')); ?></button>
                        <button type="submit" name="add_gov" class="btn btn-primary"><?php echo htmlspecialchars(t('save')); ?></button>
                      </div>
                      </form>
                    </div>
                  </div>
                </div>

                                <!-- Edit Governorate Modal -->
                                <div class="modal fade" id="editGovModal" tabindex="-1" aria-labelledby="editGovModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-sm modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editGovModalLabel"><?php echo htmlspecialchars(t('edit_gov')); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST">
                                            <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                                                        <input type="hidden" name="gov_id" id="edit_gov_id" value="">
                                                        <div class="mb-2">
                                                            <label class="form-label"><?php echo htmlspecialchars(t('region')); ?></label>
                                                            <select name="region_id" id="edit_region_select" class="form-select" required>
                                                                <option value=""><?php echo htmlspecialchars(t('choose_region')); ?></option>
                                                                        <?php
                                                                        $rr = $conn->query("SELECT * FROM Regions ORDER BY Region_Name");
                                                                        while ($rrow = $rr->fetch_assoc()) {
                                                                                echo "<option value='".intval($rrow['Region_ID'])."'>".htmlspecialchars($rrow['Region_Name'])."</option>";
                                                                        }
                                                                        ?>
                                                                </select>
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label"><?php echo htmlspecialchars(t('name')); ?></label>
                                                            <input type="text" name="gov_name" id="edit_gov_name" class="form-control" required>
                                                        </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo htmlspecialchars(t('close')); ?></button>
                                                <button type="submit" name="edit_gov" class="btn btn-primary"><?php echo htmlspecialchars(t('save')); ?></button>
                                            </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                <div class="card">
                    <div class="card-body table-responsive">
                        <table id="govTable" class="table table-striped table-hover table-bordered align-middle">
                            <thead>
                                <tr><th><?php echo htmlspecialchars(t('name')); ?></th><th><?php echo htmlspecialchars(t('region')); ?></th><th><?php echo htmlspecialchars(t('actions')); ?></th></tr>
                            </thead>
                            <tbody>
                                <?php
                                // ensure fresh result set in case it was consumed earlier
                                $govs = $conn->query("SELECT g.*, r.Region_Name FROM Governorates g LEFT JOIN Regions r ON r.Region_ID = g.Region_ID ORDER BY r.Region_Name, g.Gov_Name");
                                if ($govs && $govs->num_rows > 0) {
                                    while ($g = $govs->fetch_assoc()) {
                                        echo "<tr>",
                                             "<td>" . htmlspecialchars($g['Gov_Name']) . "</td>",
                                             "<td>" . htmlspecialchars($g['Region_Name']) . "</td>",
                                             "<td>",
                                                "<button type='button' class='btn btn-sm btn-info btn-edit-gov' data-id='".intval($g['Gov_ID'])."' data-name='".htmlspecialchars($g['Gov_Name'], ENT_QUOTES)."' data-region='".intval($g['Region_ID'])."'>✏️ " . htmlspecialchars(t('edit')) . "</button>",
                                                " ",
                                                "<form method='POST' style='display:inline;'>",
                                                    "<input type='hidden' name='del_gov' value='" . intval($g['Gov_ID']) . "'>",
                                                    "<input type='hidden' name='csrf_token' value='" . htmlspecialchars(getCsrfToken()) . "'>",
                                                    "<button type='submit' onclick=\"return confirm('" . htmlspecialchars(t('confirm_delete_gov')) . "')\" class='btn btn-sm btn-danger'>❌ " . htmlspecialchars(t('delete')) . "</button>",
                                                "</form>",
                                             "</td>",
                                        "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='3' class='text-center text-muted'>" . htmlspecialchars(t('no_governorates')) . "</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
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
                $('#govTable').DataTable({
                    "order": [],
                    "language": { "url": "<?php $page_lang = function_exists('current_lang') ? current_lang() : 'ar'; echo ($page_lang === 'en') ? 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/en-GB.json' : 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'; ?>" }
                });
            } catch (e) { console.warn('DataTables init failed', e); }
        });
        // edit governorate
        document.addEventListener('DOMContentLoaded', function(){
            document.querySelectorAll('.btn-edit-gov').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var id = this.getAttribute('data-id');
                    var name = this.getAttribute('data-name');
                    var region = this.getAttribute('data-region');
                    document.getElementById('edit_gov_id').value = id;
                    document.getElementById('edit_gov_name').value = name;
                    var sel = document.getElementById('edit_region_select');
                    if (sel) { sel.value = region; }
                    var modal = new bootstrap.Modal(document.getElementById('editGovModal'));
                    modal.show();
                });
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
