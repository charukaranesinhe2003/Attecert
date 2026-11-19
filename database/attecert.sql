-- AtteCert export of `attecert`
-- Generated 2026-11-07 17:29:31

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

CREATE DATABASE IF NOT EXISTS `attecert` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `attecert`;

DROP TABLE IF EXISTS `article_translations`;
CREATE TABLE `article_translations` (
  `article_id` int(10) unsigned NOT NULL,
  `locale` char(5) NOT NULL,
  `title` varchar(200) NOT NULL,
  `excerpt` varchar(400) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `seo_title` varchar(180) DEFAULT NULL,
  `meta_description` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`article_id`,`locale`),
  CONSTRAINT `fk_article_tr` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `article_translations` (`article_id`, `locale`, `title`, `excerpt`, `content`, `seo_title`, `meta_description`) VALUES
('1', 'en', 'A simple checklist before sending certificates for attestation', 'What to check on names, copies, and previous stamps before you hand a certificate over.', '<p>What to check on names, copies, and previous stamps before you hand a certificate over.</p><p>Requirements change by authority. AtteCert confirms the current path for your document before processing starts. Keep the holder name consistent across the certificate, passport, and application.</p><h2>Before you submit</h2><ul><li>Check the spelling of the holder name.</li><li>Keep a clear scan.</li><li>Tell us the destination and the purpose.</li></ul>', 'A simple checklist before sending certificates for attestation | AtteCert', 'What to check on names, copies, and previous stamps before you hand a certificate over.'),
('1', 'si', 'A simple checklist before sending certificates for attestation', 'What to check on names, copies, and previous stamps before you hand a certificate over.', '<p>What to check on names, copies, and previous stamps before you hand a certificate over.</p><p>Requirements change by authority. AtteCert confirms the current path for your document before processing starts. Keep the holder name consistent across the certificate, passport, and application.</p><h2>Before you submit</h2><ul><li>Check the spelling of the holder name.</li><li>Keep a clear scan.</li><li>Tell us the destination and the purpose.</li></ul>', 'A simple checklist before sending certificates for attestation | AtteCert', 'What to check on names, copies, and previous stamps before you hand a certificate over.'),
('1', 'ta', 'A simple checklist before sending certificates for attestation', 'What to check on names, copies, and previous stamps before you hand a certificate over.', '<p>What to check on names, copies, and previous stamps before you hand a certificate over.</p><p>Requirements change by authority. AtteCert confirms the current path for your document before processing starts. Keep the holder name consistent across the certificate, passport, and application.</p><h2>Before you submit</h2><ul><li>Check the spelling of the holder name.</li><li>Keep a clear scan.</li><li>Tell us the destination and the purpose.</li></ul>', 'A simple checklist before sending certificates for attestation | AtteCert', 'What to check on names, copies, and previous stamps before you hand a certificate over.'),
('2', 'en', 'Planning documents for the UAE: what to prepare', 'A practical order of steps for a Sri Lankan certificate that needs to be used in the UAE.', '<p>A practical order of steps for a Sri Lankan certificate that needs to be used in the UAE.</p><p>Requirements change by authority. AtteCert confirms the current path for your document before processing starts. Keep the holder name consistent across the certificate, passport, and application.</p><h2>Before you submit</h2><ul><li>Check the spelling of the holder name.</li><li>Keep a clear scan.</li><li>Tell us the destination and the purpose.</li></ul>', 'Planning documents for the UAE: what to prepare | AtteCert', 'A practical order of steps for a Sri Lankan certificate that needs to be used in the UAE.'),
('2', 'si', 'Planning documents for the UAE: what to prepare', 'A practical order of steps for a Sri Lankan certificate that needs to be used in the UAE.', '<p>A practical order of steps for a Sri Lankan certificate that needs to be used in the UAE.</p><p>Requirements change by authority. AtteCert confirms the current path for your document before processing starts. Keep the holder name consistent across the certificate, passport, and application.</p><h2>Before you submit</h2><ul><li>Check the spelling of the holder name.</li><li>Keep a clear scan.</li><li>Tell us the destination and the purpose.</li></ul>', 'Planning documents for the UAE: what to prepare | AtteCert', 'A practical order of steps for a Sri Lankan certificate that needs to be used in the UAE.'),
('2', 'ta', 'Planning documents for the UAE: what to prepare', 'A practical order of steps for a Sri Lankan certificate that needs to be used in the UAE.', '<p>A practical order of steps for a Sri Lankan certificate that needs to be used in the UAE.</p><p>Requirements change by authority. AtteCert confirms the current path for your document before processing starts. Keep the holder name consistent across the certificate, passport, and application.</p><h2>Before you submit</h2><ul><li>Check the spelling of the holder name.</li><li>Keep a clear scan.</li><li>Tell us the destination and the purpose.</li></ul>', 'Planning documents for the UAE: what to prepare | AtteCert', 'A practical order of steps for a Sri Lankan certificate that needs to be used in the UAE.'),
('3', 'en', 'When official translation may be required', 'How to tell whether the receiving authority is likely to ask for a certified translation.', '<p>How to tell whether the receiving authority is likely to ask for a certified translation.</p><p>Requirements change by authority. AtteCert confirms the current path for your document before processing starts. Keep the holder name consistent across the certificate, passport, and application.</p><h2>Before you submit</h2><ul><li>Check the spelling of the holder name.</li><li>Keep a clear scan.</li><li>Tell us the destination and the purpose.</li></ul>', 'When official translation may be required | AtteCert', 'How to tell whether the receiving authority is likely to ask for a certified translation.'),
('3', 'si', 'When official translation may be required', 'How to tell whether the receiving authority is likely to ask for a certified translation.', '<p>How to tell whether the receiving authority is likely to ask for a certified translation.</p><p>Requirements change by authority. AtteCert confirms the current path for your document before processing starts. Keep the holder name consistent across the certificate, passport, and application.</p><h2>Before you submit</h2><ul><li>Check the spelling of the holder name.</li><li>Keep a clear scan.</li><li>Tell us the destination and the purpose.</li></ul>', 'When official translation may be required | AtteCert', 'How to tell whether the receiving authority is likely to ask for a certified translation.'),
('3', 'ta', 'When official translation may be required', 'How to tell whether the receiving authority is likely to ask for a certified translation.', '<p>How to tell whether the receiving authority is likely to ask for a certified translation.</p><p>Requirements change by authority. AtteCert confirms the current path for your document before processing starts. Keep the holder name consistent across the certificate, passport, and application.</p><h2>Before you submit</h2><ul><li>Check the spelling of the holder name.</li><li>Keep a clear scan.</li><li>Tell us the destination and the purpose.</li></ul>', 'When official translation may be required | AtteCert', 'How to tell whether the receiving authority is likely to ask for a certified translation.');

DROP TABLE IF EXISTS `articles`;
CREATE TABLE `articles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(160) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `author_id` bigint(20) unsigned DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_articles_slug` (`slug`),
  KEY `idx_articles_public` (`status`,`published_at`),
  KEY `fk_articles_author` (`author_id`),
  CONSTRAINT `fk_articles_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `articles` (`id`, `slug`, `status`, `featured`, `image_path`, `author_id`, `published_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
('1', 'checklist-before-attestation', 'published', '1', 'guide-checklist.webp', '1', '2026-10-15 09:06:31', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL),
('2', 'uae-document-plan', 'published', '1', 'guide-travel.webp', '1', '2026-10-15 09:06:31', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL),
('3', 'when-translation-is-required', 'published', '1', 'guide-documents.webp', '1', '2026-10-15 09:06:31', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL);

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity` varchar(40) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_entity` (`entity`,`entity_id`),
  KEY `idx_audit_user` (`user_id`,`created_at`),
  KEY `idx_audit_action` (`action`,`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `case_services`;
CREATE TABLE `case_services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `case_id` bigint(20) unsigned NOT NULL,
  `service_id` int(10) unsigned NOT NULL,
  `quantity` decimal(8,2) NOT NULL DEFAULT 1.00,
  `pricing_type` enum('fixed','custom') NOT NULL,
  `unit_price` decimal(12,2) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'LKR',
  PRIMARY KEY (`id`),
  KEY `idx_case_services_case` (`case_id`),
  KEY `fk_case_services_service` (`service_id`),
  CONSTRAINT `fk_case_services_case` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_case_services_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `case_services` (`id`, `case_id`, `service_id`, `quantity`, `pricing_type`, `unit_price`, `currency`) VALUES
('1', '1', '2', '1.00', 'fixed', '3500.00', 'LKR'),
('2', '1', '7', '1.00', 'fixed', '3500.00', 'LKR'),
('3', '1', '3', '1.00', 'fixed', '12000.00', 'LKR');

DROP TABLE IF EXISTS `case_status_history`;
CREATE TABLE `case_status_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `case_id` bigint(20) unsigned NOT NULL,
  `status_id` smallint(5) unsigned NOT NULL,
  `staff_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `customer_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_case_history_case` (`case_id`,`created_at`),
  KEY `fk_csh_status` (`status_id`),
  KEY `fk_csh_staff` (`staff_id`),
  CONSTRAINT `fk_csh_case` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_csh_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_csh_status` FOREIGN KEY (`status_id`) REFERENCES `workflow_statuses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `case_status_history` (`id`, `case_id`, `status_id`, `staff_id`, `notes`, `customer_visible`, `created_at`) VALUES
('1', '1', '4', '1', 'Demo timeline', '1', '2026-10-15 09:06:31'),
('2', '1', '11', '1', 'Demo timeline', '1', '2026-10-15 09:06:31'),
('3', '1', '13', '1', 'Demo timeline', '1', '2026-10-15 09:06:31'),
('4', '1', '14', '1', 'Demo timeline', '1', '2026-10-15 09:06:31');

DROP TABLE IF EXISTS `cases`;
CREATE TABLE `cases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(24) NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `country_id` int(10) unsigned DEFAULT NULL,
  `document_type_id` int(10) unsigned DEFAULT NULL,
  `document_title` varchar(180) NOT NULL,
  `summary` text DEFAULT NULL,
  `status_id` smallint(5) unsigned NOT NULL,
  `priority` varchar(20) NOT NULL DEFAULT 'normal',
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `source` varchar(30) NOT NULL DEFAULT 'admin',
  `quote_request_id` bigint(20) unsigned DEFAULT NULL,
  `estimated_next` varchar(180) DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `customer_notes` text DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'LKR',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cases_number` (`number`),
  KEY `idx_cases_customer` (`customer_id`,`created_at`),
  KEY `idx_cases_status` (`status_id`,`deleted_at`),
  KEY `idx_cases_country` (`country_id`),
  KEY `idx_cases_created` (`created_at`),
  KEY `fk_cases_doctype` (`document_type_id`),
  KEY `fk_cases_assignee` (`assigned_to`),
  KEY `fk_cases_creator` (`created_by`),
  KEY `fk_cases_quote_request` (`quote_request_id`),
  CONSTRAINT `fk_cases_assignee` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cases_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `fk_cases_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cases_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_cases_doctype` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`),
  CONSTRAINT `fk_cases_quote_request` FOREIGN KEY (`quote_request_id`) REFERENCES `quote_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cases_status` FOREIGN KEY (`status_id`) REFERENCES `workflow_statuses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `cases` (`id`, `number`, `customer_id`, `country_id`, `document_type_id`, `document_title`, `summary`, `status_id`, `priority`, `assigned_to`, `source`, `quote_request_id`, `estimated_next`, `internal_notes`, `customer_notes`, `currency`, `created_by`, `completed_at`, `cancelled_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
('1', 'ATT-2026-000001', '1', '1', '1', 'Degree certificate', 'UAE employment file', '14', 'normal', NULL, 'seed', NULL, 'MOFA completion, then embassy legalization', NULL, NULL, 'LKR', '1', NULL, NULL, '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL);

DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `subject` varchar(180) DEFAULT NULL,
  `message` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `source` varchar(20) NOT NULL DEFAULT 'guest',
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `customer_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contact_status` (`status`,`created_at`),
  KEY `idx_contact_source` (`source`,`created_at`),
  KEY `fk_contact_user` (`user_id`),
  KEY `fk_contact_customer` (`customer_id`),
  CONSTRAINT `fk_contact_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contact_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `content_block_translations`;
CREATE TABLE `content_block_translations` (
  `block_id` int(10) unsigned NOT NULL,
  `locale` char(5) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`block_id`,`locale`),
  CONSTRAINT `fk_cbt` FOREIGN KEY (`block_id`) REFERENCES `content_blocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `content_block_translations` (`block_id`, `locale`, `value`) VALUES
('1', 'en', 'Document attestation and legalization'),
('1', 'si', 'ලේඛන සහතික කිරීම සහ නීතිගත කිරීම'),
('1', 'ta', 'ஆவண சான்றிதழ் மற்றும் சட்டப்பூர்வமாக்கல்'),
('2', 'en', 'AtteCert helps people in Sri Lanka prepare documents for official use at home and abroad.'),
('2', 'si', 'ශ්‍රී ලංකාවේ සහ විදේශයන්හි නිල භාවිතය සඳහා ලේඛන සූදානම් කිරීමට AtteCert උපකාරී වේ.'),
('2', 'ta', 'இலங்கையிலும் வெளிநாட்டிலும் அதிகாரப்பூர்வ பயன்பாட்டுக்கு ஆவணங்களை AtteCert தயார் செய்கிறது.'),
('3', 'en', 'Document attestation in Sri Lanka'),
('3', 'si', 'ශ්‍රී ලංකාවේ ලේඛන සහතික කිරීම'),
('3', 'ta', 'இலங்கையில் ஆவண சான்றிதழ்'),
('4', 'en', 'Attecert: Fast, Reliable Document Attestation in Sri Lanka'),
('4', 'si', 'AtteCert: ශ්‍රී ලංකාවේ වේගවත්, විශ්වාසදායක ලේඛන සහතික කිරීම'),
('4', 'ta', 'AtteCert: இலங்கையில் விரைவான, நம்பகமான ஆவண சான்றிதழ்'),
('5', 'en', 'Send certificates, translations, and supporting papers to one team. We coordinate attestation, MOFA, embassy legalization, and delivery.'),
('5', 'si', 'සහතික, පරිවර්තන, සහාය ලේඛන එක කණ්ඩායමකට එවන්න. සහතික කිරීම, විදේශ අමාත්‍යාංශය, තානාපති කාර්යාලය, සහ භාරදීම අපි සම්බන්ධීකරණය කරමු.'),
('5', 'ta', 'சான்றிதழ்கள், மொழிபெயர்ப்புகள், துணை ஆவணங்களை ஒரே குழுவிடம் அனுப்புங்கள். சான்றிதழ், MOFA, தூதரகம், வழங்கல் ஆகியவற்றை ஒருங்கிணைக்கிறோம்.'),
('6', 'en', 'Clear requirements'),
('6', 'si', 'පැහැදිලි අවශ්‍යතා'),
('6', 'ta', 'தெளிவான தேவைகள்'),
('7', 'en', 'Experienced handling'),
('7', 'si', 'පළපුරුදු හැසිරවීම'),
('7', 'ta', 'அனுபவமிக்க கையாளுதல்'),
('8', 'en', 'Secure document care'),
('8', 'si', 'ආරක්ෂිත ලේඛන රැකවරණය'),
('8', 'ta', 'பாதுகாப்பான ஆவண பராமரிப்பு'),
('9', 'en', 'One careful team, every step of the way.'),
('9', 'si', 'සෑම පියවරකදීම එක සැලකිලිමත් කණ්ඩායමක්.'),
('9', 'ta', 'ஒவ்வொரு படியிலும் ஒரு கவனமுள்ள குழு.'),
('10', 'en', 'From the first review to the final stamped document, the same team follows your case and keeps every file on one reference number.'),
('10', 'si', 'පළමු සමාලෝචනයේ සිට අවසන් මුද්‍රාව දක්වා එකම කණ්ඩායම ඔබේ නඩුව ගෙන යයි.'),
('10', 'ta', 'முதல் மதிப்பாய்வு முதல் இறுதி முத்திரை வரை ஒரே குழு உங்கள் வழக்கை கையாளும்.'),
('11', 'en', 'Requirements checked before processing starts'),
('11', 'si', 'සැකසීමට පෙර අවශ්‍යතා පරීක්ෂා කරයි'),
('11', 'ta', 'செயலாக்கத்துக்கு முன் தேவைகள் சரிபார்க்கப்படும்'),
('12', 'en', 'Fixed prices where a standard fee applies'),
('12', 'si', 'සම්මත ගාස්තුවක් ඇති විට ස්ථිර මිල'),
('12', 'ta', 'நிலையான கட்டணம் உள்ள இடங்களில் நிலையான விலை'),
('13', 'en', 'Status updates you can track online'),
('13', 'si', 'මාර්ගගතව බැලිය හැකි තත්ත්ව යාවත්කාලීන'),
('13', 'ta', 'இணையத்தில் காணக்கூடிய நிலை புதுப்பிப்பு'),
('14', 'en', 'Completed documents ready to download'),
('14', 'si', 'බාගත කළ හැකි සම්පූර්ණ ලේඛන'),
('14', 'ta', 'பதிவிறக்க தயாரான முடிந்த ஆவணங்கள்'),
('15', 'en', 'Our services'),
('15', 'si', 'අපේ සේවා'),
('15', 'ta', 'எங்கள் சேவைகள்'),
('16', 'en', 'Document support for home and abroad'),
('16', 'si', 'දේශීය හා විදේශ ලේඛන සහාය'),
('16', 'ta', 'உள்நாட்டு மற்றும் வெளிநாட்டு ஆவண ஆதரவு'),
('17', 'en', 'Choose the step your document needs. We confirm the exact path for your destination before work begins.'),
('17', 'si', 'ඔබේ ලේඛනයට අවශ්‍ය පියවර තෝරන්න. වැඩ ආරම්භයට පෙර ගමනාන්ත මාර්ගය තහවුරු කරමු.'),
('17', 'ta', 'ஆவணத்துக்கு தேவையான படியை தேர்வு செய்க. வேலை தொடங்கும் முன் பாதையை உறுதி செய்கிறோம்.'),
('18', 'en', 'How it works'),
('18', 'si', 'එය ක්‍රියා කරන ආකාරය'),
('18', 'ta', 'இது எப்படி செயல்படுகிறது'),
('19', 'en', 'Four clear steps. No office-to-office guesswork.'),
('19', 'si', 'පැහැදිලි පියවර හතරක්. කාර්යාලයෙන් කාර්යාලයට අනුමාන නැත.'),
('19', 'ta', 'நான்கு தெளிவான படிகள். அலுவலகம் அலுவலகமாக ஓட வேண்டாம்.'),
('20', 'en', 'You send the document. We review it, process the required authorities, and return the completed file.'),
('20', 'si', 'ඔබ ලේඛනය එවයි. අපි සමාලෝචනය කර, අවශ්‍ය අධිකාරීන් හරහා සකසා, සම්පූර්ණ ගොනුව ආපසු දෙමු.'),
('20', 'ta', 'நீங்கள் ஆவணத்தை அனுப்புகிறீர்கள். நாங்கள் மதிப்பாய்வு செய்து, அதிகாரிகள் வழியாக செயலாக்கி, முடிந்த கோப்பை தருகிறோம்.'),
('21', 'en', 'Send us your documents'),
('21', 'si', 'ලේඛන අප වෙත එවන්න'),
('21', 'ta', 'ஆவணங்களை எங்களுக்கு அனுப்புங்கள்'),
('22', 'en', 'Share the scan or original and the destination.'),
('22', 'si', 'ස්කෑන් හෝ මුල් පිටපත සහ ගමනාන්තය දෙන්න.'),
('22', 'ta', 'ஸ்கேன் அல்லது அசலையும் சேரும் இடத்தையும் பகிருங்கள்.'),
('23', 'en', 'Review and confirmation'),
('23', 'si', 'සමාලෝචනය සහ තහවුරු කිරීම'),
('23', 'ta', 'மதிப்பாய்வு மற்றும் உறுதி'),
('24', 'en', 'We confirm the path, the fee, and any missing papers.'),
('24', 'si', 'මාර්ගය, ගාස්තුව, සහ අඩුපාඩු තහවුරු කරමු.'),
('24', 'ta', 'பாதை, கட்டணம், குறையும் ஆவணங்களை உறுதி செய்கிறோம்.'),
('25', 'en', 'We process them'),
('25', 'si', 'අපි සකසමු'),
('25', 'ta', 'நாங்கள் செயலாக்குகிறோம்'),
('26', 'en', 'Attestation, MOFA, translation, or embassy work is tracked on one case.'),
('26', 'si', 'සහතික කිරීම, විදේශ අමාත්‍යාංශය, පරිවර්තනය, තානාපති කාර්යාලය එක නඩුවක ලියැවෙයි.'),
('26', 'ta', 'சான்றிதழ், MOFA, மொழிபெயர்ப்பு, தூதரகம் ஒரே வழக்கில் பதிவாகும்.'),
('27', 'en', 'We deliver to you'),
('27', 'si', 'ඔබ වෙත භාර දෙමු'),
('27', 'ta', 'உங்களிடம் வழங்குகிறோம்'),
('28', 'en', 'Collect the file or download it from your account.'),
('28', 'si', 'ගොනුව ලබා ගන්න හෝ ගිණුමෙන් බාගන්න.'),
('28', 'ta', 'கோப்பை பெறுங்கள் அல்லது கணக்கிலிருந்து பதிவிறக்குங்கள்.'),
('29', 'en', 'Track your document'),
('29', 'si', 'ඔබේ ලේඛනය සොයන්න'),
('29', 'ta', 'ஆவணத்தை கண்காணிக்கவும்'),
('30', 'en', 'Enter the reference number from your quotation or case.'),
('30', 'si', 'ඔබේ උපුටා දැක්වීමේ හෝ නඩු අංකය ඇතුළත් කරන්න.'),
('30', 'ta', 'மதிப்பீடு அல்லது வழக்கு எண்ணை உள்ளிடவும்.'),
('31', 'en', 'Destinations'),
('31', 'si', 'ගමනාන්ත'),
('31', 'ta', 'சேருமிடங்கள்'),
('32', 'en', 'Preparing documents for another country?'),
('32', 'si', 'වෙනත් රටක් සඳහා ලේඛන සූදානම් කරනවාද?'),
('32', 'ta', 'வேறொரு நாட்டுக்கு ஆவணங்களை தயார் செய்கிறீர்களா?'),
('33', 'en', 'See the usual path for a destination. Final requirements are confirmed against your document.'),
('33', 'si', 'ගමනාන්තයේ සුපුරුදු මාර්ගය බලන්න. අවසන් අවශ්‍යතා ඔබේ ලේඛනයට අනුව තහවුරු වේ.'),
('33', 'ta', 'ஒரு நாட்டின் வழக்கமான பாதையை காணுங்கள். இறுதி தேவைகள் ஆவணத்துக்கு ஏற்ப உறுதி செய்யப்படும்.'),
('34', 'en', 'About');
INSERT INTO `content_block_translations` (`block_id`, `locale`, `value`) VALUES
('34', 'si', 'අප ගැන'),
('34', 'ta', 'எங்களை பற்றி'),
('35', 'en', 'Practical support for important documents'),
('35', 'si', 'වැදගත් ලේඛන සඳහා ප්‍රායෝගික සහාය'),
('35', 'ta', 'முக்கிய ஆவணங்களுக்கு நடைமுறை ஆதரவு'),
('36', 'en', 'AtteCert coordinates the steps between you and the authorities, so the file stays with one responsible team.'),
('36', 'si', 'ඔබ සහ අධිකාරීන් අතර පියවර AtteCert සම්බන්ධීකරණය කරයි.'),
('36', 'ta', 'உங்களுக்கும் அதிகாரிகளுக்கும் இடையிலான படிகளை AtteCert ஒருங்கிணைக்கிறது.'),
('37', 'en', '2022'),
('37', 'si', '2022'),
('37', 'ta', '2022'),
('38', 'en', 'Supporting documents since'),
('38', 'si', 'සිට ලේඛන සහාය'),
('38', 'ta', 'முதல் ஆவண ஆதரவு'),
('39', 'en', 'Clear requirements'),
('39', 'si', 'පැහැදිලි අවශ්‍යතා'),
('39', 'ta', 'தெளிவான தேவைகள்'),
('40', 'en', 'We say what the authority is likely to ask for before you spend a day in a queue.'),
('40', 'si', 'පෝලිමේ දවසක් ගත කිරීමට පෙර අධිකාරිය ඉල්ලන දේ කියමු.'),
('40', 'ta', 'வரிசையில் ஒரு நாள் கழிக்கும் முன் அதிகாரம் என்ன கேட்கும் என்று சொல்கிறோம்.'),
('41', 'en', 'A tracked case'),
('41', 'si', 'ලුහුබැඳිය හැකි නඩුවක්'),
('41', 'ta', 'கண்காணிக்கும் வழக்கு'),
('42', 'en', 'Every quotation, invoice, and file stays attached to one reference number.'),
('42', 'si', 'සෑම උපුටා දැක්වීමක්, ඉන්වොයිසියක්, ගොනුවක් එක අංකයකට බැඳේ.'),
('42', 'ta', 'ஒவ்வொரு மதிப்பீடு, விலைப்பட்டியல், கோப்பும் ஒரே எண்ணில் இருக்கும்.'),
('43', 'en', 'Careful handling'),
('43', 'si', 'සැලකිලිමත් හැසිරවීම'),
('43', 'ta', 'கவனமான கையாளுதல்'),
('44', 'en', 'Completed files are released only to the customer account or authorised staff.'),
('44', 'si', 'සම්පූර්ණ ගොනු පාරිභෝගික ගිණුමට හෝ බලයලත් කාර්ය මණ්ඩලයට පමණයි.'),
('44', 'ta', 'முடிந்த கோப்புகள் வாடிக்கையாளர் கணக்கு அல்லது அங்கீகரித்த ஊழியருக்கு மட்டுமே.'),
('45', 'en', 'Requirements can change by authority and destination. We confirm the current path before processing starts.'),
('45', 'si', 'අවශ්‍යතා අධිකාරිය සහ ගමනාන්තය අනුව වෙනස් විය හැක. සැකසීමට පෙර වත්මන් මාර්ගය තහවුරු කරමු.'),
('45', 'ta', 'தேவைகள் அதிகாரம் மற்றும் நாட்டைப் பொறுத்து மாறலாம். செயலாக்கத்துக்கு முன் தற்போதைய பாதையை உறுதி செய்கிறோம்.'),
('46', 'en', 'FAQ'),
('46', 'si', 'ප්‍රශ්න'),
('46', 'ta', 'கேள்விகள்'),
('47', 'en', 'Questions before you begin'),
('47', 'si', 'ආරම්භයට පෙර ප්‍රශ්න'),
('47', 'ta', 'தொடங்கும் முன் கேள்விகள்'),
('48', 'en', 'A short briefing on documents, timing, and what we need from you.'),
('48', 'si', 'ලේඛන, කාලය, සහ අපට අවශ්‍ය දේ පිළිබඳ කෙටි විස්තරයක්.'),
('48', 'ta', 'ஆவணங்கள், நேரம், உங்களிடம் தேவையானவை பற்றிய சுருக்க விளக்கம்.'),
('49', 'en', 'Not sure where to start?'),
('49', 'si', 'කොතැනින් පටන් ගන්නද යන්න විශ්වාස නැද්ද?'),
('49', 'ta', 'எங்கு தொடங்குவது என்று தெரியவில்லையா?'),
('50', 'en', 'Tell us the document and destination. We will map the next steps.'),
('50', 'si', 'ලේඛනය සහ ගමනාන්තය කියන්න. ඊළඟ පියවර සකසමු.'),
('50', 'ta', 'ஆவணத்தையும் சேரும் இடத்தையும் சொல்லுங்கள். அடுத்த படிகளை வரைவோம்.'),
('51', 'en', 'Guides'),
('51', 'si', 'මාර්ගෝපදේශ'),
('51', 'ta', 'வழிகாட்டிகள்'),
('52', 'en', 'Useful guidance before you submit'),
('52', 'si', 'භාර දීමට පෙර ප්‍රයෝජනවත් මඟ පෙන්වීම'),
('52', 'ta', 'சமர்ப்பிக்கும் முன் பயனுள்ள வழிகாட்டுதல்'),
('53', 'en', 'Short notes on certificates, destinations, and when a translation is required.'),
('53', 'si', 'සහතික, ගමනාන්ත, සහ පරිවර්තනය අවශ්‍ය විට පිළිබඳ කෙටි සටහන්.'),
('53', 'ta', 'சான்றிதழ்கள், நாடுகள், மொழிபெயர்ப்பு தேவைப்படும் நேரம் பற்றிய குறிப்புகள்.'),
('54', 'en', 'Request a quote'),
('54', 'si', 'මිල ගණන් ඉල්ලන්න'),
('54', 'ta', 'மதிப்பீடு கோருங்கள்'),
('55', 'en', 'Tell us what you need. We\'ll map the next steps.'),
('55', 'si', 'ඔබට අවශ්‍ය දේ කියන්න. ඊළඟ පියවර අපි සකසමු.'),
('55', 'ta', 'உங்களுக்கு தேவதை சொல்லுங்கள். அடுத்த படிகளை வரைவோம்.'),
('56', 'en', 'Share the document, destination, and how we can reach you. A coordinator will confirm the path and the fee.'),
('56', 'si', 'ලේඛනය, ගමනාන්තය, සහ සම්බන්ධ විය හැකි ආකාරය දෙන්න. සම්බන්ධීකාරකයෙක් මාර්ගය සහ ගාස්තුව තහවුරු කරයි.'),
('56', 'ta', 'ஆவணம், சேருமிடம், தொடர்பு வழியை பகிருங்கள். ஒருங்கிணைப்பாளர் பாதையையும் கட்டணத்தையும் உறுதி செய்வார்.'),
('57', 'en', 'Ready to simplify your document process?'),
('57', 'si', 'ඔබේ ලේඛන ක්‍රියාවලිය සරල කිරීමට සූදානම්ද?'),
('57', 'ta', 'ஆவண செயல்முறையை எளிதாக்க தயாரா?'),
('58', 'en', 'Share the document and destination. We will confirm the path, the fee, and the timing.'),
('58', 'si', 'ලේඛනය සහ ගමනාන්තය දෙන්න. මාර්ගය, ගාස්තුව, කාලය තහවුරු කරමු.'),
('58', 'ta', 'ஆவணத்தையும் சேருமிடத்தையும் பகிருங்கள். பாதை, கட்டணம், நேரத்தை உறுதி செய்வோம்.');

DROP TABLE IF EXISTS `content_blocks`;
CREATE TABLE `content_blocks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `block_key` varchar(80) NOT NULL,
  `group_name` varchar(40) NOT NULL DEFAULT 'home',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_content_key` (`block_key`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `content_blocks` (`id`, `block_key`, `group_name`, `updated_at`) VALUES
('1', 'topbar', 'home', '2026-10-15 09:06:31'),
('2', 'footer_blurb', 'home', '2026-10-15 09:06:31'),
('3', 'hero_eyebrow', 'home', '2026-10-15 09:06:31'),
('4', 'hero_title', 'home', '2026-10-15 09:06:31'),
('5', 'hero_lead', 'home', '2026-10-15 09:06:31'),
('6', 'trust_1', 'home', '2026-10-15 09:06:31'),
('7', 'trust_2', 'home', '2026-10-15 09:06:31'),
('8', 'trust_3', 'home', '2026-10-15 09:06:31'),
('9', 'care_title', 'home', '2026-10-15 09:06:31'),
('10', 'care_lead', 'home', '2026-10-15 09:06:31'),
('11', 'care_1', 'home', '2026-10-15 09:06:31'),
('12', 'care_2', 'home', '2026-10-15 09:06:31'),
('13', 'care_3', 'home', '2026-10-15 09:06:31'),
('14', 'care_4', 'home', '2026-10-15 09:06:31'),
('15', 'services_eyebrow', 'home', '2026-10-15 09:06:31'),
('16', 'services_title', 'home', '2026-10-15 09:06:31'),
('17', 'services_lead', 'home', '2026-10-15 09:06:31'),
('18', 'process_eyebrow', 'home', '2026-10-15 09:06:31'),
('19', 'process_title', 'home', '2026-10-15 09:06:31'),
('20', 'process_lead', 'home', '2026-10-15 09:06:31'),
('21', 'process_1_title', 'home', '2026-10-15 09:06:31'),
('22', 'process_1_text', 'home', '2026-10-15 09:06:31'),
('23', 'process_2_title', 'home', '2026-10-15 09:06:31'),
('24', 'process_2_text', 'home', '2026-10-15 09:06:31'),
('25', 'process_3_title', 'home', '2026-10-15 09:06:31'),
('26', 'process_3_text', 'home', '2026-10-15 09:06:31'),
('27', 'process_4_title', 'home', '2026-10-15 09:06:31'),
('28', 'process_4_text', 'home', '2026-10-15 09:06:31'),
('29', 'track_title', 'home', '2026-10-15 09:06:31'),
('30', 'track_lead', 'home', '2026-10-15 09:06:31'),
('31', 'countries_eyebrow', 'home', '2026-10-15 09:06:31'),
('32', 'countries_title', 'home', '2026-10-15 09:06:31'),
('33', 'countries_lead', 'home', '2026-10-15 09:06:31'),
('34', 'about_eyebrow', 'home', '2026-10-15 09:06:31'),
('35', 'about_title', 'home', '2026-10-15 09:06:31'),
('36', 'about_lead', 'home', '2026-10-15 09:06:31'),
('37', 'about_year', 'home', '2026-10-15 09:06:31'),
('38', 'about_year_label', 'home', '2026-10-15 09:06:31'),
('39', 'stat_1_title', 'home', '2026-10-15 09:06:31'),
('40', 'stat_1_text', 'home', '2026-10-15 09:06:31'),
('41', 'stat_2_title', 'home', '2026-10-15 09:06:31'),
('42', 'stat_2_text', 'home', '2026-10-15 09:06:31'),
('43', 'stat_3_title', 'home', '2026-10-15 09:06:31'),
('44', 'stat_3_text', 'home', '2026-10-15 09:06:31'),
('45', 'disclaimer', 'home', '2026-10-15 09:06:31'),
('46', 'faq_eyebrow', 'home', '2026-10-15 09:06:31'),
('47', 'faq_title', 'home', '2026-10-15 09:06:31'),
('48', 'faq_lead', 'home', '2026-10-15 09:06:31'),
('49', 'faq_card_title', 'home', '2026-10-15 09:06:31'),
('50', 'faq_card_text', 'home', '2026-10-15 09:06:31'),
('51', 'guides_eyebrow', 'home', '2026-10-15 09:06:31'),
('52', 'guides_title', 'home', '2026-10-15 09:06:31'),
('53', 'guides_lead', 'home', '2026-10-15 09:06:31'),
('54', 'quote_eyebrow', 'home', '2026-10-15 09:06:31'),
('55', 'quote_title', 'home', '2026-10-15 09:06:31'),
('56', 'quote_lead', 'home', '2026-10-15 09:06:31'),
('57', 'final_title', 'home', '2026-10-15 09:06:31'),
('58', 'final_lead', 'home', '2026-10-15 09:06:31');

DROP TABLE IF EXISTS `countries`;
CREATE TABLE `countries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` char(2) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_countries_code` (`code`),
  UNIQUE KEY `uq_countries_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `countries` (`id`, `code`, `slug`, `is_active`, `featured`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES
('1', 'AE', 'uae', '1', '1', '1', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL),
('2', 'QA', 'qatar', '1', '1', '2', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL),
('3', 'KW', 'kuwait', '1', '1', '3', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL),
('4', 'GB', 'united-kingdom', '1', '1', '4', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL),
('5', 'CA', 'canada', '1', '1', '5', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL),
('6', 'AU', 'australia', '1', '1', '6', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL);

DROP TABLE IF EXISTS `country_services`;
CREATE TABLE `country_services` (
  `country_id` int(10) unsigned NOT NULL,
  `service_id` int(10) unsigned NOT NULL,
  `price_override` decimal(12,2) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`country_id`,`service_id`),
  KEY `fk_cs_service` (`service_id`),
  CONSTRAINT `fk_cs_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cs_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `country_services` (`country_id`, `service_id`, `price_override`, `notes`) VALUES
('1', '1', NULL, NULL),
('1', '2', NULL, NULL),
('1', '3', NULL, NULL),
('1', '4', NULL, NULL),
('1', '5', NULL, NULL),
('1', '6', NULL, NULL),
('2', '1', NULL, NULL),
('2', '2', NULL, NULL),
('2', '3', NULL, NULL),
('2', '4', NULL, NULL),
('2', '5', NULL, NULL),
('2', '6', NULL, NULL),
('3', '1', NULL, NULL),
('3', '2', NULL, NULL),
('3', '3', NULL, NULL),
('3', '4', NULL, NULL),
('3', '5', NULL, NULL),
('3', '6', NULL, NULL),
('4', '1', NULL, NULL),
('4', '2', NULL, NULL),
('4', '3', NULL, NULL),
('4', '4', NULL, NULL),
('4', '5', NULL, NULL),
('4', '6', NULL, NULL),
('5', '1', NULL, NULL),
('5', '2', NULL, NULL),
('5', '3', NULL, NULL),
('5', '4', NULL, NULL),
('5', '5', NULL, NULL),
('5', '6', NULL, NULL),
('6', '1', NULL, NULL),
('6', '2', NULL, NULL),
('6', '3', NULL, NULL),
('6', '4', NULL, NULL),
('6', '5', NULL, NULL),
('6', '6', NULL, NULL);

DROP TABLE IF EXISTS `country_translations`;
CREATE TABLE `country_translations` (
  `country_id` int(10) unsigned NOT NULL,
  `locale` char(5) NOT NULL,
  `name` varchar(120) NOT NULL,
  `summary` varchar(300) DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `documents_required` text DEFAULT NULL,
  `process_text` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `processing_info` varchar(180) DEFAULT NULL,
  `seo_title` varchar(180) DEFAULT NULL,
  `meta_description` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`country_id`,`locale`),
  CONSTRAINT `fk_country_tr` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `country_translations` (`country_id`, `locale`, `name`, `summary`, `requirements`, `documents_required`, `process_text`, `notes`, `processing_info`, `seo_title`, `meta_description`) VALUES
('1', 'en', 'UAE', 'Employment and residence files often need MOFA attestation and UAE embassy legalization.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'UAE document requirements | AtteCert', 'Employment and residence files often need MOFA attestation and UAE embassy legalization.'),
('1', 'si', 'එක්සත් අරාබි එමීර් රාජ්‍යය', 'Employment and residence files often need MOFA attestation and UAE embassy legalization.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'එක්සත් අරාබි එමීර් රාජ්‍යය document requirements | AtteCert', 'Employment and residence files often need MOFA attestation and UAE embassy legalization.'),
('1', 'ta', 'ஐக்கிய அரபு எமிரேட்ஸ்', 'Employment and residence files often need MOFA attestation and UAE embassy legalization.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'ஐக்கிய அரபு எமிரேட்ஸ் document requirements | AtteCert', 'Employment and residence files often need MOFA attestation and UAE embassy legalization.'),
('2', 'en', 'Qatar', 'Certificates for Qatar commonly follow attestation before the Qatar embassy step.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'Qatar document requirements | AtteCert', 'Certificates for Qatar commonly follow attestation before the Qatar embassy step.'),
('2', 'si', 'කටාර්', 'Certificates for Qatar commonly follow attestation before the Qatar embassy step.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'කටාර් document requirements | AtteCert', 'Certificates for Qatar commonly follow attestation before the Qatar embassy step.'),
('2', 'ta', 'கத்தார்', 'Certificates for Qatar commonly follow attestation before the Qatar embassy step.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'கத்தார் document requirements | AtteCert', 'Certificates for Qatar commonly follow attestation before the Qatar embassy step.'),
('3', 'en', 'Kuwait', 'Kuwait files are checked against the current embassy legalization path.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'Kuwait document requirements | AtteCert', 'Kuwait files are checked against the current embassy legalization path.'),
('3', 'si', 'කුවේට්', 'Kuwait files are checked against the current embassy legalization path.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'කුවේට් document requirements | AtteCert', 'Kuwait files are checked against the current embassy legalization path.'),
('3', 'ta', 'குவைத்', 'Kuwait files are checked against the current embassy legalization path.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'குவைத் document requirements | AtteCert', 'Kuwait files are checked against the current embassy legalization path.'),
('4', 'en', 'United Kingdom', 'UK use can require a different legalization or apostille route.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'United Kingdom document requirements | AtteCert', 'UK use can require a different legalization or apostille route.'),
('4', 'si', 'එක්සත් රාජධානිය', 'UK use can require a different legalization or apostille route.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'එක්සත් රාජධානිය document requirements | AtteCert', 'UK use can require a different legalization or apostille route.'),
('4', 'ta', 'ஐக்கிய இராச்சியம்', 'UK use can require a different legalization or apostille route.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'ஐக்கிய இராச்சியம் document requirements | AtteCert', 'UK use can require a different legalization or apostille route.'),
('5', 'en', 'Canada', 'Canada accepts a defined authentication path that we confirm per document.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'Canada document requirements | AtteCert', 'Canada accepts a defined authentication path that we confirm per document.'),
('5', 'si', 'කැනඩාව', 'Canada accepts a defined authentication path that we confirm per document.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'කැනඩාව document requirements | AtteCert', 'Canada accepts a defined authentication path that we confirm per document.'),
('5', 'ta', 'கனடா', 'Canada accepts a defined authentication path that we confirm per document.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'கனடா document requirements | AtteCert', 'Canada accepts a defined authentication path that we confirm per document.'),
('6', 'en', 'Australia', 'Australian authorities may ask for attestation or an apostille depending on the document.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'Australia document requirements | AtteCert', 'Australian authorities may ask for attestation or an apostille depending on the document.'),
('6', 'si', 'ඕස්ට්‍රේලියාව', 'Australian authorities may ask for attestation or an apostille depending on the document.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'ඕස්ට්‍රේලියාව document requirements | AtteCert', 'Australian authorities may ask for attestation or an apostille depending on the document.'),
('6', 'ta', 'ஆஸ்திரேலியா', 'Australian authorities may ask for attestation or an apostille depending on the document.', 'Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.', 'The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.', 'Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.', 'Requirements change. AtteCert confirms the current path before processing starts.', 'Often 5–15 working days after a complete file is received.', 'ஆஸ்திரேலியா document requirements | AtteCert', 'Australian authorities may ask for attestation or an apostille depending on the document.');

DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(160) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `whatsapp` varchar(40) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_user` (`user_id`),
  KEY `idx_customers_email` (`email`),
  KEY `idx_customers_phone` (`phone`),
  KEY `idx_customers_name` (`name`),
  CONSTRAINT `fk_customers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `customers` (`id`, `user_id`, `name`, `email`, `phone`, `whatsapp`, `address`, `city`, `notes`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
('1', '2', 'Nimal Perera', 'customer@attecert.local', '+94 77 000 0000', NULL, NULL, 'Colombo', NULL, '1', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL);

DROP TABLE IF EXISTS `document_download_logs`;
CREATE TABLE `document_download_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_download_document` (`document_id`,`created_at`),
  KEY `fk_ddl_user` (`user_id`),
  CONSTRAINT `fk_ddl_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ddl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `document_type_translations`;
CREATE TABLE `document_type_translations` (
  `document_type_id` int(10) unsigned NOT NULL,
  `locale` char(5) NOT NULL,
  `name` varchar(160) NOT NULL,
  PRIMARY KEY (`document_type_id`,`locale`),
  CONSTRAINT `fk_dtt` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `document_type_translations` (`document_type_id`, `locale`, `name`) VALUES
('1', 'en', 'Degree certificate'),
('1', 'si', 'උපාධි සහතිකය'),
('1', 'ta', 'பட்டச் சான்றிதழ்'),
('2', 'en', 'Birth certificate'),
('2', 'si', 'උප්පැන්න සහතිකය'),
('2', 'ta', 'பிறப்பு சான்றிதழ்'),
('3', 'en', 'Marriage certificate'),
('3', 'si', 'විවාහ සහතිකය'),
('3', 'ta', 'திருமண சான்றிதழ்'),
('4', 'en', 'Police clearance'),
('4', 'si', 'පොලිස් වාර්තාව'),
('4', 'ta', 'காவல் சான்றிதழ்'),
('5', 'en', 'Other document'),
('5', 'si', 'වෙනත් ලේඛනය'),
('5', 'ta', 'பிற ஆவணம்');

DROP TABLE IF EXISTS `document_types`;
CREATE TABLE `document_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_document_types_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `document_types` (`id`, `code`, `is_active`, `sort_order`) VALUES
('1', 'DEGREE', '1', '1'),
('2', 'BIRTH', '1', '2'),
('3', 'MARRIAGE', '1', '3'),
('4', 'POLICE', '1', '4'),
('5', 'OTHER', '1', '5');

DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `case_id` bigint(20) unsigned DEFAULT NULL,
  `quote_request_id` bigint(20) unsigned DEFAULT NULL,
  `category` varchar(30) NOT NULL,
  `subcategory` varchar(40) DEFAULT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(180) NOT NULL,
  `mime` varchar(120) NOT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `customer_visible` tinyint(1) NOT NULL DEFAULT 0,
  `version` smallint(5) unsigned NOT NULL DEFAULT 1,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_documents_case` (`case_id`,`category`,`deleted_at`),
  KEY `idx_documents_request` (`quote_request_id`),
  KEY `fk_documents_user` (`uploaded_by`),
  CONSTRAINT `fk_documents_case` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_documents_request` FOREIGN KEY (`quote_request_id`) REFERENCES `quote_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_documents_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `email_logs`;
CREATE TABLE `email_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recipient` varchar(190) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `template` varchar(80) DEFAULT NULL,
  `status` varchar(30) NOT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `related_type` varchar(40) DEFAULT NULL,
  `related_id` bigint(20) unsigned DEFAULT NULL,
  `body_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_email_logs_related` (`related_type`,`related_id`),
  KEY `idx_email_logs_status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `embassy_processes`;
CREATE TABLE `embassy_processes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `case_id` bigint(20) unsigned NOT NULL,
  `embassy_name` varchar(160) NOT NULL,
  `country_id` int(10) unsigned DEFAULT NULL,
  `reference_no` varchar(80) DEFAULT NULL,
  `submitted_at` date DEFAULT NULL,
  `expected_at` date DEFAULT NULL,
  `completed_at` date DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'not_started',
  `notes` text DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_embassy_case` (`case_id`),
  KEY `idx_embassy_status` (`status`),
  KEY `fk_embassy_country` (`country_id`),
  KEY `fk_embassy_user` (`updated_by`),
  CONSTRAINT `fk_embassy_case` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_embassy_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_embassy_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `faq_translations`;
CREATE TABLE `faq_translations` (
  `faq_id` int(10) unsigned NOT NULL,
  `locale` char(5) NOT NULL,
  `question` varchar(255) NOT NULL,
  `answer` text NOT NULL,
  PRIMARY KEY (`faq_id`,`locale`),
  CONSTRAINT `fk_faq_tr` FOREIGN KEY (`faq_id`) REFERENCES `faqs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `faq_translations` (`faq_id`, `locale`, `question`, `answer`) VALUES
('1', 'en', 'Which documents can you handle?', 'Birth, marriage, degree, and police certificates are common. Other documents are reviewed before we confirm a path.'),
('1', 'si', 'Which documents can you handle?', 'Birth, marriage, degree, and police certificates are common. Other documents are reviewed before we confirm a path.'),
('1', 'ta', 'Which documents can you handle?', 'Birth, marriage, degree, and police certificates are common. Other documents are reviewed before we confirm a path.'),
('2', 'en', 'Do I need the original?', 'Some authorities accept a certified copy and some require the original. We tell you which one before submission.'),
('2', 'si', 'Do I need the original?', 'Some authorities accept a certified copy and some require the original. We tell you which one before submission.'),
('2', 'ta', 'Do I need the original?', 'Some authorities accept a certified copy and some require the original. We tell you which one before submission.'),
('3', 'en', 'How long does it take?', 'A straightforward file is often completed in one to three weeks. Embassy and MOFA queues can add time.'),
('3', 'si', 'How long does it take?', 'A straightforward file is often completed in one to three weeks. Embassy and MOFA queues can add time.'),
('3', 'ta', 'How long does it take?', 'A straightforward file is often completed in one to three weeks. Embassy and MOFA queues can add time.'),
('4', 'en', 'Can I track the file?', 'Yes. Each case has a reference number and a customer timeline that shows the steps we mark as visible.'),
('4', 'si', 'Can I track the file?', 'Yes. Each case has a reference number and a customer timeline that shows the steps we mark as visible.'),
('4', 'ta', 'Can I track the file?', 'Yes. Each case has a reference number and a customer timeline that shows the steps we mark as visible.'),
('5', 'en', 'When is a translation required?', 'When the receiving authority does not accept the original language. We confirm that before ordering a translation.'),
('5', 'si', 'When is a translation required?', 'When the receiving authority does not accept the original language. We confirm that before ordering a translation.'),
('5', 'ta', 'When is a translation required?', 'When the receiving authority does not accept the original language. We confirm that before ordering a translation.'),
('6', 'en', 'How do fees work?', 'Standard services have fixed prices. Anything that depends on the document is quoted before you confirm.'),
('6', 'si', 'How do fees work?', 'Standard services have fixed prices. Anything that depends on the document is quoted before you confirm.'),
('6', 'ta', 'How do fees work?', 'Standard services have fixed prices. Anything that depends on the document is quoted before you confirm.');

DROP TABLE IF EXISTS `faqs`;
CREATE TABLE `faqs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(80) DEFAULT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `show_on_home` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `faqs` (`id`, `category`, `sort_order`, `is_active`, `show_on_home`, `created_at`, `updated_at`) VALUES
('1', 'General', '1', '1', '1', '2026-10-15 09:06:31', '2026-10-15 09:06:31'),
('2', 'General', '2', '1', '1', '2026-10-15 09:06:31', '2026-10-15 09:06:31'),
('3', 'General', '3', '1', '1', '2026-10-15 09:06:31', '2026-10-15 09:06:31'),
('4', 'General', '4', '1', '1', '2026-10-15 09:06:31', '2026-10-15 09:06:31'),
('5', 'General', '5', '1', '1', '2026-10-15 09:06:31', '2026-10-15 09:06:31'),
('6', 'General', '6', '1', '1', '2026-10-15 09:06:31', '2026-10-15 09:06:31');

DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE `invoice_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `service_id` int(10) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(8,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_ii_invoice` (`invoice_id`),
  KEY `fk_ii_service` (`service_id`),
  CONSTRAINT `fk_ii_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ii_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(24) NOT NULL,
  `case_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `quotation_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `currency` char(3) NOT NULL DEFAULT 'LKR',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `additional_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_due` decimal(12,2) GENERATED ALWAYS AS (`total` - `amount_paid`) STORED,
  `issued_at` datetime DEFAULT NULL,
  `due_at` date DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoices_number` (`number`),
  KEY `idx_invoices_case` (`case_id`),
  KEY `idx_invoices_customer` (`customer_id`,`status`),
  KEY `idx_invoices_status` (`status`,`due_at`),
  KEY `fk_invoices_quotation` (`quotation_id`),
  KEY `fk_invoices_creator` (`created_by`),
  CONSTRAINT `fk_invoices_case` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`),
  CONSTRAINT `fk_invoices_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invoices_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_invoices_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `successful` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_lookup` (`email`,`ip`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `mofa_processes`;
CREATE TABLE `mofa_processes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `case_id` bigint(20) unsigned NOT NULL,
  `reference_no` varchar(80) DEFAULT NULL,
  `submitted_at` date DEFAULT NULL,
  `expected_at` date DEFAULT NULL,
  `completed_at` date DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'not_started',
  `notes` text DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mofa_case` (`case_id`),
  KEY `idx_mofa_status` (`status`),
  KEY `fk_mofa_user` (`updated_by`),
  CONSTRAINT `fk_mofa_case` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mofa_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(180) NOT NULL,
  `body` varchar(500) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`,`read_at`,`created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `purpose` varchar(20) NOT NULL DEFAULT 'reset',
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_password_resets_email` (`email`),
  KEY `idx_password_resets_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(24) NOT NULL,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `case_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'LKR',
  `method` varchar(40) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'completed',
  `paid_at` date NOT NULL,
  `reference` varchar(80) DEFAULT NULL,
  `receipt_reference` varchar(80) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payments_number` (`number`),
  KEY `idx_payments_invoice` (`invoice_id`),
  KEY `idx_payments_case` (`case_id`),
  KEY `idx_payments_paid` (`paid_at`),
  KEY `fk_payments_customer` (`customer_id`),
  KEY `fk_payments_user` (`recorded_by`),
  CONSTRAINT `fk_payments_case` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`),
  CONSTRAINT `fk_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`),
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(80) NOT NULL,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`id`, `code`, `name`) VALUES
('1', 'customers.view', 'View customers'),
('2', 'customers.edit', 'Edit customers'),
('3', 'cases.view', 'View cases'),
('4', 'cases.create', 'Create cases'),
('5', 'cases.edit', 'Edit cases'),
('6', 'cases.status_update', 'Update case status'),
('7', 'documents.upload', 'Upload documents'),
('8', 'documents.download', 'Download documents'),
('9', 'documents.delete', 'Archive documents'),
('10', 'quotations.create', 'Create quotations'),
('11', 'quotations.approve', 'Approve discounts and issue quotations'),
('12', 'quotations.send', 'Email quotations'),
('13', 'invoices.create', 'Create invoices'),
('14', 'invoices.send', 'Email invoices'),
('15', 'invoices.cancel', 'Cancel invoices'),
('16', 'payments.create', 'Record payments'),
('17', 'payments.view', 'View payments'),
('18', 'services.manage', 'Manage services'),
('19', 'countries.manage', 'Manage countries'),
('20', 'content.manage', 'Manage content'),
('21', 'translations.manage', 'Manage translations'),
('22', 'users.manage', 'Manage staff'),
('23', 'settings.manage', 'Manage settings'),
('24', 'reports.view', 'View reports'),
('25', 'audit.view', 'View audit log'),
('26', 'quotes.manage', 'Manage quote requests');

DROP TABLE IF EXISTS `quotation_items`;
CREATE TABLE `quotation_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` bigint(20) unsigned NOT NULL,
  `service_id` int(10) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(8,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pricing_type` varchar(20) NOT NULL DEFAULT 'fixed',
  PRIMARY KEY (`id`),
  KEY `idx_qi_quotation` (`quotation_id`),
  KEY `fk_qi_service` (`service_id`),
  CONSTRAINT `fk_qi_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qi_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `quotation_versions`;
CREATE TABLE `quotation_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` bigint(20) unsigned NOT NULL,
  `version` smallint(5) unsigned NOT NULL,
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`snapshot`)),
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qv` (`quotation_id`,`version`),
  CONSTRAINT `fk_qv_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `quotations`;
CREATE TABLE `quotations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(24) NOT NULL,
  `case_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `version` smallint(5) unsigned NOT NULL DEFAULT 1,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `currency` char(3) NOT NULL DEFAULT 'LKR',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `additional_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `valid_until` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `issued_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `viewed_at` datetime DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(500) DEFAULT NULL,
  `accepted_ip` varchar(45) DEFAULT NULL,
  `accepted_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quotations_number` (`number`),
  KEY `idx_quotations_case` (`case_id`,`version`),
  KEY `idx_quotations_customer` (`customer_id`,`status`),
  KEY `idx_quotations_status` (`status`,`created_at`),
  KEY `fk_quotations_parent` (`parent_id`),
  KEY `fk_quotations_creator` (`created_by`),
  KEY `fk_quotations_acceptor` (`accepted_by`),
  CONSTRAINT `fk_quotations_acceptor` FOREIGN KEY (`accepted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_quotations_case` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`),
  CONSTRAINT `fk_quotations_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_quotations_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_quotations_parent` FOREIGN KEY (`parent_id`) REFERENCES `quotations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `quote_request_services`;
CREATE TABLE `quote_request_services` (
  `quote_request_id` bigint(20) unsigned NOT NULL,
  `service_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`quote_request_id`,`service_id`),
  KEY `fk_qrs_service` (`service_id`),
  CONSTRAINT `fk_qrs_request` FOREIGN KEY (`quote_request_id`) REFERENCES `quote_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qrs_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `quote_requests`;
CREATE TABLE `quote_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `whatsapp` varchar(40) DEFAULT NULL,
  `country_id` int(10) unsigned DEFAULT NULL,
  `document_type_id` int(10) unsigned DEFAULT NULL,
  `document_count` int(10) unsigned NOT NULL DEFAULT 1,
  `message` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `converted_case_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_quote_requests_status` (`status`,`created_at`),
  KEY `idx_quote_requests_email` (`email`),
  KEY `fk_qr_country` (`country_id`),
  KEY `fk_qr_doctype` (`document_type_id`),
  KEY `fk_qr_case` (`converted_case_id`),
  CONSTRAINT `fk_qr_case` FOREIGN KEY (`converted_case_id`) REFERENCES `cases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qr_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qr_doctype` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `rate_limits`;
CREATE TABLE `rate_limits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `action_key` varchar(120) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 1,
  `window_start` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate_limits` (`action_key`,`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role_id` smallint(5) unsigned NOT NULL,
  `permission_id` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
('1', '1'),
('1', '2'),
('1', '3'),
('1', '4'),
('1', '5'),
('1', '6'),
('1', '7'),
('1', '8'),
('1', '9'),
('1', '10'),
('1', '11'),
('1', '12'),
('1', '13'),
('1', '14'),
('1', '15'),
('1', '16'),
('1', '17'),
('1', '18'),
('1', '19'),
('1', '20'),
('1', '21'),
('1', '22'),
('1', '23'),
('1', '24'),
('1', '25'),
('1', '26'),
('2', '1'),
('2', '2'),
('2', '3'),
('2', '4'),
('2', '5'),
('2', '6'),
('2', '7'),
('2', '8'),
('2', '9'),
('2', '10'),
('2', '11'),
('2', '12'),
('2', '13'),
('2', '14'),
('2', '15'),
('2', '16'),
('2', '17'),
('2', '18'),
('2', '19'),
('2', '20'),
('2', '21'),
('2', '22'),
('2', '24'),
('2', '25'),
('2', '26'),
('3', '1'),
('3', '2'),
('3', '3'),
('3', '4'),
('3', '5'),
('3', '6'),
('3', '7'),
('3', '8'),
('3', '10'),
('3', '11'),
('3', '12'),
('3', '13'),
('3', '14'),
('3', '17'),
('3', '24'),
('3', '26'),
('4', '1'),
('4', '3'),
('4', '5'),
('4', '6'),
('4', '7'),
('4', '8'),
('5', '1'),
('5', '3'),
('5', '8'),
('5', '10'),
('5', '11'),
('5', '12'),
('5', '13'),
('5', '14'),
('5', '15'),
('5', '16'),
('5', '17'),
('5', '24'),
('6', '19'),
('6', '20'),
('6', '21'),
('7', '1'),
('7', '2'),
('7', '3'),
('7', '4'),
('7', '8'),
('7', '26');

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `code`, `name`, `description`) VALUES
('1', 'super_admin', 'Super admin', NULL),
('2', 'admin', 'Admin', NULL),
('3', 'manager', 'Manager', NULL),
('4', 'processing_staff', 'Processing staff', NULL),
('5', 'finance_staff', 'Finance staff', NULL),
('6', 'content_editor', 'Content editor', NULL),
('7', 'customer_support', 'Customer support', NULL),
('8', 'customer', 'Customer', NULL);

DROP TABLE IF EXISTS `schema_migrations`;
CREATE TABLE `schema_migrations` (
  `version` varchar(80) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `schema_migrations` (`version`, `applied_at`) VALUES
('001_schema.sql', '2026-10-15 09:06:30'),
('002_contact_accounts.sql', '2026-10-15 09:06:30');

DROP TABLE IF EXISTS `sequences`;
CREATE TABLE `sequences` (
  `name` varchar(40) NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `current_value` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`name`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sequences` (`name`, `year`, `current_value`) VALUES
('case', '2026', '1');

DROP TABLE IF EXISTS `service_translations`;
CREATE TABLE `service_translations` (
  `service_id` int(10) unsigned NOT NULL,
  `locale` char(5) NOT NULL,
  `name` varchar(160) NOT NULL,
  `summary` varchar(300) NOT NULL,
  `description` text DEFAULT NULL,
  `process_text` text DEFAULT NULL,
  `documents_required` text DEFAULT NULL,
  `seo_title` varchar(180) DEFAULT NULL,
  `meta_description` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`service_id`,`locale`),
  CONSTRAINT `fk_service_tr` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `service_translations` (`service_id`, `locale`, `name`, `summary`, `description`, `process_text`, `documents_required`, `seo_title`, `meta_description`) VALUES
('1', 'en', 'Document Attestation', 'Authentication so a document can be accepted by the next authority.', 'Authentication so a document can be accepted by the next authority. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'Document Attestation | AtteCert', 'Authentication so a document can be accepted by the next authority.'),
('1', 'si', 'ලේඛන සහතික කිරීම', 'සහතිකය ඊළඟ අධිකාරිය පිළිගන්නා ලෙස සූදානම් කිරීම.', 'සහතිකය ඊළඟ අධිකාරිය පිළිගන්නා ලෙස සූදානම් කිරීම. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'ලේඛන සහතික කිරීම | AtteCert', 'සහතිකය ඊළඟ අධිකාරිය පිළිගන්නා ලෙස සූදානම් කිරීම.'),
('1', 'ta', 'ஆவண சான்றிதழ்', 'அடுத்த அதிகாரம் ஏற்கும்படி ஆவணத்தை தயார் செய்தல்.', 'அடுத்த அதிகாரம் ஏற்கும்படி ஆவணத்தை தயார் செய்தல். We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'ஆவண சான்றிதழ் | AtteCert', 'அடுத்த அதிகாரம் ஏற்கும்படி ஆவணத்தை தயார் செய்தல்.'),
('2', 'en', 'Official Translation', 'Certified translation when the receiving authority needs another language.', 'Certified translation when the receiving authority needs another language. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'Official Translation | AtteCert', 'Certified translation when the receiving authority needs another language.'),
('2', 'si', 'නිල පරිවර්තනය', 'ලබන අධිකාරියට වෙනත් භාෂාවක් අවශ්‍ය විට සහතික පරිවර්තනය.', 'ලබන අධිකාරියට වෙනත් භාෂාවක් අවශ්‍ය විට සහතික පරිවර්තනය. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'නිල පරිවර්තනය | AtteCert', 'ලබන අධිකාරියට වෙනත් භාෂාවක් අවශ්‍ය විට සහතික පරිවර්තනය.'),
('2', 'ta', 'அதிகாரப்பூர்வ மொழிபெயர்ப்பு', 'பெறும் அதிகாரத்துக்கு வேறு மொழி தேவைப்பட்டால் சான்றளிக்கப்பட்ட மொழிபெயர்ப்பு.', 'பெறும் அதிகாரத்துக்கு வேறு மொழி தேவைப்பட்டால் சான்றளிக்கப்பட்ட மொழிபெயர்ப்பு. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'அதிகாரப்பூர்வ மொழிபெயர்ப்பு | AtteCert', 'பெறும் அதிகாரத்துக்கு வேறு மொழி தேவைப்பட்டால் சான்றளிக்கப்பட்ட மொழிபெயர்ப்பு.'),
('3', 'en', 'Embassy Legalization', 'Legalization through the destination embassy or high commission.', 'Legalization through the destination embassy or high commission. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'Embassy Legalization | AtteCert', 'Legalization through the destination embassy or high commission.'),
('3', 'si', 'තානාපති කාර්යාල නීතිගත කිරීම', 'ගමනාන්ත තානාපති කාර්යාලය හරහා නීතිගත කිරීම.', 'ගමනාන්ත තානාපති කාර්යාලය හරහා නීතිගත කිරීම. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'තානාපති කාර්යාල නීතිගත කිරීම | AtteCert', 'ගමනාන්ත තානාපති කාර්යාලය හරහා නීතිගත කිරීම.'),
('3', 'ta', 'தூதரக சட்டப்பூர்வமாக்கல்', 'சேரும் நாட்டு தூதரகம் வழியாக சட்டப்பூர்வமாக்கல்.', 'சேரும் நாட்டு தூதரகம் வழியாக சட்டப்பூர்வமாக்கல். We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'தூதரக சட்டப்பூர்வமாக்கல் | AtteCert', 'சேரும் நாட்டு தூதரகம் வழியாக சட்டப்பூர்வமாக்கல்.'),
('4', 'en', 'MOFA Appointments', 'Help preparing the Ministry of Foreign Affairs appointment and file.', 'Help preparing the Ministry of Foreign Affairs appointment and file. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'MOFA Appointments | AtteCert', 'Help preparing the Ministry of Foreign Affairs appointment and file.'),
('4', 'si', 'විදේශ අමාත්‍යාංශ වේලාවන්', 'විදේශ අමාත්‍යාංශ වේලාව සහ ගොනුව සූදානම් කිරීම.', 'විදේශ අමාත්‍යාංශ වේලාව සහ ගොනුව සූදානම් කිරීම. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'විදේශ අමාත්‍යාංශ වේලාවන් | AtteCert', 'විදේශ අමාත්‍යාංශ වේලාව සහ ගොනුව සූදානම් කිරීම.'),
('4', 'ta', 'MOFA நேரம்', 'வெளியுறவு அமைச்சக நேரம் மற்றும் கோப்பை தயார் செய்தல்.', 'வெளியுறவு அமைச்சக நேரம் மற்றும் கோப்பை தயார் செய்தல். We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'MOFA நேரம் | AtteCert', 'வெளியுறவு அமைச்சக நேரம் மற்றும் கோப்பை தயார் செய்தல்.'),
('5', 'en', 'Apostille / Legalization', 'The route depends on whether the destination accepts an apostille.', 'The route depends on whether the destination accepts an apostille. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'Apostille / Legalization | AtteCert', 'The route depends on whether the destination accepts an apostille.'),
('5', 'si', 'ඇපොස්ටිල් / නීතිගත කිරීම', 'ගමනාන්තය ඇපොස්ටිල් භාර ගන්නේද යන්න මත මාර්ගය වෙනස් වේ.', 'ගමනාන්තය ඇපොස්ටිල් භාර ගන්නේද යන්න මත මාර්ගය වෙනස් වේ. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'ඇපොස්ටිල් / නීතිගත කිරීම | AtteCert', 'ගමනාන්තය ඇපොස්ටිල් භාර ගන්නේද යන්න මත මාර්ගය වෙනස් වේ.'),
('5', 'ta', 'அப்போஸ்டில் / சட்டப்பூர்வமாக்கல்', 'சேரும் நாடு அப்போஸ்டில் ஏற்கிறதா என்பதைப் பொறுத்து பாதை மாறும்.', 'சேரும் நாடு அப்போஸ்டில் ஏற்கிறதா என்பதைப் பொறுத்து பாதை மாறும். We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'அப்போஸ்டில் / சட்டப்பூர்வமாக்கல் | AtteCert', 'சேரும் நாடு அப்போஸ்டில் ஏற்கிறதா என்பதைப் பொறுத்து பாதை மாறும்.'),
('6', 'en', 'Embassy Services', 'Mission-specific document handling beyond a standard legalization.', 'Mission-specific document handling beyond a standard legalization. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'Embassy Services | AtteCert', 'Mission-specific document handling beyond a standard legalization.'),
('6', 'si', 'තානාපති සේවා', 'සාමාන්‍ය නීතිගත කිරීමෙන් ඔබ්බට තානාපති කාර්යාල සේවා.', 'සාමාන්‍ය නීතිගත කිරීමෙන් ඔබ්බට තානාපති කාර්යාල සේවා. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'තානාපති සේවා | AtteCert', 'සාමාන්‍ය නීතිගත කිරීමෙන් ඔබ්බට තානාපති කාර්යාල සේවා.'),
('6', 'ta', 'தூதரக சேவைகள்', 'சாதாரண சட்டப்பூர்வமாக்கலுக்கு அப்பால் தூதரக ஆவண சேவை.', 'சாதாரண சட்டப்பூர்வமாக்கலுக்கு அப்பால் தூதரக ஆவண சேவை. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'தூதரக சேவைகள் | AtteCert', 'சாதாரண சட்டப்பூர்வமாக்கலுக்கு அப்பால் தூதரக ஆவண சேவை.'),
('7', 'en', 'MOFA Attestation', 'Attestation at the Ministry of Foreign Affairs.', 'Attestation at the Ministry of Foreign Affairs. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'MOFA Attestation | AtteCert', 'Attestation at the Ministry of Foreign Affairs.'),
('7', 'si', 'විදේශ අමාත්‍යාංශ සහතිකය', 'විදේශ අමාත්‍යාංශ සහතික කිරීම.', 'විදේශ අමාත්‍යාංශ සහතික කිරීම. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'විදේශ අමාත්‍යාංශ සහතිකය | AtteCert', 'විදේශ අමාත්‍යාංශ සහතික කිරීම.'),
('7', 'ta', 'MOFA சான்றிதழ்', 'வெளியுறவு அமைச்சக சான்றிதழ்.', 'வெளியுறவு அமைச்சக சான்றிதழ். We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'MOFA சான்றிதழ் | AtteCert', 'வெளியுறவு அமைச்சக சான்றிதழ்.'),
('8', 'en', 'Courier', 'Delivery of the completed file.', 'Delivery of the completed file. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'Courier | AtteCert', 'Delivery of the completed file.'),
('8', 'si', 'කුරියර්', 'සම්පූර්ණ ලේඛනය භාරදීම.', 'සම්පූර්ණ ලේඛනය භාරදීම. We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'කුරියර් | AtteCert', 'සම්පූර්ණ ලේඛනය භාරදීම.'),
('8', 'ta', 'கூரியர்', 'முடிந்த கோப்பை வழங்குதல்.', 'முடிந்த கோப்பை வழங்குதல். We confirm the exact path before work starts.', '1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.', 'A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.', 'கூரியர் | AtteCert', 'முடிந்த கோப்பை வழங்குதல்.');

DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `pricing_type` enum('fixed','custom') NOT NULL DEFAULT 'custom',
  `fixed_price` decimal(12,2) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'LKR',
  `processing_days` varchar(40) DEFAULT NULL,
  `category` varchar(60) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `show_price` tinyint(1) NOT NULL DEFAULT 0,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `icon` varchar(40) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_services_code` (`code`),
  UNIQUE KEY `uq_services_slug` (`slug`),
  KEY `idx_services_public` (`is_active`,`is_public`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `services` (`id`, `code`, `slug`, `pricing_type`, `fixed_price`, `currency`, `processing_days`, `category`, `is_active`, `is_public`, `show_price`, `featured`, `sort_order`, `icon`, `created_at`, `updated_at`, `deleted_at`) VALUES
('1', 'DOC-ATT', 'document-attestation', 'fixed', '4500.00', 'LKR', '3–7 working days', 'attestation', '1', '1', '1', '1', '1', 'certificate', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL),
('2', 'TRANS', 'official-translation', 'fixed', '3500.00', 'LKR', '2–5 working days', 'translation', '1', '1', '1', '1', '2', 'translate', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL),
('3', 'EMB-LEG', 'embassy-legalization', 'fixed', '12000.00', 'LKR', '5–15 working days', 'embassy', '1', '1', '1', '1', '3', 'building', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL),
('4', 'MOFA-APP', 'mofa-appointments', 'fixed', '2500.00', 'LKR', '1–5 working days', 'mofa', '1', '1', '1', '1', '4', 'calendar', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL),
('5', 'APOS', 'apostille', 'custom', NULL, 'LKR', 'Varies by document', 'apostille', '1', '1', '0', '1', '5', 'stamp', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL),
('6', 'EMB-SVC', 'embassy-services', 'custom', NULL, 'LKR', 'Varies by mission', 'embassy', '1', '1', '0', '1', '6', 'globe', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL),
('7', 'MOFA-ATT', 'mofa-attestation', 'fixed', '3500.00', 'LKR', '3–8 working days', 'mofa', '1', '1', '1', '0', '7', 'stamp', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL),
('8', 'COURIER', 'courier', 'fixed', '1500.00', 'LKR', '1–3 working days', 'delivery', '1', '1', '1', '0', '8', 'send', '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL);

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
('1', 'company_name', 'AtteCert', '2026-10-15 09:06:31'),
('2', 'email', 'hello@attecert.lk', '2026-10-15 09:06:31'),
('3', 'phone', '+94 11 000 0000', '2026-10-15 09:06:31'),
('4', 'whatsapp', '94710000000', '2026-10-15 09:06:31'),
('5', 'address', 'Colombo, Sri Lanka', '2026-10-15 09:06:31'),
('6', 'hours', 'Monday to Friday, 9:00 to 17:00', '2026-10-15 09:06:31'),
('7', 'map_url', 'https://maps.google.com/?q=Colombo', '2026-10-15 09:06:31'),
('8', 'registration', '', '2026-10-15 09:06:31'),
('9', 'currency', 'LKR', '2026-10-15 09:06:31'),
('10', 'quote_validity_days', '14', '2026-10-15 09:06:31'),
('11', 'invoice_due_days', '7', '2026-10-15 09:06:31'),
('12', 'quote_terms', 'This quotation is valid for the period shown. Government and embassy fees can change before submission.', '2026-10-15 09:06:31'),
('13', 'invoice_terms', 'Please quote the invoice number with your payment. Processing starts after payment is recorded.', '2026-10-15 09:06:31'),
('14', 'maintenance_mode', '0', '2026-10-15 09:06:31'),
('15', 'email_verification', '0', '2026-10-15 09:06:31'),
('16', 'upload_max_mb', '10', '2026-10-15 09:06:31'),
('17', 'facebook', '', '2026-10-15 09:06:31'),
('18', 'instagram', '', '2026-10-15 09:06:31'),
('19', 'linkedin', '', '2026-10-15 09:06:31');

DROP TABLE IF EXISTS `ui_translations`;
CREATE TABLE `ui_translations` (
  `locale` char(5) NOT NULL,
  `trans_key` varchar(160) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`locale`,`trans_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
  `user_id` bigint(20) unsigned NOT NULL,
  `role_id` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `fk_ur_role` (`role_id`),
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
('1', '1'),
('2', '8');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `locale` char(5) NOT NULL DEFAULT 'en',
  `email_verified_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `failed_login_count` int(10) unsigned NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_active` (`is_active`,`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `locale`, `email_verified_at`, `is_active`, `must_change_password`, `failed_login_count`, `locked_until`, `last_login_at`, `last_login_ip`, `created_at`, `updated_at`, `deleted_at`) VALUES
('1', 'AtteCert Admin', 'admin@attecert.local', '+94 11 000 0000', '$2y$10$6sKhxP/nFGErYDESFe/20epAzuTtWQIfm8aJi1r4RNcKsvFTUkMWW', 'en', '2026-10-15 09:06:31', '1', '1', '0', NULL, NULL, NULL, '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL),
('2', 'Nimal Perera', 'customer@attecert.local', '+94 77 000 0000', '$2y$10$ekL.YaH5dXKnA63RSNVxyOJoTZ3ilWerF/XBpK2fC15fcpPnZZPCy', 'en', '2026-10-15 09:06:31', '1', '0', '0', NULL, NULL, NULL, '2026-10-15 09:06:31', '2026-10-15 09:06:31', NULL);

DROP TABLE IF EXISTS `workflow_status_translations`;
CREATE TABLE `workflow_status_translations` (
  `status_id` smallint(5) unsigned NOT NULL,
  `locale` char(5) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`status_id`,`locale`),
  CONSTRAINT `fk_wst_status` FOREIGN KEY (`status_id`) REFERENCES `workflow_statuses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `workflow_status_translations` (`status_id`, `locale`, `name`, `description`) VALUES
('1', 'en', 'New request', 'New request'),
('1', 'si', 'නව ඉල්ලීම', 'නව ඉල්ලීම'),
('1', 'ta', 'புதிய கோரிக்கை', 'புதிய கோரிக்கை'),
('2', 'en', 'Under review', 'Under review'),
('2', 'si', 'සමාලෝචනය යටතේ', 'සමාලෝචනය යටතේ'),
('2', 'ta', 'மதிப்பாய்வில்', 'மதிப்பாய்வில்'),
('3', 'en', 'Awaiting documents', 'Awaiting documents'),
('3', 'si', 'ලේඛන බලාපොරොත්තුවෙන්', 'ලේඛන බලාපොරොත්තුවෙන්'),
('3', 'ta', 'ஆவணங்களுக்காக காத்திருப்பு', 'ஆவணங்களுக்காக காத்திருப்பு'),
('4', 'en', 'Documents received', 'Documents received'),
('4', 'si', 'ලේඛන ලැබුණි', 'ලේඛන ලැබුණි'),
('4', 'ta', 'ஆவணங்கள் பெறப்பட்டன', 'ஆவணங்கள் பெறப்பட்டன'),
('5', 'en', 'Quotation prepared', 'Quotation prepared'),
('5', 'si', 'මිල ගණන් සූදානම්', 'මිල ගණන් සූදානම්'),
('5', 'ta', 'மதிப்பீடு தயார்', 'மதிப்பீடு தயார்'),
('6', 'en', 'Quotation sent', 'Quotation sent'),
('6', 'si', 'මිල ගණන් යවන ලදි', 'මිල ගණන් යවන ලදි'),
('6', 'ta', 'மதிப்பீடு அனுப்பப்பட்டது', 'மதிப்பீடு அனுப்பப்பட்டது'),
('7', 'en', 'Awaiting confirmation', 'Awaiting confirmation'),
('7', 'si', 'තහවුරු කිරීම බලාපොරොත්තුවෙන්', 'තහවුරු කිරීම බලාපොරොත්තුවෙන්'),
('7', 'ta', 'உறுதிக்காக காத்திருப்பு', 'உறுதிக்காக காத்திருப்பு'),
('8', 'en', 'Confirmed', 'Confirmed'),
('8', 'si', 'තහවුරු විය', 'තහවුරු විය'),
('8', 'ta', 'உறுதி செய்யப்பட்டது', 'உறுதி செய்யப்பட்டது'),
('9', 'en', 'Invoice generated', 'Invoice generated'),
('9', 'si', 'ඉන්වොයිසිය සූදානම්', 'ඉන්වොයිසිය සූදානම්'),
('9', 'ta', 'விலைப்பட்டியல் உருவாக்கப்பட்டது', 'விலைப்பட்டியல் உருவாக்கப்பட்டது'),
('10', 'en', 'Awaiting payment', 'Awaiting payment'),
('10', 'si', 'ගෙවීම බලාපොරොත්තුවෙන්', 'ගෙවීම බලාපොරොත්තුවෙන්'),
('10', 'ta', 'கட்டணத்துக்காக காத்திருப்பு', 'கட்டணத்துக்காக காத்திருப்பு'),
('11', 'en', 'Payment received', 'Payment received'),
('11', 'si', 'ගෙවීම ලැබුණි', 'ගෙවීම ලැබුණි'),
('11', 'ta', 'கட்டணம் பெறப்பட்டது', 'கட்டணம் பெறப்பட்டது'),
('12', 'en', 'Verification', 'Verification'),
('12', 'si', 'සත්‍යාපනය', 'සත්‍යාපනය'),
('12', 'ta', 'சரிபார்ப்பு', 'சரிபார்ப்பு'),
('13', 'en', 'Submitted to MOFA', 'Submitted to MOFA'),
('13', 'si', 'විදේශ අමාත්‍යාංශයට භාර දුන්නා', 'විදේශ අමාත්‍යාංශයට භාර දුන්නා'),
('13', 'ta', 'MOFA-விடம் சமர்ப்பிக்கப்பட்டது', 'MOFA-விடம் சமர்ப்பிக்கப்பட்டது'),
('14', 'en', 'MOFA processing', 'MOFA processing'),
('14', 'si', 'විදේශ අමාත්‍යාංශ සැකසීම', 'විදේශ අමාත්‍යාංශ සැකසීම'),
('14', 'ta', 'MOFA செயலாக்கம்', 'MOFA செயலாக்கம்'),
('15', 'en', 'MOFA completed', 'MOFA completed'),
('15', 'si', 'විදේශ අමාත්‍යාංශ කටයුතු නිමා', 'විදේශ අමාත්‍යාංශ කටයුතු නිමා'),
('15', 'ta', 'MOFA முடிந்தது', 'MOFA முடிந்தது'),
('16', 'en', 'Submitted to embassy', 'Submitted to embassy'),
('16', 'si', 'තානාපති කාර්යාලයට භාර දුන්නා', 'තානාපති කාර්යාලයට භාර දුන්නා'),
('16', 'ta', 'தூதரகத்தில் சமர்ப்பிக்கப்பட்டது', 'தூதரகத்தில் சமர்ப்பிக்கப்பட்டது'),
('17', 'en', 'Embassy processing', 'Embassy processing'),
('17', 'si', 'තානාපති කාර්යාල සැකසීම', 'තානාපති කාර්යාල සැකසීම'),
('17', 'ta', 'தூதரக செயலாக்கம்', 'தூதரக செயலாக்கம்'),
('18', 'en', 'Embassy completed', 'Embassy completed'),
('18', 'si', 'තානාපති කාර්යාල කටයුතු නිමා', 'තානාපති කාර්යාල කටයුතු නිමා'),
('18', 'ta', 'தூதரக பணி முடிந்தது', 'தூதரக பணி முடிந்தது'),
('19', 'en', 'Translation processing', 'Translation processing'),
('19', 'si', 'පරිවර්තනය සිදු වෙමින්', 'පරිවර්තනය සිදු වෙමින්'),
('19', 'ta', 'மொழிபெயர்ப்பு நடைபெறுகிறது', 'மொழிபெயர்ப்பு நடைபெறுகிறது'),
('20', 'en', 'Ready for collection', 'Ready for collection'),
('20', 'si', 'රැගෙන යාමට සූදානම්', 'රැගෙන යාමට සූදානම්'),
('20', 'ta', 'பெற தயார்', 'பெற தயார்'),
('21', 'en', 'Ready for delivery', 'Ready for delivery'),
('21', 'si', 'භාරදීමට සූදානම්', 'භාරදීමට සූදානම්'),
('21', 'ta', 'வழங்க தயார்', 'வழங்க தயார்'),
('22', 'en', 'Completed', 'Completed'),
('22', 'si', 'නිමයි', 'නිමයි'),
('22', 'ta', 'நிறைவடைந்தது', 'நிறைவடைந்தது'),
('23', 'en', 'Cancelled', 'Cancelled'),
('23', 'si', 'අවලංගුයි', 'අවලංගුයි'),
('23', 'ta', 'ரத்து செய்யப்பட்டது', 'ரத்து செய்யப்பட்டது');

DROP TABLE IF EXISTS `workflow_statuses`;
CREATE TABLE `workflow_statuses` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `customer_visible` tinyint(1) NOT NULL DEFAULT 1,
  `is_terminal` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `badge_tone` varchar(20) NOT NULL DEFAULT 'neutral',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_workflow_code` (`code`),
  KEY `idx_workflow_sort` (`sort_order`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `workflow_statuses` (`id`, `code`, `sort_order`, `customer_visible`, `is_terminal`, `is_active`, `badge_tone`) VALUES
('1', 'new_request', '10', '1', '0', '1', 'neutral'),
('2', 'under_review', '20', '1', '0', '1', 'warning'),
('3', 'awaiting_documents', '30', '1', '0', '1', 'warning'),
('4', 'documents_received', '40', '1', '0', '1', 'neutral'),
('5', 'quotation_prepared', '50', '1', '0', '1', 'neutral'),
('6', 'quotation_sent', '60', '1', '0', '1', 'neutral'),
('7', 'awaiting_confirmation', '70', '1', '0', '1', 'warning'),
('8', 'confirmed', '80', '1', '0', '1', 'success'),
('9', 'invoice_generated', '90', '1', '0', '1', 'neutral'),
('10', 'awaiting_payment', '100', '1', '0', '1', 'warning'),
('11', 'payment_received', '110', '1', '0', '1', 'success'),
('12', 'verification', '120', '1', '0', '1', 'warning'),
('13', 'submitted_to_mofa', '130', '1', '0', '1', 'warning'),
('14', 'mofa_processing', '140', '1', '0', '1', 'warning'),
('15', 'mofa_completed', '150', '1', '0', '1', 'success'),
('16', 'submitted_to_embassy', '160', '1', '0', '1', 'warning'),
('17', 'embassy_processing', '170', '1', '0', '1', 'warning'),
('18', 'embassy_completed', '180', '1', '0', '1', 'success'),
('19', 'translation_processing', '190', '1', '0', '1', 'warning'),
('20', 'ready_for_collection', '200', '1', '0', '1', 'success'),
('21', 'ready_for_delivery', '210', '1', '0', '1', 'success'),
('22', 'completed', '220', '1', '1', '1', 'success'),
('23', 'cancelled', '230', '1', '1', '1', 'danger');

SET FOREIGN_KEY_CHECKS = 1;
