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
  `preferred_file_import_profile_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_entities_config_type_name_user_id_unique` (`config_type`,`name`,`user_id`),
  KEY `account_entities_config_type_config_id_index` (`config_type`,`config_id`),
  KEY `account_entities_user_type_active_index` (`user_id`,`config_type`,`active`),
  KEY `account_entities_preferred_file_import_profile_id_index` (`preferred_file_import_profile_id`),
  CONSTRAINT `account_entities_preferred_file_import_profile_id_foreign` FOREIGN KEY (`preferred_file_import_profile_id`) REFERENCES `file_import_profiles` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `account_entities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `account_entity_category_preference`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_entity_category_preference` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_entity_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  `preferred` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_entity_category_preference_unique` (`account_entity_id`,`category_id`),
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
  KEY `account_monthly_summaries_account_entity_id_foreign` (`account_entity_id`),
  KEY `account_monthly_summaries_user_type_dtype_account_date_index` (`user_id`,`transaction_type`,`data_type`,`account_entity_id`,`date`),
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
DROP TABLE IF EXISTS `ai_document_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_document_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ai_document_id` bigint unsigned NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_document_files_ai_document_id_index` (`ai_document_id`),
  CONSTRAINT `ai_document_files_ai_document_id_foreign` FOREIGN KEY (`ai_document_id`) REFERENCES `ai_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ready_for_processing',
  `source_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `processed_transaction_data` json DEFAULT NULL,
  `ai_chat_history` json DEFAULT NULL,
  `google_drive_file_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `received_mail_id` bigint unsigned DEFAULT NULL,
  `custom_prompt` text COLLATE utf8mb4_unicode_ci,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_documents_google_drive_file_id_unique` (`google_drive_file_id`),
  KEY `ai_documents_received_mail_id_foreign` (`received_mail_id`),
  KEY `ai_documents_user_id_index` (`user_id`),
  KEY `ai_documents_status_index` (`status`),
  KEY `ai_documents_source_type_index` (`source_type`),
  CONSTRAINT `ai_documents_received_mail_id_foreign` FOREIGN KEY (`received_mail_id`) REFERENCES `received_mails` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_documents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_provider_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_provider_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `provider` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_key` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `vision_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_provider_configs_user_id_foreign` (`user_id`),
  CONSTRAINT `ai_provider_configs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_user_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_user_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `ai_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `prompt_chat_history_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `ocr_language` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'eng',
  `generic_document_language` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_max_width_vision` smallint unsigned DEFAULT NULL,
  `image_max_height_vision` smallint unsigned DEFAULT NULL,
  `image_quality_vision` tinyint unsigned NOT NULL DEFAULT '85',
  `image_max_width_tesseract` smallint unsigned DEFAULT NULL,
  `image_max_height_tesseract` smallint unsigned DEFAULT NULL,
  `asset_similarity_threshold` decimal(4,3) NOT NULL DEFAULT '0.500',
  `asset_max_suggestions` tinyint unsigned NOT NULL DEFAULT '10',
  `match_auto_accept_threshold` decimal(4,3) NOT NULL DEFAULT '0.950',
  `duplicate_date_window_days` tinyint unsigned NOT NULL DEFAULT '3',
  `duplicate_amount_tolerance_percent` decimal(5,2) NOT NULL DEFAULT '10.00',
  `duplicate_similarity_threshold` decimal(4,3) NOT NULL DEFAULT '0.500',
  `category_matching_mode` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'best_match',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_user_settings_user_id_unique` (`user_id`),
  CONSTRAINT `ai_user_settings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
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
DROP TABLE IF EXISTS `category_learning`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `category_learning` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `item_description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  `usage_count` int unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_learning_user_id_item_description_unique` (`user_id`,`item_description`),
  KEY `category_learning_category_id_index` (`category_id`),
  KEY `category_learning_active_index` (`active`),
  CONSTRAINT `category_learning_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `category_learning_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
  `generic_decimal_precision` int unsigned DEFAULT NULL,
  `detailed_decimal_precision` int unsigned DEFAULT NULL,
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
  `rate` decimal(20,10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `currency_rates_date_from_id_to_id_unique` (`date`,`from_id`,`to_id`),
  KEY `currency_rates_to_id_foreign` (`to_id`),
  KEY `currency_rates_from_to_date_index` (`from_id`,`to_id`,`date`),
  CONSTRAINT `currency_rates_from_id_foreign` FOREIGN KEY (`from_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `currency_rates_to_id_foreign` FOREIGN KEY (`to_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `file_import_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `file_import_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `file_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'csv',
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `delimiter` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_header_row` tinyint(1) NOT NULL DEFAULT '1',
  `date_format` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decimal_separator` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thousand_separator` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sign_handling` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mapping_json` json DEFAULT NULL,
  `options_json` json DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `file_import_profiles_key_unique` (`key`),
  KEY `file_import_profiles_user_id_type_index` (`user_id`,`type`),
  KEY `file_import_profiles_active_index` (`active`),
  KEY `file_import_profiles_type_file_type_index` (`type`,`file_type`),
  CONSTRAINT `file_import_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
DROP TABLE IF EXISTS `google_drive_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `google_drive_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `service_account_email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_account_json` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `folder_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `folder_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `post_import_actions` json DEFAULT NULL,
  `processed_folder_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processed_folder_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sync_interval_minutes` smallint unsigned NOT NULL DEFAULT '15',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `last_sync_at` timestamp NULL DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `error_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `google_drive_configs_user_id_index` (`user_id`),
  KEY `google_drive_configs_enabled_index` (`enabled`),
  KEY `google_drive_configs_processed_folder_id_index` (`processed_folder_id`),
  CONSTRAINT `google_drive_configs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
  `price` decimal(20,10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `investment_prices_date_investment_id_unique` (`date`,`investment_id`),
  KEY `investment_prices_investment_date_index` (`investment_id`,`date`),
  CONSTRAINT `investment_prices_investment_id_foreign` FOREIGN KEY (`investment_id`) REFERENCES `investments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `investment_provider_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `investment_provider_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `provider_key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `credentials` text COLLATE utf8mb4_unicode_ci,
  `options` json DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `rate_limit_overrides` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `investment_provider_configs_user_id_provider_key_unique` (`user_id`,`provider_key`),
  CONSTRAINT `investment_provider_configs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
  `provider_settings` json DEFAULT NULL,
  `last_price_fetch_attempted_at` timestamp NULL DEFAULT NULL,
  `last_price_fetch_succeeded_at` timestamp NULL DEFAULT NULL,
  `last_price_fetch_error_at` timestamp NULL DEFAULT NULL,
  `last_price_fetch_error_message` text COLLATE utf8mb4_unicode_ci,
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
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `received_mails_user_id_foreign` (`user_id`),
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
  `price` decimal(20,10) unsigned DEFAULT NULL,
  `quantity` decimal(14,4) unsigned DEFAULT NULL,
  `commission` decimal(14,4) unsigned DEFAULT NULL,
  `tax` decimal(14,4) unsigned DEFAULT NULL,
  `dividend` decimal(12,4) unsigned DEFAULT NULL,
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
  `amount_from` decimal(12,4) unsigned NOT NULL,
  `amount_to` decimal(12,4) unsigned NOT NULL,
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
  `amount` decimal(12,4) unsigned NOT NULL,
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
  UNIQUE KEY `transaction_items_tags_tag_id_transaction_item_id_unique` (`tag_id`,`transaction_item_id`),
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
  `by_day` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `by_month` tinyint unsigned DEFAULT NULL,
  `count` int DEFAULT NULL,
  `inflation` double DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction_schedules_transaction_id_foreign` (`transaction_id`),
  KEY `transaction_schedules_active_next_date_index` (`active`,`next_date`),
  CONSTRAINT `transaction_schedules_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ai_document_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `date` date DEFAULT NULL,
  `transaction_type` enum('withdrawal','deposit','transfer','buy','sell','add_shares','remove_shares','dividend','interest_yield') COLLATE utf8mb4_unicode_ci NOT NULL,
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
  KEY `transactions_config_type_config_id_index` (`config_type`,`config_id`),
  KEY `transactions_currency_id_foreign` (`currency_id`),
  KEY `transactions_ai_document_id_foreign` (`ai_document_id`),
  KEY `transactions_user_type_flags_date_index` (`user_id`,`config_type`,`schedule`,`budget`,`date`),
  CONSTRAINT `transactions_ai_document_id_foreign` FOREIGN KEY (`ai_document_id`) REFERENCES `ai_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `two_factor_authentications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `two_factor_authentications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `authenticatable_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `authenticatable_id` bigint unsigned NOT NULL,
  `shared_secret` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled_at` timestamp NULL DEFAULT NULL,
  `label` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `digits` tinyint unsigned NOT NULL DEFAULT '6',
  `seconds` tinyint unsigned NOT NULL DEFAULT '30',
  `window` tinyint unsigned NOT NULL DEFAULT '0',
  `algorithm` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sha1',
  `recovery_codes` text COLLATE utf8mb4_unicode_ci,
  `recovery_codes_generated_at` timestamp NULL DEFAULT NULL,
  `safe_devices` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `two_factor_authenticatable_index` (`authenticatable_type`,`authenticatable_id`)
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
  `auto_merge_standard_transaction_items` tinyint(1) NOT NULL DEFAULT '0',
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
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2025_10_03_000000_rename_password_resets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_03_22_185317_add_unsigned_to_decimal_columns',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_01_31_000001_add_transaction_type_enum_column_to_transactions_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_01_31_000002_add_unsigned_to_decimal_columns',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_01_31_000003_drop_transaction_types_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_01_31_180342_create_ai_documents_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_01_31_180343_add_ai_document_id_to_transactions_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_01_31_180343_create_ai_document_files_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_01_31_180343_create_ai_provider_configs_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_01_31_180343_create_category_learning_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_01_31_180343_update_received_mails_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_02_04_092750_create_google_drive_configs_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_03_08_213140_add_uuid_to_failed_jobs_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_03_10_204637_create_ai_user_settings_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_03_21_081423_add_auto_merge_to_users_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2026_03_24_193858_add_description_to_categories_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_03_26_000001_add_performance_indexes',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_03_28_202100_create_csv_import_profiles_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_04_02_120000_add_provider_settings_to_investments_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2026_04_02_120100_create_investment_provider_configs_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2026_04_02_130000_add_price_fetch_state_to_investments_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2026_04_02_173148_backfill_web_scraping_provider_settings_on_investments_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2026_04_02_173150_drop_legacy_scrape_columns_from_investments_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2026_04_13_150955_add_preferred_csv_import_profile_id_to_account_entities_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2026_04_14_000001_add_decimal_precision_to_currencies_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2026_04_24_162843_add_folder_name_and_disposition_to_google_drive_configs',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2026_05_25_000001_add_active_flag_to_category_learning_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2026_07_07_000001_drop_redundant_config_id_config_type_index_from_transactions_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2026_07_23_000001_create_two_factor_authentications_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2026_08_04_000001_add_by_day_to_transaction_schedules_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2026_08_08_000001_widen_transaction_details_investment_price_scale',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2026_08_20_000001_add_pk_and_unique_key_to_account_entity_category_preference_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2026_08_20_000002_add_unique_key_to_transaction_items_tags_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2026_08_20_000003_drop_redundant_fk_indexes_subsumed_by_composite_indexes',4);
