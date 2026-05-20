<?php
/**
 * map_integration.php
 * تكامل خرائط جوجل لعرض مواقع المدارس
 * يعرض خريطة تفاعلية بجميع المدارس - محسّنة ومتجاوبة
 */

// Ensure session is started safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "db.php";
require_once "security_helpers.php";
require_once "navbar.php";
require_once __DIR__ . '/i18n.php';

$map_lang = function_exists('current_lang') ? current_lang() : 'ar';
$map_dir = $map_lang === 'en' ? 'ltr' : 'rtl';

// الحصول على معرف API من الإعدادات
$google_maps_api_key = 'AIzaSyD9hl8xxxxxxxxxxxxxxxxxxxxxxxx';

// نوع العرض
$view_type = sanitizeInput($_GET['view'] ?? 'all');
$school_id = intval($_GET['school'] ?? 0);
$gov_id = intval($_GET['gov'] ?? 0);

// الحصول على المدارس
$schools = [];

if ($view_type === 'school' && $school_id > 0) {
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            o.Office_Name,
            g.Gov_Name
        FROM Schools s
        LEFT JOIN Offices o ON s.Office_ID = o.Office_ID
        LEFT JOIN Governorates g ON o.Gov_ID = g.Gov_ID
        WHERE s.School_ID = ? AND (s.Latitude IS NOT NULL AND s.Longitude IS NOT NULL)
    ");
    $stmt->bind_param("i", $school_id);
} else if ($view_type === 'governorate' && $gov_id > 0) {
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            o.Office_Name,
            g.Gov_Name
        FROM Schools s
        LEFT JOIN Offices o ON s.Office_ID = o.Office_ID
        LEFT JOIN Governorates g ON o.Gov_ID = g.Gov_ID
        WHERE g.Gov_ID = ? AND (s.Latitude IS NOT NULL AND s.Longitude IS NOT NULL)
    ");
    $stmt->bind_param("i", $gov_id);
} else {
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            o.Office_Name,
            g.Gov_Name
        FROM Schools s
        LEFT JOIN Offices o ON s.Office_ID = o.Office_ID
        LEFT JOIN Governorates g ON o.Gov_ID = g.Gov_ID
        WHERE s.Latitude IS NOT NULL AND s.Longitude IS NOT NULL
    ");
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $lat = isset($row['Latitude']) ? floatval($row['Latitude']) : 0.0;
    $lng = isset($row['Longitude']) ? floatval($row['Longitude']) : 0.0;
    if ($lat && $lng) {
        $row['Latitude'] = $lat;
        $row['Longitude'] = $lng;
        $schools[] = $row;
    }
}

// إعداد الإحداثيات الافتراضية
$default_lat = 21.5433;
$default_lng = 55.2075;
$default_zoom = ($view_type === 'school') ? 16 : 10;

if (count($schools) === 1) {
    $default_lat = $schools[0]['Latitude'];
    $default_lng = $schools[0]['Longitude'];
    $default_zoom = 16;
} else if (count($schools) > 1) {
    $avg_lat = array_sum(array_column($schools, 'Latitude')) / count($schools);
    $avg_lng = array_sum(array_column($schools, 'Longitude')) / count($schools);
    $default_lat = $avg_lat;
    $default_lng = $avg_lng;
}

$page_lang = function_exists('current_lang') ? current_lang() : 'ar';

// Localize DB fields inside the $schools array so JS and HTML see translated values
if ($page_lang === 'en' && count($schools) > 0) {
    foreach ($schools as &$r) {
        $sid = intval($r['School_ID'] ?? 0);
        $rawName = $r['School_Name'] ?? '';
        if (function_exists('get_localized_field')) {
            $r['School_Name'] = get_localized_field($conn, 'Schools', 'School_ID', $sid, 'School_Name', (function_exists('translate_db_text') ? translate_db_text($rawName, $page_lang) : $rawName));
        } else if (function_exists('translate_db_text')) {
            $r['School_Name'] = translate_db_text($rawName, $page_lang);
        }

        if (isset($r['Office_Name'])) {
            $r['Office_Name'] = function_exists('translate_db_text') ? translate_db_text($r['Office_Name'], $page_lang) : $r['Office_Name'];
        }
        if (isset($r['Gov_Name'])) {
            $r['Gov_Name'] = function_exists('translate_db_text') ? translate_db_text($r['Gov_Name'], $page_lang) : $r['Gov_Name'];
        }
        if (isset($r['City'])) {
            $r['City'] = function_exists('translate_db_text') ? translate_db_text($r['City'], $page_lang) : $r['City'];
        }
        if (isset($r['Education_Level'])) {
            $r['Education_Level'] = function_exists('translate_term') ? translate_term('education_level', $r['Education_Level']) : $r['Education_Level'];
        }
        if (isset($r['School_Type'])) {
            $r['School_Type'] = function_exists('translate_term') ? translate_term('school_type', $r['School_Type']) : $r['School_Type'];
        }
    }
    unset($r);
}

$schools_json = json_encode($schools, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($map_lang); ?>" dir="<?php echo htmlspecialchars($map_dir); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(($map_lang === 'en') ? 'School Map - Saudi Schools Guide' : 'خريطة المدارس - دليل مدارس المملكة'); ?></title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
        }

        .map-wrapper {
            display: flex;
            height: calc(100vh - 120px);
            width: 100%;
            gap: 0;
        }

        .map-sidebar {
            width: 320px;
            background: white;
            overflow-y: auto;
            box-shadow: -2px 0 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            border-right: 2px solid var(--accent);
        }

        .map-main {
            flex: 1;
            position: relative;
            background: #e0e0e0;
        }

        #map {
            width: 100%;
            height: 100%;
        }

        .map-sidebar h2 {
            color: var(--primary);
            margin: 0 0 15px 0;
            font-size: 1.3rem;
            border-bottom: 3px solid var(--accent);
            padding-bottom: 12px;
        }

        .school-count {
            background: var(--bg-secondary);
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 15px;
            font-weight: 600;
            color: var(--primary);
        }

        .school-item {
            padding: 15px;
            border: 2px solid var(--border);
            border-radius: 10px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .school-item:hover {
            background: var(--bg-secondary);
            border-color: var(--accent);
            box-shadow: 0 4px 12px rgba(0, 242, 254, 0.15);
            transform: translateX(-5px);
        }

        .school-item h4 {
            color: var(--primary);
            margin: 0 0 8px 0;
            font-size: 1rem;
        }

        .school-item p {
            color: var(--text-light);
            font-size: 0.85rem;
            margin: 4px 0;
        }

        .school-item .badge {
            display: inline-block;
            background: var(--accent);
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-top: 8px;
            font-weight: 600;
        }

        .info-window {
            max-width: 280px;
            padding: 15px;
            font-family: 'Tajawal', sans-serif;
            border-right: 3px solid var(--primary);
        }

        .info-window h3 {
            color: var(--primary);
            margin: 0 0 10px 0;
            font-size: 1.1rem;
        }

        .info-window p {
            color: var(--text-light);
            font-size: 0.9rem;
            margin: 6px 0;
        }

        .info-window a {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 14px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .info-window a:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
        }

        .map-controls {
            position: absolute;
            bottom: 20px;
            left: 20px;
            background: white;
            padding: 12px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 100;
        }

        .map-controls button {
            display: block;
            width: 100%;
            padding: 10px 15px;
            margin-bottom: 8px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Tajawal', sans-serif;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .map-controls button:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 51, 102, 0.3);
        }

        .map-controls button:last-child {
            margin-bottom: 0;
        }

        .legend {
            position: absolute;
            top: 20px;
            left: 20px;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 100;
            max-width: 250px;
        }

        .legend h3 {
            color: var(--primary);
            margin: 0 0 12px 0;
            font-size: 1rem;
            border-bottom: 2px solid var(--accent);
            padding-bottom: 8px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            font-size: 0.9rem;
            gap: 10px;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .stats-info {
            position: absolute;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 100;
            min-width: 200px;
        }

        .stats-info h3 {
            color: var(--primary);
            margin: 0 0 10px 0;
            font-size: 1rem;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 0.9rem;
            color: var(--text-light);
            border-bottom: 1px solid var(--border);
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .stat-value {
            font-weight: 600;
            color: var(--primary);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .map-wrapper {
                height: calc(100vh - 100px);
            }

            .map-sidebar {
                width: 280px;
            }
        }

        @media (max-width: 768px) {
            .map-wrapper {
                flex-direction: column;
                height: auto;
            }

            .map-sidebar {
                width: 100%;
                max-height: 40vh;
                border-right: none;
                border-bottom: 2px solid var(--accent);
            }

            .map-main {
                height: 50vh;
            }

            .legend {
                left: 10px;
                right: 10px;
                max-width: unset;
            }

            .stats-info {
                left: 10px;
                right: 10px;
                max-width: unset;
            }

            .map-controls {
                left: 10px;
                right: 10px;
                bottom: 10px;
                max-width: unset;
            }

            .school-item {
                margin-bottom: 10px;
                padding: 12px;
            }

            .school-item h4 {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 480px) {
            .map-sidebar {
                max-height: 35vh;
                padding: 12px;
            }

            .map-main {
                height: 45vh;
            }

            .map-sidebar h2 {
                font-size: 1.1rem;
                margin-bottom: 10px;
            }

            .school-item {
                padding: 10px;
            }

            .school-item h4 {
                font-size: 0.9rem;
            }

            .school-item p {
                font-size: 0.8rem;
            }

            .legend,
            .stats-info,
            .map-controls {
                font-size: 0.85rem;
                padding: 10px;
            }

            .legend h3,
            .stats-info h3 {
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <?php render_navbar('map', true, 'admin.php', $map_lang === 'en' ? '⬅ Back to Admin' : '⬅️ العودة للإدارة'); ?>

    <div class="map-wrapper">
        <!-- الشريط الجانبي -->
        <div class="map-sidebar">
            <h2>🗺️ <?php echo htmlspecialchars($map_lang === 'en' ? 'Schools List' : 'قائمة المدارس'); ?></h2>
            <div class="school-count">
                <?php echo htmlspecialchars($map_lang === 'en' ? 'Total Schools:' : 'عدد المدارس:'); ?> <span><?php echo count($schools); ?></span>
            </div>

            <?php if (count($schools) > 0): ?>
                <?php foreach ($schools as $school): ?>
                    <div class="school-item" onclick="focusSchool(<?php echo $school['School_ID']; ?>)">
                        <h4><?php echo htmlspecialchars($school['School_Name']); ?></h4>
                        <p>📍 <?php echo htmlspecialchars($school['City'] ?? ($map_lang === 'en' ? 'Not specified' : 'غير محدد')); ?></p>
                        <p>🏢 <?php echo htmlspecialchars($school['Office_Name'] ?? ($map_lang === 'en' ? 'Not specified' : 'غير محدد')); ?></p>
                        <span class="badge"><?php echo htmlspecialchars($school['Education_Level']); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; color: var(--text-light); padding: 20px;">
                    <p>❌ <?php echo htmlspecialchars($map_lang === 'en' ? 'No schools with coordinates' : 'لا توجد مدارس بإحداثيات محددة'); ?></p>
                    <p style="font-size: 0.9rem;"><?php echo htmlspecialchars($map_lang === 'en' ? 'Please add GPS coordinates for schools' : 'يرجى إضافة إحداثيات GPS للمدارس'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- الخريطة -->
        <div class="map-main">
            <div id="map"></div>

            <!-- وسائل التحكم -->
            <div class="map-controls">
                <button onclick="zoomIn()">🔍 <?php echo htmlspecialchars($map_lang === 'en' ? 'Zoom In' : 'تكبير'); ?></button>
                <button onclick="zoomOut()">🔍 <?php echo htmlspecialchars($map_lang === 'en' ? 'Zoom Out' : 'تصغير'); ?></button>
                <button onclick="resetMap()">🔄 <?php echo htmlspecialchars($map_lang === 'en' ? 'Reset' : 'إعادة'); ?></button>
            </div>

            <!-- وسيلة الشرح -->
            <div class="legend">
                <h3>📍 <?php echo htmlspecialchars($map_lang === 'en' ? 'Legend' : 'وسيلة شرح'); ?></h3>
                <div class="legend-item">
                    <div class="legend-color" style="background: #FF5C5C;"></div>
                    <span><?php echo htmlspecialchars($map_lang === 'en' ? 'School' : 'مدرسة'); ?></span>
                </div>
            </div>

            <!-- معلومات الإحصائيات -->
            <div class="stats-info">
                <h3>📊 <?php echo htmlspecialchars($map_lang === 'en' ? 'Statistics' : 'الإحصائيات'); ?></h3>
                <div class="stat-row">
                    <span><?php echo htmlspecialchars($map_lang === 'en' ? 'Total Schools:' : 'إجمالي المدارس:'); ?></span>
                    <span class="stat-value"><?php echo count($schools); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- خرائط جوجل -->
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo $google_maps_api_key; ?>&language=<?php echo $map_lang === 'en' ? 'en' : 'ar'; ?>"></script>

    <script>
        let map;
        let markers = [];
        let schoolsData = <?php echo $schools_json; ?>;
        const defaultLat = <?php echo $default_lat; ?>;
        const defaultLng = <?php echo $default_lng; ?>;
        const defaultZoom = <?php echo $default_zoom; ?>;

        function initMap() {
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: defaultZoom,
                center: { lat: defaultLat, lng: defaultLng },
                mapTypeId: 'roadmap',
                styles: [
                    { elementType: 'geometry', stylers: [{ color: '#f5f5f5' }] },
                    { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#c9c9c9' }] }
                ]
            });

            addSchoolMarkers();
        }

        function addSchoolMarkers() {
            schoolsData.forEach((school, index) => {
                const marker = new google.maps.Marker({
                    position: { lat: parseFloat(school.Latitude), lng: parseFloat(school.Longitude) },
                    map: map,
                    title: school.School_Name,
                    icon: 'http://maps.google.com/mapfiles/ms/icons/red-dot.png'
                });

                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div class="info-window">
                            <h3>${school.School_Name}</h3>
                            <p><strong><?php echo $map_lang === 'en' ? 'City:' : 'المدينة:'; ?></strong> ${school.City || <?php echo json_encode($map_lang === 'en' ? 'Not specified' : 'غير محدد'); ?>}</p>
                            <p><strong><?php echo $map_lang === 'en' ? 'Stage:' : 'المرحلة:'; ?></strong> ${school.Education_Level}</p>
                            <p><strong><?php echo $map_lang === 'en' ? 'Type:' : 'النوع:'; ?></strong> ${school.School_Type}</p>
                            <p><strong><?php echo $map_lang === 'en' ? 'Office:' : 'المكتب:'; ?></strong> ${school.Office_Name || <?php echo json_encode($map_lang === 'en' ? 'Not specified' : 'غير محدد'); ?>}</p>
                            <a href="details.php?school_id=${school.School_ID}"><?php echo $map_lang === 'en' ? 'View details' : 'عرض التفاصيل'; ?></a>
                        </div>
                    `
                });

                marker.addListener('click', () => {
                    infoWindows.forEach(iw => iw.close());
                    infoWindow.open(map, marker);
                });

                markers.push(marker);
                if (!window.infoWindows) window.infoWindows = [];
                window.infoWindows.push(infoWindow);
            });
        }

        function focusSchool(schoolId) {
            const school = schoolsData.find(s => s.School_ID === schoolId);
            if (school) {
                map.setCenter({ lat: parseFloat(school.Latitude), lng: parseFloat(school.Longitude) });
                map.setZoom(16);
            }
        }

        function zoomIn() {
            map.setZoom(map.getZoom() + 1);
        }

        function zoomOut() {
            map.setZoom(map.getZoom() - 1);
        }

        function resetMap() {
            map.setCenter({ lat: defaultLat, lng: defaultLng });
            map.setZoom(defaultZoom);
        }

        // Initialize map when page loads
        window.addEventListener('load', initMap);
    </script>
</script>

<?php include 'footer.php'; ?>
</body>
</html>
            left: 320px;
            background: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 10;
            max-width: 250px;
        }
        
        .stats-info h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .stats-info p {
            color: #666;
            font-size: 12px;
            margin: 5px 0;
        }
        
        @media (max-width: 768px) {
            .map-container {
                flex-direction: column;
            }
            
            .map-sidebar {
                width: 100%;
                height: 40%;
                order: 2;
            }
            
            .map-main {
                order: 1;
                height: 60%;
            }
            
            .legend, .stats-info, .controls {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="map-container">
        <!-- الشريط الجانبي -->
        <div class="map-sidebar">
                <h2>🏫 <?php echo htmlspecialchars($map_lang === 'en' ? 'Schools' : 'المدارس'); ?> (<?php echo count($schools); ?>)</h2>
            <?php foreach ($schools as $school): ?>
                <div class="school-item" onclick="focusSchool(<?php echo $school['Latitude']; ?>, <?php echo $school['Longitude']; ?>, '<?php echo htmlspecialchars($school['School_Name']); ?>')">
                    <h4><?php echo htmlspecialchars($school['School_Name']); ?></h4>
                    <p>📍 <?php echo htmlspecialchars($school['City'] ?? ($map_lang === 'en' ? 'Not specified' : 'غير محدد')); ?></p>
                    <p>🏛️ <?php echo htmlspecialchars(translate_term('school_type', $school['School_Type'])); ?></p>
                    <p>📚 <?php echo htmlspecialchars(translate_term('education_level', $school['Education_Level'])); ?></p>
                    <span class="badge"><?php echo htmlspecialchars($school['Gov_Name'] ?? ($map_lang === 'en' ? 'N/A' : 'غير محدد')); ?></span>

                    <div class="school-actions">
                        <a class="view-map-btn"
                           href="https://www.google.com/maps/search/?api=1&query=<?php echo rawurlencode($school['Latitude'] . ',' . $school['Longitude']); ?>"
                           target="_blank" rel="noopener noreferrer"
                           onclick="event.stopPropagation();">
                                    <?php echo $map_lang === 'en' ? 'Open in Maps' : 'عرض الموقع على الخريطة'; ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- الخريطة -->
        <div class="map-main">
            <div id="map"></div>
            
            <!-- المراقبات -->
            <div class="controls">
                <button onclick="zoomIn()">🔍+ <?php echo htmlspecialchars($map_lang === 'en' ? 'Zoom In' : 'تقريب'); ?></button>
                <button onclick="zoomOut()">🔍- <?php echo htmlspecialchars($map_lang === 'en' ? 'Zoom Out' : 'إبعاد'); ?></button>
                <button onclick="resetView()">🔄 <?php echo htmlspecialchars($map_lang === 'en' ? 'Reset' : 'إعادة تعيين'); ?></button>
                <button onclick="downloadMap()">📥 <?php echo htmlspecialchars($map_lang === 'en' ? 'Download' : 'تحميل'); ?></button>
            </div>
            
            <!-- الإسطورة -->
            <div class="legend">
                <h3><?php echo htmlspecialchars($map_lang === 'en' ? 'Legend' : 'وسائل الإيضاح'); ?></h3>
                <div class="legend-item">
                    <span><?php echo htmlspecialchars($map_lang === 'en' ? 'Kindergarten' : 'روضة الأطفال'); ?></span>
                    <div class="legend-color" style="background: #4caf50;"></div>
                </div>
                <div class="legend-item">
                    <span><?php echo htmlspecialchars($map_lang === 'en' ? 'Elementary' : 'ابتدائي'); ?></span>
                    <div class="legend-color" style="background: #2196f3;"></div>
                </div>
                <div class="legend-item">
                    <span><?php echo htmlspecialchars($map_lang === 'en' ? 'High School' : 'ثانوي'); ?></span>
                    <div class="legend-color" style="background: #f44336;"></div>
                </div>
            </div>
            
            <!-- معلومات الإحصائيات -->
            <div class="stats-info">
                <h3>📊 <?php echo htmlspecialchars($map_lang === 'en' ? 'Statistics' : 'الإحصائيات'); ?></h3>
                <p><strong><?php echo htmlspecialchars($map_lang === 'en' ? 'Total Schools:' : 'إجمالي المدارس:'); ?></strong> <?php echo count($schools); ?></p>
                <p><strong><?php echo htmlspecialchars($map_lang === 'en' ? 'Governorate:' : 'المحافظة:'); ?></strong> <?php echo htmlspecialchars($map_lang === 'en' ? 'Saudi Arabia' : 'المملكة'); ?></p>
                <p><strong><?php echo htmlspecialchars($map_lang === 'en' ? 'Last Update:' : 'آخر تحديث:'); ?></strong> <?php echo date('Y-m-d'); ?></p>
            </div>
        </div>
    </div>
    
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars($google_maps_api_key); ?>&language=ar&callback=initMap"></script>
    <script>
            // Auth failure handler; Google calls this when the key is invalid
        window.gm_authFailure = function() {
            console.error('Google Maps authentication failed.');
            alert(<?php echo json_encode($map_lang === 'en' ? 'Google Maps failed to load: check API key, billing, and restrictions.' : 'فشل تحميل خرائط Google: تحقق من مفتاح API وإعدادات الفوترة/القيود في لوحة التحكم.'); ?>);
        };
        // تلميح في الكونسول إذا كان المفتاح مثالياً/موضعياً
        (function(){
            var key = '<?php echo addslashes($google_maps_api_key); ?>';
            if (!key || key.indexOf('xxxxx') !== -1 || key.indexOf('xxxxxxxx') !== -1) {
                console.warn(<?php echo json_encode($map_lang === 'en' ? 'Warning: the Google Maps key looks like a placeholder. Replace it with a valid key.' : 'احذر: يبدو أن مفتاح Google Maps هو مفتاح تجريبي أو مُخفّى. استبدله بمفتاح صالح.'); ?>);
            }
        })();
        let map;
        let markers = [];
        let infoWindows = [];
        const defaultLat = <?php echo $default_lat; ?>;
        const defaultLng = <?php echo $default_lng; ?>;
        const defaultZoom = <?php echo $default_zoom; ?>;
        
        const schoolsData = <?php echo json_encode($schools, JSON_UNESCAPED_UNICODE); ?>;
        
            // Initialize the map
        function initMap() {
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: defaultZoom,
                center: { lat: defaultLat, lng: defaultLng },
                mapTypeControl: true,
                mapTypeId: google.maps.MapTypeId.ROADMAP
            });
            
            // Add markers
            addMarkers();
        }
        
        // إضافة علامات المدارس
        function addMarkers() {
            const colors = {
                'روضة': '#4caf50',
                'ابتدائي': '#2196f3',
                'ثانوي': '#f44336'
            };
            
            schoolsData.forEach(school => {
                const color = colors[school.Education_Level] || '#1e88e5';
                
                // Create custom marker
                const marker = new google.maps.Marker({
                    position: {
                        lat: parseFloat(school.Latitude),
                        lng: parseFloat(school.Longitude)
                    },
                    map: map,
                    title: school.School_Name,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 10,
                        fillColor: color,
                        fillOpacity: 0.8,
                        strokeColor: '#fff',
                        strokeWeight: 2
                    }
                });
                
                // Info window
                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div class="info-window">
                            <h3>${school.School_Name}</h3>
                            <p><strong><?php echo $map_lang === 'en' ? 'Type:' : 'النوع:'; ?></strong> ${school.School_Type}</p>
                            <p><strong><?php echo $map_lang === 'en' ? 'Stage:' : 'المرحلة:'; ?></strong> ${school.Education_Level}</p>
                            <p><strong><?php echo $map_lang === 'en' ? 'City:' : 'المدينة:'; ?></strong> ${school.City || 'N/A'}</p>
                            <p><strong><?php echo $map_lang === 'en' ? 'Governorate:' : 'المحافظة:'; ?></strong> ${school.Gov_Name || 'N/A'}</p>
                            <p><strong><?php echo $map_lang === 'en' ? 'Website:' : 'الموقع الإلكتروني:'; ?></strong> 
                                ${school.School_Website ? `<a href="${school.School_Website}" target="_blank"><?php echo $map_lang === 'en' ? 'Visit site' : 'زيارة الموقع'; ?></a>` : 'N/A'}
                            </p>
                            <p><strong><?php echo $map_lang === 'en' ? 'Ministerial rating:' : 'التقييم الوزاري:'; ?></strong> ${school.Ministerial_Rating || 'N/A'}</p>
                        </div>
                    `
                });
                
                marker.addListener('click', () => {
                    // إغلاق جميع النوافذ الأخرى
                    infoWindows.forEach(iw => iw.close());
                    infoWindow.open(map, marker);
                });
                
                markers.push(marker);
                infoWindows.push(infoWindow);
            });
        }
        
        // Focus a specific school
        function focusSchool(lat, lng, name) {
            map.setCenter({ lat: parseFloat(lat), lng: parseFloat(lng) });
            map.setZoom(16);
            
            // فتح نافذة المعلومات
            markers.forEach((marker, index) => {
                if (marker.getTitle() === name) {
                    infoWindows.forEach(iw => iw.close());
                    infoWindows[index].open(map, marker);
                }
            });
        }
        
        // Zoom controls
        function zoomIn() {
            map.setZoom(map.getZoom() + 1);
        }
        
        function zoomOut() {
            map.setZoom(map.getZoom() - 1);
        }
        
        // Reset map
        function resetView() {
            map.setCenter({ lat: defaultLat, lng: defaultLng });
            map.setZoom(defaultZoom);
            infoWindows.forEach(iw => iw.close());
        }
        
        // Download map
        function downloadMap() {
        alert(<?php echo json_encode($map_lang === 'en' ? 'You can save the map by pressing Ctrl+P or using a screenshot tool.' : 'يمكن حفظ الخريطة بالضغط على Ctrl+P (طباعة) أو استخدام أداة التقاط الشاشة'); ?>);
        }
    </script>
</body>
</html>
