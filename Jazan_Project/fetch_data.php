
<?php
require_once "db.php";
$filtersHelper = __DIR__ . '/filters_helper.php';
if (file_exists($filtersHelper)) {
    require_once $filtersHelper;
}
$rootI18n = __DIR__ . '/i18n.php';
if (file_exists($rootI18n)) {
    require_once $rootI18n;
}
$conn->set_charset("utf8mb4");

$current_lang = function_exists('current_lang') ? current_lang() : 'ar';

// Prefer explicit language from AJAX request when provided
$current_lang = trim((string)($_REQUEST['jazan_lang'] ?? $current_lang));

// مهم: تحديد نوع الرد
header("Content-Type: text/html; charset=utf-8");

// ================= المحافظات =================
$regionIdRaw = trim((string)($_POST['region_id'] ?? ''));
$govIdRaw = trim((string)($_POST['gov_id'] ?? ''));

if ($regionIdRaw !== '' && $govIdRaw === '') {
    $rid = (int) $regionIdRaw;

    $stmt = $conn->prepare("SELECT Gov_ID, Gov_Name FROM Governorates WHERE Region_ID = ? ORDER BY Gov_Name ASC");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    ob_start();
    echo '<option value="">' . htmlspecialchars(t('all_governorates')) . '</option>';

    foreach ($rows as $row) {
        echo '<option value="'.$row['Gov_ID'].'">'.htmlspecialchars(translate_db_text($row['Gov_Name'], $current_lang)).'</option>';
    }

    $html = ob_get_clean();
    echo $html;
    exit();
}

// ================= المكاتب =================
if ($govIdRaw !== '') {
    $gid = (int) $govIdRaw;

    $stmt = $conn->prepare("SELECT Office_ID, Office_Name FROM Offices WHERE Gov_ID = ? ORDER BY Office_Name ASC");
    $stmt->bind_param("i", $gid);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    ob_start();
    echo '<option value="">' . htmlspecialchars(t('all_offices')) . '</option>';

    foreach ($rows as $row) {
        echo '<option value="'.$row['Office_ID'].'">'.htmlspecialchars(translate_db_text($row['Office_Name'], $current_lang)).'</option>';
    }

    $html = ob_get_clean();
    echo $html;
    exit();
}
?>

