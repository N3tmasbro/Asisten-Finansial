/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.6.23-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: finance_app
-- ------------------------------------------------------
-- Server version	10.6.23-MariaDB-0ubuntu0.22.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `budgets`
--

DROP TABLE IF EXISTS `budgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `budgets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `amount` bigint(20) NOT NULL,
  `period_type` varchar(255) NOT NULL DEFAULT 'monthly',
  `period_start` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `budgets_user_id_category_id_period_type_unique` (`user_id`,`category_id`,`period_type`),
  KEY `budgets_category_id_foreign` (`category_id`),
  CONSTRAINT `budgets_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budgets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `budgets`
--

LOCK TABLES `budgets` WRITE;
/*!40000 ALTER TABLE `budgets` DISABLE KEYS */;
INSERT INTO `budgets` VALUES (1,9,12,800000,'monthly',NULL,'2026-08-19 08:39:16','2026-08-19 08:39:16'),(2,9,13,200000,'monthly',NULL,'2026-08-19 08:39:16','2026-08-19 08:39:16');
/*!40000 ALTER TABLE `budgets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `categories_user_id_type_index` (`user_id`,`type`),
  CONSTRAINT `categories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (12,NULL,'Makan & Minum','expense','🍔',1,1,'2026-08-19 08:14:09','2026-08-19 08:14:09'),(13,NULL,'Transport','expense','🚗',1,2,'2026-08-19 08:14:09','2026-08-19 08:14:09'),(14,NULL,'Belanja','expense','🛍️',1,3,'2026-08-19 08:14:09','2026-08-19 08:14:09'),(15,NULL,'Tagihan','expense','📱',1,4,'2026-08-19 08:14:09','2026-08-19 08:14:09'),(16,NULL,'Hiburan','expense','🎬',1,5,'2026-08-19 08:14:09','2026-08-19 08:14:09'),(17,NULL,'Kesehatan','expense','🏥',1,6,'2026-08-19 08:14:09','2026-08-19 08:14:09'),(18,NULL,'Pendidikan','expense','📚',1,7,'2026-08-19 08:14:09','2026-08-19 08:14:09'),(19,NULL,'Lainnya','expense','📦',1,99,'2026-08-19 08:14:09','2026-08-19 08:14:09'),(20,NULL,'Gaji','income','💰',1,1,'2026-08-19 08:14:09','2026-08-19 08:14:09'),(21,NULL,'Bonus/THR','income','🎁',1,2,'2026-08-19 08:14:09','2026-08-19 08:14:09'),(22,NULL,'Freelance/Sampingan','income','💻',1,3,'2026-08-19 08:14:09','2026-08-19 08:14:09'),(23,NULL,'Lainnya','income','📦',1,99,'2026-08-19 08:14:09','2026-08-19 08:14:09');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_messages`
--

DROP TABLE IF EXISTS `chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `direction` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `intent` varchar(255) DEFAULT NULL,
  `ai_raw_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ai_raw_response`)),
  `wa_message_id` varchar(255) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_messages_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `chat_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_messages`
--

LOCK TABLES `chat_messages` WRITE;
/*!40000 ALTER TABLE `chat_messages` DISABLE KEYS */;
INSERT INTO `chat_messages` VALUES (1,9,'incoming','Gajian 4jt masukkan ke wallet berupa cash 1jt sisanya di wallet BCA','add_transaction','{\"intent\":\"add_transaction\",\"confidence\":0.95}','ACE7411EC72FE492938230836A4FDCBD',NULL,'2026-08-19 08:10:48','2026-08-19 08:10:49'),(2,9,'incoming','Gajian 4jt','add_transaction','{\"intent\":\"add_transaction\",\"confidence\":1}','AC06F3753CCD6FBFCFFFA4AE496DE723',NULL,'2026-08-19 08:11:13','2026-08-19 08:11:15'),(3,9,'incoming','Gajian 4jt','add_transaction','{\"intent\":\"add_transaction\",\"confidence\":1}','ACF8F814EBD8E9B53E593ECAA355EE33','2026-08-19 08:14:49','2026-08-19 08:14:37','2026-08-19 08:14:49'),(4,9,'outgoing','Oke, sudah aku catat ya! Pemasukan sebesar Rp4.000.000 dari Gajian sudah masuk ke dompet Cash kamu. Mantap! 💰','add_transaction',NULL,NULL,NULL,'2026-08-19 08:14:49','2026-08-19 08:14:49'),(5,9,'incoming','Buat wallet pegangan gw 1jt sementara sisanya di wallet BCA','manage_records','{\"intent\":\"manage_records\",\"confidence\":0.95}','AC5A24622C91ED9EE3727D61AAF5123A','2026-08-19 08:15:31','2026-08-19 08:15:28','2026-08-19 08:15:31'),(6,9,'outgoing','Wallet \"pegangan\" berhasil dibuat sebagai Cash ✅ Saldo awal: Rp0','manage_records',NULL,NULL,NULL,'2026-08-19 08:15:31','2026-08-19 08:15:31'),(7,9,'incoming','Bukan, maksudnya buat agar cash ge 1jt doang sementara tambah wallet BCA 3jt','manage_records','{\"intent\":\"manage_records\",\"confidence\":0.95}','AC82739097ABC5DF2C76FDB1FA511E35','2026-08-19 08:16:26','2026-08-19 08:16:10','2026-08-19 08:16:26'),(8,9,'outgoing','Aku kurang paham perintahnya 🤔 Coba bilang misalnya:\n• \"buat wallet Dana\"\n• \"buat kategori Investasi\"\n• \"buat budget makan 2 juta\"','manage_records',NULL,NULL,NULL,'2026-08-19 08:16:26','2026-08-19 08:16:26'),(9,9,'incoming','Bukan, maksudnya buat agar cash gw 1jt doang sementara tambah wallet BCA 3jt','manage_records','{\"intent\":\"manage_records\",\"confidence\":0.98}','ACD64C6A6820CD937795FAA35BFF2961','2026-08-19 08:22:31','2026-08-19 08:22:19','2026-08-19 08:22:31'),(10,9,'outgoing','✅ \"Cash\": Rp4.000.000 → Rp1.000.000\n✅ Wallet \"BCA\" dibuat baru, saldo Rp3.000.000','manage_records',NULL,NULL,NULL,'2026-08-19 08:22:31','2026-08-19 08:22:31'),(11,9,'incoming','Hapus wallet pegangan','manage_records','{\"intent\":\"manage_records\",\"confidence\":0.95}','AC94BEB5207DFA5FF4A870E1074CB234','2026-08-19 08:23:23','2026-08-19 08:23:01','2026-08-19 08:23:23'),(12,9,'outgoing','Aku kurang paham perintahnya 🤔 Coba bilang misalnya:\n• \"buat wallet Dana\"\n• \"saldo cash 1jt\" atau \"BCA 3jt\"\n• \"cash 1jt, BCA 3jt\"\n• \"buat kategori Investasi\"\n• \"buat budget makan 2 juta\"','manage_records',NULL,NULL,NULL,'2026-08-19 08:23:23','2026-08-19 08:23:23'),(13,9,'incoming','Buat budget utk Konsumsi 800rb\nUtk bensin 200rb','manage_records','{\"intent\":\"manage_records\",\"confidence\":0.98}','ACAC8595897B15D58812D982BC02A6D8','2026-08-19 08:24:53','2026-08-19 08:24:48','2026-08-19 08:24:53'),(14,9,'outgoing','Aku kurang paham perintahnya 🤔 Coba bilang misalnya:\n• \"buat wallet Dana\"\n• \"saldo cash 1jt\" atau \"BCA 3jt\"\n• \"cash 1jt, BCA 3jt\"\n• \"buat kategori Investasi\"\n• \"buat budget makan 2 juta\"','manage_records',NULL,NULL,NULL,'2026-08-19 08:24:53','2026-08-19 08:24:53'),(15,9,'incoming','Isi bensin 100rb','add_transaction','{\"intent\":\"add_transaction\",\"confidence\":1}','ACCC0172EFADBFB32696BCA767FE90B1','2026-08-19 08:25:33','2026-08-19 08:25:22','2026-08-19 08:25:33'),(16,9,'outgoing','Siap, transaksi sudah aku catat ya!\n\nIsi bensin sebesar Rp100.000 masuk ke kategori Transport (Cash). Aman ya! 🚗💨','add_transaction',NULL,NULL,NULL,'2026-08-19 08:25:33','2026-08-19 08:25:33'),(17,9,'incoming','Makan nasgor 30rb\nNgopi 15rb\nEs krim 10rb\nRokok 35rb','add_transaction','{\"intent\":\"add_transaction\",\"confidence\":1}','ACF5C7EFF44F9A5A6F9A5F131B0B7E1D','2026-08-19 08:26:03','2026-08-19 08:25:59','2026-08-19 08:26:03'),(18,9,'outgoing','Siap, sudah aku catat ya transaksi kamu hari ini:\n\n- Makan nasgor: Rp30.000\n- Ngopi: Rp15.000\n- Es krim: Rp10.000\n- Rokok: Rp35.000\n\nSemuanya pakai dompet Cash ya. Mantap, lanjut terus catatannya! 💸✨','add_transaction',NULL,NULL,NULL,'2026-08-19 08:26:03','2026-08-19 08:26:03'),(19,9,'incoming','Buat budget utk Konsumsi 800rb\nUtk bensin 200rb','manage_records','{\"intent\":\"manage_records\",\"confidence\":0.98}','ACF4A648C6D6362DC264A46DDEB0DD0E','2026-08-19 08:39:16','2026-08-19 08:39:12','2026-08-19 08:39:16'),(20,9,'outgoing','✅ Budget Makan & Minum: Rp800.000/bulan\n✅ Budget Transport: Rp200.000/bulan','manage_records',NULL,NULL,NULL,'2026-08-19 08:39:16','2026-08-19 08:39:16');
/*!40000 ALTER TABLE `chat_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `insights_cache`
--

DROP TABLE IF EXISTS `insights_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `insights_cache` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `period` varchar(255) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `insights_cache_user_id_type_period_unique` (`user_id`,`type`,`period`),
  KEY `insights_cache_expires_at_index` (`expires_at`),
  CONSTRAINT `insights_cache_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `insights_cache`
--

LOCK TABLES `insights_cache` WRITE;
/*!40000 ALTER TABLE `insights_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `insights_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2014_10_12_000000_create_users_table',1),(2,'2014_10_12_100000_create_password_reset_tokens_table',1),(3,'2019_08_19_000000_create_failed_jobs_table',1),(4,'2019_12_14_000001_create_personal_access_tokens_table',1),(5,'2026_08_01_000001_add_phone_and_tier_to_users_table',1),(6,'2026_08_01_000002_create_phone_verifications_table',1),(7,'2026_08_01_000003_create_categories_table',1),(8,'2026_08_01_000004_create_wallets_table',1),(9,'2026_08_01_000005_create_chat_messages_table',1),(10,'2026_08_01_000006_create_transactions_table',1),(11,'2026_08_01_000007_create_whatsapp_sessions_table',1),(12,'2026_08_01_000008_create_budgets_table',1),(13,'2026_08_01_000009_create_insights_cache_table',1),(14,'2026_08_01_000010_create_subscriptions_table',1),(15,'2026_08_01_045234_create_jobs_table',1),(16,'2026_08_03_032707_add_wa_lid_to_users_table',1),(17,'2026_08_10_000001_add_soft_deletes_to_transactions_table',1),(18,'2026_08_13_073259_create_recurring_patterns_table',1),(19,'2026_08_19_073635_add_notes_to_transactions_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES (1,'App\\Models\\User',9,'auth-token','123edc387eec6e4ce2d491dbbd83feeeec2fcc7db911b356af6be1cf18a9e71d','[\"*\"]','2026-08-19 08:39:41',NULL,'2026-08-19 08:08:08','2026-08-19 08:39:41');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `phone_verifications`
--

DROP TABLE IF EXISTS `phone_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `phone_verifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `phone_number` varchar(255) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `phone_verifications_user_id_foreign` (`user_id`),
  KEY `phone_verifications_phone_number_otp_code_index` (`phone_number`,`otp_code`),
  CONSTRAINT `phone_verifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `phone_verifications`
--

LOCK TABLES `phone_verifications` WRITE;
/*!40000 ALTER TABLE `phone_verifications` DISABLE KEYS */;
INSERT INTO `phone_verifications` VALUES (1,9,'6285817793293','077137','2026-08-19 08:08:34','2026-08-19 08:18:20','2026-08-19 08:08:20','2026-08-19 08:08:34');
/*!40000 ALTER TABLE `phone_verifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recurring_patterns`
--

DROP TABLE IF EXISTS `recurring_patterns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recurring_patterns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `description_pattern` varchar(255) NOT NULL,
  `amount_avg` int(10) unsigned NOT NULL,
  `amount_tolerance` int(10) unsigned NOT NULL,
  `expected_day_of_month` tinyint(3) unsigned NOT NULL,
  `day_tolerance` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `occurrences_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `last_seen_date` date DEFAULT NULL,
  `next_expected_date` date DEFAULT NULL,
  `reminded_h3` tinyint(1) NOT NULL DEFAULT 0,
  `reminded_h1` tinyint(1) NOT NULL DEFAULT 0,
  `current_cycle_month` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recurring_patterns_category_id_foreign` (`category_id`),
  KEY `recurring_patterns_user_id_is_active_index` (`user_id`,`is_active`),
  KEY `recurring_patterns_next_expected_date_is_active_index` (`next_expected_date`,`is_active`),
  CONSTRAINT `recurring_patterns_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recurring_patterns_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recurring_patterns`
--

LOCK TABLES `recurring_patterns` WRITE;
/*!40000 ALTER TABLE `recurring_patterns` DISABLE KEYS */;
/*!40000 ALTER TABLE `recurring_patterns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `tier` varchar(255) NOT NULL DEFAULT 'free',
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `starts_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ends_at` timestamp NULL DEFAULT NULL,
  `payment_gateway` varchar(255) DEFAULT NULL,
  `payment_gateway_id` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscriptions_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscriptions`
--

LOCK TABLES `subscriptions` WRITE;
/*!40000 ALTER TABLE `subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `wallet_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `chat_message_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `amount` bigint(20) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `raw_input` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `ai_confidence` double(8,2) DEFAULT NULL,
  `is_reviewed` tinyint(1) NOT NULL DEFAULT 1,
  `corrected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transactions_wallet_id_foreign` (`wallet_id`),
  KEY `transactions_category_id_foreign` (`category_id`),
  KEY `transactions_user_id_transaction_date_index` (`user_id`,`transaction_date`),
  KEY `transactions_user_id_category_id_index` (`user_id`,`category_id`),
  KEY `transactions_chat_message_id_index` (`chat_message_id`),
  CONSTRAINT `transactions_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `transactions_chat_message_id_foreign` FOREIGN KEY (`chat_message_id`) REFERENCES `chat_messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` VALUES (82,9,9,20,3,'income',4000000,'Gajian',NULL,'Gajian 4jt','2026-08-19',1.00,1,NULL,'2026-08-19 08:14:47','2026-08-19 08:14:47',NULL),(83,9,9,13,15,'expense',100000,'Isi bensin',NULL,'Isi bensin 100rb','2026-08-19',1.00,1,NULL,'2026-08-19 08:25:28','2026-08-19 08:25:28',NULL),(84,9,9,12,17,'expense',30000,'Makan nasgor',NULL,'Makan nasgor 30rb\nNgopi 15rb\nEs krim 10rb\nRokok 35rb','2026-08-19',1.00,1,NULL,'2026-08-19 08:26:02','2026-08-19 08:26:02',NULL),(85,9,9,12,17,'expense',15000,'Ngopi',NULL,'Makan nasgor 30rb\nNgopi 15rb\nEs krim 10rb\nRokok 35rb','2026-08-19',1.00,1,NULL,'2026-08-19 08:26:02','2026-08-19 08:26:02',NULL),(86,9,9,12,17,'expense',10000,'Es krim',NULL,'Makan nasgor 30rb\nNgopi 15rb\nEs krim 10rb\nRokok 35rb','2026-08-19',1.00,1,NULL,'2026-08-19 08:26:02','2026-08-19 08:26:02',NULL),(87,9,9,19,17,'expense',35000,'Rokok',NULL,'Makan nasgor 30rb\nNgopi 15rb\nEs krim 10rb\nRokok 35rb','2026-08-19',0.90,1,NULL,'2026-08-19 08:26:02','2026-08-19 08:26:02',NULL);
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone_number` varchar(255) DEFAULT NULL,
  `wa_lid` varchar(255) DEFAULT NULL COMMENT 'WhatsApp LID identifier for newer WA protocol',
  `subscription_tier` varchar(255) NOT NULL DEFAULT 'free',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_phone_number_unique` (`phone_number`),
  KEY `users_wa_lid_index` (`wa_lid`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (9,'Ridho','senkusanjo@gmail.com','6285817793293','46132327653514','free',NULL,'$2y$12$E4/lbZnG4KAlY2sxJ7Otmu8G3efbz1bW9AoXhCdbsgXf5w76azjWa',NULL,'2026-08-19 08:08:08','2026-08-19 08:10:48');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wallets`
--

DROP TABLE IF EXISTS `wallets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `balance` bigint(20) NOT NULL DEFAULT 0,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wallets_user_id_index` (`user_id`),
  CONSTRAINT `wallets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wallets`
--

LOCK TABLES `wallets` WRITE;
/*!40000 ALTER TABLE `wallets` DISABLE KEYS */;
INSERT INTO `wallets` VALUES (9,9,'Cash','cash',810000,1,'2026-08-19 08:08:08','2026-08-19 08:26:02'),(10,9,'pegangan','cash',0,0,'2026-08-19 08:15:31','2026-08-19 08:15:31'),(11,9,'BCA','bank',3000000,0,'2026-08-19 08:22:31','2026-08-19 08:22:31');
/*!40000 ALTER TABLE `wallets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_sessions`
--

DROP TABLE IF EXISTS `whatsapp_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `phone_number` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'disconnected',
  `provider` varchar(255) NOT NULL DEFAULT 'baileys',
  `session_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`session_data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `whatsapp_sessions_user_id_index` (`user_id`),
  KEY `whatsapp_sessions_phone_number_index` (`phone_number`),
  CONSTRAINT `whatsapp_sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_sessions`
--

LOCK TABLES `whatsapp_sessions` WRITE;
/*!40000 ALTER TABLE `whatsapp_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_sessions` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-19  8:41:05
