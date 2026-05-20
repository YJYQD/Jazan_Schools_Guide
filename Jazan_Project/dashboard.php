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

// توليد CSRF Token
initiateCsrfToken();

// الحصول على الإحصائيات
$regions_count = db_fetch_count($conn, "SELECT COUNT(*) AS total FROM Regions");
$gov_count = db_fetch_count($conn, "SELECT COUNT(*) AS total FROM Governorates");
$offices_count = db_fetch_count($conn, "SELECT COUNT(*) AS total FROM Offices");
$schools_count = db_fetch_count($conn, "SELECT COUNT(*) AS total FROM Schools");
$principals_count = db_fetch_count($conn, "SELECT COUNT(*) AS total FROM Users WHERE Role = 'principal'");
$schools_with_images_count = db_fetch_count($conn, "SELECT COUNT(*) AS total FROM Schools WHERE School_Image IS NOT NULL AND School_Image <> ''");
$dash_lang = function_exists('current_lang') ? current_lang() : 'ar';

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($dash_lang); ?>" dir="<?php echo $dash_lang === 'en' ? 'ltr' : 'rtl'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($dash_lang === 'en' ? 'Dashboard - Admin Panel' : 'Dashboard - لوحة التحكم'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
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
                <h2 class="mb-4" style="color: var(--primary);">⚙️ <?php echo htmlspecialchars(t('summary')); ?></h2>

                <div class="row row-cols-1 row-cols-md-3 g-3">
                    <div class="col">
                        <div class="card p-3 h-100">
                            <div class="card-body text-center">
                                <h3 class="text-primary"><?= intval($regions_count) ?></h3>
                                <p class="mb-0"><?php echo htmlspecialchars(t('regions')); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card p-3 h-100">
                            <div class="card-body text-center">
                                <h3 class="text-primary"><?= intval($gov_count) ?></h3>
                                <p class="mb-0"><?php echo htmlspecialchars(t('governorates_plural')); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card p-3 h-100">
                            <div class="card-body text-center">
                                <h3 class="text-primary"><?= intval($offices_count) ?></h3>
                                <p class="mb-0"><?php echo htmlspecialchars(t('offices_plural')); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card p-3 h-100">
                            <div class="card-body text-center">
                                <h3 class="text-primary"><?= intval($schools_count) ?></h3>
                                <p class="mb-0"><?php echo htmlspecialchars(t('schools_plural')); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card p-3 h-100">
                            <div class="card-body text-center">
                                <h3 class="text-primary"><?= intval($principals_count) ?></h3>
                                <p class="mb-0"><?php echo htmlspecialchars(t('school_principals')); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card p-3 h-100">
                            <div class="card-body text-center">
                                <h3 class="text-primary"><?= intval($schools_with_images_count) ?></h3>
                                <p class="mb-0"><?php echo htmlspecialchars($dash_lang === 'en' ? 'Schools with images' : 'مدارس لها صور'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- الميزات المتقدمة -->
    <div class="container py-3">
        <div class="card mb-4">
            <div class="card-body">
                <h3 class="mb-3"><?php echo htmlspecialchars($dash_lang === 'en' ? 'Advanced Features' : 'الميزات المتقدمة'); ?> 📊</h3>
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="stats_dashboard.php" class="d-block p-4 rounded-3 text-white" style="background: linear-gradient(135deg,#6366f1 0%,#7c3aed 100%); text-decoration:none;">
                            <h5><?php echo htmlspecialchars($dash_lang === 'en' ? 'Statistics Dashboard' : 'لوحة الإحصائيات'); ?></h5>
                            <p class="mb-0 small"><?php echo htmlspecialchars($dash_lang === 'en' ? 'Charts and advanced indicators' : 'رسوم بيانية ومؤشرات متقدمة'); ?></p>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="audit_trail.php" class="d-block p-4 rounded-3 text-white" style="background: linear-gradient(135deg,#a78bfa 0%,#f093fb 100%); text-decoration:none;">
                            <h5><?php echo htmlspecialchars($dash_lang === 'en' ? 'Audit Trail' : 'سجل العمليات'); ?></h5>
                            <p class="mb-0 small"><?php echo htmlspecialchars($dash_lang === 'en' ? 'Track all changes' : 'تتبع جميع التغييرات'); ?></p>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="admin_import.php" class="d-block p-4 rounded-3 text-white" style="background: linear-gradient(135deg,#ff7a18 0%,#fb4a00 100%); text-decoration:none;">
                            <h5><?php echo htmlspecialchars(t('import_csv')); ?></h5>
                            <p class="mb-0 small"><?php echo htmlspecialchars($dash_lang === 'en' ? 'Upload school data in bulk' : 'رفع بيانات المدارس دفعة واحدة'); ?></p>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="backup_system.php" class="d-block p-4 rounded-3 text-white" style="background: linear-gradient(135deg,#00b894 0%,#00796b 100%); text-decoration:none;">
                            <h5><?php echo htmlspecialchars($dash_lang === 'en' ? 'Backups' : 'النسخ الاحتياطية'); ?></h5>
                            <p class="mb-0 small"><?php echo htmlspecialchars($dash_lang === 'en' ? 'Save data safely' : 'حفظ البيانات بأمان'); ?></p>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="mb-3"><?php echo htmlspecialchars($dash_lang === 'en' ? 'Data Export' : 'تصدير البيانات'); ?> 📤</h3>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="export_pdf.php?output=pdf" class="btn btn-lg btn-danger flex-fill"><?php echo htmlspecialchars(t('export_pdf')); ?> 📄</a>
                    <a href="export_excel.php?format=csv" class="btn btn-lg btn-success flex-fill"><?php echo htmlspecialchars(t('export_csv')); ?> 📊</a>
                    <a href="export_excel.php?format=xlsx" class="btn btn-lg btn-primary flex-fill"><?php echo htmlspecialchars(t('export_excel')); ?> 📈</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
