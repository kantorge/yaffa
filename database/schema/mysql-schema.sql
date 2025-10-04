/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `account_entities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_entities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `alias` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `config_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_entities_config_type_name_user_id_unique` (`config_type`,`name`,`user_id`),
  KEY `account_entities_user_id_foreign` (`user_id`),
  KEY `account_entities_config_type_config_id_index` (`config_type`,`config_id`),
  CONSTRAINT `account_entities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `account_entity_category_preference`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_entity_category_preference` (
  `account_entity_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  `preferred` tinyint(1) NOT NULL,
  KEY `account_entity_category_preference_account_entity_id_foreign` (`account_entity_id`),
  KEY `account_entity_category_preference_category_id_foreign` (`category_id`),
  CONSTRAINT `account_entity_category_preference_account_entity_id_foreign` FOREIGN KEY (`account_entity_id`) REFERENCES `account_entities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_entity_category_preference_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `account_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_groups_name_user_id_unique` (`name`,`user_id`),
  KEY `account_groups_user_id_foreign` (`user_id`),
  CONSTRAINT `account_groups_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `account_monthly_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_monthly_summaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `account_entity_id` bigint unsigned DEFAULT NULL,
  `transaction_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(14,4) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `account_monthly_summaries_user_id_foreign` (`user_id`),
  KEY `account_monthly_summaries_account_entity_id_foreign` (`account_entity_id`),
  CONSTRAINT `account_monthly_summaries_account_entity_id_foreign` FOREIGN KEY (`account_entity_id`) REFERENCES `account_entities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_monthly_summaries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `opening_balance` decimal(30,10) NOT NULL DEFAULT '0.0000000000',
  `account_group_id` bigint unsigned NOT NULL,
  `currency_id` bigint unsigned NOT NULL,
  `default_date_range` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `accounts_account_group_id_foreign` (`account_group_id`),
  KEY `accounts_currency_id_foreign` (`currency_id`),
  CONSTRAINT `accounts_account_group_id_foreign` FOREIGN KEY (`account_group_id`) REFERENCES `account_groups` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `accounts_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `parent_id` bigint unsigned DEFAULT NULL,
  `default_aggregation` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'month',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `categories_user_id_foreign` (`user_id`),
  KEY `categories_parent_id_foreign` (`parent_id`),
  CONSTRAINT `categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `categories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `iso_code` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `base` tinyint(1) DEFAULT NULL,
  `auto_update` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `currencies_name_user_id_unique` (`name`,`user_id`),
  UNIQUE KEY `currencies_iso_code_user_id_unique` (`iso_code`,`user_id`),
  UNIQUE KEY `currencies_base_user_id_unique` (`base`,`user_id`),
  KEY `currencies_user_id_foreign` (`user_id`),
  CONSTRAINT `currencies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currency_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currency_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `from_id` bigint unsigned NOT NULL,
  `to_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `rate` decimal(20,10) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `currency_rates_date_from_id_to_id_unique` (`date`,`from_id`,`to_id`),
  KEY `currency_rates_from_id_foreign` (`from_id`),
  KEY `currency_rates_to_id_foreign` (`to_id`),
  CONSTRAINT `currency_rates_from_id_foreign` FOREIGN KEY (`from_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `currency_rates_to_id_foreign` FOREIGN KEY (`to_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `flaggable_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `flaggable_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flags_flaggable_type_flaggable_id_index` (`flaggable_type`,`flaggable_id`),
  KEY `flags_name_flaggable_id_flaggable_type_index` (`name`,`flaggable_id`,`flaggable_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `investment_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `investment_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `investment_groups_name_user_id_unique` (`name`,`user_id`),
  KEY `investment_groups_user_id_foreign` (`user_id`),
  CONSTRAINT `investment_groups_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `investment_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `investment_prices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `investment_id` bigint unsigned NOT NULL,
  `price` decimal(20,10) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `investment_prices_date_investment_id_unique` (`date`,`investment_id`),
  KEY `investment_prices_investment_id_foreign` (`investment_id`),
  CONSTRAINT `investment_prices_investment_id_foreign` FOREIGN KEY (`investment_id`) REFERENCES `investments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `investments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `investments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `isin` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `auto_update` tinyint(1) NOT NULL DEFAULT '0',
  `investment_price_provider` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `investment_group_id` bigint unsigned NOT NULL,
  `currency_id` bigint unsigned NOT NULL,
  `scrape_url` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scrape_selector` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `investments_name_user_id_unique` (`name`,`user_id`),
  UNIQUE KEY `investments_symbol_user_id_unique` (`symbol`,`user_id`),
  UNIQUE KEY `investments_isin_user_id_unique` (`isin`,`user_id`),
  KEY `investments_user_id_foreign` (`user_id`),
  KEY `investments_investment_group_id_foreign` (`investment_group_id`),
  KEY `investments_currency_id_foreign` (`currency_id`),
  CONSTRAINT `investments_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `investments_investment_group_id_foreign` FOREIGN KEY (`investment_group_id`) REFERENCES `investment_groups` (`id`),
  CONSTRAINT `investments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint unsigned DEFAULT NULL,
  `category_suggestion_dismissed` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payees_category_id_foreign` (`category_id`),
  CONSTRAINT `payees_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `received_mails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `received_mails` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `message_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `html` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_data` json DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `processed` tinyint(1) NOT NULL DEFAULT '0',
  `handled` tinyint(1) NOT NULL DEFAULT '0',
  `transaction_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `received_mails_user_id_foreign` (`user_id`),
  KEY `received_mails_transaction_id_foreign` (`transaction_id`),
  CONSTRAINT `received_mails_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `received_mails_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tags_name_user_id_unique` (`name`,`user_id`),
  KEY `tags_user_id_foreign` (`user_id`),
  CONSTRAINT `tags_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_entries` (
  `sequence` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `family_hash` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `should_display_on_index` tinyint(1) NOT NULL DEFAULT '1',
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`sequence`),
  UNIQUE KEY `telescope_entries_uuid_unique` (`uuid`),
  KEY `telescope_entries_batch_id_index` (`batch_id`),
  KEY `telescope_entries_family_hash_index` (`family_hash`),
  KEY `telescope_entries_created_at_index` (`created_at`),
  KEY `telescope_entries_type_should_display_on_index_index` (`type`,`should_display_on_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_entries_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_entries_tags` (
  `entry_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tag` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`entry_uuid`,`tag`),
  KEY `telescope_entries_tags_tag_index` (`tag`),
  CONSTRAINT `telescope_entries_tags_entry_uuid_foreign` FOREIGN KEY (`entry_uuid`) REFERENCES `telescope_entries` (`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_monitoring`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_monitoring` (
  `tag` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transaction_details_investment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_details_investment` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned NOT NULL,
  `investment_id` bigint unsigned NOT NULL,
  `price` decimal(10,4) DEFAULT NULL,
  `quantity` decimal(14,4) DEFAULT NULL,
  `commission` decimal(14,4) DEFAULT NULL,
  `tax` decimal(14,4) DEFAULT NULL,
  `dividend` decimal(12,4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction_details_investment_account_id_foreign` (`account_id`),
  KEY `transaction_details_investment_investment_id_foreign` (`investment_id`),
  CONSTRAINT `transaction_details_investment_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `account_entities` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `transaction_details_investment_investment_id_foreign` FOREIGN KEY (`investment_id`) REFERENCES `investments` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transaction_details_standard`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_details_standard` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_from_id` bigint unsigned DEFAULT NULL,
  `account_to_id` bigint unsigned DEFAULT NULL,
  `amount_from` decimal(12,4) NOT NULL,
  `amount_to` decimal(12,4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction_details_standard_account_to_id_foreign` (`account_to_id`),
  KEY `transaction_details_standard_account_from_id_account_to_id_index` (`account_from_id`,`account_to_id`),
  CONSTRAINT `transaction_details_standard_account_from_id_foreign` FOREIGN KEY (`account_from_id`) REFERENCES `account_entities` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `transaction_details_standard_account_to_id_foreign` FOREIGN KEY (`account_to_id`) REFERENCES `account_entities` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transaction_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  `amount` decimal(12,4) NOT NULL,
  `comment` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction_items_transaction_id_foreign` (`transaction_id`),
  KEY `transaction_items_category_id_foreign` (`category_id`),
  CONSTRAINT `transaction_items_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `transaction_items_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transaction_items_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_items_tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transaction_item_id` bigint unsigned NOT NULL,
  `tag_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction_items_tags_transaction_item_id_foreign` (`transaction_item_id`),
  KEY `transaction_items_tags_tag_id_foreign` (`tag_id`),
  CONSTRAINT `transaction_items_tags_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transaction_items_tags_transaction_item_id_foreign` FOREIGN KEY (`transaction_item_id`) REFERENCES `transaction_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transaction_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` bigint unsigned NOT NULL,
  `automatic_recording` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `start_date` date NOT NULL,
  `next_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `frequency` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `interval` int NOT NULL DEFAULT '1',
  `count` int DEFAULT NULL,
  `inflation` double(8,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction_schedules_transaction_id_foreign` (`transaction_id`),
  CONSTRAINT `transaction_schedules_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transaction_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_multiplier` smallint DEFAULT NULL,
  `quantity_multiplier` smallint DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `date` date DEFAULT NULL,
  `transaction_type_id` bigint unsigned NOT NULL,
  `reconciled` tinyint(1) NOT NULL DEFAULT '0',
  `schedule` tinyint(1) NOT NULL DEFAULT '0',
  `budget` tinyint(1) NOT NULL DEFAULT '0',
  `comment` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `config_type` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_id` bigint unsigned NOT NULL,
  `currency_id` bigint unsigned DEFAULT NULL,
  `cashflow_value` decimal(12,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transactions_user_id_foreign` (`user_id`),
  KEY `transactions_transaction_type_id_foreign` (`transaction_type_id`),
  KEY `transactions_config_type_config_id_index` (`config_type`,`config_id`),
  KEY `transactions_config_id_config_type_index` (`config_id`,`config_type`),
  KEY `transactions_currency_id_foreign` (`currency_id`),
  CONSTRAINT `transactions_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_transaction_type_id_foreign` FOREIGN KEY (`transaction_type_id`) REFERENCES `transaction_types` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `locale` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en-EN',
  `start_date` date NOT NULL DEFAULT ((now() - interval 30 day)),
  `end_date` date NOT NULL DEFAULT ((now() + interval 50 year)),
  `account_details_date_range` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2014_10_12_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2014_10_12_100000_create_password_resets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2018_08_08_100000_create_telescope_entries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2019_08_19_000000_create_failed_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2019_12_14_000001_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2020_04_12_165028_create_accountrgroups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2020_04_13_185943_create_currencies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2020_04_25_120052_create_account_entities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2020_04_25_142455_create_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2020_08_15_193008_create_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2020_08_15_193813_create_payees_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2020_08_24_192208_create_currency_rates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2020_08_24_192704_create_investment_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2020_08_25_110622_create_tags_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2020_08_25_114547_create_investments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2020_09_07_190341_create_transaction_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2020_09_07_191813_create_investment_prices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2020_09_07_192329_create_transaction_headers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2020_09_07_194118_create_transaction_details_standard_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2020_10_29_220149_create_transaction_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2020_10_30_195419_create_transaction_items_tags_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2020_11_01_124328_create_transaction_schedules_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2020_11_16_194346_create_transaction_details_investment_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2022_05_15_203856_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2022_06_24_213903_create_account_entity_category_preference_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2023_01_29_135221_create_flags_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2023_05_29_120656_create_mailbox_inbound_emails_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2024_01_12_192725_laravel_sanctum_expiry',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2024_01_14_105421_drop_custom_decimal_digit_for_currencies',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2024_01_14_155308_simplify_transaction_config_values',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2024_01_14_165211_add_summary_fields_to_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2024_01_16_172916_create_account_monthly_summaries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2024_01_25_184213_create_job_batches_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2024_02_10_110636_add_active_column_to_transaction_schedules_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2024_03_04_193208_simplify_transaction_type_operators',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2024_10_05_140223_add_default_aggregation_colum_to_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2024_11_24_132331_add_scrape_settings_to_investments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2024_11_24_195005_fix_unique_settings_of_investments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2024_12_26_090003_fix_currency_rate_decimals',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2025_02_08_124255_fix_account_opening_balance_decimals',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2025_03_10_205956_fix_investment_price_decimals',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2025_09_27_122558_add_default_account_date_column',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2025_10_03_000000_rename_password_resets_table',2);
