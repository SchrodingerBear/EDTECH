-- =============================================================================
-- THESIS 1 - Innovatech PH (AI-Assisted AR 360 Virtual Campus Navigation)
--   Database : `u467106394_thesis`
--   Import   : create `u467106394_thesis` in hPanel first, then import this file (phpMyAdmin)
--   Idempotent : DROP-all + re-create; safe to re-import.
--   Accounts (password = password for ALL - change after first login):
--     thesis_1_owner@innovatech.ph    role owner        -> admin/owner/dashboard
--     thesis_1_sysadmin@innovatech.ph role system_admin -> admin/system/dashboard
--     thesis_1_sysstaff@innovatech.ph role system_staff -> admin/system/dashboard
-- =============================================================================
-- =============================================================================
-- Innovatech PH — AI-Assisted AR 360° Virtual Campus Navigation
-- Canonical MySQL / MariaDB schema (utf8mb4, InnoDB)
-- Legacy dump u467106394_numuseum.sql is the OLD museum system. Do not mix.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+08:00';



-- -----------------------------------------------------------------------------
-- DROP TABLES (safe re-import). Reverse dependency order; FK checks are off.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `media_assets`;
DROP TABLE IF EXISTS `ar_walk_pings`;
DROP TABLE IF EXISTS `ar_walk_sessions`;
DROP TABLE IF EXISTS `ar_waypoints`;
DROP TABLE IF EXISTS `ai_info_jobs`;
DROP TABLE IF EXISTS `cubemap_faces`;
DROP TABLE IF EXISTS `ai_stitch_jobs`;
DROP TABLE IF EXISTS `floor_plan_markers`;
DROP TABLE IF EXISTS `floor_plans`;
DROP TABLE IF EXISTS `scene_hotspots`;
DROP TABLE IF EXISTS `tour_scenes`;
DROP TABLE IF EXISTS `facilities`;
DROP TABLE IF EXISTS `rooms`;
DROP TABLE IF EXISTS `campus_areas`;
DROP TABLE IF EXISTS `buildings`;
DROP TABLE IF EXISTS `institution_files`;
DROP TABLE IF EXISTS `institution_themes`;
DROP TABLE IF EXISTS `institutions`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `email_templates`;
DROP TABLE IF EXISTS `platform_settings`;
DROP TABLE IF EXISTS `user_sessions`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `user_page_access`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;

-- -----------------------------------------------------------------------------
-- 1. ROLES & USERS
-- owner  = Innovatech platform (landing, roles, logs, emails, institutions)
-- admin  = assigned institution content (360 / AR / themes / landing mode)
-- staff  = under admin; info, images, floor plans, AI stitch
-- user   = view-only tours + live AR walk
-- -----------------------------------------------------------------------------

CREATE TABLE `roles` (
  `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(32) NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `slug`, `name`, `description`) VALUES
(1, 'owner', 'Owner', 'Platform: product landing, roles, logs, access, emails, system config, institutions'),
(2, 'admin', 'Organization Admin', 'Institution content: 360/AR tours, themes, landing mode, visual editor'),
(3, 'staff', 'Organization Staff', 'Under admin: facilities, floor plans, featured images, AI stitch/capture'),
(4, 'user',  'User',  'View-only campus tours and AR walking'),
(5, 'system_admin', 'System Admin', 'Elsewhere from owner: manage organizations, accounts, folders, platform insights'),
(6, 'system_staff', 'System Staff', 'Under system admin: manage organizations and platform content');

CREATE TABLE `permissions` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(80) NOT NULL,
  `module` VARCHAR(64) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`slug`, `module`, `description`) VALUES
('platform.landing.manage', 'platform', 'Edit Innovatech product landing'),
('platform.settings.manage', 'platform', 'System setup and configuration'),
('platform.emails.manage', 'platform', 'Email / SMTP templates'),
('platform.roles.manage', 'platform', 'Roles and access'),
('platform.logs.view', 'platform', 'Audit and access logs'),
('platform.institutions.manage', 'platform', 'Create institutions and assign admins'),
('institution.manage', 'institution', 'Edit assigned institution profile and folder'),
('institution.theme.manage', 'institution', 'Themes, popups, infographics, animations'),
('institution.landing.manage', 'institution', 'Landing mode 360 vs floor plan, starting point'),
('content.tours.manage', 'content', '360 scenes, hotspots, featured images'),
('content.locations.manage', 'content', 'Buildings, rooms, facilities, areas'),
('content.floor_plans.manage', 'content', 'Floor plans and percentage markers'),
('tools.ai_info.use', 'tools', 'AI facility descriptions'),
('tools.ai_stitch.use', 'tools', 'Cubemap upload and in-app 360 capture stitch'),
('ar.manage', 'ar', 'AR overlays, geo waypoints, walk detection'),
('tour.view', 'public', 'View virtual tours');

CREATE TABLE `role_permissions` (
  `role_id` TINYINT UNSIGNED NOT NULL,
  `permission_id` SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions`
WHERE `slug` IN (
  'institution.manage',
  'institution.theme.manage',
  'institution.landing.manage',
  'content.tours.manage',
  'content.locations.manage',
  'content.floor_plans.manage',
  'tools.ai_info.use',
  'tools.ai_stitch.use',
  'ar.manage',
  'tour.view'
);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions`
WHERE `slug` IN (
  'content.tours.manage',
  'content.locations.manage',
  'content.floor_plans.manage',
  'tools.ai_info.use',
  'tools.ai_stitch.use',
  'tour.view'
);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, `id` FROM `permissions` WHERE `slug` = 'tour.view';

-- system_admin: manage institutions/accounts (owner still owns roles + settings)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, `id` FROM `permissions`
WHERE `slug` IN (
  'platform.institutions.manage',
  'platform.logs.view',
  'institution.manage',
  'institution.landing.manage',
  'content.tours.manage',
  'content.locations.manage',
  'content.floor_plans.manage',
  'tools.ai_info.use',
  'tools.ai_stitch.use',
  'tour.view'
);

-- system_staff: sub of system admin → organizations + tools, no settings
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, `id` FROM `permissions`
WHERE `slug` IN (
  'platform.institutions.manage',
  'institution.manage',
  'institution.landing.manage',
  'content.tours.manage',
  'content.locations.manage',
  'content.floor_plans.manage',
  'tools.ai_info.use',
  'tools.ai_stitch.use',
  'tour.view'
);

-- per-account page access (owner's role management system)
-- empty set = full access

CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` TINYINT UNSIGNED NOT NULL,
  `institution_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL for owner; required for admin/staff; optional for user',
  `supervisor_user_id` INT UNSIGNED DEFAULT NULL COMMENT 'staff reports to this admin',
  `email` VARCHAR(191) NOT NULL,
  `username` VARCHAR(64) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(80) NOT NULL,
  `last_name` VARCHAR(80) NOT NULL,
  `phone` VARCHAR(32) DEFAULT NULL,
  `avatar_path` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `email_verified_at` DATETIME DEFAULT NULL,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_institution` (`institution_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `fk_users_supervisor` FOREIGN KEY (`supervisor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- per-account page access (owner's role management system)
-- empty set = full access
CREATE TABLE `user_page_access` (
  `user_id` INT UNSIGNED NOT NULL,
  `page_key` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`user_id`, `page_key`),
  CONSTRAINT `fk_up_acc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pw_resets_user` (`user_id`),
  KEY `idx_pw_resets_hash` (`token_hash`),
  CONSTRAINT `fk_pw_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_user` (`user_id`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. OWNER PLATFORM: product landing, emails, config, logs
-- -----------------------------------------------------------------------------

CREATE TABLE `platform_settings` (
  `id` TINYINT UNSIGNED NOT NULL,
  `product_name` VARCHAR(191) NOT NULL DEFAULT 'AI-Assisted AR 360° Virtual Campus Navigation',
  `company_name` VARCHAR(191) NOT NULL DEFAULT 'Innovatech PH',
  `logo_path` VARCHAR(255) DEFAULT NULL,
  `hero_image_path` VARCHAR(255) DEFAULT NULL,
  `landing_html` MEDIUMTEXT DEFAULT NULL,
  `contact_email` VARCHAR(191) DEFAULT NULL,
  `smtp_host` VARCHAR(191) DEFAULT NULL,
  `smtp_port` SMALLINT UNSIGNED DEFAULT 587,
  `smtp_username` VARCHAR(191) DEFAULT NULL,
  `smtp_password_enc` VARCHAR(255) DEFAULT NULL,
  `smtp_from_name` VARCHAR(128) DEFAULT NULL,
  `smtp_from_email` VARCHAR(191) DEFAULT NULL,
  `institution_folder_root` VARCHAR(255) NOT NULL DEFAULT 'storage/institutions',
  `template_pack_path` VARCHAR(255) NOT NULL DEFAULT 'storage/templates/org_pack',
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `platform_settings` (`id`) VALUES (1);

CREATE TABLE `email_templates` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(64) NOT NULL,
  `subject` VARCHAR(191) NOT NULL,
  `body_html` MEDIUMTEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_templates_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `email_templates` (`slug`, `subject`, `body_html`) VALUES
('admin_invite', 'Your Innovatech campus admin account', '<p>You have been assigned as admin.</p>'),
('staff_invite', 'Your campus staff account', '<p>You have been added as staff.</p>'),
('password_reset', 'Reset your password', '<p>Reset link: {{link}}</p>');

CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_user_id` INT UNSIGNED DEFAULT NULL,
  `institution_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(80) NOT NULL,
  `module` VARCHAR(64) NOT NULL,
  `entity_type` VARCHAR(64) DEFAULT NULL,
  `entity_id` INT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `meta_json` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_actor` (`actor_user_id`),
  KEY `idx_audit_institution` (`institution_id`),
  KEY `idx_audit_created` (`created_at`),
  CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. INSTITUTIONS (multi-school) + generated project folders
-- Owner creates institution → copy template pack into folder_path
-- Admin then sets landing mode, starting point, theme (visual, not code)
-- -----------------------------------------------------------------------------

CREATE TABLE `institutions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(80) NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `short_name` VARCHAR(64) DEFAULT NULL,
  `institution_type` ENUM('school', 'college', 'university', 'other') NOT NULL DEFAULT 'school',
  `description` TEXT DEFAULT NULL,
  `logo_path` VARCHAR(255) DEFAULT NULL,
  `cover_image_path` VARCHAR(255) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `city` VARCHAR(80) DEFAULT NULL,
  `province` VARCHAR(80) DEFAULT NULL,
  `country` VARCHAR(80) NOT NULL DEFAULT 'Philippines',
  `latitude` DECIMAL(10, 7) DEFAULT NULL,
  `longitude` DECIMAL(10, 7) DEFAULT NULL,
  `website_url` VARCHAR(255) DEFAULT NULL,
  `contact_email` VARCHAR(191) DEFAULT NULL,
  `contact_phone` VARCHAR(32) DEFAULT NULL,
  `folder_path` VARCHAR(255) NOT NULL COMMENT 'Generated project dir e.g. storage/institutions/immaculada',
  `landing_mode` ENUM('360_rotation', 'floor_plan') NOT NULL DEFAULT '360_rotation',
  `starting_scene_id` INT UNSIGNED DEFAULT NULL,
  `starting_floor_plan_id` INT UNSIGNED DEFAULT NULL,
  `require_landscape_mobile` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'AR/360 landing locked landscape fullscreen on mobile',
  `is_published` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT UNSIGNED DEFAULT NULL COMMENT 'owner user',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_institutions_slug` (`slug`),
  KEY `idx_institutions_published` (`is_published`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE SET NULL;

ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE SET NULL;

CREATE TABLE `institution_themes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institution_id` INT UNSIGNED NOT NULL,
  `primary_color` VARCHAR(16) NOT NULL DEFAULT '#1a365d',
  `secondary_color` VARCHAR(16) NOT NULL DEFAULT '#ed8936',
  `accent_color` VARCHAR(16) NOT NULL DEFAULT '#38b2ac',
  `font_family` VARCHAR(128) DEFAULT NULL,
  `popup_animation` VARCHAR(64) NOT NULL DEFAULT 'fade',
  `marker_style` VARCHAR(64) NOT NULL DEFAULT 'circle',
  `infographic_style` VARCHAR(64) NOT NULL DEFAULT 'card',
  `custom_css` MEDIUMTEXT DEFAULT NULL,
  `theme_json` JSON DEFAULT NULL COMMENT 'Extra visual tokens edited in the drag-drop environment',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_theme_institution` (`institution_id`),
  CONSTRAINT `fk_theme_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `institution_files` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institution_id` INT UNSIGNED NOT NULL,
  `relative_path` VARCHAR(255) NOT NULL COMMENT 'Inside folder_path; template files copied on create',
  `file_role` ENUM('aframe_scene', 'stylesheet', 'script', 'asset', 'config', 'other') NOT NULL DEFAULT 'other',
  `is_visual_editable` TINYINT(1) NOT NULL DEFAULT 1,
  `mime_type` VARCHAR(128) DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inst_file` (`institution_id`, `relative_path`),
  CONSTRAINT `fk_inst_files_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. CAMPUS STRUCTURE
-- -----------------------------------------------------------------------------

CREATE TABLE `buildings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institution_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `code` VARCHAR(32) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `ai_description` TEXT DEFAULT NULL,
  `featured_image_path` VARCHAR(255) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_buildings_institution` (`institution_id`),
  CONSTRAINT `fk_buildings_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `campus_areas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institution_id` INT UNSIGNED NOT NULL,
  `building_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(191) NOT NULL,
  `area_type` VARCHAR(64) DEFAULT NULL COMMENT 'quad, parking, gate, sports, etc.',
  `description` TEXT DEFAULT NULL,
  `ai_description` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_areas_institution` (`institution_id`),
  CONSTRAINT `fk_areas_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_areas_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rooms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institution_id` INT UNSIGNED NOT NULL,
  `building_id` INT UNSIGNED DEFAULT NULL,
  `campus_area_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(191) NOT NULL,
  `code` VARCHAR(32) DEFAULT NULL,
  `floor_label` VARCHAR(32) DEFAULT NULL,
  `room_type` VARCHAR(64) DEFAULT NULL COMMENT 'classroom, office, lab, restroom, etc.',
  `description` TEXT DEFAULT NULL,
  `ai_description` TEXT DEFAULT NULL,
  `featured_image_path` VARCHAR(255) DEFAULT NULL,
  `capacity` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rooms_institution` (`institution_id`),
  CONSTRAINT `fk_rooms_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rooms_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rooms_area` FOREIGN KEY (`campus_area_id`) REFERENCES `campus_areas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `facilities` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institution_id` INT UNSIGNED NOT NULL,
  `building_id` INT UNSIGNED DEFAULT NULL,
  `room_id` INT UNSIGNED DEFAULT NULL,
  `campus_area_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(191) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `ai_description` TEXT DEFAULT NULL,
  `featured_image_path` VARCHAR(255) DEFAULT NULL,
  `info_json` JSON DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_facilities_institution` (`institution_id`),
  CONSTRAINT `fk_facilities_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_facilities_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_facilities_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_facilities_area` FOREIGN KEY (`campus_area_id`) REFERENCES `campus_areas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. 360 TOURS (A-Frame) + room-to-room navigation
-- -----------------------------------------------------------------------------

CREATE TABLE `tour_scenes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institution_id` INT UNSIGNED NOT NULL,
  `building_id` INT UNSIGNED DEFAULT NULL,
  `room_id` INT UNSIGNED DEFAULT NULL,
  `campus_area_id` INT UNSIGNED DEFAULT NULL,
  `facility_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(191) NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `ai_description` TEXT DEFAULT NULL,
  `featured_image_path` VARCHAR(255) DEFAULT NULL,
  `equirect_path` VARCHAR(255) DEFAULT NULL COMMENT 'Stitched 360 panorama',
  `initial_yaw` DECIMAL(8, 3) NOT NULL DEFAULT 0,
  `initial_pitch` DECIMAL(8, 3) NOT NULL DEFAULT 0,
  `is_landing_start` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'If landing_mode=360_rotation, one scene should be start',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scene_slug` (`institution_id`, `slug`),
  KEY `idx_scenes_institution` (`institution_id`),
  CONSTRAINT `fk_scenes_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scenes_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_scenes_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_scenes_area` FOREIGN KEY (`campus_area_id`) REFERENCES `campus_areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_scenes_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `institutions`
  ADD CONSTRAINT `fk_inst_start_scene` FOREIGN KEY (`starting_scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE SET NULL;

CREATE TABLE `scene_hotspots` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institution_id` INT UNSIGNED NOT NULL,
  `from_scene_id` INT UNSIGNED NOT NULL,
  `to_scene_id` INT UNSIGNED DEFAULT NULL,
  `hotspot_type` ENUM('navigation', 'info', 'facility', 'media', 'ar_label') NOT NULL DEFAULT 'navigation',
  `label` VARCHAR(191) DEFAULT NULL,
  `body_html` TEXT DEFAULT NULL,
  `yaw` DECIMAL(8, 3) NOT NULL DEFAULT 0,
  `pitch` DECIMAL(8, 3) NOT NULL DEFAULT 0,
  `icon_path` VARCHAR(255) DEFAULT NULL,
  `target_facility_id` INT UNSIGNED DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_hotspots_from` (`from_scene_id`),
  CONSTRAINT `fk_hotspots_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hotspots_from` FOREIGN KEY (`from_scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hotspots_to` FOREIGN KEY (`to_scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hotspots_facility` FOREIGN KEY (`target_facility_id`) REFERENCES `facilities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. FLOOR PLANS — percentage markers (responsive; never pixel-only)
-- -----------------------------------------------------------------------------

CREATE TABLE `floor_plans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institution_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(191) NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `original_width` INT UNSIGNED NOT NULL COMMENT 'Intrinsic px width; lock aspect',
  `original_height` INT UNSIGNED NOT NULL COMMENT 'Intrinsic px height; lock aspect',
  `aspect_ratio` DECIMAL(10, 6) NOT NULL COMMENT 'width / height',
  `object_fit` ENUM('contain', 'cover') NOT NULL DEFAULT 'contain',
  `min_display_width` INT UNSIGNED NOT NULL DEFAULT 320,
  `is_campus_landing` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'If landing_mode=floor_plan',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_floor_plans_institution` (`institution_id`),
  CONSTRAINT `fk_floor_plans_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `institutions`
  ADD CONSTRAINT `fk_inst_start_floor` FOREIGN KEY (`starting_floor_plan_id`) REFERENCES `floor_plans` (`id`) ON DELETE SET NULL;

CREATE TABLE `floor_plan_markers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institution_id` INT UNSIGNED NOT NULL,
  `floor_plan_id` INT UNSIGNED NOT NULL,
  `label` VARCHAR(191) NOT NULL,
  `marker_shape` ENUM('circle', 'pin', 'custom') NOT NULL DEFAULT 'circle',
  `x_percent` DECIMAL(8, 4) NOT NULL COMMENT '0–100 of image width; source of truth for all screens',
  `y_percent` DECIMAL(8, 4) NOT NULL COMMENT '0–100 of image height',
  `size_percent` DECIMAL(6, 3) NOT NULL DEFAULT 4.000 COMMENT 'Diameter relative to image min-side',
  `target_scene_id` INT UNSIGNED DEFAULT NULL,
  `target_room_id` INT UNSIGNED DEFAULT NULL,
  `target_building_id` INT UNSIGNED DEFAULT NULL,
  `target_facility_id` INT UNSIGNED DEFAULT NULL,
  `popup_title` VARCHAR(191) DEFAULT NULL,
  `popup_html` TEXT DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_markers_plan` (`floor_plan_id`),
  CONSTRAINT `fk_markers_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_markers_plan` FOREIGN KEY (`floor_plan_id`) REFERENCES `floor_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_markers_scene` FOREIGN KEY (`target_scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_markers_room` FOREIGN KEY (`target_room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_markers_building` FOREIGN KEY (`target_building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_markers_facility` FOREIGN KEY (`target_facility_id`) REFERENCES `facilities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. AI STITCH — cubemap upload OR in-system guided capture
-- Faces: front, back, left, right, up (top), down
-- -----------------------------------------------------------------------------

CREATE TABLE `ai_stitch_jobs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institution_id` INT UNSIGNED NOT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `source_type` ENUM('cubemap_upload', 'in_app_capture') NOT NULL,
  `status` ENUM('draft', 'uploading', 'queued', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'draft',
  `guide_step` ENUM('front', 'back', 'left', 'right', 'up', 'down', 'done') DEFAULT NULL,
  `output_equirect_path` VARCHAR(255) DEFAULT NULL,
  `output_scene_id` INT UNSIGNED DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `provider` VARCHAR(64) DEFAULT NULL,
  `external_job_id` VARCHAR(128) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_stitch_institution` (`institution_id`),
  CONSTRAINT `fk_stitch_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stitch_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_stitch_scene` FOREIGN KEY (`output_scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cubemap_faces` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` INT UNSIGNED NOT NULL,
  `face` ENUM('front', 'back', 'left', 'right', 'up', 'down') NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `captured_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_job_face` (`job_id`, `face`),
  CONSTRAINT `fk_faces_job` FOREIGN KEY (`job_id`) REFERENCES `ai_stitch_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_info_jobs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institution_id` INT UNSIGNED NOT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `target_type` ENUM('building', 'room', 'facility', 'campus_area', 'tour_scene') NOT NULL,
  `target_id` INT UNSIGNED NOT NULL,
  `prompt` TEXT DEFAULT NULL,
  `output_text` TEXT DEFAULT NULL,
  `status` ENUM('queued', 'completed', 'failed') NOT NULL DEFAULT 'queued',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_info_institution` (`institution_id`),
  CONSTRAINT `fk_ai_info_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_info_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. AR — overlays + walk-with-camera (Maps Live View style)
-- Floor-plan markers and AR-captured areas map to the same real-world point
-- -----------------------------------------------------------------------------

CREATE TABLE `ar_waypoints` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institution_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `floor_plan_marker_id` INT UNSIGNED DEFAULT NULL,
  `scene_id` INT UNSIGNED DEFAULT NULL,
  `room_id` INT UNSIGNED DEFAULT NULL,
  `building_id` INT UNSIGNED DEFAULT NULL,
  `facility_id` INT UNSIGNED DEFAULT NULL,
  `latitude` DECIMAL(10, 7) DEFAULT NULL,
  `longitude` DECIMAL(10, 7) DEFAULT NULL,
  `altitude_m` DECIMAL(8, 2) DEFAULT NULL,
  `heading_deg` DECIMAL(6, 2) DEFAULT NULL,
  `detect_radius_m` DECIMAL(8, 2) NOT NULL DEFAULT 12.00,
  `visual_target_path` VARCHAR(255) DEFAULT NULL COMMENT 'AR image target / captured area fingerprint',
  `overlay_title` VARCHAR(191) DEFAULT NULL,
  `overlay_html` TEXT DEFAULT NULL,
  `direction_label` VARCHAR(191) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ar_wp_institution` (`institution_id`),
  CONSTRAINT `fk_ar_wp_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ar_wp_marker` FOREIGN KEY (`floor_plan_marker_id`) REFERENCES `floor_plan_markers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ar_wp_scene` FOREIGN KEY (`scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ar_wp_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ar_wp_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ar_wp_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ar_walk_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institution_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = guest visitor',
  `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at` DATETIME DEFAULT NULL,
  `device_type` VARCHAR(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ar_walk_inst` (`institution_id`),
  CONSTRAINT `fk_ar_walk_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ar_walk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ar_walk_pings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` BIGINT UNSIGNED NOT NULL,
  `waypoint_id` INT UNSIGNED DEFAULT NULL,
  `latitude` DECIMAL(10, 7) DEFAULT NULL,
  `longitude` DECIMAL(10, 7) DEFAULT NULL,
  `matched_by` ENUM('gps', 'visual_target', 'manual') NOT NULL DEFAULT 'gps',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pings_session` (`session_id`),
  CONSTRAINT `fk_pings_session` FOREIGN KEY (`session_id`) REFERENCES `ar_walk_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pings_waypoint` FOREIGN KEY (`waypoint_id`) REFERENCES `ar_waypoints` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. MEDIA (featured images, tour assets)
-- -----------------------------------------------------------------------------

CREATE TABLE `media_assets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `institution_id` INT UNSIGNED NOT NULL,
  `uploaded_by` INT UNSIGNED DEFAULT NULL,
  `kind` ENUM('featured', 'gallery', 'floor_plan', 'pano', 'cubemap_face', 'ar_target', 'logo', 'other') NOT NULL DEFAULT 'other',
  `file_path` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(191) DEFAULT NULL,
  `mime_type` VARCHAR(128) DEFAULT NULL,
  `width` INT UNSIGNED DEFAULT NULL,
  `height` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_media_institution` (`institution_id`),
  CONSTRAINT `fk_media_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_media_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- Seed labeled accounts (password for ALL accounts = `password` — change after
-- first login). Labeled so it's obvious which thesis you're logging into when
-- both systems run on the same hosting account.
--   thesis_1_owner@innovatech.ph    -> role owner        (admin/owner/dashboard)
--   thesis_1_sysadmin@innovatech.ph -> role system_admin (admin/system/dashboard)
--   thesis_1_sysstaff@innovatech.ph -> role system_staff (admin/system/dashboard)
-- Hash below is verified bcrypt of: password
-- -----------------------------------------------------------------------------
INSERT INTO `users` (
  `role_id`, `email`, `username`, `password_hash`, `first_name`, `last_name`, `is_active`, `email_verified_at`
) VALUES
(1, 'thesis_1_owner@innovatech.ph', 'thesis_1_owner',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Thesis 1', 'Owner', 1, NOW()),
(5, 'thesis_1_sysadmin@innovatech.ph', 'thesis_1_sysadmin',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Thesis 1', 'System Admin', 1, NOW()),
(6, 'thesis_1_sysstaff@innovatech.ph', 'thesis_1_sysstaff',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Thesis 1', 'System Staff', 1, NOW());

