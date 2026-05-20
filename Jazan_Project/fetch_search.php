<?php
/**
 * ملف معالجة البحث - مشروع المملكة
 * يستقبل البيانات من نموذج البحث ويعيد نتائج المدارس كـ HTML
 */
require_once "db.php";
require_once "filters_helper.php";
require_once "security_helpers.php";
require_once __DIR__ . '/i18n.php';

generate_csrf_token();

$conn->set_charset("utf8mb4");
$current_lang = function_exists('current_lang') ? current_lang() : 'ar';

// التحقق من الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit("<p>❌ " . htmlspecialchars(t('invalid_request')) . "</p>");
}

// بناء شروط الفلاتر الموحدة
$baseSql = "FROM Schools 
    LEFT JOIN Offices ON Schools.Office_ID = Offices.Office_ID 
    LEFT JOIN Governorates ON Offices.Gov_ID = Governorates.Gov_ID 
    WHERE 1=1";

$params = [];
$types = "";
$filterConditions = buildFilterConditions($params, $types);
$baseSql .= $filterConditions;

// Pagination
$perPage = defined('SCHOOLS_PER_PAGE') ? SCHOOLS_PER_PAGE : 10;
$page = max(1, intval($_POST['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Count total
$countSql = "SELECT COUNT(*) as total " . $baseSql;
$total = db_fetch_count($conn, $countSql, $types, $params);

// execute select with limit/offset
$selectSql = "SELECT Schools.*, Offices.Office_Name, Governorates.Gov_Name " . $baseSql . " ORDER BY Schools.School_Name ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($selectSql);
$bindParams = array_merge($params, [$perPage, $offset]);
if (!empty($params)) {
    $stmt->bind_param($types . 'ii', ...$bindParams);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
// fetch rows into array
$schools = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $schools[] = $row;
}
$stmt->close();

// Batch fetch reviews for displayed schools
$schoolIds = array_column($schools, 'School_ID');
$reviewsBySchool = [];
if (!empty($schoolIds)) {
    $placeholders = implode(',', array_fill(0, count($schoolIds), '?'));
    $revSql = "SELECT Review_ID, School_ID, Visitor_Name, Comment, Review_Date FROM School_Reviews WHERE School_ID IN ($placeholders) ORDER BY Review_Date DESC";
    $revStmt = $conn->prepare($revSql);
    $revTypes = str_repeat('i', count($schoolIds));
    $revStmt->bind_param($revTypes, ...$schoolIds);
    $revStmt->execute();
    $revRes = $revStmt->get_result();
    while ($r = $revRes->fetch_assoc()) {
        $reviewsBySchool[$r['School_ID']][] = $r;
    }
    $revStmt->close();
}

if (count($schools) > 0) {
    foreach ($schools as $row) {
        $sid = $row['School_ID'];
        $city_val = htmlspecialchars(translate_db_text($row['City'] ?? '', $current_lang));
        $gov_val  = htmlspecialchars(translate_db_text($row['Gov_Name'] ?? 'المملكة', $current_lang));
        $full_loc = (trim($city_val) == trim($gov_val)) ? $gov_val : $city_val . "، " . $gov_val;
        $t_color = ($row['School_Type'] == 'حكومي') ? '#0369a1' : '#16a34a';
        ?>
        <div class="school-card">
            <div class="card-header">
                <div>
                    <h3 style="margin:0 0 15px 0; color:#003366; font-size:1.7rem;"><?php echo htmlspecialchars(get_localized_field($conn, 'Schools', 'School_ID', intval($row['School_ID']), 'School_Name', translate_db_text($row['School_Name'], $current_lang))); ?></h3>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <span class="badge" style="background:#f0f9ff; color:<?php echo $t_color; ?>; border:1px solid <?php echo $t_color; ?>;">🏛️ <?php echo htmlspecialchars(translate_term('school_type', $row['School_Type'])); ?></span>
                        <span class="badge" style="background:#e0f2fe; color:#0369a1;">🎓 <?php echo htmlspecialchars(translate_term('education_level', $row['Education_Level'])); ?></span>
                        <span class="badge" style="background:#fff7ed; color:#c2410c; border:1px solid #ffedd5;">📍 <?php echo $full_loc; ?></span>
                    </div>
                    <p style="margin-top:15px; font-size:1rem; color:#64748b;">
                        🏢 <?php echo htmlspecialchars(t('office_label')); ?>: <b><?php echo htmlspecialchars(translate_db_text($row['Office_Name'] ?? '', $current_lang)); ?></b> | 
                        🏅 <?php echo htmlspecialchars(t('rating_label')); ?>: <span style="color:#fbbf24; font-weight:bold; font-size:1.1rem;"><?php echo htmlspecialchars(translate_db_text($row['Ministerial_Rating'] ?? '', $current_lang)); ?> ⭐</span>
                    </p>
                </div>
                <?php
                $has_coordinates = !empty($row['Latitude']) && !empty($row['Longitude']);
                if ($has_coordinates) {
                    echo '<a href="map_integration.php?view=school&school=' . intval($sid) . '" class="btn-map">' . htmlspecialchars(t('school_location')) . '</a>';
                } else {
                    $website = trim($row['School_Website'] ?? '');
                    $website = filter_var($website, FILTER_SANITIZE_URL);
                    if (!empty($website)) {
                        echo '<a href="' . htmlspecialchars($website) . '" target="_blank" rel="noopener noreferrer" class="btn-map">' . htmlspecialchars(t('website_link')) . '</a>';
                    } else {
                        echo '<span class="btn-map" style="opacity:0.6; cursor:default;">' . htmlspecialchars(t('no_link')) . '</span>';
                    }
                }
                ?>
            </div>

            <!-- قسم الصور -->
            <div class="school-media" style="padding: 20px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                <h4 style="margin: 0 0 15px 0; color: #1e293b; font-size: 1.1rem;">📸 <?php echo htmlspecialchars(t('school_images')); ?></h4>
                
                <!-- عرض الصورة الحالية -->
                <?php
                $schoolImageRaw = trim((string) ($row['School_Image'] ?? ''));
                $imageParts = preg_split('/[,;|\n\r]+/u', $schoolImageRaw) ?: [];
                $imageItems = [];
                foreach ($imageParts as $imgPart) {
                    $imgName = trim((string) $imgPart);
                    if ($imgName === '') {
                        continue;
                    }
                    if (preg_match('/^https?:\/\//i', $imgName)) {
                        $imageItems[] = ['name' => $imgName, 'url' => $imgName];
                    } else {
                        $cleanName = basename($imgName);
                        $imageItems[] = ['name' => $cleanName, 'url' => 'uploads/schools/' . $cleanName];
                    }
                }
                $seenNames = [];
                $dedupedItems = [];
                foreach ($imageItems as $item) {
                    $key = (string) $item['name'];
                    if ($key === '' || isset($seenNames[$key])) {
                        continue;
                    }
                    $seenNames[$key] = true;
                    $dedupedItems[] = $item;
                }
                if (!empty($dedupedItems)) {
                    $visibleImages = array_slice($dedupedItems, 0, 4);
                    $hiddenCount = max(0, count($dedupedItems) - count($visibleImages));
                    $jsonImages = htmlspecialchars(json_encode(array_column($dedupedItems, 'url'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="school-gallery" data-images="<?php echo $jsonImages; ?>" data-school="<?php echo htmlspecialchars(get_localized_field($conn, 'Schools', 'School_ID', intval($row['School_ID']), 'School_Name', translate_db_text($row['School_Name'], $current_lang))); ?>" data-school-id="<?php echo intval($sid); ?>" data-csrf-token="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                        <?php foreach ($visibleImages as $imgIndex => $imgItem): ?>
                            <div class="school-thumb-item">
                                <button type="button" class="school-thumb-btn" onclick="openSchoolGallery(this.closest('.school-gallery'), <?php echo intval($imgIndex); ?>)">
                                    <img src="<?php echo htmlspecialchars($imgItem['url']); ?>" alt="<?php echo htmlspecialchars(get_localized_field($conn, 'Schools', 'School_ID', intval($row['School_ID']), 'School_Name', translate_db_text($row['School_Name'], $current_lang))); ?>" class="school-photo-thumb" loading="lazy">
                                </button>
                                <?php if(isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'principal'], true)): ?>
                                    <button type="button" class="school-thumb-delete" title="<?php echo htmlspecialchars(t('delete_image')); ?>" onclick="deleteSchoolImage(event, <?php echo intval($sid); ?>, '<?php echo htmlspecialchars($imgItem['name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>')">×</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($hiddenCount > 0): ?>
                            <button type="button" class="school-thumb-more" onclick="openSchoolGallery(this.closest('.school-gallery'), 4)">+<?php echo intval($hiddenCount); ?></button>
                        <?php endif; ?>
                    </div>
                    <?php if(isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'principal'], true)): ?>
                        <div class="school-gallery-actions">
                            <button type="button" class="school-delete-all-btn" onclick="deleteAllSchoolImages(<?php echo intval($sid); ?>, '<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>')"><?php echo htmlspecialchars(t('delete_all_images')); ?></button>
                        </div>
                    <?php endif; ?>
                    <?php
                } else {
                    echo '<div class="school-photo-empty">📷 ' . htmlspecialchars(t('no_current_image')) . '</div>';
                }
                ?>
                
                <!-- نموذج الرفع (للمسؤولين والمديرين فقط) -->
                <?php if(isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'principal'], true)): ?>
                    <form onsubmit="return uploadSchoolImage(event, this)" class="school-image-form">
                        <input type="hidden" name="school_id" value="<?php echo intval($sid); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                        
                        <label for="image_<?php echo intval($sid); ?>"><?php echo htmlspecialchars(t('choose_image')); ?></label>
                        <input type="file" id="image_<?php echo intval($sid); ?>" class="auto-image-upload" name="image[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple data-school-id="<?php echo intval($sid); ?>" data-csrf="<?php echo getCsrfToken(); ?>">
                        <button type="submit"><?php echo htmlspecialchars(t('upload_image')); ?></button>
                        <div class="school-upload-icons">
                            <span class="upload-icon" title="<?php echo htmlspecialchars(t('max_image_size')); ?>" role="img" aria-label="<?php echo htmlspecialchars(t('max_image_size')); ?>">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M14 2H6C4.895 2 4 2.895 4 4V20C4 21.105 4.895 22 6 22H18C19.105 22 20 21.105 20 20V8L14 2Z" stroke="#64748b" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 2V8H20" stroke="#64748b" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 12H16" stroke="#64748b" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 16H14" stroke="#64748b" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <span class="sr-only"><?php echo htmlspecialchars(t('max_image_size')); ?></span>
                            </span>
                            <span class="upload-icon" title="<?php echo htmlspecialchars(t('multiple_images')); ?>" role="img" aria-label="<?php echo htmlspecialchars(t('multiple_images')); ?>">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M21 15V6a2 2 0 0 0-2-2H8l-2 2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h11" stroke="#64748b" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><rect x="7" y="12" width="11" height="8" rx="2" stroke="#64748b" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <span class="sr-only"><?php echo htmlspecialchars(t('multiple_images')); ?></span>
                            </span>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="reviews-section">
                <h4 style="margin:0 0 20px 0; color:#1e293b; font-size:1.2rem;">💬 <?php echo htmlspecialchars(t('feedback_title')); ?></h4>
                <div class="reviews-list">
                    <?php
                    $revs = $reviewsBySchool[$sid] ?? [];
                    foreach ($revs as $rv): ?>
                        <div class="review-item">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <b style="color:#003366; font-size:1.1rem;">@<?php echo htmlspecialchars($rv['Visitor_Name']); ?></b>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                                    <form method="POST" action="delete_review.php" style="display:inline;">
                                        <input type="hidden" name="id" value="<?php echo $rv['Review_ID']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                                        <button type="submit" onclick="return confirm('<?php echo htmlspecialchars(t('delete_comment')); ?>')" style="background:none;border:1px solid #ef4444;color:#ef4444;padding:3px 10px;border-radius:8px;cursor:pointer;">🗑️ <?php echo htmlspecialchars($current_lang === 'en' ? 'Delete' : 'حذف'); ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <p style="margin:0; font-size:1.05rem; color:#475569; line-height:1.7;"><?php echo htmlspecialchars(get_localized_field($conn, 'School_Reviews', 'Review_ID', intval($rv['Review_ID']), 'Comment', $rv['Comment'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (isset($_SESSION['username'])): ?>
                <form method="POST" action="add_review.php" class="add-review-form">
                    <input type="hidden" name="school_id" value="<?php echo $sid; ?>">
                    <input type="text" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" readonly style="background:#f1f5f9; font-weight:bold; color:#003366;">
                    <textarea name="comment_text" placeholder="<?php echo htmlspecialchars(t('write_experience')); ?>" required style="height:55px; resize:none; padding:12px; border-radius:12px; border:1px solid #cbd5e1;"></textarea>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                    <button type="submit" name="submit_comment" style="background: #003366; color: white; padding: 0 35px; border: none; border-radius: 12px; cursor: pointer; font-weight: bold;"><?php echo htmlspecialchars(t('publish_experience')); ?></button>
                </form>
            <?php else: ?>
                <p style="text-align:center; padding:15px; background:#fffbeb; color:#92400e; border-radius:15px; font-size:0.95rem; border:1px solid #fef3c7;"><?php echo htmlspecialchars(t('login_to_participate')); ?></p>
            <?php endif; ?>
            </div>
        </div>
        <?php
    }
    // render pagination
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($totalPages > 1) {
        echo '<div style="display:flex; gap:8px; justify-content:center; margin:30px 0;">';
        if ($page > 1) { echo '<a class="level-pill" onclick="submitSearch({page: ' . ($page - 1) . '})">' . htmlspecialchars(t('previous')) . '</a>'; }
        $start = max(1, $page - 3);
        $end = min($totalPages, $page + 3);
        for ($p = $start; $p <= $end; $p++) { $cls = ($p == $page) ? 'active-pill' : ''; echo '<a class="level-pill ' . $cls . '" onclick="submitSearch({page: ' . $p . '})">' . $p . '</a>'; }
        if ($page < $totalPages) { echo '<a class="level-pill" onclick="submitSearch({page: ' . ($page + 1) . '})">' . htmlspecialchars(t('next')) . '</a>'; }
        echo '</div>';
    }
} else {
    echo "<div style='text-align:center; padding:100px; background:white; border-radius:30px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border:1px solid #edf2f7;'>";
    echo "<p style='color:#64748b; font-size:1.4rem;'>🔍 " . htmlspecialchars(t('no_matching_schools')) . "</p>";
    echo "</div>";
}
?>
