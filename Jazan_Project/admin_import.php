<?php
session_start();
require_once "db.php";
require_once "security_helpers.php";
require_once "i18n.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once "navbar.php";

initiateCsrfToken();

$message = '';
$message_type = '';
$page_lang = function_exists('current_lang') ? current_lang() : 'ar';

if (isset($_SESSION['import_flash'])) {
    $message = $_SESSION['import_flash']['message'] ?? '';
    $message_type = $_SESSION['import_flash']['type'] ?? '';
    unset($_SESSION['import_flash']);
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($page_lang); ?>" dir="<?php echo $page_lang === 'en' ? 'ltr' : 'rtl'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('import_page_title')); ?></title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            background:
                radial-gradient(circle at top right, rgba(99, 102, 241, 0.18), transparent 35%),
                radial-gradient(circle at left center, rgba(16, 185, 129, 0.16), transparent 32%),
                linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
            color: #0f172a;
        }

        .import-shell {
            max-width: 1100px;
            margin: 40px auto 70px;
            padding: 0 20px;
        }

        .hero {
            background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 55%, #0ea5e9 100%);
            color: white;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.18);
            margin-bottom: 24px;
        }

        .hero h1 {
            margin: 0 0 10px;
            font-size: clamp(2rem, 4vw, 3rem);
        }

        .hero p {
            margin: 0;
            color: rgba(255, 255, 255, 0.9);
            max-width: 760px;
            line-height: 1.9;
        }

        .layout {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 22px;
        }

        .panel {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }

        .panel h2 {
            margin-top: 0;
            color: #0f172a;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 18px;
            font-weight: 600;
        }

        .alert.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .drop-hint {
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            padding: 18px;
            background: #f8fafc;
            color: #334155;
            line-height: 1.9;
            margin-bottom: 18px;
        }

        .form-group {
            display: grid;
            gap: 8px;
            margin-bottom: 18px;
        }

        label {
            font-weight: 700;
            color: #1e293b;
        }

        input[type="file"] {
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            border-radius: 12px;
            padding: 12px 18px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #1d4ed8, #0ea5e9);
            color: white;
            box-shadow: 0 12px 28px rgba(29, 78, 216, 0.28);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #0f172a;
        }

        .checklist {
            display: grid;
            gap: 12px;
        }

        .check-item {
            padding: 14px 16px;
            border-radius: 14px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            border: 1px solid #e2e8f0;
        }

        .check-item strong {
            display: block;
            margin-bottom: 4px;
            color: #0f172a;
        }

        .mini-note {
            margin-top: 16px;
            color: #475569;
            font-size: 0.95rem;
            line-height: 1.8;
        }

        @media (max-width: 900px) {
            .layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php render_navbar('admin', true, 'admin.php', t('back_home_short')); ?>
    <div class="import-shell">
        <section class="hero">
            <h1><?php echo htmlspecialchars(t('bulk_data_import_heading')); ?></h1>
            <p><?php echo htmlspecialchars(t('bulk_data_import_description')); ?></p>
        </section>

        <?php if ($message): ?>
            <div class="alert <?php echo htmlspecialchars($message_type); ?>">
                <?php echo nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')); ?>
            </div>
        <?php endif; ?>

        <div class="layout">
            <section class="panel">
                <h2><?php echo htmlspecialchars(t('upload_csv_file')); ?></h2>
                <div class="drop-hint">
                    <?php echo htmlspecialchars(t('bulk_data_import_description')); ?>
                </div>

                <form action="process_import.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="form-group">
                        <label for="csv_file"><?php echo htmlspecialchars(t('csv_file')); ?></label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
                    </div>

                    <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars(t('import_data')); ?></button>
                    <a href="admin.php" class="btn btn-secondary" style="margin-right: 10px;"><?php echo htmlspecialchars(t('back_to_dashboard')); ?></a>
                </form>

                <p class="mini-note">
                    <?php echo htmlspecialchars(t('bulk_import_note')); ?>
                </p>
            </section>

            <aside class="panel">
                <h2><?php echo htmlspecialchars(t('quick_rules')); ?></h2>
                <div class="checklist">
                    <div class="check-item">
                        <strong><?php echo htmlspecialchars(t('unique_key')); ?></strong>
                        <?php echo htmlspecialchars(t('unique_key') . ' - ' . t('bulk_import_note')); ?>
                    </div>
                    <div class="check-item">
                        <strong><?php echo htmlspecialchars(t('encoding')); ?></strong>
                        <?php echo htmlspecialchars(t('encoding_note')); ?>
                    </div>
                    <div class="check-item">
                        <strong><?php echo htmlspecialchars(t('format')); ?></strong>
                        <?php echo htmlspecialchars(t('format_note')); ?>
                    </div>
                    <div class="check-item">
                        <strong><?php echo htmlspecialchars(t('performance')); ?></strong>
                        <?php echo htmlspecialchars(t('processing_note')); ?>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</body>
</html>
