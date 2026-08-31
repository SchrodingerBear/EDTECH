-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: innovatech_campus
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.24.04.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `ai_info_jobs`
--

DROP TABLE IF EXISTS `ai_info_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_info_jobs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `created_by` int unsigned NOT NULL,
  `target_type` enum('building','room','facility','campus_area','tour_scene') COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` int unsigned NOT NULL,
  `prompt` text COLLATE utf8mb4_unicode_ci,
  `output_text` text COLLATE utf8mb4_unicode_ci,
  `status` enum('queued','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_info_institution` (`institution_id`),
  KEY `fk_ai_info_user` (`created_by`),
  CONSTRAINT `fk_ai_info_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_info_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_info_jobs`
--

LOCK TABLES `ai_info_jobs` WRITE;
/*!40000 ALTER TABLE `ai_info_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_info_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_stitch_jobs`
--

DROP TABLE IF EXISTS `ai_stitch_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_stitch_jobs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `created_by` int unsigned NOT NULL,
  `source_type` enum('cubemap_upload','in_app_capture') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','uploading','queued','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `guide_step` enum('front','back','left','right','up','down','done') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `output_equirect_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `output_scene_id` int unsigned DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `provider` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_job_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_stitch_institution` (`institution_id`),
  KEY `fk_stitch_user` (`created_by`),
  KEY `fk_stitch_scene` (`output_scene_id`),
  CONSTRAINT `fk_stitch_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stitch_scene` FOREIGN KEY (`output_scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_stitch_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_stitch_jobs`
--

LOCK TABLES `ai_stitch_jobs` WRITE;
/*!40000 ALTER TABLE `ai_stitch_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_stitch_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ar_walk_pings`
--

DROP TABLE IF EXISTS `ar_walk_pings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ar_walk_pings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint unsigned NOT NULL,
  `waypoint_id` int unsigned DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `matched_by` enum('gps','visual_target','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gps',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pings_session` (`session_id`),
  KEY `fk_pings_waypoint` (`waypoint_id`),
  CONSTRAINT `fk_pings_session` FOREIGN KEY (`session_id`) REFERENCES `ar_walk_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pings_waypoint` FOREIGN KEY (`waypoint_id`) REFERENCES `ar_waypoints` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ar_walk_pings`
--

LOCK TABLES `ar_walk_pings` WRITE;
/*!40000 ALTER TABLE `ar_walk_pings` DISABLE KEYS */;
/*!40000 ALTER TABLE `ar_walk_pings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ar_walk_sessions`
--

DROP TABLE IF EXISTS `ar_walk_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ar_walk_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL COMMENT 'NULL = guest visitor',
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at` datetime DEFAULT NULL,
  `device_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ar_walk_inst` (`institution_id`),
  KEY `fk_ar_walk_user` (`user_id`),
  CONSTRAINT `fk_ar_walk_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ar_walk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ar_walk_sessions`
--

LOCK TABLES `ar_walk_sessions` WRITE;
/*!40000 ALTER TABLE `ar_walk_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `ar_walk_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ar_waypoints`
--

DROP TABLE IF EXISTS `ar_waypoints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ar_waypoints` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `floor_plan_marker_id` int unsigned DEFAULT NULL,
  `scene_id` int unsigned DEFAULT NULL,
  `room_id` int unsigned DEFAULT NULL,
  `building_id` int unsigned DEFAULT NULL,
  `facility_id` int unsigned DEFAULT NULL,
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
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ar_wp_institution` (`institution_id`),
  KEY `fk_ar_wp_marker` (`floor_plan_marker_id`),
  KEY `fk_ar_wp_scene` (`scene_id`),
  KEY `fk_ar_wp_room` (`room_id`),
  KEY `fk_ar_wp_building` (`building_id`),
  KEY `fk_ar_wp_facility` (`facility_id`),
  CONSTRAINT `fk_ar_wp_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ar_wp_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ar_wp_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ar_wp_marker` FOREIGN KEY (`floor_plan_marker_id`) REFERENCES `floor_plan_markers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ar_wp_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ar_wp_scene` FOREIGN KEY (`scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ar_waypoints`
--

LOCK TABLES `ar_waypoints` WRITE;
/*!40000 ALTER TABLE `ar_waypoints` DISABLE KEYS */;
/*!40000 ALTER TABLE `ar_waypoints` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` int unsigned DEFAULT NULL,
  `institution_id` int unsigned DEFAULT NULL,
  `action` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_actor` (`actor_user_id`),
  KEY `idx_audit_institution` (`institution_id`),
  KEY `idx_audit_created` (`created_at`),
  CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_audit_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 17:32:46'),(2,1,NULL,'auth.login','auth',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 17:41:21'),(3,1,NULL,'auth.login','auth',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 17:42:52'),(4,1,NULL,'auth.login','auth',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 17:44:39'),(5,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 18:29:53'),(6,2,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 18:29:53'),(7,3,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 18:29:53'),(8,4,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 18:29:53'),(9,5,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 18:29:53'),(10,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 18:30:01'),(11,1,NULL,'institution.create','institutions','institution',2,'::1',NULL,NULL,'2026-08-28 18:30:01'),(12,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 18:30:55'),(13,1,NULL,'institution.create','institutions','institution',3,'::1',NULL,NULL,'2026-08-28 18:30:55'),(14,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 18:31:44'),(15,1,NULL,'institution.create','institutions','institution',4,'::1',NULL,NULL,'2026-08-28 18:31:44'),(16,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 18:37:16'),(17,1,NULL,'institution.create','institutions','institution',5,'::1',NULL,NULL,'2026-08-28 18:37:16'),(18,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 18:41:13'),(19,1,NULL,'institution.create','institutions','institution',6,'::1',NULL,NULL,'2026-08-28 18:41:13'),(20,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 18:42:17'),(21,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 18:42:41'),(22,1,NULL,'institution.create','institutions','institution',7,'::1',NULL,NULL,'2026-08-28 18:42:41'),(23,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 18:42:58'),(24,1,NULL,'institution.create','institutions','institution',8,'::1',NULL,NULL,'2026-08-28 18:42:59'),(25,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 18:44:36'),(26,1,NULL,'institution.create','institutions','institution',9,'::1',NULL,NULL,'2026-08-28 18:44:36'),(27,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 18:53:21'),(28,1,NULL,'institution.create','institutions','institution',10,'::1',NULL,NULL,'2026-08-28 18:53:21'),(29,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 18:53:47'),(30,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 18:55:16'),(31,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:00:42'),(32,1,NULL,'institution.create','institutions','institution',11,'::1',NULL,NULL,'2026-08-28 19:00:42'),(33,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:01:01'),(34,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:02:17'),(35,1,NULL,'institution.create','institutions','institution',12,'::1',NULL,NULL,'2026-08-28 19:02:17'),(36,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:02:56'),(37,1,NULL,'institution.create','institutions','institution',13,'::1',NULL,NULL,'2026-08-28 19:02:56'),(38,NULL,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:03:23'),(39,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:04:18'),(40,2,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:04:18'),(41,3,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:04:18'),(42,4,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:04:18'),(43,5,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:04:18'),(44,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:05:50'),(45,2,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:05:50'),(46,3,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:05:50'),(47,4,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:05:50'),(48,5,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:05:50'),(49,1,NULL,'auth.login','auth',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 19:12:53'),(50,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:36:18'),(51,4,1,'auth.login','auth',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 19:54:13'),(52,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:54:19'),(53,1,NULL,'account.create','accounts','user',15,'::1','curl/8.5.0',NULL,'2026-08-28 19:54:19'),(54,4,1,'auth.login','auth',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 19:54:54'),(55,2,NULL,'auth.login','auth',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 19:54:57'),(56,3,NULL,'auth.login','auth',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 19:55:06'),(57,2,NULL,'auth.login','auth',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 19:55:13'),(58,3,NULL,'auth.login','auth',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 19:55:17'),(59,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:55:28'),(60,1,NULL,'account.create','accounts','user',16,'::1','curl/8.5.0',NULL,'2026-08-28 19:55:28'),(61,5,1,'auth.login','auth',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 19:55:53'),(62,5,1,'ai_stitch.job.create','ai','job',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 19:56:08'),(63,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:56:13'),(64,1,NULL,'account.create','accounts','user',17,'::1','curl/8.5.0',NULL,'2026-08-28 19:56:13'),(65,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 19:56:26'),(66,1,NULL,'institution.create','institutions','institution',14,'::1',NULL,NULL,'2026-08-28 19:56:26'),(67,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:01:39'),(68,2,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:01:39'),(69,4,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:01:39'),(70,5,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:01:39'),(71,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:01:39'),(72,2,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:01:53'),(73,5,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:01:53'),(74,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:01:53'),(75,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:02:46'),(76,2,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:02:47'),(77,4,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:02:47'),(78,5,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:02:47'),(79,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:02:48'),(80,4,1,'auth.login','auth',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 20:03:00'),(81,4,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:03:07'),(82,4,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:03:25'),(83,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:03:25'),(84,4,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:03:42'),(85,4,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:04:25'),(86,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:04:48'),(87,2,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:04:48'),(88,4,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:04:48'),(89,5,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:04:48'),(90,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:04:48'),(91,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:04:48'),(92,4,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:04:48'),(93,5,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:04:49'),(94,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:59:02'),(95,2,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:59:02'),(96,4,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:59:02'),(97,5,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:59:02'),(98,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:59:02'),(99,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:59:03'),(100,4,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:59:03'),(101,5,1,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:59:03'),(102,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:59:16'),(103,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:59:27'),(104,1,NULL,'auth.login','auth',NULL,NULL,'::1','curl/8.5.0',NULL,'2026-08-28 20:59:37'),(105,4,1,'auth.login','auth',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 21:40:17'),(106,1,NULL,'auth.login','auth',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 22:39:41'),(107,4,1,'auth.login','auth',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 22:42:40'),(108,1,NULL,'auth.login','auth',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 22:47:19'),(109,4,1,'auth.login','auth',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,'2026-08-28 22:57:11');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `buildings`
--

DROP TABLE IF EXISTS `buildings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `buildings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `ai_description` text COLLATE utf8mb4_unicode_ci,
  `featured_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_buildings_institution` (`institution_id`),
  CONSTRAINT `fk_buildings_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `buildings`
--

LOCK TABLES `buildings` WRITE;
/*!40000 ALTER TABLE `buildings` DISABLE KEYS */;
INSERT INTO `buildings` VALUES (1,1,'GYMNASIUM','GYM-01','A facility where students participate in sports activities, school events, physical education classes, and extracurricular activities.','The GYMNASIUM building at Immaculada Concepcion College is a key part of campus life. Visitors commonly look for it when exploring facilities, offices and learning spaces. Use the 360° tour to walk inside and see what this building offers.',NULL,0,'2026-08-28 20:34:57','2026-08-28 22:16:42',NULL);
/*!40000 ALTER TABLE `buildings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campus_areas`
--

DROP TABLE IF EXISTS `campus_areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campus_areas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `building_id` int unsigned DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `area_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'quad, parking, gate, sports, etc.',
  `description` text COLLATE utf8mb4_unicode_ci,
  `ai_description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_areas_institution` (`institution_id`),
  KEY `fk_areas_building` (`building_id`),
  CONSTRAINT `fk_areas_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_areas_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campus_areas`
--

LOCK TABLES `campus_areas` WRITE;
/*!40000 ALTER TABLE `campus_areas` DISABLE KEYS */;
/*!40000 ALTER TABLE `campus_areas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cubemap_faces`
--

DROP TABLE IF EXISTS `cubemap_faces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cubemap_faces` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `job_id` int unsigned NOT NULL,
  `face` enum('front','back','left','right','up','down') COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `captured_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_job_face` (`job_id`,`face`),
  CONSTRAINT `fk_faces_job` FOREIGN KEY (`job_id`) REFERENCES `ai_stitch_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cubemap_faces`
--

LOCK TABLES `cubemap_faces` WRITE;
/*!40000 ALTER TABLE `cubemap_faces` DISABLE KEYS */;
/*!40000 ALTER TABLE `cubemap_faces` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_templates`
--

DROP TABLE IF EXISTS `email_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_templates` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_html` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_templates_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_templates`
--

LOCK TABLES `email_templates` WRITE;
/*!40000 ALTER TABLE `email_templates` DISABLE KEYS */;
INSERT INTO `email_templates` VALUES (1,'admin_invite','Your Innovatech Campus admin account','<h2>Welcome, {{name}}!</h2><p>Here are your Innovatech Campus login credentials:</p><p><b>Email:</b> {{email}}<br><b>Username:</b> {{username}}<br><b>Temporary password:</b> {{password}}</p><p><b>Role:</b> {{role}}<br><b>Institution:</b> {{institution}}</p><p><a href=\"{{login_link}}\">Sign in to the admin panel</a></p><p>For security, change your password after first login.</p>',1,'2026-08-28 19:45:20'),(2,'staff_invite','Your Innovatech Campus staff account','<h2>Welcome, {{name}}!</h2><p>Here are your Innovatech Campus login credentials:</p><p><b>Email:</b> {{email}}<br><b>Username:</b> {{username}}<br><b>Temporary password:</b> {{password}}</p><p><b>Role:</b> {{role}}<br><b>Institution:</b> {{institution}}</p><p><a href=\"{{login_link}}\">Sign in to the admin panel</a></p><p>For security, change your password after first login.</p>',1,'2026-08-28 19:45:20'),(3,'password_reset','Reset your password','<p>Reset link: {{link}}</p>',1,'2026-08-28 17:16:54'),(4,'support_ticket_status','Your support ticket #{{ticket_id}} is {{ticket_status}}','<p>Hi {{name}},</p><p>Your support ticket &quot;<strong>{{ticket_subject}}</strong>&quot; is now <strong>{{ticket_status}}</strong>.</p><p>{{ticket_message}}</p><p><a href="{{link}}">Open the admin dashboard</a></p>',1,'2026-08-31 13:20:00'),(5,'new_ticket','New support ticket #{{ticket_id}} — {{ticket_subject}}','<p>A new support ticket has been opened.</p><p><strong>{{ticket_subject}}</strong></p><p>{{ticket_message}}</p><p>Ticket #{{ticket_id}} &middot; Priority: {{priority}} &middot; {{institution}}</p><p>Submitted by {{name}} ({{email}}).</p><p><a href="{{link}}">Open the support desk</a></p>',1,'2026-08-31 13:41:00');
/*!40000 ALTER TABLE `email_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_tickets`
--

DROP TABLE IF EXISTS `support_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_tickets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `contact_email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution_id` int unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `priority` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `facilities`
--

DROP TABLE IF EXISTS `facilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facilities` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `building_id` int unsigned DEFAULT NULL,
  `room_id` int unsigned DEFAULT NULL,
  `campus_area_id` int unsigned DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `ai_description` text COLLATE utf8mb4_unicode_ci,
  `featured_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `info_json` json DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_facilities_institution` (`institution_id`),
  KEY `fk_facilities_building` (`building_id`),
  KEY `fk_facilities_room` (`room_id`),
  KEY `fk_facilities_area` (`campus_area_id`),
  CONSTRAINT `fk_facilities_area` FOREIGN KEY (`campus_area_id`) REFERENCES `campus_areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_facilities_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_facilities_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_facilities_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facilities`
--

LOCK TABLES `facilities` WRITE;
/*!40000 ALTER TABLE `facilities` DISABLE KEYS */;
/*!40000 ALTER TABLE `facilities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `floor_plan_markers`
--

DROP TABLE IF EXISTS `floor_plan_markers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `floor_plan_markers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `floor_plan_id` int unsigned NOT NULL,
  `label` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `marker_shape` enum('circle','pin','custom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'circle',
  `x_percent` decimal(8,4) NOT NULL COMMENT '0–100 of image width; source of truth for all screens',
  `y_percent` decimal(8,4) NOT NULL COMMENT '0–100 of image height',
  `size_percent` decimal(6,3) NOT NULL DEFAULT '4.000' COMMENT 'Diameter relative to image min-side',
  `target_scene_id` int unsigned DEFAULT NULL,
  `target_floor_plan_id` int unsigned DEFAULT NULL,
  `target_room_id` int unsigned DEFAULT NULL,
  `target_building_id` int unsigned DEFAULT NULL,
  `target_facility_id` int unsigned DEFAULT NULL,
  `popup_title` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `popup_html` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_markers_plan` (`floor_plan_id`),
  KEY `fk_markers_institution` (`institution_id`),
  KEY `fk_markers_scene` (`target_scene_id`),
  KEY `fk_markers_room` (`target_room_id`),
  KEY `fk_markers_building` (`target_building_id`),
  KEY `fk_markers_facility` (`target_facility_id`),
  KEY `fk_markers_target_plan` (`target_floor_plan_id`),
  CONSTRAINT `fk_markers_building` FOREIGN KEY (`target_building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_markers_facility` FOREIGN KEY (`target_facility_id`) REFERENCES `facilities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_markers_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_markers_plan` FOREIGN KEY (`floor_plan_id`) REFERENCES `floor_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_markers_room` FOREIGN KEY (`target_room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_markers_scene` FOREIGN KEY (`target_scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_markers_target_plan` FOREIGN KEY (`target_floor_plan_id`) REFERENCES `floor_plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `floor_plan_markers`
--

LOCK TABLES `floor_plan_markers` WRITE;
/*!40000 ALTER TABLE `floor_plan_markers` DISABLE KEYS */;
INSERT INTO `floor_plan_markers` VALUES (1,1,1,'Gymnasium','circle',65.3051,21.0029,4.000,NULL,NULL,NULL,1,NULL,NULL,'This is Gymnasium',0),(2,1,1,'Main Building','circle',32.4806,31.3920,4.000,NULL,NULL,NULL,NULL,NULL,NULL,'This is main building',0),(3,1,1,'Entrance','circle',51.7585,67.9936,4.000,NULL,NULL,NULL,NULL,NULL,NULL,'This is entrance',0);
/*!40000 ALTER TABLE `floor_plan_markers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `floor_plans`
--

DROP TABLE IF EXISTS `floor_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `floor_plans` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_width` int unsigned NOT NULL COMMENT 'Intrinsic px width; lock aspect',
  `original_height` int unsigned NOT NULL COMMENT 'Intrinsic px height; lock aspect',
  `aspect_ratio` decimal(10,6) NOT NULL COMMENT 'width / height',
  `object_fit` enum('contain','cover') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'contain',
  `min_display_width` int unsigned NOT NULL DEFAULT '320',
  `is_campus_landing` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'If landing_mode=floor_plan',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_floor_plans_institution` (`institution_id`),
  CONSTRAINT `fk_floor_plans_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `floor_plans`
--

LOCK TABLES `floor_plans` WRITE;
/*!40000 ALTER TABLE `floor_plans` DISABLE KEYS */;
INSERT INTO `floor_plans` VALUES (1,1,'Campus','assets/floorplans/6b43b2c37796.png',1389,1132,1.227032,'contain',320,1,4,'2026-08-28 20:35:45','2026-08-28 22:42:48');
/*!40000 ALTER TABLE `floor_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institution_files`
--

DROP TABLE IF EXISTS `institution_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `institution_files` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `relative_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Inside folder_path; template files copied on create',
  `file_role` enum('aframe_scene','stylesheet','script','asset','config','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `is_visual_editable` tinyint(1) NOT NULL DEFAULT '1',
  `mime_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inst_file` (`institution_id`,`relative_path`),
  CONSTRAINT `fk_inst_files_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institution_files`
--

LOCK TABLES `institution_files` WRITE;
/*!40000 ALTER TABLE `institution_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `institution_files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institution_themes`
--

DROP TABLE IF EXISTS `institution_themes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `institution_themes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `primary_color` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#1a365d',
  `secondary_color` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#ed8936',
  `accent_color` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#38b2ac',
  `font_family` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `popup_animation` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fade',
  `marker_style` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'circle',
  `infographic_style` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'card',
  `custom_css` mediumtext COLLATE utf8mb4_unicode_ci,
  `theme_json` json DEFAULT NULL COMMENT 'Extra visual tokens edited in the drag-drop environment',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_theme_institution` (`institution_id`),
  CONSTRAINT `fk_theme_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institution_themes`
--

LOCK TABLES `institution_themes` WRITE;
/*!40000 ALTER TABLE `institution_themes` DISABLE KEYS */;
INSERT INTO `institution_themes` VALUES (1,1,'#1a365d','#ed8936','#38b2ac',NULL,'fade','circle','card',NULL,NULL,'2026-08-28 18:29:54');
/*!40000 ALTER TABLE `institution_themes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institutions`
--

DROP TABLE IF EXISTS `institutions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `institutions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'school',
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
  `starting_scene_id` int unsigned DEFAULT NULL,
  `starting_floor_plan_id` int unsigned DEFAULT NULL,
  `require_landscape_mobile` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'AR/360 landing locked landscape fullscreen on mobile',
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int unsigned DEFAULT NULL COMMENT 'owner user',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_institutions_slug` (`slug`),
  KEY `idx_institutions_published` (`is_published`,`is_active`),
  KEY `fk_inst_start_scene` (`starting_scene_id`),
  KEY `fk_inst_start_floor` (`starting_floor_plan_id`),
  CONSTRAINT `fk_inst_start_floor` FOREIGN KEY (`starting_floor_plan_id`) REFERENCES `floor_plans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inst_start_scene` FOREIGN KEY (`starting_scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institutions`
--

LOCK TABLES `institutions` WRITE;
/*!40000 ALTER TABLE `institutions` DISABLE KEYS */;
INSERT INTO `institutions` VALUES (1,'immaculada-concepcion-college','Immaculada Concepcion College','ICC','college',NULL,NULL,NULL,NULL,'Manila',NULL,'Philippines',NULL,NULL,NULL,NULL,NULL,'organizations/immaculada-concepcion-college','floor_plan',NULL,1,1,1,1,1,'2026-08-28 18:15:00','2026-08-28 20:35:53',NULL);
/*!40000 ALTER TABLE `institutions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `media_assets`
--

DROP TABLE IF EXISTS `media_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `media_assets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `uploaded_by` int unsigned DEFAULT NULL,
  `kind` enum('featured','gallery','floor_plan','pano','cubemap_face','ar_target','logo','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `width` int unsigned DEFAULT NULL,
  `height` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_media_institution` (`institution_id`),
  KEY `fk_media_user` (`uploaded_by`),
  CONSTRAINT `fk_media_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_media_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `media_assets`
--

LOCK TABLES `media_assets` WRITE;
/*!40000 ALTER TABLE `media_assets` DISABLE KEYS */;
/*!40000 ALTER TABLE `media_assets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pw_resets_user` (`user_id`),
  KEY `idx_pw_resets_hash` (`token_hash`),
  CONSTRAINT `fk_pw_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'platform.landing.manage','platform','Edit Innovatech product landing'),(2,'platform.settings.manage','platform','System setup and configuration'),(3,'platform.emails.manage','platform','Email / SMTP templates'),(4,'platform.roles.manage','platform','Roles and access'),(5,'platform.logs.view','platform','Audit and access logs'),(6,'platform.institutions.manage','platform','Create institutions and assign admins'),(7,'institution.manage','institution','Edit assigned institution profile and folder'),(8,'institution.theme.manage','institution','Themes, popups, infographics, animations'),(9,'institution.landing.manage','institution','Landing mode 360 vs floor plan, starting point'),(10,'content.tours.manage','content','360 scenes, hotspots, featured images'),(11,'content.locations.manage','content','Buildings, rooms, facilities, areas'),(12,'content.floor_plans.manage','content','Floor plans and percentage markers'),(13,'tools.ai_info.use','tools','AI facility descriptions'),(14,'tools.ai_stitch.use','tools','Cubemap upload and in-app 360 capture stitch'),(15,'ar.manage','ar','AR overlays, geo waypoints, walk detection'),(16,'tour.view','public','View virtual tours');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `platform_settings`
--

DROP TABLE IF EXISTS `platform_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_settings` (
  `id` tinyint unsigned NOT NULL,
  `product_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AI-Assisted AR 360° Virtual Campus Navigation',
  `company_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Innovatech PH',
  `logo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landing_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `contact_email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_host` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_port` smallint unsigned DEFAULT '587',
  `smtp_username` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_password_enc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_from_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_from_email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution_folder_root` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'storage/institutions',
  `template_pack_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'storage/templates/org_pack',
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `platform_settings`
--

LOCK TABLES `platform_settings` WRITE;
/*!40000 ALTER TABLE `platform_settings` DISABLE KEYS */;
INSERT INTO `platform_settings` VALUES (1,'AI-Assisted AR 360° Virtual Campus Navigation','Innovatech PH',NULL,NULL,NULL,NULL,'',587,'','','Innovatech','hello@innovatech.ph','storage/institutions','storage/templates/org_pack',NULL,'2026-08-28 19:55:13');
/*!40000 ALTER TABLE `platform_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `role_id` tinyint unsigned NOT NULL,
  `permission_id` smallint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_rp_perm` (`permission_id`),
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(5,5),(1,6),(5,6),(6,6),(1,7),(2,7),(5,7),(6,7),(1,8),(2,8),(1,9),(2,9),(5,9),(6,9),(1,10),(2,10),(3,10),(5,10),(6,10),(1,11),(2,11),(3,11),(5,11),(6,11),(1,12),(2,12),(3,12),(5,12),(6,12),(1,13),(2,13),(3,13),(5,13),(6,13),(1,14),(2,14),(3,14),(5,14),(6,14),(1,15),(2,15),(1,16),(2,16),(3,16),(4,16),(5,16),(6,16);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'owner','Owner','Platform: product landing, roles, logs, access, emails, system config, institutions'),(2,'admin','Admin','Institution content: 360/AR tours, themes, landing mode, visual editor'),(3,'staff','Staff','Under admin: facilities, floor plans, featured images, AI stitch/capture'),(4,'user','User','View-only campus tours and AR walking'),(5,'system_admin','System Admin','Elsewhere from owner: manage organizations, accounts, folders, platform insights'),(6,'system_staff','System Staff','Under system admin: manage organizations and platform content');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rooms`
--

DROP TABLE IF EXISTS `rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rooms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `building_id` int unsigned DEFAULT NULL,
  `campus_area_id` int unsigned DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `floor_label` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `room_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'classroom, office, lab, restroom, etc.',
  `description` text COLLATE utf8mb4_unicode_ci,
  `ai_description` text COLLATE utf8mb4_unicode_ci,
  `featured_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rooms_institution` (`institution_id`),
  KEY `fk_rooms_building` (`building_id`),
  KEY `fk_rooms_area` (`campus_area_id`),
  CONSTRAINT `fk_rooms_area` FOREIGN KEY (`campus_area_id`) REFERENCES `campus_areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rooms_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rooms_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rooms`
--

LOCK TABLES `rooms` WRITE;
/*!40000 ALTER TABLE `rooms` DISABLE KEYS */;
/*!40000 ALTER TABLE `rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scene_hotspots`
--

DROP TABLE IF EXISTS `scene_hotspots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `scene_hotspots` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `from_scene_id` int unsigned NOT NULL,
  `to_scene_id` int unsigned DEFAULT NULL,
  `hotspot_type` enum('navigation','info','facility','media','ar_label') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'navigation',
  `label` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body_html` text COLLATE utf8mb4_unicode_ci,
  `yaw` decimal(8,3) NOT NULL DEFAULT '0.000',
  `pitch` decimal(8,3) NOT NULL DEFAULT '0.000',
  `icon_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_facility_id` int unsigned DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_hotspots_from` (`from_scene_id`),
  KEY `fk_hotspots_institution` (`institution_id`),
  KEY `fk_hotspots_to` (`to_scene_id`),
  KEY `fk_hotspots_facility` (`target_facility_id`),
  CONSTRAINT `fk_hotspots_facility` FOREIGN KEY (`target_facility_id`) REFERENCES `facilities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hotspots_from` FOREIGN KEY (`from_scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hotspots_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hotspots_to` FOREIGN KEY (`to_scene_id`) REFERENCES `tour_scenes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scene_hotspots`
--

LOCK TABLES `scene_hotspots` WRITE;
/*!40000 ALTER TABLE `scene_hotspots` DISABLE KEYS */;
/*!40000 ALTER TABLE `scene_hotspots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tour_scenes`
--

DROP TABLE IF EXISTS `tour_scenes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tour_scenes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `institution_id` int unsigned NOT NULL,
  `building_id` int unsigned DEFAULT NULL,
  `room_id` int unsigned DEFAULT NULL,
  `campus_area_id` int unsigned DEFAULT NULL,
  `facility_id` int unsigned DEFAULT NULL,
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
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scene_slug` (`institution_id`,`slug`),
  KEY `idx_scenes_institution` (`institution_id`),
  KEY `fk_scenes_building` (`building_id`),
  KEY `fk_scenes_room` (`room_id`),
  KEY `fk_scenes_area` (`campus_area_id`),
  KEY `fk_scenes_facility` (`facility_id`),
  CONSTRAINT `fk_scenes_area` FOREIGN KEY (`campus_area_id`) REFERENCES `campus_areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_scenes_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_scenes_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_scenes_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scenes_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tour_scenes`
--

LOCK TABLES `tour_scenes` WRITE;
/*!40000 ALTER TABLE `tour_scenes` DISABLE KEYS */;
/*!40000 ALTER TABLE `tour_scenes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_page_access`
--

DROP TABLE IF EXISTS `user_page_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_page_access` (
  `user_id` int unsigned NOT NULL,
  `page_key` varchar(64) NOT NULL,
  PRIMARY KEY (`user_id`,`page_key`),
  CONSTRAINT `fk_up_acc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_page_access`
--

LOCK TABLES `user_page_access` WRITE;
/*!40000 ALTER TABLE `user_page_access` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_page_access` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_user` (`user_id`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `role_id` tinyint unsigned NOT NULL,
  `institution_id` int unsigned DEFAULT NULL COMMENT 'NULL for owner; required for admin/staff; optional for user',
  `supervisor_user_id` int unsigned DEFAULT NULL COMMENT 'staff reports to this admin',
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
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_institution` (`institution_id`),
  KEY `fk_users_supervisor` (`supervisor_user_id`),
  CONSTRAINT `fk_users_institution` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `fk_users_supervisor` FOREIGN KEY (`supervisor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,NULL,NULL,'owner@innovatech.ph','owner','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Platform','Owner',NULL,NULL,1,'2026-08-28 17:17:00','2026-08-28 22:47:19',NULL,'2026-08-28 17:17:00','2026-08-28 22:47:19',NULL),(2,5,NULL,NULL,'system.admin@innovatech.ph','system-admin','$2y$10$diTwFzU88w2NVLbFZ/CR..tWIPiA70RNc8IsHbi2XMKaT1WGiKbjy','System','Admin',NULL,NULL,1,NULL,'2026-08-28 20:59:02',NULL,'2026-08-28 18:15:00','2026-08-28 20:59:02',NULL),(3,6,NULL,NULL,'system.staff@innovatech.ph','system-staff','$2y$10$diTwFzU88w2NVLbFZ/CR..tWIPiA70RNc8IsHbi2XMKaT1WGiKbjy','System','Staff',NULL,NULL,1,NULL,'2026-08-28 19:55:17',NULL,'2026-08-28 18:15:00','2026-08-28 19:55:17',NULL),(4,2,1,NULL,'admin@innovatech.ph','maria-reyes','$2y$10$diTwFzU88w2NVLbFZ/CR..tWIPiA70RNc8IsHbi2XMKaT1WGiKbjy','Maria','Reyes',NULL,NULL,1,NULL,'2026-08-28 22:57:11',NULL,'2026-08-28 18:15:00','2026-08-28 22:57:11',NULL),(5,3,1,NULL,'staff@innovatech.ph','juan-dela-cruz','$2y$10$diTwFzU88w2NVLbFZ/CR..tWIPiA70RNc8IsHbi2XMKaT1WGiKbjy','Juan','Dela Cruz',NULL,NULL,1,NULL,'2026-08-28 20:59:03',NULL,'2026-08-28 18:15:00','2026-08-28 20:59:03',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-29  0:04:27
