-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 01, 2026 at 03:22 PM
-- Server version: 8.0.46-0ubuntu0.24.04.3
-- PHP Version: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `innovatech_campus`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_info_jobs`
--

CREATE TABLE `ai_info_jobs` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `target_type` enum('building','room','facility','campus_area','tour_scene') COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` int UNSIGNED NOT NULL,
  `prompt` text COLLATE utf8mb4_unicode_ci,
  `output_text` text COLLATE utf8mb4_unicode_ci,
  `status` enum('queued','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_info_jobs`
--

INSERT INTO `ai_info_jobs` (`id`, `institution_id`, `created_by`, `target_type`, `target_id`, `prompt`, `output_text`, `status`, `created_at`) VALUES
(1, 1, 1, 'building', 1, NULL, 'Gymnasium is part of Immaculada Concepcion College. Gymnasium features a welcoming, functional layout designed for the campus community. Visitors can explore it through the 360° tour and locate it instantly on the campus floor plan.', 'completed', '2026-08-31 12:50:09'),
(2, 1, 4, 'tour_scene', 1, NULL, 'Entrance is part of Immaculada Concepcion College. Entrance features a welcoming, functional layout designed for the campus community. Visitors can explore it through the 360° tour and locate it instantly on the campus floor plan.', 'completed', '2026-08-31 12:55:07'),
(3, 1, 4, 'building', 2, NULL, 'Main Building is a building of Immaculada Concepcion College.\n\nDesigned under the campus code MB, it keeps the campus community\'s daily needs covered.\n\nFind it on the campus map, or step inside through the interactive 360° tour.', 'completed', '2026-08-31 14:28:09'),
(4, 1, 4, 'tour_scene', 1, NULL, 'Entrance is a 360° tour stop of Immaculada Concepcion College.\n\nIt appears on the campus floor plan and is featured in the 360° tour for easy navigation.', 'completed', '2026-08-31 16:05:00'),
(5, 1, 4, 'tour_scene', 1, NULL, 'Entrance is a 360° tour stop of Immaculada Concepcion College.\n\nVisitors can explore it through the 360° tour and find it instantly on the campus floor plan.', 'completed', '2026-08-31 16:05:08'),
(6, 1, 4, 'tour_scene', 1, NULL, 'Entrance is a 360° tour stop of Immaculada Concepcion College.\n\nFind it on the campus map, or step inside through the interactive 360° tour.', 'completed', '2026-08-31 16:05:41'),
(7, 1, 4, 'tour_scene', 1, NULL, 'Entrance is a 360° tour stop of Immaculada Concepcion College.\n\nVisitors can explore it through the 360° tour and find it instantly on the campus floor plan.', 'completed', '2026-08-31 16:05:41'),
(8, 1, 4, 'building', 1, NULL, 'Gymnasium is a building of Immaculada Concepcion College.\n\nDesigned under the campus code GYM-01, it keeps the campus community\'s daily needs covered.\n\nIt appears on the campus floor plan and is featured in the 360° tour for easy navigation.', 'completed', '2026-09-01 22:52:59');

-- --------------------------------------------------------

--
-- Table structure for table `ai_stitch_jobs`
--

CREATE TABLE `ai_stitch_jobs` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `source_type` enum('cubemap_upload','in_app_capture') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','uploading','queued','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `guide_step` enum('front','back','left','right','up','down','done') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `output_equirect_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `output_scene_id` int UNSIGNED DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `provider` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_job_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ar_walk_pings`
--

CREATE TABLE `ar_walk_pings` (
  `id` bigint UNSIGNED NOT NULL,
  `session_id` bigint UNSIGNED NOT NULL,
  `waypoint_id` int UNSIGNED DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `matched_by` enum('gps','visual_target','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gps',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ar_walk_sessions`
--

CREATE TABLE `ar_walk_sessions` (
  `id` bigint UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL COMMENT 'NULL = guest visitor',
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at` datetime DEFAULT NULL,
  `device_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ar_waypoints`
--

CREATE TABLE `ar_waypoints` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `floor_plan_marker_id` int UNSIGNED DEFAULT NULL,
  `scene_id` int UNSIGNED DEFAULT NULL,
  `room_id` int UNSIGNED DEFAULT NULL,
  `building_id` int UNSIGNED DEFAULT NULL,
  `facility_id` int UNSIGNED DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `altitude_m` decimal(8,2) DEFAULT NULL,
  `heading_deg` decimal(6,2) DEFAULT NULL,
  `detect_radius_m` decimal(8,2) NOT NULL DEFAULT '12.00',
  `visual_target_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'AR image target / captured area fingerprint',
  `overlay_title` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overlay_html` text COLLATE utf8mb4_unicode_ci,
  `direction_label` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `actor_user_id` int UNSIGNED DEFAULT NULL,
  `institution_id` int UNSIGNED DEFAULT NULL,
  `action` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `actor_user_id`, `institution_id`, `action`, `module`, `entity_type`, `entity_id`, `ip_address`, `user_agent`, `meta_json`, `created_at`) VALUES
(1, 1, NULL, 'auth.login', 'auth', NULL, NULL, '::1', 'curl/8.5.0', NULL, '2026-08-30 18:45:01'),
(2, 2, NULL, 'auth.login', 'auth', NULL, NULL, '::1', 'curl/8.5.0', NULL, '2026-08-30 18:45:03'),
(3, 3, NULL, 'auth.login', 'auth', NULL, NULL, '::1', 'curl/8.5.0', NULL, '2026-08-30 18:45:03'),
(4, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 10:33:24'),
(5, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 10:35:50'),
(6, 1, 1, 'institution.create', 'institutions', 'institution', 1, '127.0.0.1', NULL, NULL, '2026-08-31 10:49:52'),
(7, 1, NULL, 'account.create', 'accounts', 'user', 4, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Edg/151.0.0.0 Mobile Safari/537.36', NULL, '2026-08-31 10:50:20'),
(8, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Edg/151.0.0.0 Mobile Safari/537.36', NULL, '2026-08-31 11:06:38'),
(9, 2, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Edg/151.0.0.0 Mobile Safari/537.36', NULL, '2026-08-31 11:08:18'),
(10, 3, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 11:08:43'),
(11, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 11:09:03'),
(12, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 11:09:22'),
(13, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 11:22:11'),
(14, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 11:23:56'),
(15, 1, NULL, 'auth.login', 'auth', NULL, NULL, '::1', 'curl/8.5.0', NULL, '2026-08-31 11:38:23'),
(16, 1, NULL, 'auth.login', 'auth', NULL, NULL, '::1', 'curl/8.5.0', NULL, '2026-08-31 11:40:47'),
(17, 1, NULL, 'auth.login', 'auth', NULL, NULL, '::1', 'curl/8.5.0', NULL, '2026-08-31 11:41:12'),
(18, 1, NULL, 'auth.login', 'auth', NULL, NULL, '::1', 'curl/8.5.0', NULL, '2026-08-31 11:41:47'),
(19, 1, NULL, 'auth.login', 'auth', NULL, NULL, '::1', 'curl/8.5.0', NULL, '2026-08-31 11:43:05'),
(20, 1, NULL, 'auth.login', 'auth', NULL, NULL, '::1', 'curl/8.5.0', NULL, '2026-08-31 11:46:10'),
(21, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 11:50:30'),
(22, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 11:51:36'),
(23, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 12:02:07'),
(24, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 12:02:55'),
(25, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 12:03:54'),
(26, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 12:05:07'),
(27, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 12:09:05'),
(28, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 12:21:21'),
(29, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 12:27:41'),
(30, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 12:27:59'),
(31, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 12:32:59'),
(32, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 12:38:00'),
(33, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 12:38:42'),
(34, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 12:39:02'),
(35, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 12:39:39'),
(36, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 12:40:17'),
(37, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 12:43:45'),
(38, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 12:50:09'),
(39, 1, NULL, 'ai_info.generate', 'ai', 'building', 1, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 12:50:09'),
(40, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 12:54:44'),
(41, 4, 1, 'ai_info.generate', 'ai', 'institution', 1, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 12:54:44'),
(42, 4, 1, 'ai_info.generate', 'ai', 'tour_scene', 1, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 12:55:07'),
(43, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 13:04:11'),
(44, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 13:38:16'),
(45, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 13:49:15'),
(46, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 13:49:16'),
(47, 1, NULL, 'auth.login', 'auth', NULL, NULL, '192.168.100.32', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', NULL, '2026-08-31 13:52:29'),
(48, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 14:28:08'),
(49, 4, 1, 'ai_info.generate', 'ai', 'building', 2, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 14:28:09'),
(50, 4, 1, 'ai_info.generate', 'ai', 'waypoint', 1, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 14:28:09'),
(51, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 14:29:57'),
(52, 4, 1, 'ai_info.generate', 'ai', 'hotspot', 2, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 14:29:57'),
(53, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 14:30:31'),
(54, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 14:31:20'),
(55, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 14:31:42'),
(56, 4, 1, 'ai_info.generate', 'ai', 'institution', 1, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 14:31:42'),
(57, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 15:15:31'),
(58, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 15:16:18'),
(59, 4, 1, 'ai_stitch.job.create', 'ai', 'job', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 15:18:18'),
(60, 4, 1, 'ai_stitch.job.create', 'ai', 'job', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 15:19:48'),
(61, 4, 1, 'ai_info.generate', 'ai', 'tour_scene', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 16:05:00'),
(62, 4, 1, 'ai_info.generate', 'ai', 'tour_scene', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 16:05:08'),
(63, 4, 1, 'ai_info.generate', 'ai', 'tour_scene', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 16:05:41'),
(64, 4, 1, 'ai_info.generate', 'ai', 'tour_scene', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 16:05:41'),
(65, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 16:08:22'),
(66, 1, NULL, 'auth.login', 'auth', NULL, NULL, '192.168.100.32', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', NULL, '2026-08-31 16:14:08'),
(67, 1, NULL, 'auth.login', 'auth', NULL, NULL, '192.168.100.32', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', NULL, '2026-08-31 16:19:57'),
(68, 1, NULL, 'auth.login', 'auth', NULL, NULL, '192.168.100.32', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', NULL, '2026-08-31 16:20:00'),
(69, 4, 1, 'auth.login', 'auth', NULL, NULL, '192.168.100.32', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', NULL, '2026-08-31 16:20:03'),
(70, 2, NULL, 'auth.login', 'auth', NULL, NULL, '192.168.100.32', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', NULL, '2026-08-31 16:20:16'),
(71, 2, NULL, 'auth.login', 'auth', NULL, NULL, '192.168.100.32', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', NULL, '2026-08-31 16:20:24'),
(72, 1, NULL, 'auth.login', 'auth', NULL, NULL, '192.168.100.32', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', NULL, '2026-08-31 16:20:39'),
(73, 1, NULL, 'auth.login', 'auth', NULL, NULL, '192.168.100.32', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', NULL, '2026-08-31 16:21:54'),
(74, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 16:22:07'),
(75, 1, NULL, 'auth.login', 'auth', NULL, NULL, '192.168.100.32', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '2026-08-31 16:22:16'),
(76, 1, NULL, 'auth.login', 'auth', NULL, NULL, '192.168.100.32', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', NULL, '2026-08-31 16:22:22'),
(77, 1, NULL, 'auth.login', 'auth', NULL, NULL, '192.168.100.32', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', NULL, '2026-08-31 16:22:49'),
(78, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 16:29:51'),
(79, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 16:33:13'),
(80, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 16:57:39'),
(81, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 17:05:48'),
(82, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 17:13:20'),
(83, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 17:13:41'),
(84, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 17:48:52'),
(85, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 17:49:55'),
(86, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 17:50:59'),
(87, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 17:52:32'),
(88, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 17:56:09'),
(89, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 18:28:41'),
(90, 4, 1, 'ai_info.generate', 'ai', 'hotspot', 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, '2026-08-31 18:29:39'),
(91, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 18:33:38'),
(92, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 18:36:50'),
(93, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 18:37:16'),
(94, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 18:38:58'),
(95, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 18:40:08'),
(96, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 18:42:20'),
(97, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'curl/8.5.0', NULL, '2026-08-31 18:42:44'),
(98, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', NULL, '2026-09-01 20:36:50'),
(99, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', NULL, '2026-09-01 21:44:33'),
(100, 4, 1, 'ai_info.generate', 'ai', 'institution', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', NULL, '2026-09-01 21:54:16'),
(101, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', NULL, '2026-09-01 21:55:43'),
(102, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', NULL, '2026-09-01 21:55:54'),
(103, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', NULL, '2026-09-01 22:23:01'),
(104, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', NULL, '2026-09-01 22:23:18'),
(105, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', NULL, '2026-09-01 22:26:48'),
(106, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', NULL, '2026-09-01 22:36:02'),
(107, 1, NULL, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', NULL, '2026-09-01 22:38:06'),
(108, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', NULL, '2026-09-01 22:38:16'),
(109, 4, 1, 'ai_info.generate', 'ai', 'building', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', NULL, '2026-09-01 22:52:59'),
(110, 4, 1, 'auth.login', 'auth', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', NULL, '2026-09-01 23:01:42');

-- --------------------------------------------------------

--
-- Table structure for table `buildings`
--

CREATE TABLE `buildings` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `ai_description` text COLLATE utf8mb4_unicode_ci,
  `featured_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `buildings`
--

INSERT INTO `buildings` (`id`, `institution_id`, `name`, `code`, `description`, `ai_description`, `featured_image_path`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'Gymnasium', 'GYM-01', NULL, 'Gymnasium is a building of Immaculada Concepcion College.\n\nDesigned under the campus code GYM-01, it keeps the campus community\'s daily needs covered.\n\nIt appears on the campus floor plan and is featured in the 360° tour for easy navigation.', 'organizations/immaculada-concepcion-college/assets/30f14122e6d46f89.avif', 0, '2026-08-31 11:12:40', '2026-09-01 22:52:58', NULL),
(2, 1, 'Main Building', 'MB', NULL, 'Main Building is a building of Immaculada Concepcion College.\n\nDesigned under the campus code MB, it keeps the campus community\'s daily needs covered.\n\nFind it on the campus map, or step inside through the interactive 360° tour.', '', 0, '2026-08-31 11:13:44', '2026-08-31 14:28:09', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `campus_areas`
--

CREATE TABLE `campus_areas` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `building_id` int UNSIGNED DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `area_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'quad, parking, gate, sports, etc.',
  `description` text COLLATE utf8mb4_unicode_ci,
  `ai_description` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `campus_areas`
--

INSERT INTO `campus_areas` (`id`, `institution_id`, `building_id`, `name`, `area_type`, `description`, `ai_description`) VALUES
(1, 1, 2, 'Main Entrance', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cubemap_faces`
--

CREATE TABLE `cubemap_faces` (
  `id` int UNSIGNED NOT NULL,
  `job_id` int UNSIGNED NOT NULL,
  `face` enum('front','back','left','right','up','down') COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `captured_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` smallint UNSIGNED NOT NULL,
  `slug` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_html` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_templates`
--

INSERT INTO `email_templates` (`id`, `slug`, `subject`, `body_html`, `is_active`, `updated_at`) VALUES
(1, 'admin_invite', 'Your Innovatech campus admin account', '<p>You have been assigned as admin.</p>', 1, '2026-08-30 18:44:41'),
(2, 'staff_invite', 'Your campus staff account', '<p>You have been added as staff.</p>', 1, '2026-08-30 18:44:41'),
(3, 'password_reset', 'Reset your Innovatech password, {{name}}', '<p>Hi {{name}},</p><p>Click {{link}} to set a new password for {{email}}. It expires in 1 hour.</p>', 1, '2026-08-31 12:27:59'),
(4, 'support_ticket_status', 'Your support ticket #{{ticket_id}} is {{ticket_status}}', '<p>Hi {{name}},</p><p>Your support ticket &quot;<strong>{{ticket_subject}}</strong>&quot; is now <strong>{{ticket_status}}</strong>.</p><p>{{ticket_message}}</p><p><a href=\"{{link}}\">Open the admin dashboard</a></p>', 1, '2026-08-31 12:23:28'),
(5, 'new_ticket', 'New support ticket #{{ticket_id}} — {{ticket_subject}}', '<p>A new support ticket has been opened.</p><p><strong>{{ticket_subject}}</strong></p><p>{{ticket_message}}</p><p>Ticket #{{ticket_id}} &middot; Priority: {{priority}} &middot; {{institution}}</p><p>Submitted by {{name}} ({{email}}).</p><p><a href=\"{{link}}\">Open the support desk</a></p>', 1, '2026-08-31 13:46:52');

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `building_id` int UNSIGNED DEFAULT NULL,
  `room_id` int UNSIGNED DEFAULT NULL,
  `campus_area_id` int UNSIGNED DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `ai_description` text COLLATE utf8mb4_unicode_ci,
  `featured_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `info_json` json DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `floor_plans`
--

CREATE TABLE `floor_plans` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `building_id` int UNSIGNED DEFAULT NULL,
  `floor_level` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_width` int UNSIGNED NOT NULL COMMENT 'Intrinsic px width; lock aspect',
  `original_height` int UNSIGNED NOT NULL COMMENT 'Intrinsic px height; lock aspect',
  `aspect_ratio` decimal(10,6) NOT NULL COMMENT 'width / height',
  `object_fit` enum('contain','cover') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'contain',
  `north_angle` decimal(6,2) NOT NULL DEFAULT '0.00',
  `min_display_width` int UNSIGNED NOT NULL DEFAULT '320',
  `is_campus_landing` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'If landing_mode=floor_plan',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `floor_plans`
--

INSERT INTO `floor_plans` (`id`, `institution_id`, `building_id`, `floor_level`, `title`, `image_path`, `original_width`, `original_height`, `aspect_ratio`, `object_fit`, `north_angle`, `min_display_width`, `is_campus_landing`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, NULL, NULL, 'Campus', 'organizations/immaculada-concepcion-college/assets/floorplans/96c72f04ade818ae.png', 1389, 1132, 1.227032, 'contain', 0.00, 320, 1, 4, '2026-08-31 11:34:52', '2026-09-01 23:21:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `floor_plan_markers`
--

CREATE TABLE `floor_plan_markers` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `floor_plan_id` int UNSIGNED NOT NULL,
  `label` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `marker_type` enum('scene','entrance','exit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scene',
  `marker_shape` enum('circle','pin','custom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'circle',
  `x_percent` decimal(8,4) NOT NULL COMMENT '0–100 of image width; source of truth for all screens',
  `y_percent` decimal(8,4) NOT NULL COMMENT '0–100 of image height',
  `size_percent` decimal(6,3) NOT NULL DEFAULT '4.000' COMMENT 'Diameter relative to image min-side',
  `marker_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facing_angle` decimal(6,2) NOT NULL DEFAULT '0.00',
  `target_scene_id` int UNSIGNED DEFAULT NULL,
  `target_room_id` int UNSIGNED DEFAULT NULL,
  `target_building_id` int UNSIGNED DEFAULT NULL,
  `target_facility_id` int UNSIGNED DEFAULT NULL,
  `target_floor_plan_id` int UNSIGNED DEFAULT NULL,
  `popup_title` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `popup_html` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `floor_plan_markers`
--

INSERT INTO `floor_plan_markers` (`id`, `institution_id`, `floor_plan_id`, `label`, `marker_type`, `marker_shape`, `x_percent`, `y_percent`, `size_percent`, `marker_image_path`, `facing_angle`, `target_scene_id`, `target_room_id`, `target_building_id`, `target_facility_id`, `target_floor_plan_id`, `popup_title`, `popup_html`, `sort_order`) VALUES
(2, 1, 1, 'Main Building', 'scene', 'circle', 31.4385, 28.8347, 4.000, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(3, 1, 1, 'Entrance', 'scene', 'circle', 53.9728, 72.6287, 4.000, NULL, 0.00, 1, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(5, 1, 1, 'Gym Main', 'scene', 'circle', 20.0000, 30.0000, 4.000, 'assets/ar_targets/marker_images/mk_1.png', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `fp_connections`
--

CREATE TABLE `fp_connections` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `from_floor_plan_id` int UNSIGNED NOT NULL,
  `from_marker_id` int UNSIGNED NOT NULL,
  `to_floor_plan_id` int UNSIGNED DEFAULT NULL,
  `to_marker_id` int UNSIGNED DEFAULT NULL,
  `to_scene_id` int UNSIGNED DEFAULT NULL,
  `to_building_id` int UNSIGNED DEFAULT NULL,
  `note` varchar(191) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fp_navigation_paths`
--

CREATE TABLE `fp_navigation_paths` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `floor_plan_id` int UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `nodes_json` mediumtext NOT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fp_waypoints`
--

CREATE TABLE `fp_waypoints` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `floor_plan_id` int UNSIGNED NOT NULL,
  `label` varchar(64) NOT NULL,
  `x_percent` decimal(8,4) NOT NULL,
  `y_percent` decimal(8,4) NOT NULL,
  `type` enum('normal','corner') NOT NULL DEFAULT 'normal',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `fp_waypoints`
--

INSERT INTO `fp_waypoints` (`id`, `institution_id`, `floor_plan_id`, `label`, `x_percent`, `y_percent`, `type`, `sort_order`, `created_at`) VALUES
(2, 1, 1, 'Waypoint', 50.0000, 50.0000, 'normal', 0, '2026-09-01 21:45:24'),
(3, 1, 1, 'Waypoint', 50.0000, 50.0000, 'normal', 0, '2026-09-01 21:45:44');

-- --------------------------------------------------------

--
-- Table structure for table `institutions`
--

CREATE TABLE `institutions` (
  `id` int UNSIGNED NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution_type` enum('school','college','university','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'school',
  `description` text COLLATE utf8mb4_unicode_ci,
  `logo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Philippines',
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `website_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `folder_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Generated project dir e.g. storage/institutions/immaculada',
  `landing_mode` enum('360_rotation','floor_plan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '360_rotation',
  `starting_scene_id` int UNSIGNED DEFAULT NULL,
  `starting_floor_plan_id` int UNSIGNED DEFAULT NULL,
  `require_landscape_mobile` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'AR/360 landing locked landscape fullscreen on mobile',
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int UNSIGNED DEFAULT NULL COMMENT 'owner user',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `institutions`
--

INSERT INTO `institutions` (`id`, `slug`, `name`, `short_name`, `institution_type`, `description`, `logo_path`, `cover_image_path`, `address`, `city`, `province`, `country`, `latitude`, `longitude`, `website_url`, `contact_email`, `contact_phone`, `folder_path`, `landing_mode`, `starting_scene_id`, `starting_floor_plan_id`, `require_landscape_mobile`, `is_published`, `is_active`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'immaculada-concepcion-college', 'Immaculada Concepcion College', 'ICC', 'school', NULL, NULL, 'organizations/immaculada-concepcion-college/assets/552414c35a5e616e.png', 'Purok II, Barangay 185, Zone 16, District 3, Caloocan, Northern Manila District, Metro Manila, 1426, Philippines', 'Caloocan', 'Metro Manila', 'Philippines', 14.7677658, 121.0797429, 'https://www.immaculada.edu.ph/home', 'icc@immaculada.edu.ph', '+63 709 42 25', 'organizations/immaculada-concepcion-college', 'floor_plan', NULL, 1, 1, 1, 1, 1, '2026-08-31 10:49:52', '2026-09-01 23:19:39', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `institution_files`
--

CREATE TABLE `institution_files` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `relative_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Inside folder_path; template files copied on create',
  `file_role` enum('aframe_scene','stylesheet','script','asset','config','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `is_visual_editable` tinyint(1) NOT NULL DEFAULT '1',
  `mime_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` int UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `institution_themes`
--

CREATE TABLE `institution_themes` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `primary_color` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#1a365d',
  `secondary_color` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#ed8936',
  `accent_color` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#38b2ac',
  `font_family` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `popup_animation` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fade',
  `marker_style` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'circle',
  `infographic_style` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'card',
  `custom_css` mediumtext COLLATE utf8mb4_unicode_ci,
  `theme_json` json DEFAULT NULL COMMENT 'Extra visual tokens edited in the drag-drop environment',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `institution_themes`
--

INSERT INTO `institution_themes` (`id`, `institution_id`, `primary_color`, `secondary_color`, `accent_color`, `font_family`, `popup_animation`, `marker_style`, `infographic_style`, `custom_css`, `theme_json`, `updated_at`) VALUES
(1, 1, '#1a365d', '#ed8936', '#38b2ac', NULL, 'fade', 'circle', 'card', NULL, NULL, '2026-08-31 11:10:02');

-- --------------------------------------------------------

--
-- Table structure for table `media_assets`
--

CREATE TABLE `media_assets` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `uploaded_by` int UNSIGNED DEFAULT NULL,
  `kind` enum('featured','gallery','floor_plan','pano','cubemap_face','ar_target','logo','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `width` int UNSIGNED DEFAULT NULL,
  `height` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `panoramas`
--

CREATE TABLE `panoramas` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `equirect_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `thumbnail_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capture_data` json DEFAULT NULL,
  `width` int UNSIGNED DEFAULT NULL,
  `height` int UNSIGNED DEFAULT NULL,
  `file_size` int UNSIGNED DEFAULT NULL,
  `status` enum('draft','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` smallint UNSIGNED NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `slug`, `module`, `description`) VALUES
(1, 'platform.landing.manage', 'platform', 'Edit Innovatech product landing'),
(2, 'platform.settings.manage', 'platform', 'System setup and configuration'),
(3, 'platform.emails.manage', 'platform', 'Email / SMTP templates'),
(4, 'platform.roles.manage', 'platform', 'Roles and access'),
(5, 'platform.logs.view', 'platform', 'Audit and access logs'),
(6, 'platform.institutions.manage', 'platform', 'Create institutions and assign admins'),
(7, 'institution.manage', 'institution', 'Edit assigned institution profile and folder'),
(8, 'institution.theme.manage', 'institution', 'Themes, popups, infographics, animations'),
(9, 'institution.landing.manage', 'institution', 'Landing mode 360 vs floor plan, starting point'),
(10, 'content.tours.manage', 'content', '360 scenes, hotspots, featured images'),
(11, 'content.locations.manage', 'content', 'Buildings, rooms, facilities, areas'),
(12, 'content.floor_plans.manage', 'content', 'Floor plans and percentage markers'),
(13, 'tools.ai_info.use', 'tools', 'AI facility descriptions'),
(14, 'tools.ai_stitch.use', 'tools', 'Cubemap upload and in-app 360 capture stitch'),
(15, 'ar.manage', 'ar', 'AR overlays, geo waypoints, walk detection'),
(16, 'tour.view', 'public', 'View virtual tours');

-- --------------------------------------------------------

--
-- Table structure for table `platform_settings`
--

CREATE TABLE `platform_settings` (
  `id` tinyint UNSIGNED NOT NULL,
  `product_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AI-Assisted AR 360° Virtual Campus Navigation',
  `company_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Innovatech PH',
  `logo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landing_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `contact_email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_host` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_port` smallint UNSIGNED DEFAULT '587',
  `smtp_username` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_password_enc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_from_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_from_email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution_folder_root` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'storage/institutions',
  `template_pack_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'storage/templates/org_pack',
  `updated_by` int UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `platform_settings`
--

INSERT INTO `platform_settings` (`id`, `product_name`, `company_name`, `logo_path`, `hero_image_path`, `landing_html`, `contact_email`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password_enc`, `smtp_from_name`, `smtp_from_email`, `institution_folder_root`, `template_pack_path`, `updated_by`, `updated_at`) VALUES
(1, 'AI-Assisted AR 360° Virtual Campus Navigation', 'Innovatech PH', 'assets/logo2.webp', 'public/uploads/a870e058ed4406bc.png', '{\"features\":[[\"360° Virtual Tours\",\"Explore campus locations through immersive 360° virtual tours from any device.\",\"\",\"assets/360° Virtual Tours.png\"],[\"AR Campus Overlays\",\"Navigate campus spaces with interactive AR overlays that guide you to buildings, rooms, and facilities.\",\"\",\"assets/AR Campus Overlays.png\"],[\"AI-Assisted Cube map and Information System\",\"Generate immersive 360° environments and quickly create informative location descriptions using available campus data.\",\"\",\"assets/AI Cubemap Stitching.png\"],[\"360 / AR Interactive Hotspots\",\"Discover campus locations through interactive 360° and AR hotspots with instant access to relevant information.\",\"\",\"assets/360 AR Hotspot  Facility Information.png\"],[\"Admin Dashboard\",\"Manage campus locations, AR content, virtual tours, and facility information in one centralized dashboard.\",\"\",\"assets/Admin Dashboard.png\"],[\"Interactive Floor Plans\",\"Find buildings, rooms, and facilities with interactive floor plans and guided campus navigation.\",\"\",\"public/uploads/876fbf5dcca33444.png\"]]}', 'support@innovatechservicesph.com', 'smtp.hostinger.com', 465, 'support@innovatechservicesph.com', 'aDJENm1ZT3NeJDM=', 'Innovatech PH', 'support@innovatechservicesph.com', 'storage/institutions', 'storage/templates/org_pack', 1, '2026-08-31 12:07:34');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` tinyint UNSIGNED NOT NULL,
  `slug` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `slug`, `name`, `description`) VALUES
(1, 'owner', 'Owner', 'Platform: product landing, roles, logs, access, emails, system config, institutions'),
(2, 'admin', 'Organization Admin', 'Institution content: 360/AR tours, themes, landing mode, visual editor'),
(3, 'staff', 'Organization Staff', 'Under admin: facilities, floor plans, featured images, AI stitch/capture'),
(4, 'user', 'User', 'View-only campus tours and AR walking'),
(5, 'system_admin', 'System Admin', 'Elsewhere from owner: manage organizations, accounts, folders, platform insights'),
(6, 'system_staff', 'System Staff', 'Under system admin: manage organizations and platform content');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` tinyint UNSIGNED NOT NULL,
  `permission_id` smallint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(5, 5),
(1, 6),
(5, 6),
(6, 6),
(1, 7),
(2, 7),
(5, 7),
(6, 7),
(1, 8),
(2, 8),
(1, 9),
(2, 9),
(5, 9),
(6, 9),
(1, 10),
(2, 10),
(3, 10),
(5, 10),
(6, 10),
(1, 11),
(2, 11),
(3, 11),
(5, 11),
(6, 11),
(1, 12),
(2, 12),
(3, 12),
(5, 12),
(6, 12),
(1, 13),
(2, 13),
(3, 13),
(5, 13),
(6, 13),
(1, 14),
(2, 14),
(3, 14),
(5, 14),
(6, 14),
(1, 15),
(2, 15),
(1, 16),
(2, 16),
(3, 16),
(4, 16),
(5, 16),
(6, 16);

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `building_id` int UNSIGNED DEFAULT NULL,
  `campus_area_id` int UNSIGNED DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `floor_label` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `room_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'classroom, office, lab, restroom, etc.',
  `description` text COLLATE utf8mb4_unicode_ci,
  `ai_description` text COLLATE utf8mb4_unicode_ci,
  `featured_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scene_hotspots`
--

CREATE TABLE `scene_hotspots` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `from_scene_id` int UNSIGNED NOT NULL,
  `to_scene_id` int UNSIGNED DEFAULT NULL,
  `hotspot_type` enum('navigation','info','facility','media','ar_label') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'navigation',
  `label` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body_html` text COLLATE utf8mb4_unicode_ci,
  `yaw` decimal(8,3) NOT NULL DEFAULT '0.000',
  `pitch` decimal(8,3) NOT NULL DEFAULT '0.000',
  `icon_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_facility_id` int UNSIGNED DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `scene_hotspots`
--

INSERT INTO `scene_hotspots` (`id`, `institution_id`, `from_scene_id`, `to_scene_id`, `hotspot_type`, `label`, `body_html`, `yaw`, `pitch`, `icon_path`, `target_facility_id`, `sort_order`) VALUES
(3, 1, 1, NULL, 'navigation', 'Guard Post', 'Guard Post is a tour hotspot of Immaculada Concepcion College.', 160.500, -3.800, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int UNSIGNED NOT NULL,
  `subject` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `contact_email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution_id` int UNSIGNED DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `priority` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `support_tickets`
--

INSERT INTO `support_tickets` (`id`, `subject`, `message`, `contact_email`, `institution_id`, `status`, `priority`, `created_by`, `created_at`, `updated_at`) VALUES
(4, 'Test', 'Sean', 'seancvpugosa@gmail.com', 1, 'open', 'low', 4, '2026-09-01 21:55:07', '2026-09-01 21:55:07');

-- --------------------------------------------------------

--
-- Table structure for table `tour_scenes`
--

CREATE TABLE `tour_scenes` (
  `id` int UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED NOT NULL,
  `building_id` int UNSIGNED DEFAULT NULL,
  `room_id` int UNSIGNED DEFAULT NULL,
  `campus_area_id` int UNSIGNED DEFAULT NULL,
  `facility_id` int UNSIGNED DEFAULT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `ai_description` text COLLATE utf8mb4_unicode_ci,
  `featured_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equirect_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Stitched 360 panorama',
  `initial_yaw` decimal(8,3) NOT NULL DEFAULT '0.000',
  `initial_pitch` decimal(8,3) NOT NULL DEFAULT '0.000',
  `is_landing_start` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'If landing_mode=360_rotation, one scene should be start',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tour_scenes`
--

INSERT INTO `tour_scenes` (`id`, `institution_id`, `building_id`, `room_id`, `campus_area_id`, `facility_id`, `title`, `slug`, `description`, `ai_description`, `featured_image_path`, `equirect_path`, `initial_yaw`, `initial_pitch`, `is_landing_start`, `sort_order`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 2, NULL, NULL, NULL, 'Entrance', 'entrance', 'Entrance is a 360° the first tour stop of Immaculada Concepcion College', 'Entrance is a 360° tour stop of Immaculada Concepcion College.\n\nVisitors can explore it through the 360° tour and find it instantly on the campus floor plan.', 'organizations/immaculada-concepcion-college/assets/30f14122e6d46f89.avif', 'organizations/immaculada-concepcion-college/assets/scenes/ae72511ab1f73793.jpg', 0.000, 0.000, 1, 0, 4, '2026-08-31 12:10:16', '2026-08-31 18:29:02', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `role_id` tinyint UNSIGNED NOT NULL,
  `institution_id` int UNSIGNED DEFAULT NULL COMMENT 'NULL for owner; required for admin/staff; optional for user',
  `supervisor_user_id` int UNSIGNED DEFAULT NULL COMMENT 'staff reports to this admin',
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `email_verified_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `institution_id`, `supervisor_user_id`, `email`, `username`, `password_hash`, `first_name`, `last_name`, `phone`, `avatar_path`, `is_active`, `email_verified_at`, `last_login_at`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, NULL, NULL, 'owner@innovatech.ph', 'thesis_1_owner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Owner', '639533180925', NULL, 1, '2026-08-30 18:44:45', '2026-09-01 22:38:06', NULL, '2026-08-30 18:44:45', '2026-09-01 22:38:06', NULL),
(2, 5, NULL, NULL, 'systemadmin@innovatech.ph', 'thesis_1_sysadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Admin', '', NULL, 1, '2026-08-30 18:44:45', '2026-08-31 16:20:24', NULL, '2026-08-30 18:44:45', '2026-08-31 16:20:24', NULL),
(3, 6, NULL, NULL, 'systemstaff@innovatech.ph', 'thesis_1_sysstaff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Staff', '', NULL, 1, '2026-08-30 18:44:45', '2026-08-31 11:08:43', NULL, '2026-08-30 18:44:45', '2026-08-31 11:08:54', NULL),
(4, 2, 1, NULL, 'orgadmin@gmail.com', 'organization-admin', '$2y$10$AMPZLaNhVnNpvJQ88NeszuveWZyW3gaGZglqlbPSJRnVqLgwTjXh2', 'Organization', 'Admin', NULL, NULL, 1, NULL, '2026-09-01 23:01:42', 1, '2026-08-31 10:50:20', '2026-09-01 23:01:42', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_page_access`
--

CREATE TABLE `user_page_access` (
  `user_id` int UNSIGNED NOT NULL,
  `page_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ai_info_jobs`
--
ALTER TABLE `ai_info_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ai_info_institution` (`institution_id`),
  ADD KEY `fk_ai_info_user` (`created_by`);

--
-- Indexes for table `ai_stitch_jobs`
--
ALTER TABLE `ai_stitch_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stitch_institution` (`institution_id`),
  ADD KEY `fk_stitch_user` (`created_by`),
  ADD KEY `fk_stitch_scene` (`output_scene_id`);

--
-- Indexes for table `ar_walk_pings`
--
ALTER TABLE `ar_walk_pings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pings_session` (`session_id`),
  ADD KEY `fk_pings_waypoint` (`waypoint_id`);

--
-- Indexes for table `ar_walk_sessions`
--
ALTER TABLE `ar_walk_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ar_walk_inst` (`institution_id`),
  ADD KEY `fk_ar_walk_user` (`user_id`);

--
-- Indexes for table `ar_waypoints`
--
ALTER TABLE `ar_waypoints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ar_wp_institution` (`institution_id`),
  ADD KEY `fk_ar_wp_marker` (`floor_plan_marker_id`),
  ADD KEY `fk_ar_wp_scene` (`scene_id`),
  ADD KEY `fk_ar_wp_room` (`room_id`),
  ADD KEY `fk_ar_wp_building` (`building_id`),
  ADD KEY `fk_ar_wp_facility` (`facility_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_actor` (`actor_user_id`),
  ADD KEY `idx_audit_institution` (`institution_id`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `buildings`
--
ALTER TABLE `buildings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_buildings_institution` (`institution_id`);

--
-- Indexes for table `campus_areas`
--
ALTER TABLE `campus_areas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_areas_institution` (`institution_id`),
  ADD KEY `fk_areas_building` (`building_id`);

--
-- Indexes for table `cubemap_faces`
--
ALTER TABLE `cubemap_faces`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_job_face` (`job_id`,`face`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email_templates_slug` (`slug`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_facilities_institution` (`institution_id`),
  ADD KEY `fk_facilities_building` (`building_id`),
  ADD KEY `fk_facilities_room` (`room_id`),
  ADD KEY `fk_facilities_area` (`campus_area_id`);

--
-- Indexes for table `floor_plans`
--
ALTER TABLE `floor_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_floor_plans_institution` (`institution_id`),
  ADD KEY `fp_building` (`building_id`);

--
-- Indexes for table `floor_plan_markers`
--
ALTER TABLE `floor_plan_markers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_markers_plan` (`floor_plan_id`),
  ADD KEY `fk_markers_institution` (`institution_id`),
  ADD KEY `fk_markers_scene` (`target_scene_id`),
  ADD KEY `fk_markers_room` (`target_room_id`),
  ADD KEY `fk_markers_building` (`target_building_id`),
  ADD KEY `fk_markers_facility` (`target_facility_id`);

--
-- Indexes for table `fp_connections`
--
ALTER TABLE `fp_connections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `con_fp` (`from_floor_plan_id`,`institution_id`);

--
-- Indexes for table `fp_navigation_paths`
--
ALTER TABLE `fp_navigation_paths`
  ADD PRIMARY KEY (`id`),
  ADD KEY `path_fp` (`floor_plan_id`,`institution_id`);

--
-- Indexes for table `fp_waypoints`
--
ALTER TABLE `fp_waypoints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `way_fp` (`floor_plan_id`,`institution_id`);

--
-- Indexes for table `institutions`
--
ALTER TABLE `institutions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_institutions_slug` (`slug`),
  ADD KEY `idx_institutions_published` (`is_published`,`is_active`),
  ADD KEY `fk_inst_start_scene` (`starting_scene_id`),
  ADD KEY `fk_inst_start_floor` (`starting_floor_plan_id`);

--
-- Indexes for table `institution_files`
--
ALTER TABLE `institution_files`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inst_file` (`institution_id`,`relative_path`);

--
-- Indexes for table `institution_themes`
--
ALTER TABLE `institution_themes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_theme_institution` (`institution_id`);

--
-- Indexes for table `media_assets`
--
ALTER TABLE `media_assets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_media_institution` (`institution_id`),
  ADD KEY `fk_media_user` (`uploaded_by`);

--
-- Indexes for table `panoramas`
--
ALTER TABLE `panoramas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_panoramas_institution` (`institution_id`),
  ADD KEY `idx_panoramas_status` (`status`),
  ADD KEY `fk_panoramas_user` (`created_by`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pw_resets_user` (`user_id`),
  ADD KEY `idx_pw_resets_hash` (`token_hash`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_permissions_slug` (`slug`);

--
-- Indexes for table `platform_settings`
--
ALTER TABLE `platform_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_slug` (`slug`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_rp_perm` (`permission_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rooms_institution` (`institution_id`),
  ADD KEY `fk_rooms_building` (`building_id`),
  ADD KEY `fk_rooms_area` (`campus_area_id`);

--
-- Indexes for table `scene_hotspots`
--
ALTER TABLE `scene_hotspots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hotspots_from` (`from_scene_id`),
  ADD KEY `fk_hotspots_institution` (`institution_id`),
  ADD KEY `fk_hotspots_to` (`to_scene_id`),
  ADD KEY `fk_hotspots_facility` (`target_facility_id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `tour_scenes`
--
ALTER TABLE `tour_scenes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_scene_slug` (`institution_id`,`slug`),
  ADD KEY `idx_scenes_institution` (`institution_id`),
  ADD KEY `fk_scenes_building` (`building_id`),
  ADD KEY `fk_scenes_room` (`room_id`),
  ADD KEY `fk_scenes_area` (`campus_area_id`),
  ADD KEY `fk_scenes_facility` (`facility_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD KEY `idx_users_role` (`role_id`),
  ADD KEY `idx_users_institution` (`institution_id`),
  ADD KEY `fk_users_supervisor` (`supervisor_user_id`);

--
-- Indexes for table `user_page_access`
--
ALTER TABLE `user_page_access`
  ADD PRIMARY KEY (`user_id`,`page_key`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sessions_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ai_info_jobs`
--
ALTER TABLE `ai_info_jobs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `ai_stitch_jobs`
--
ALTER TABLE `ai_stitch_jobs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ar_walk_pings`
--
ALTER TABLE `ar_walk_pings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ar_walk_sessions`
--
ALTER TABLE `ar_walk_sessions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ar_waypoints`
--
ALTER TABLE `ar_waypoints`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `buildings`
--
ALTER TABLE `buildings`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `campus_areas`
--
ALTER TABLE `campus_areas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `cubemap_faces`
--
ALTER TABLE `cubemap_faces`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` smallint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `floor_plans`
--
ALTER TABLE `floor_plans`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `floor_plan_markers`
--
ALTER TABLE `floor_plan_markers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `fp_connections`
--
ALTER TABLE `fp_connections`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fp_navigation_paths`
--
ALTER TABLE `fp_navigation_paths`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `fp_waypoints`
--
ALTER TABLE `fp_waypoints`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `institutions`
--
ALTER TABLE `institutions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `institution_files`
--
ALTER TABLE `institution_files`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `institution_themes`
--
ALTER TABLE `institution_themes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `media_assets`
--
ALTER TABLE `media_assets`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `panoramas`
--
ALTER TABLE `panoramas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` smallint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` tinyint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `scene_hotspots`
--
ALTER TABLE `scene_hotspots`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tour_scenes`
--
ALTER TABLE `tour_scenes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_info_jobs`
--
ALTER TABLE `ai_info_jobs`
  ADD CONSTRAINT `fk_ai_info_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ai_info_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `ai_stitch_jobs`
--
ALTER TABLE `ai_stitch_jobs`
  ADD CONSTRAINT `fk_stitch_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stitch_scene` FOREIGN KEY (`output_scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_stitch_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `ar_walk_pings`
--
ALTER TABLE `ar_walk_pings`
  ADD CONSTRAINT `fk_pings_session` FOREIGN KEY (`session_id`) REFERENCES `ar_walk_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pings_waypoint` FOREIGN KEY (`waypoint_id`) REFERENCES `ar_waypoints` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ar_walk_sessions`
--
ALTER TABLE `ar_walk_sessions`
  ADD CONSTRAINT `fk_ar_walk_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ar_walk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ar_waypoints`
--
ALTER TABLE `ar_waypoints`
  ADD CONSTRAINT `fk_ar_wp_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ar_wp_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ar_wp_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ar_wp_marker` FOREIGN KEY (`floor_plan_marker_id`) REFERENCES `floor_plan_markers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ar_wp_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ar_wp_scene` FOREIGN KEY (`scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_audit_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `buildings`
--
ALTER TABLE `buildings`
  ADD CONSTRAINT `fk_buildings_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `campus_areas`
--
ALTER TABLE `campus_areas`
  ADD CONSTRAINT `fk_areas_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_areas_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cubemap_faces`
--
ALTER TABLE `cubemap_faces`
  ADD CONSTRAINT `fk_faces_job` FOREIGN KEY (`job_id`) REFERENCES `ai_stitch_jobs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `facilities`
--
ALTER TABLE `facilities`
  ADD CONSTRAINT `fk_facilities_area` FOREIGN KEY (`campus_area_id`) REFERENCES `campus_areas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_facilities_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_facilities_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_facilities_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `floor_plans`
--
ALTER TABLE `floor_plans`
  ADD CONSTRAINT `fk_floor_plans_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `floor_plan_markers`
--
ALTER TABLE `floor_plan_markers`
  ADD CONSTRAINT `fk_markers_building` FOREIGN KEY (`target_building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_markers_facility` FOREIGN KEY (`target_facility_id`) REFERENCES `facilities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_markers_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_markers_plan` FOREIGN KEY (`floor_plan_id`) REFERENCES `floor_plans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_markers_room` FOREIGN KEY (`target_room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_markers_scene` FOREIGN KEY (`target_scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `institutions`
--
ALTER TABLE `institutions`
  ADD CONSTRAINT `fk_inst_start_floor` FOREIGN KEY (`starting_floor_plan_id`) REFERENCES `floor_plans` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inst_start_scene` FOREIGN KEY (`starting_scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `institution_files`
--
ALTER TABLE `institution_files`
  ADD CONSTRAINT `fk_inst_files_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `institution_themes`
--
ALTER TABLE `institution_themes`
  ADD CONSTRAINT `fk_theme_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `media_assets`
--
ALTER TABLE `media_assets`
  ADD CONSTRAINT `fk_media_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_media_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `panoramas`
--
ALTER TABLE `panoramas`
  ADD CONSTRAINT `fk_panoramas_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_panoramas_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_pw_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `fk_rooms_area` FOREIGN KEY (`campus_area_id`) REFERENCES `campus_areas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rooms_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rooms_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scene_hotspots`
--
ALTER TABLE `scene_hotspots`
  ADD CONSTRAINT `fk_hotspots_facility` FOREIGN KEY (`target_facility_id`) REFERENCES `facilities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hotspots_from` FOREIGN KEY (`from_scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hotspots_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hotspots_to` FOREIGN KEY (`to_scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tour_scenes`
--
ALTER TABLE `tour_scenes`
  ADD CONSTRAINT `fk_scenes_area` FOREIGN KEY (`campus_area_id`) REFERENCES `campus_areas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_scenes_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_scenes_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_scenes_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_scenes_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `fk_users_supervisor` FOREIGN KEY (`supervisor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_page_access`
--
ALTER TABLE `user_page_access`
  ADD CONSTRAINT `fk_up_acc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;