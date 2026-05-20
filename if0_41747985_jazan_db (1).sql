-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 20 مايو 2026 الساعة 21:33
-- إصدار الخادم: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_41747985_jazan_db`
--

-- --------------------------------------------------------

--
-- بنية الجدول `audit_trail`
--

CREATE TABLE `audit_trail` (
  `Audit_ID` int(11) NOT NULL,
  `User_ID` int(11) DEFAULT NULL,
  `Action_Type` enum('CREATE','UPDATE','DELETE','LOGIN','EXPORT','BACKUP') DEFAULT NULL,
  `Table_Name` varchar(50) DEFAULT NULL,
  `Record_ID` int(11) DEFAULT NULL,
  `Old_Value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `New_Value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`New_Value`)),
  `Action_Details` varchar(500) DEFAULT NULL,
  `IP_Address` varchar(45) DEFAULT NULL,
  `User_Agent` text DEFAULT NULL,
  `Action_Timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `backup_history`
--

CREATE TABLE `backup_history` (
  `Backup_ID` int(11) NOT NULL,
  `Backup_File` varchar(255) NOT NULL,
  `Backup_Date` timestamp NULL DEFAULT current_timestamp(),
  `Backup_Size` varchar(50) DEFAULT NULL,
  `Status` enum('SUCCESS','FAILED','PENDING') DEFAULT NULL,
  `Created_By` int(11) DEFAULT NULL,
  `Notes` text DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `cities`
--

CREATE TABLE `cities` (
  `City_ID` int(11) NOT NULL,
  `City_Name` varchar(100) NOT NULL,
  `Office_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `education_offices`
--

CREATE TABLE `education_offices` (
  `Office_ID` int(11) NOT NULL,
  `Office_Name` varchar(100) NOT NULL,
  `Region_ID` int(11) DEFAULT NULL,
  `Location` varchar(100) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `education_offices`
--

INSERT INTO `education_offices` (`Office_ID`, `Office_Name`, `Region_ID`, `Location`) VALUES
(1, 'مكتب تعليم وسط جازان', NULL, 'جازان'),
(2, 'مكتب تعليم صبيا', NULL, 'صبيا'),
(3, 'مكتب تعليم أبو عريش', NULL, 'أبو عريش'),
(4, 'مكتب تعليم صامطة', NULL, 'صامطة'),
(5, 'مكتب تعليم الدرب', NULL, 'الدرب'),
(6, 'مكتب تعليم بيش', NULL, 'بيش'),
(7, 'مكتب تعليم العارضة', NULL, 'العارضة'),
(8, 'مكتب تعليم الداير', NULL, 'الداير'),
(9, 'مكتب تعليم العيدابي', NULL, 'العيدابي'),
(10, 'مكتب تعليم أحد المسارحة', NULL, 'أحد المسارحة'),
(11, 'مكتب تعليم فرسان', NULL, 'فرسان'),
(12, 'مكتب تعليم ضمد', NULL, 'ضمد');

-- --------------------------------------------------------

--
-- بنية الجدول `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `email_verifications`
--

INSERT INTO `email_verifications` (`id`, `user_id`, `token`, `expires_at`, `created_at`) VALUES
(1, 9, '9becbe295882994c4d88148eef1d5600c5b5921d5821c0b0b3b7c1ebfbee178c', '2026-05-17 17:48:57', '2026-05-17 13:48:57'),
(2, 10, '8fe37ae776dfa5943c00b02e12148d12778e8ac91c64b47221d006f87caae886', '2026-05-17 18:03:50', '2026-05-17 14:03:50');

-- --------------------------------------------------------

--
-- بنية الجدول `governorates`
--

CREATE TABLE `governorates` (
  `Gov_ID` int(10) UNSIGNED NOT NULL,
  `Gov_Name` varchar(100) NOT NULL,
  `Region_ID` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `governorates`
--

INSERT INTO `governorates` (`Gov_ID`, `Gov_Name`, `Region_ID`) VALUES
(1, 'صبيا', 1),
(2, 'أبو عريش', 1),
(3, 'صامطة', 1),
(4, 'الدرب', 1),
(5, 'بيش', 1),
(6, 'أحد المسارحة', 1),
(7, 'العارضة', 1),
(8, 'ضمد', 1),
(9, 'العيدابي', 1),
(10, 'فرسان', 1),
(11, 'الريث', 1),
(12, 'الدائر', 1),
(13, 'هروب', 1),
(14, 'فيفاء', 1),
(15, 'الطوال', 1),
(16, 'تجربة', 1);

-- --------------------------------------------------------

--
-- بنية الجدول `offices`
--

CREATE TABLE `offices` (
  `Office_ID` int(10) UNSIGNED NOT NULL,
  `Office_Name` varchar(100) NOT NULL,
  `Location` varchar(100) DEFAULT 'منطقة جازان',
  `Region_ID` int(10) UNSIGNED DEFAULT NULL,
  `Gov_ID` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `offices`
--

INSERT INTO `offices` (`Office_ID`, `Office_Name`, `Location`, `Region_ID`, `Gov_ID`) VALUES
(1, 'مكتب تعليم صبيا - بنين', 'منطقة جازان', NULL, 1),
(2, 'مكتب تعليم صبيا - بنات', 'منطقة جازان', NULL, 1),
(3, 'مكتب تعليم أبو عريش', 'منطقة جازان', NULL, 2),
(4, 'مكتب تعليم صامطة', 'منطقة جازان', NULL, 3),
(5, 'مكتب تعليم الدرب', 'منطقة جازان', NULL, 4),
(6, 'مكتب تعليم بيش', 'منطقة جازان', NULL, 5),
(7, 'مكتب تعليم أحد المسارحة', 'منطقة جازان', NULL, 6),
(8, 'مكتب تعليم العارضة', 'منطقة جازان', NULL, 7),
(9, 'مكتب تعليم ضمد', 'منطقة جازان', NULL, 8),
(11, 'مكتب البرمجة', 'منطقة جازان', NULL, 16);

-- --------------------------------------------------------

--
-- بنية الجدول `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `created_at`) VALUES
(1, 1, 'c27c93d3613edf30ce0c0aae17d395b04d96dea24fd23f42b1afea4e16d2491d', '2026-05-19 20:54:05', '2026-05-19 16:54:05'),
(2, 1, '96082f214e1d13592e5c0ebb7b79a0511bad4fab63260eec5a741ea7e80fdaf0', '2026-05-19 20:54:16', '2026-05-19 16:54:16');

-- --------------------------------------------------------

--
-- بنية الجدول `regions`
--

CREATE TABLE `regions` (
  `Region_ID` int(10) UNSIGNED NOT NULL,
  `Region_Name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `regions`
--

INSERT INTO `regions` (`Region_ID`, `Region_Name`) VALUES
(1, 'جازان'),
(2, 'الرياض');

-- --------------------------------------------------------

--
-- بنية الجدول `review_translations`
--

CREATE TABLE `review_translations` (
  `id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `lang` varchar(5) NOT NULL,
  `comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `review_translations`
--

INSERT INTO `review_translations` (`id`, `review_id`, `lang`, `comment`) VALUES
(1, 0, 'ar', 'تجربة 1'),
(2, 0, 'en', 'Test 1');

-- --------------------------------------------------------

--
-- بنية الجدول `schools`
--

CREATE TABLE `schools` (
  `School_ID` int(11) NOT NULL,
  `School_Name` varchar(200) NOT NULL,
  `City` varchar(100) DEFAULT NULL,
  `Education_Level` varchar(50) DEFAULT NULL,
  `Gender` varchar(50) DEFAULT NULL,
  `Office_ID` int(10) UNSIGNED DEFAULT NULL,
  `School_Website` varchar(255) DEFAULT NULL,
  `Rating` varchar(10) DEFAULT NULL,
  `Ministerial_Rating` varchar(50) DEFAULT 'ب',
  `School_Type` varchar(50) DEFAULT 'حكومي',
  `City_ID` int(11) DEFAULT NULL,
  `School_Image` varchar(255) DEFAULT NULL,
  `School_Logo` varchar(255) DEFAULT NULL,
  `Latitude` decimal(10,8) DEFAULT NULL,
  `Longitude` decimal(11,8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `schools`
--

INSERT INTO `schools` (`School_ID`, `School_Name`, `City`, `Education_Level`, `Gender`, `Office_ID`, `School_Website`, `Rating`, `Ministerial_Rating`, `School_Type`, `City_ID`, `School_Image`, `School_Logo`, `Latitude`, `Longitude`) VALUES
(1, 'ابتدائية صبيا الأولى', 'صبيا', 'ابتدائي', NULL, 1, 'https://maps.google.com', NULL, 'ممتاز', 'حكومي', NULL, NULL, NULL, 16.88920000, 42.55110000),
(2, 'متوسطة صبيا الثانية', 'صبيا', 'متوسط', NULL, 1, 'https://maps.google.com', NULL, 'جيد جداً', 'حكومي', NULL, NULL, NULL, NULL, NULL),
(3, 'ثانوية صبيا الكبرى', 'صبيا', 'ثانوي', NULL, 1, 'https://maps.google.com', NULL, 'ممتاز', 'أهلي', NULL, NULL, NULL, NULL, NULL),
(4, 'روضة أطفال صبيا', 'صبيا', 'روضة', NULL, 2, 'https://maps.google.com', NULL, 'ممتاز', 'حكومي', NULL, NULL, NULL, NULL, NULL),
(5, 'مجمع أبو عريش التعليمي', 'أبو عريش', 'مجمع', NULL, 3, 'https://maps.google.com', NULL, 'ممتاز', 'حكومي', NULL, NULL, NULL, NULL, NULL),
(6, 'ابتدائية القدس', 'أبو عريش', 'ابتدائي', NULL, 3, 'https://maps.google.com', NULL, 'جيد', 'حكومي', NULL, NULL, NULL, NULL, NULL),
(7, 'ابتدائية صامطة الأولى', 'صامطة', 'ابتدائي', NULL, 4, 'https://maps.google.com', NULL, 'جيد جداً', 'حكومي', NULL, NULL, NULL, NULL, NULL),
(8, 'ثانوية حطين', 'صامطة', 'ثانوي', NULL, 4, 'https://maps.google.com', NULL, 'ممتاز', 'حكومي', NULL, NULL, NULL, NULL, NULL),
(9, 'ابتدائية الدرب الأولى', 'الدرب', 'ابتدائي', NULL, 5, 'https://maps.google.com', NULL, 'جيد', 'حكومي', NULL, NULL, NULL, NULL, NULL),
(10, 'مجمع بيش التعليمي', 'بيش', 'مجمع', NULL, 6, 'https://maps.google.com', NULL, 'ممتاز', 'حكومي', NULL, NULL, NULL, NULL, NULL),
(11, 'ابتدائية الأحد الأولى', 'أحد المسارحة', 'ابتدائي', NULL, 7, 'https://maps.google.com', NULL, 'ممتاز', 'حكومي', NULL, 'school_11_building_6a09b31a12b444.36205532.jpg,school_11_building_6a09b3b6d10445.44007716.png', NULL, NULL, NULL),
(12, 'ثانوية العارضة الأولى', 'العارضة', 'ثانوي', NULL, 8, 'https://maps.google.com', NULL, 'جيد جداً', 'حكومي', NULL, NULL, NULL, NULL, NULL),
(13, 'متوسطة ضمد الأولى', 'ضمد', 'متوسط', NULL, 9, 'https://maps.google.com', NULL, 'ممتاز', 'حكومي', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `school_ratings`
--

CREATE TABLE `school_ratings` (
  `Rating_ID` int(11) NOT NULL,
  `School_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Rating` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `school_reviews`
--

CREATE TABLE `school_reviews` (
  `Review_ID` int(11) NOT NULL,
  `School_ID` int(11) DEFAULT NULL,
  `Visitor_Name` varchar(100) DEFAULT NULL,
  `Comment` mediumtext DEFAULT NULL,
  `Review_Date` timestamp NULL DEFAULT current_timestamp(),
  `Rating_Stars` int(11) DEFAULT 5,
  `Rating` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `school_reviews`
--

INSERT INTO `school_reviews` (`Review_ID`, `School_ID`, `Visitor_Name`, `Comment`, `Review_Date`, `Rating_Stars`, `Rating`) VALUES
(0, 11, 'yahya', 'تجربة 1', '2026-05-16 17:21:38', 5, NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `school_translations`
--

CREATE TABLE `school_translations` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `lang` varchar(5) NOT NULL,
  `name` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `school_translations`
--

INSERT INTO `school_translations` (`id`, `school_id`, `lang`, `name`) VALUES
(1, 1, 'ar', 'ابتدائية صبيا الأولى'),
(2, 2, 'ar', 'متوسطة صبيا الثانية'),
(3, 3, 'ar', 'ثانوية صبيا الكبرى'),
(4, 4, 'ar', 'روضة أطفال صبيا'),
(5, 5, 'ar', 'مجمع أبو عريش التعليمي'),
(6, 6, 'ar', 'ابتدائية القدس'),
(7, 7, 'ar', 'ابتدائية صامطة الأولى'),
(8, 8, 'ar', 'ثانوية حطين'),
(9, 9, 'ar', 'ابتدائية الدرب الأولى'),
(10, 10, 'ar', 'مجمع بيش التعليمي'),
(11, 11, 'ar', 'ابتدائية الأحد الأولى'),
(12, 12, 'ar', 'ثانوية العارضة الأولى'),
(13, 13, 'ar', 'متوسطة ضمد الأولى'),
(14, 1, 'en', 'Sabya First Elementary School'),
(15, 2, 'en', 'Sabya Second Intermediate School'),
(16, 3, 'en', 'Sabya Grand High School'),
(17, 4, 'en', 'Sabya Kindergarten'),
(18, 5, 'en', 'Abu Arish Educational Complex'),
(19, 6, 'en', 'Al-Quds Elementary School'),
(20, 7, 'en', 'Samtah First Elementary School'),
(21, 8, 'en', 'Hittin High School'),
(22, 9, 'en', 'Al-Darb First Elementary School'),
(23, 10, 'en', 'Bish Educational Complex'),
(24, 11, 'en', 'Al-Ahad First Elementary School'),
(25, 12, 'en', 'Al-Aridah First High School'),
(26, 13, 'en', 'Damad First Intermediate School');

-- --------------------------------------------------------

--
-- بنية الجدول `settings`
--

CREATE TABLE `settings` (
  `Setting_ID` int(11) NOT NULL,
  `Setting_Key` varchar(100) NOT NULL,
  `Setting_Value` longtext DEFAULT NULL,
  `Setting_Type` enum('string','boolean','number','json') DEFAULT NULL,
  `Updated_At` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `settings`
--

INSERT INTO `settings` (`Setting_ID`, `Setting_Key`, `Setting_Value`, `Setting_Type`, `Updated_At`) VALUES
(1, 'google_maps_api_key', '', 'string', NULL),
(2, 'default_language', 'ar', 'string', NULL),
(3, 'auto_backup_enabled', '1', 'boolean', NULL),
(4, 'auto_backup_frequency', 'weekly', 'string', NULL),
(5, 'max_upload_size_mb', '5', 'number', NULL),
(6, 'uploads_folder', 'uploads/', 'string', NULL),
(7, 'backup_folder', 'backups/', 'string', NULL),
(8, 'enable_rating_system', '1', 'boolean', NULL),
(9, 'enable_export_pdf', '1', 'boolean', NULL),
(10, 'enable_export_excel', '1', 'boolean', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `User_ID` int(11) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Role` enum('admin','user') DEFAULT 'user',
  `Email` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`User_ID`, `Username`, `Password`, `Role`, `Email`, `is_verified`) VALUES
(1, 'yahya', '$2y$10$8py.7pSIXzW5bV58bSLeNOcWxYX0UOyjdI12IlYCvUelUHFLfFlwa', 'admin', NULL, 0),
(5, '202506198@stu.jazanu.edu.sa', 'ALZM1234', 'user', NULL, 0),
(7, 'YJYQD', '$2y$12$zR4DTG/LDmwF7udmJUlumes8HoBYUQr4R43nKqWZCeYlpTwok3Ag.', '', NULL, 0),
(8, 'yahya1', '$2y$12$4tBuELmMbSHFh88bn4OHSu/v2WjCpw/s7LvN5Yh7Vv2s.JKGqHiKC', '', NULL, 0),
(9, 'YJYQD1', '$2y$12$csbdsUn2PZkp3BE.68lcvOqjFTlxJrg9Bak54CGcDJw.Tmy/CDEHK', 'user', 'wwf600600@gmail.com', 0),
(10, 'php', '$2y$12$cd4e8z0f2aR/G.7IWFzqouV9mylsVo98r3FaqSI6up.hNmzlBMMhq', 'user', 'wwf600600@mail.com', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`Audit_ID`);

--
-- Indexes for table `backup_history`
--
ALTER TABLE `backup_history`
  ADD PRIMARY KEY (`Backup_ID`),
  ADD KEY `idx_backup_date` (`Backup_Date`);

--
-- Indexes for table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`City_ID`),
  ADD KEY `Office_ID` (`Office_ID`);

--
-- Indexes for table `education_offices`
--
ALTER TABLE `education_offices`
  ADD PRIMARY KEY (`Office_ID`),
  ADD KEY `Region_ID` (`Region_ID`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `governorates`
--
ALTER TABLE `governorates`
  ADD PRIMARY KEY (`Gov_ID`),
  ADD KEY `Region_ID` (`Region_ID`);

--
-- Indexes for table `offices`
--
ALTER TABLE `offices`
  ADD PRIMARY KEY (`Office_ID`),
  ADD UNIQUE KEY `Office_Name` (`Office_Name`),
  ADD KEY `fk_jazan_offices_regions` (`Region_ID`),
  ADD KEY `Gov_ID` (`Gov_ID`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `regions`
--
ALTER TABLE `regions`
  ADD PRIMARY KEY (`Region_ID`);

--
-- Indexes for table `review_translations`
--
ALTER TABLE `review_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_review_lang` (`review_id`,`lang`),
  ADD KEY `idx_review` (`review_id`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`School_ID`),
  ADD KEY `fk_final_school_city` (`City_ID`),
  ADD KEY `Office_ID` (`Office_ID`),
  ADD KEY `Education_Level` (`Education_Level`),
  ADD KEY `City` (`City`),
  ADD KEY `idx_schools_office` (`Office_ID`);

--
-- Indexes for table `school_translations`
--
ALTER TABLE `school_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_school_lang` (`school_id`,`lang`),
  ADD KEY `idx_school` (`school_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`Setting_ID`),
  ADD UNIQUE KEY `Setting_Key` (`Setting_Key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`User_ID`),
  ADD UNIQUE KEY `Username` (`Username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `Audit_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup_history`
--
ALTER TABLE `backup_history`
  MODIFY `Backup_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cities`
--
ALTER TABLE `cities`
  MODIFY `City_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `governorates`
--
ALTER TABLE `governorates`
  MODIFY `Gov_ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `Office_ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `regions`
--
ALTER TABLE `regions`
  MODIFY `Region_ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `review_translations`
--
ALTER TABLE `review_translations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `School_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `school_translations`
--
ALTER TABLE `school_translations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `Setting_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `User_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- قيود الجداول المُلقاة.
--

--
-- قيود الجداول `governorates`
--
ALTER TABLE `governorates`
  ADD CONSTRAINT `fk_gov_region` FOREIGN KEY (`Region_ID`) REFERENCES `regions` (`Region_ID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_gov_to_reg` FOREIGN KEY (`Region_ID`) REFERENCES `regions` (`Region_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_region` FOREIGN KEY (`Region_ID`) REFERENCES `regions` (`Region_ID`) ON DELETE CASCADE;

--
-- قيود الجداول `offices`
--
ALTER TABLE `offices`
  ADD CONSTRAINT `fk_gov` FOREIGN KEY (`Gov_ID`) REFERENCES `governorates` (`Gov_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_jazan_offices_regions` FOREIGN KEY (`Region_ID`) REFERENCES `regions` (`Region_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_offices_gov` FOREIGN KEY (`Gov_ID`) REFERENCES `governorates` (`Gov_ID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- قيود الجداول `schools`
--
ALTER TABLE `schools`
  ADD CONSTRAINT `fk_final_school_city` FOREIGN KEY (`City_ID`) REFERENCES `cities` (`City_ID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_final_school_office` FOREIGN KEY (`Office_ID`) REFERENCES `offices` (`Office_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_office` FOREIGN KEY (`Office_ID`) REFERENCES `offices` (`Office_ID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
