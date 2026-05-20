0<?php
// migrate_translations.php
// Creates translation tables and seeds them from existing Schools and School_Reviews.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';
if (!isset($conn) || !$conn instanceof mysqli) {
    echo "DB connection not available. Check db.php\n";
    exit(1);
}

$argv_lang = $argv[1] ?? null;
$defaultLang = $argv_lang ?: 'ar';
echo "Using language: $defaultLang\n";

function translate_school_name_to_en($arabicName) {
    static $map = [
        'ابتدائية صبيا الأولى' => 'Sabya First Elementary School',
        'متوسطة صبيا الثانية' => 'Sabya Second Intermediate School',
        'ثانوية صبيا الكبرى' => 'Sabya Grand High School',
        'روضة أطفال صبيا' => 'Sabya Kindergarten',
        'مجمع أبو عريش التعليمي' => 'Abu Arish Educational Complex',
        'ابتدائية القدس' => 'Al-Quds Elementary School',
        'ابتدائية صامطة الأولى' => 'Samtah First Elementary School',
        'ثانوية حطين' => 'Hittin High School',
        'ابتدائية الدرب الأولى' => 'Al-Darb First Elementary School',
        'مجمع بيش التعليمي' => 'Bish Educational Complex',
        'ابتدائية الأحد الأولى' => 'Al-Ahad First Elementary School',
        'ثانوية العارضة الأولى' => 'Al-Aridah First High School',
        'متوسطة ضمد الأولى' => 'Damad First Intermediate School',
    ];

    return $map[$arabicName] ?? $arabicName;
}

function translate_review_comment_to_en($arabicComment) {
    static $map = [
        'تجربة 1' => 'Test 1',
    ];

    return $map[$arabicComment] ?? $arabicComment;
}

$sqls = [
    "CREATE TABLE IF NOT EXISTS School_Translations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        lang VARCHAR(5) NOT NULL,
        name TEXT,
        UNIQUE KEY ux_school_lang (school_id, lang),
        INDEX idx_school (school_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS Review_Translations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        review_id INT NOT NULL,
        lang VARCHAR(5) NOT NULL,
        comment TEXT,
        UNIQUE KEY ux_review_lang (review_id, lang),
        INDEX idx_review (review_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS ui_translations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        `key` VARCHAR(191) NOT NULL,
        lang VARCHAR(5) NOT NULL,
        text TEXT,
        UNIQUE KEY ux_key_lang (`key`, lang),
        INDEX idx_key (`key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($sqls as $s) {
    if ($conn->query($s) === false) {
        echo "Failed to run statement: " . $conn->error . "\n";
        exit(2);
    }
}

echo "Translation tables ensured.\n";

// Seed UI translations from i18n.php if available
$uiSeeded = 0;
$all = null;
if (function_exists('jazan_get_all_translations')) {
    $all = jazan_get_all_translations();
}
if (is_array($all)) {
    $insUi = $conn->prepare("INSERT INTO ui_translations (`key`, lang, text) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE text = VALUES(text)");
    foreach ($all as $lang => $map) {
        foreach ($map as $k => $v) {
            $insUi->bind_param('sss', $k, $lang, $v);
            $insUi->execute();
            $uiSeeded++;
        }
    }
    $insUi->close();
    echo "Seeded $uiSeeded UI translations.\n";
} else {
    echo "No UI translations available to seed.\n";
}

// Seed schools
$seeded = 0;
$res = $conn->query("SELECT School_ID, School_Name FROM Schools");
if ($res) {
    $ins = $conn->prepare("INSERT INTO School_Translations (school_id, lang, name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
    while ($r = $res->fetch_assoc()) {
        $id = (int)$r['School_ID'];
        $name = $defaultLang === 'en' ? translate_school_name_to_en($r['School_Name']) : $r['School_Name'];
        $ins->bind_param('iss', $id, $defaultLang, $name);
        $ins->execute();
        $seeded++;
    }
    $ins->close();
}

echo "Seeded $seeded school translations.\n";

// Seed reviews
$seededR = 0;
$res = $conn->query("SELECT Review_ID, Comment FROM School_Reviews");
if ($res) {
    $ins = $conn->prepare("INSERT INTO Review_Translations (review_id, lang, comment) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE comment = VALUES(comment)");
    while ($r = $res->fetch_assoc()) {
        $id = (int)$r['Review_ID'];
        $c = $defaultLang === 'en' ? translate_review_comment_to_en($r['Comment']) : $r['Comment'];
        $ins->bind_param('iss', $id, $defaultLang, $c);
        $ins->execute();
        $seededR++;
    }
    $ins->close();
}

echo "Seeded $seededR review translations.\n";

echo "Done.\n";

?>