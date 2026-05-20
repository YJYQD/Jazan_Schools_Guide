<?php
/**
 * filters_helper.php
 * دالة مشتركة موحدة لبناء الفلاتر
 * تستخدم في جميع الملفات (index.php, fetch_search.php, fetch_stats.php)
 */

/**
 * بناء شروط SQL من الفلاتر
 * تضاف الشروط فقط إذا كانت القيمة غير فارغة و غير 0
 * 
 * @param array &$params - مرجع لمصفوفة المعاملات
 * @param string &$types - مرجع لمرجع نوع المعاملات
 * @return string - شروط SQL (بدون WHERE، تبدأ بـ AND)
 */
function buildFilterConditions(&$params, &$types) {
    // استقبال الفلاتر الآمنة من $_POST فقط (نوحّد البروتوكول إلى POST)
    $source = $_POST;

    // مساعدة: التحقق من قيمة فلتر صالحة
    $isValid = function($v) {
        if (!isset($v)) return false;
        $s = trim((string)$v);
        if ($s === '' || $s === '0' || strtolower($s) === 'all' || strtolower($s) === 'null') return false;
        return true;
    };

    // تحضير الفلاتر
    $filters = [];
    $filters['q']            = $isValid($source['q'] ?? null) ? trim($source['q']) : '';
    $filters['region_id']    = $isValid($source['region_id'] ?? null) ? intval($source['region_id']) : 0;
    $filters['gov_id']       = $isValid($source['gov_id'] ?? null) ? intval($source['gov_id']) : 0;
    $filters['office_id']    = $isValid($source['office_id'] ?? null) ? intval($source['office_id']) : 0;
    $filters['school_type']  = $isValid($source['school_type'] ?? null) ? trim($source['school_type']) : '';
    $filters['gender']       = $isValid($source['gender'] ?? null) ? trim($source['gender']) : '';
    $filters['level']        = $isValid($source['level'] ?? null) ? trim($source['level']) : '';
    
    $conditions = '';
    $params = [];
    $types = '';
    
    // شرط البحث النصي
    if (!empty($filters['q'])) {
        $conditions .= " AND (Schools.School_Name LIKE ? OR Schools.City LIKE ?)";
        $q_param = '%' . $filters['q'] . '%';
        $params[] = $q_param;
        $params[] = $q_param;
        $types .= 'ss';
    }
    
    // شروط المنطقة والمحافظة
    if ($filters['region_id'] > 0) {
        $conditions .= " AND Governorates.Region_ID = ?";
        $params[] = $filters['region_id'];
        $types .= 'i';
    }

    if ($filters['gov_id'] > 0) {
        $conditions .= " AND Offices.Gov_ID = ?";
        $params[] = $filters['gov_id'];
        $types .= 'i';
    }
    
    // شرط المكتب
    if ($filters['office_id'] > 0) {
        $conditions .= " AND Schools.Office_ID = ?";
        $params[] = $filters['office_id'];
        $types .= 'i';
    }
    
    // شرط نوع المدرسة
    if (!empty($filters['school_type'])) {
        $conditions .= " AND Schools.School_Type = ?";
        $params[] = $filters['school_type'];
        $types .= 's';
    }
    
    // شرط الجنس
    if (!empty($filters['gender'])) {
        $conditions .= " AND Schools.Gender = ?";
        $params[] = $filters['gender'];
        $types .= 's';
    }
    
    // شرط المرحلة التعليمية
    if (!empty($filters['level'])) {
        $conditions .= " AND Schools.Education_Level = ?";
        $params[] = $filters['level'];
        $types .= 's';
    }
    
    return $conditions;
}

/**
 * الحصول على قيمة فلتر معين
 */
function getFilterValue($name, $default = '') {
    $source = $_POST;

    if ($name === 'region_id' || $name === 'gov_id' || $name === 'office_id') {
        return isset($source[$name]) ? intval($source[$name]) : intval($default);
    }

    return isset($source[$name]) ? trim($source[$name]) : $default;
}

/**
 * Translate database-stored texts (school names, regions) by replacing
 * Arabic components with English equivalents when `$lang` is 'en'.
 * This does not call external services — it's a deterministic keyword replacer
 * that reorders simple ordinals (1st/2nd/3rd) when they appear at the end.
 */
function translate_db_text($text, $lang = 'ar') {
    if ($lang !== 'en' || !is_string($text) || trim($text) === '') return $text;
    // Prefer centralized map in i18n.php if present
    if (function_exists('get_db_fixed_translations')) {
        $db_translation = get_db_fixed_translations();
    } else {
        $db_translation = [
            'مكتب التعليم' => 'Education Office',
            'مكتب تعليم' => 'Education Office',
            'إدارة التعليم' => 'Education Department',
            'المنطقة' => 'Region',
            'منطقة' => 'Region',
            'جازان' => 'Jazan',
            'جيزان' => 'Jazan',
            'أبو عريش' => 'Abu Arish',
            'أبي عريش' => 'Abu Arish',
            'أحد المسارحة' => 'Ahad Al-Masarihah',
            'الدرب' => 'Al-Darb',
            'العارضة' => 'Al-Aridah',
            'بيش' => 'Baish',
            'صامطة' => 'Samtah',
            'صبيا' => 'Sabya',
            'ضمد' => 'Damad',
            'فرسان' => 'Farasan',
            'الطوال' => 'Al-Tuwal',
            'فيفاء' => 'Faifa',
            'هروب' => 'Hurub',
            'الداير' => 'Al-Daer',
            'الداير بني مالك' => 'Al-Daer Bani Malik',
            'مكتب تعليم صبيا' => 'Sabya Education Office'
        ];
    }

    // Do replacements (longer keys first to avoid partial matches)
    uksort($db_translation, function($a, $b){ return mb_strlen($b) - mb_strlen($a); });
    $translated = strtr($text, $db_translation);

    // Normalize whitespace
    $translated = preg_replace('/\s+/u', ' ', trim($translated));

    $ratingMap = [
        'ممتاز' => 'Excellent',
        'جيد جداً' => 'Very Good',
        'جيد' => 'Good',
        'مقبول' => 'Acceptable'
    ];
    if (isset($ratingMap[$translated])) {
        return $ratingMap[$translated];
    }

    // If an ordinal like '1st' appears at the end, move it to the front
    // Example: "Elementary School Abu Arish 1st" -> "1st Abu Arish Elementary School"
    $parts = explode(' ', $translated);
    $last = end($parts);
    if (preg_match('/^\d+(st|nd|rd|th)$/i', $last)) {
        array_pop($parts);
        array_unshift($parts, $last);
        $translated = implode(' ', $parts);
    }

    return $translated;
}
?>
