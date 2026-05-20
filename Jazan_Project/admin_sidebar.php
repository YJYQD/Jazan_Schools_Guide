<?php
// لوحة جانبية محسنة لإعادة الاستخدام عبر صفحات الإدارة
require_once __DIR__ . '/i18n.php';
?>
<div class="d-flex">
    <nav class="d-flex flex-column flex-shrink-0 p-3 bg-light" style="width: 240px; min-height: 82vh; border-radius:12px; box-shadow: 0 6px 18px rgba(2,6,23,0.06);">
        <a href="dashboard.php" class="d-flex align-items-center mb-3 mb-md-0 text-decoration-none">
            <span class="fs-5 fw-bold"><?php echo htmlspecialchars(t('admin_dashboard')); ?></span>
        </a>

        <!-- Main -->
        <div class="mb-3">
            <h6 class="text-uppercase small text-muted mb-2"><?php echo htmlspecialchars(t('main_section')); ?></h6>
            <ul class="nav nav-pills flex-column">
                <li class="nav-item"><a href="dashboard.php" class="nav-link link-dark">🏠 <?php echo htmlspecialchars(t('summary')); ?></a></li>
            </ul>
        </div>

        <!-- Content -->
        <div class="mb-3">
            <h6 class="text-uppercase small text-muted mb-2"><?php echo htmlspecialchars(t('content_section')); ?></h6>
            <ul class="nav nav-pills flex-column">
                <li><a href="regions.php" class="nav-link link-dark">📍 <?php echo htmlspecialchars(t('regions')); ?></a></li>
                <li><a href="governorates.php" class="nav-link link-dark">🏙️ <?php echo htmlspecialchars(t('governorates_plural')); ?></a></li>
                <li><a href="offices.php" class="nav-link link-dark">🏛️ <?php echo htmlspecialchars(t('offices_plural')); ?></a></li>
                <li><a href="schools.php" class="nav-link link-dark">🏫 <?php echo htmlspecialchars(t('schools_plural')); ?></a></li>
            </ul>
        </div>

        <!-- Users / Media -->
        <div class="mb-3">
            <h6 class="text-uppercase small text-muted mb-2"><?php echo htmlspecialchars(t('users_media_section')); ?></h6>
            <ul class="nav nav-pills flex-column">
                <li><a href="principals.php" class="nav-link link-dark">👨‍🏫 <?php echo htmlspecialchars(t('school_principals')); ?></a></li>
                <li><a href="images.php" class="nav-link link-dark">🖼️ <?php echo htmlspecialchars(t('images')); ?></a></li>
            </ul>
        </div>

        <!-- Tools -->
        <div class="mb-3">
            <h6 class="text-uppercase small text-muted mb-2"><?php echo htmlspecialchars(t('data_tools_section')); ?></h6>
            <div class="d-grid gap-2">
                <a href="admin_import.php" class="btn btn-outline-primary btn-sm text-start" title="<?php echo htmlspecialchars(t('import_csv')); ?>">📥 <?php echo htmlspecialchars(t('import_csv')); ?></a>
                <a href="export_excel.php?format=csv" class="btn btn-outline-success btn-sm text-start" title="<?php echo htmlspecialchars(t('export_csv')); ?>">📤 <?php echo htmlspecialchars(t('export_csv')); ?></a>
                <a href="export_excel.php?format=xlsx" class="btn btn-outline-success btn-sm text-start" title="<?php echo htmlspecialchars(t('export_excel')); ?>">📤 <?php echo htmlspecialchars(t('export_excel')); ?></a>
                <a href="export_pdf.php?output=pdf" class="btn btn-outline-danger btn-sm text-start" title="<?php echo htmlspecialchars(t('export_pdf')); ?>">📄 <?php echo htmlspecialchars(t('export_pdf')); ?></a>
            </div>
        </div>

        <!-- Settings (admin only) -->
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <div class="mb-3">
                <h6 class="text-uppercase small text-muted mb-2"><?php echo htmlspecialchars(t('settings_section')); ?></h6>
                <ul class="nav nav-pills flex-column">
                    <li><a href="settings.php" class="nav-link link-dark">⚙️ <?php echo htmlspecialchars(t('system_settings')); ?></a></li>
                    <li><a href="backup_system.php" class="nav-link link-dark">💾 <?php echo htmlspecialchars(t('backups')); ?></a></li>
                    <li><a href="audit_trail.php" class="nav-link link-dark">📋 <?php echo htmlspecialchars(t('audit_trail')); ?></a></li>
                </ul>
            </div>
        <?php endif; ?>

        <hr>
        <div class="mt-auto">
            <a href="index.php" class="btn btn-outline-secondary btn-sm w-100"><?php echo htmlspecialchars(t('back_home')); ?></a>
        </div>
    </nav>
</div>
