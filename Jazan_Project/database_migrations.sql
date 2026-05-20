-- =====================================================
-- تحديثات قاعدة البيانات للميزات المتقدمة
-- =====================================================
-- ملاحظة: قم بتنفيذ هذه التحديثات على قاعدة البيانات الخاصة بك

-- =====================================================
-- 1. إضافة أعمدة جديدة لجدول Schools
-- =====================================================
-- أعمدة التقييم والصور والموقع الجغرافي
ALTER TABLE Schools ADD COLUMN IF NOT EXISTS School_Image VARCHAR(255) AFTER School_Website;
ALTER TABLE Schools ADD COLUMN IF NOT EXISTS School_Logo VARCHAR(255) AFTER School_Image;
ALTER TABLE Schools ADD COLUMN IF NOT EXISTS Latitude DECIMAL(10, 8) AFTER School_Logo;

-- 2. Translation tables for localization (Schools and Reviews)
CREATE TABLE IF NOT EXISTS School_Translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    lang VARCHAR(8) NOT NULL,
    name TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    website VARCHAR(512) DEFAULT NULL,
    UNIQUE KEY uniq_school_lang (school_id, lang),
    INDEX idx_school_id (school_id)
);

CREATE TABLE IF NOT EXISTS Review_Translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    lang VARCHAR(8) NOT NULL,
    comment TEXT DEFAULT NULL,
    visitor_name VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uniq_review_lang (review_id, lang),
    INDEX idx_review_id (review_id)
);
ALTER TABLE Schools ADD COLUMN IF NOT EXISTS Longitude DECIMAL(11, 8) AFTER Latitude;

-- =====================================================
-- 2. جدول جديد: School_Ratings (نظام التقييم بالنجوم)
-- =====================================================
CREATE TABLE IF NOT EXISTS School_Ratings (
    Rating_ID INT AUTO_INCREMENT PRIMARY KEY,
    School_ID INT NOT NULL,
    User_ID INT NOT NULL,
    Rating INT CHECK (Rating >= 1 AND Rating <= 5),
    Review_Text TEXT,
    Created_Date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (School_ID) REFERENCES Schools(School_ID) ON DELETE CASCADE,
    FOREIGN KEY (User_ID) REFERENCES Users(User_ID) ON DELETE CASCADE,
    UNIQUE KEY unique_user_school (User_ID, School_ID)
);

-- =====================================================
-- 3. جدول جديد: Audit_Trail (سجل العمليات)
-- =====================================================
CREATE TABLE IF NOT EXISTS Audit_Trail (
    Audit_ID INT AUTO_INCREMENT PRIMARY KEY,
    User_ID INT,
    Action_Type ENUM('CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'EXPORT', 'BACKUP', 'IMPORT') NOT NULL,
    Table_Name VARCHAR(50),
    Record_ID INT,
    Old_Value JSON,
    New_Value JSON,
    Action_Details VARCHAR(500),
    IP_Address VARCHAR(45),
    User_Agent TEXT,
    Action_Timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (User_ID) REFERENCES Users(User_ID) ON DELETE SET NULL
);

-- =====================================================
-- 4. تحديث جدول School_Reviews - إضافة حقل Rating
-- =====================================================
ALTER TABLE School_Reviews ADD COLUMN IF NOT EXISTS Rating INT DEFAULT 0;
ALTER TABLE School_Reviews MODIFY COLUMN Rating INT CHECK (Rating >= 0 AND Rating <= 5);

-- Performance indexes
CREATE INDEX IF NOT EXISTS idx_school_name ON Schools(School_Name);
CREATE INDEX IF NOT EXISTS idx_city ON Schools(City);
CREATE INDEX IF NOT EXISTS idx_office_id ON Schools(Office_ID);
CREATE INDEX IF NOT EXISTS idx_reviews_school_id ON School_Reviews(School_ID);

-- =====================================================
-- 5. جدول جديد: Backup_History (سجل النسخ الاحتياطية)
-- =====================================================
CREATE TABLE IF NOT EXISTS Backup_History (
    Backup_ID INT AUTO_INCREMENT PRIMARY KEY,
    Backup_File VARCHAR(255) NOT NULL,
    Backup_Date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Backup_Size VARCHAR(50),
    Status ENUM('SUCCESS', 'FAILED', 'PENDING') DEFAULT 'PENDING',
    Created_By INT,
    Notes TEXT,
    FOREIGN KEY (Created_By) REFERENCES Users(User_ID) ON DELETE SET NULL
);

-- =====================================================
-- 6. جدول جديد: Settings (إعدادات النظام)
-- =====================================================
CREATE TABLE IF NOT EXISTS Settings (
    Setting_ID INT AUTO_INCREMENT PRIMARY KEY,
    Setting_Key VARCHAR(100) UNIQUE NOT NULL,
    Setting_Value LONGTEXT,
    Setting_Type ENUM('string', 'boolean', 'number', 'json') DEFAULT 'string',
    Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- 7. إضافة بيانات إعدادات افتراضية
-- =====================================================
INSERT IGNORE INTO Settings (Setting_Key, Setting_Value, Setting_Type) VALUES
('google_maps_api_key', '', 'string'),
('default_language', 'ar', 'string'),
('auto_backup_enabled', '1', 'boolean'),
('auto_backup_frequency', 'weekly', 'string'),
('max_upload_size_mb', '5', 'number'),
('uploads_folder', 'uploads/', 'string'),
('backup_folder', 'backups/', 'string'),
('enable_rating_system', '1', 'boolean'),
('enable_export_pdf', '1', 'boolean'),
('enable_export_excel', '1', 'boolean');

-- =====================================================
-- 8. إنشاء مؤشرات (Indexes) لتحسين الأداء
-- =====================================================
CREATE INDEX IF NOT EXISTS idx_schools_office ON Schools(Office_ID);
CREATE INDEX IF NOT EXISTS idx_schools_rating ON School_Ratings(School_ID);
CREATE INDEX IF NOT EXISTS idx_audit_user ON Audit_Trail(User_ID);
CREATE INDEX IF NOT EXISTS idx_audit_table ON Audit_Trail(Table_Name);
CREATE INDEX IF NOT EXISTS idx_audit_date ON Audit_Trail(Action_Timestamp);
CREATE INDEX IF NOT EXISTS idx_backup_date ON Backup_History(Backup_Date);

-- دعم الأنظمة التي أنشأت Audit_Trail مسبقاً قبل إضافة IMPORT
ALTER TABLE Audit_Trail
    MODIFY COLUMN Action_Type ENUM('CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'EXPORT', 'BACKUP', 'IMPORT') NOT NULL;

-- =====================================================
-- التحديثات المكتملة بنجاح!
-- =====================================================
-- لتشغيل هذا الملف:
-- mysql -h sql309.infinityfree.com -u if0_41747985 -p if0_41747985_jazan_db < database_migrations.sql
-- =====================================================
