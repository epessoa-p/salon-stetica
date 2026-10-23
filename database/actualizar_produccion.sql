-- =====================================================================
--  Glamora · Actualización de producción (estructura completa del proyecto)
--
--  SEGURO DE REEJECUTAR: cada tabla, columna, cambio de tipo, clave
--  foránea, permiso y registro de migración comprueba antes si ya existe
--  o si ya tiene la forma correcta. Ejecutarlo de nuevo no duplica ni
--  rompe nada. Probado dos veces sobre una base con el esquema antiguo.
--
--  Lleva la base, esté en el punto que esté, al estado actual del código:
--  todas las migraciones desde 2026_07_24 hasta 2026_10_12 (planes y
--  suscripciones, clientes, servicios, agenda, inventario, compras,
--  ingresos/gastos, comisiones, métodos de pago y preferencias).
--  Incluye el contenido de actualizar_modulo_comisiones.sql: no hace
--  falta ejecutar ese archivo aparte.
--
--  Compatible con MySQL 5.7+ y MariaDB 10.2+ (sin DELIMITER).
--  Ejecutar completo: phpMyAdmin → pestaña SQL → pegar y ejecutar.
--  Recomendado: sacar un respaldo (Exportar) antes de ejecutarlo.
--
--  Generado automáticamente desde el esquema de desarrollo.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Tablas nuevas del proyecto
-- ---------------------------------------------------------------------

-- plans
CREATE TABLE IF NOT EXISTS `plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'BOB',
  `billing_days` int(10) unsigned NOT NULL DEFAULT 30,
  `trial_days` int(10) unsigned NOT NULL DEFAULT 14,
  `max_branches` int(10) unsigned DEFAULT NULL,
  `max_personal` int(10) unsigned DEFAULT NULL,
  `max_cajas` int(10) unsigned DEFAULT NULL,
  `max_users` int(10) unsigned DEFAULT NULL,
  `features` JSON DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plans_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- subscriptions
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `plan_id` bigint(20) unsigned NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'trial',
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_users_override` int(10) unsigned DEFAULT NULL,
  `max_branches_override` int(10) unsigned DEFAULT NULL,
  `max_cajas_override` int(10) unsigned DEFAULT NULL,
  `max_personal_override` int(10) unsigned DEFAULT NULL,
  `max_products_override` int(10) unsigned DEFAULT NULL,
  `features_override` JSON DEFAULT NULL,
  `grace_days` smallint(5) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscriptions_company_id_foreign` (`company_id`),
  KEY `subscriptions_plan_id_foreign` (`plan_id`),
  KEY `subscriptions_created_by_foreign` (`created_by`),
  CONSTRAINT `subscriptions_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subscriptions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `subscriptions_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- subscription_payments
CREATE TABLE IF NOT EXISTS `subscription_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `subscription_id` bigint(20) unsigned NOT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'BOB',
  `method` varchar(255) NOT NULL DEFAULT 'transferencia',
  `reference` varchar(255) DEFAULT NULL,
  `proof` varchar(255) DEFAULT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `confirmed_by` bigint(20) unsigned DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscription_payments_subscription_id_foreign` (`subscription_id`),
  KEY `subscription_payments_company_id_foreign` (`company_id`),
  KEY `subscription_payments_confirmed_by_foreign` (`confirmed_by`),
  KEY `subscription_payments_created_by_foreign` (`created_by`),
  CONSTRAINT `subscription_payments_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subscription_payments_confirmed_by_foreign` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `subscription_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `subscription_payments_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- clients
CREATE TABLE IF NOT EXISTS `clients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `id_number` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `gender` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clients_company_id_foreign` (`company_id`),
  KEY `clients_created_by_foreign` (`created_by`),
  CONSTRAINT `clients_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clients_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- service_categories
CREATE TABLE IF NOT EXISTS `service_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `service_categories_company_id_foreign` (`company_id`),
  KEY `service_categories_created_by_foreign` (`created_by`),
  CONSTRAINT `service_categories_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `service_categories_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- services
CREATE TABLE IF NOT EXISTS `services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `service_category_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duration_minutes` int(10) unsigned DEFAULT NULL,
  `commission_percent` decimal(5,2) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `services_company_id_foreign` (`company_id`),
  KEY `services_service_category_id_foreign` (`service_category_id`),
  KEY `services_created_by_foreign` (`created_by`),
  CONSTRAINT `services_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `services_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `services_service_category_id_foreign` FOREIGN KEY (`service_category_id`) REFERENCES `service_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- appointments
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `personal_id` bigint(20) unsigned DEFAULT NULL,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pendiente',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appointments_branch_id_foreign` (`branch_id`),
  KEY `appointments_client_id_foreign` (`client_id`),
  KEY `appointments_personal_id_foreign` (`personal_id`),
  KEY `appointments_service_id_foreign` (`service_id`),
  KEY `appointments_created_by_foreign` (`created_by`),
  KEY `appointments_company_id_starts_at_index` (`company_id`,`starts_at`),
  CONSTRAINT `appointments_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `appointments_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `appointments_personal_id_foreign` FOREIGN KEY (`personal_id`) REFERENCES `personals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `appointments_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- product_categories
CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_categories_company_id_foreign` (`company_id`),
  KEY `product_categories_created_by_foreign` (`created_by`),
  CONSTRAINT `product_categories_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_categories_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- brands
CREATE TABLE IF NOT EXISTS `brands` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `brands_company_id_foreign` (`company_id`),
  KEY `brands_created_by_foreign` (`created_by`),
  CONSTRAINT `brands_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `brands_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- suppliers
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_name` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `tax_id` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `suppliers_company_id_foreign` (`company_id`),
  KEY `suppliers_created_by_foreign` (`created_by`),
  CONSTRAINT `suppliers_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `suppliers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- payment_methods
CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` varchar(80) NOT NULL,
  `code` varchar(30) NOT NULL,
  `icon` varchar(40) DEFAULT NULL,
  `affects_cash` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_methods_company_id_code_unique` (`company_id`,`code`),
  KEY `payment_methods_company_id_active_index` (`company_id`,`active`),
  CONSTRAINT `payment_methods_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- company_settings
CREATE TABLE IF NOT EXISTS `company_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'BOB',
  `currency_symbol` varchar(10) NOT NULL DEFAULT 'Bs',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_included` tinyint(1) NOT NULL DEFAULT 1,
  `tax_label` varchar(30) NOT NULL DEFAULT 'IVA',
  `invoice_business_name` varchar(255) DEFAULT NULL,
  `invoice_nit` varchar(30) DEFAULT NULL,
  `invoice_address` varchar(255) DEFAULT NULL,
  `invoice_phone` varchar(40) DEFAULT NULL,
  `invoice_footer` text DEFAULT NULL,
  `sale_prefix` varchar(10) NOT NULL DEFAULT 'V-',
  `sale_padding` tinyint(3) unsigned NOT NULL DEFAULT 6,
  `appointment_slot_minutes` smallint(5) unsigned NOT NULL DEFAULT 30,
  `require_cash_session` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_settings_company_id_unique` (`company_id`),
  CONSTRAINT `company_settings_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- commission_rules
CREATE TABLE IF NOT EXISTS `commission_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `personal_id` bigint(20) unsigned DEFAULT NULL COMMENT 'NULL = todo el equipo',
  `applies_to` varchar(20) NOT NULL COMMENT 'services | products | category | service',
  `service_id` bigint(20) unsigned DEFAULT NULL COMMENT 'solo si applies_to = service',
  `service_category_id` bigint(20) unsigned DEFAULT NULL COMMENT 'solo si applies_to = category',
  `type` varchar(20) NOT NULL DEFAULT 'percent' COMMENT 'percent | fixed',
  `value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commission_rules_company_active_index` (`company_id`,`active`),
  KEY `commission_rules_personal_id_foreign` (`personal_id`),
  KEY `commission_rules_service_id_foreign` (`service_id`),
  KEY `commission_rules_service_category_id_foreign` (`service_category_id`),
  CONSTRAINT `commission_rules_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commission_rules_personal_id_foreign` FOREIGN KEY (`personal_id`) REFERENCES `personals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commission_rules_service_category_id_foreign` FOREIGN KEY (`service_category_id`) REFERENCES `service_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commission_rules_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- commission_settlements
CREATE TABLE IF NOT EXISTS `commission_settlements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `personal_id` bigint(20) unsigned NOT NULL,
  `number` varchar(20) NOT NULL COMMENT 'LIQ-0001, único por empresa',
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `commissions_count` int(10) unsigned NOT NULL DEFAULT 0,
  `commissions_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `tips_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `include_tips` tinyint(1) NOT NULL DEFAULT 0,
  `paid_at` datetime NOT NULL,
  `paid_by` bigint(20) unsigned DEFAULT NULL,
  `cash_session_id` bigint(20) unsigned DEFAULT NULL,
  `cash_movement_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commission_settlements_company_number_unique` (`company_id`,`number`),
  KEY `commission_settlements_company_personal_index` (`company_id`,`personal_id`,`paid_at`),
  KEY `commission_settlements_personal_id_foreign` (`personal_id`),
  KEY `commission_settlements_paid_by_foreign` (`paid_by`),
  KEY `commission_settlements_cash_session_id_foreign` (`cash_session_id`),
  CONSTRAINT `commission_settlements_cash_session_id_foreign` FOREIGN KEY (`cash_session_id`) REFERENCES `cash_sessions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commission_settlements_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commission_settlements_paid_by_foreign` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commission_settlements_personal_id_foreign` FOREIGN KEY (`personal_id`) REFERENCES `personals` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. Columnas que falten (en tablas nuevas que ya existían o en tablas
--    heredadas que las migraciones ampliaron)
-- ---------------------------------------------------------------------

-- plans
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'name') = 0, 'ALTER TABLE `plans` ADD COLUMN `name` varchar(255) NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'slug') = 0, 'ALTER TABLE `plans` ADD COLUMN `slug` varchar(255) NOT NULL AFTER `name`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'description') = 0, 'ALTER TABLE `plans` ADD COLUMN `description` text NULL AFTER `slug`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'price') = 0, 'ALTER TABLE `plans` ADD COLUMN `price` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `description`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'currency') = 0, 'ALTER TABLE `plans` ADD COLUMN `currency` varchar(10) NOT NULL DEFAULT ''BOB'' AFTER `price`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'billing_days') = 0, 'ALTER TABLE `plans` ADD COLUMN `billing_days` int(10) unsigned NOT NULL DEFAULT 30 AFTER `currency`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'trial_days') = 0, 'ALTER TABLE `plans` ADD COLUMN `trial_days` int(10) unsigned NOT NULL DEFAULT 14 AFTER `billing_days`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'max_branches') = 0, 'ALTER TABLE `plans` ADD COLUMN `max_branches` int(10) unsigned NULL AFTER `trial_days`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'max_personal') = 0, 'ALTER TABLE `plans` ADD COLUMN `max_personal` int(10) unsigned NULL AFTER `max_branches`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'max_cajas') = 0, 'ALTER TABLE `plans` ADD COLUMN `max_cajas` int(10) unsigned NULL AFTER `max_personal`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'max_users') = 0, 'ALTER TABLE `plans` ADD COLUMN `max_users` int(10) unsigned NULL AFTER `max_cajas`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'features') = 0, 'ALTER TABLE `plans` ADD COLUMN `features` JSON NULL AFTER `max_users`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'active') = 0, 'ALTER TABLE `plans` ADD COLUMN `active` tinyint(1) NOT NULL DEFAULT 1 AFTER `features`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'sort_order') = 0, 'ALTER TABLE `plans` ADD COLUMN `sort_order` int(10) unsigned NOT NULL DEFAULT 0 AFTER `active`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `plans` ADD COLUMN `created_at` timestamp NULL AFTER `sort_order`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `plans` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `plans` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- subscriptions
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'plan_id') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `plan_id` bigint(20) unsigned NOT NULL AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'status') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `status` varchar(255) NOT NULL DEFAULT ''trial'' AFTER `plan_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'trial_ends_at') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `trial_ends_at` timestamp NULL AFTER `status`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'starts_at') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `starts_at` timestamp NULL AFTER `trial_ends_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'ends_at') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `ends_at` timestamp NULL AFTER `starts_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'amount') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `amount` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `ends_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'max_users_override') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `max_users_override` int(10) unsigned NULL AFTER `amount`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'max_branches_override') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `max_branches_override` int(10) unsigned NULL AFTER `max_users_override`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'max_cajas_override') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `max_cajas_override` int(10) unsigned NULL AFTER `max_branches_override`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'max_personal_override') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `max_personal_override` int(10) unsigned NULL AFTER `max_cajas_override`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'max_products_override') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `max_products_override` int(10) unsigned NULL AFTER `max_personal_override`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'features_override') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `features_override` JSON NULL AFTER `max_products_override`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'grace_days') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `grace_days` smallint(5) unsigned NULL AFTER `features_override`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'notes') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `notes` text NULL AFTER `grace_days`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'created_by') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `created_by` bigint(20) unsigned NULL AFTER `notes`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `created_at` timestamp NULL AFTER `created_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `subscriptions` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- subscription_payments
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'subscription_id') = 0, 'ALTER TABLE `subscription_payments` ADD COLUMN `subscription_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `subscription_payments` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `subscription_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'amount') = 0, 'ALTER TABLE `subscription_payments` ADD COLUMN `amount` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'currency') = 0, 'ALTER TABLE `subscription_payments` ADD COLUMN `currency` varchar(10) NOT NULL DEFAULT ''BOB'' AFTER `amount`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'method') = 0, 'ALTER TABLE `subscription_payments` ADD COLUMN `method` varchar(255) NOT NULL DEFAULT ''transferencia'' AFTER `currency`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'reference') = 0, 'ALTER TABLE `subscription_payments` ADD COLUMN `reference` varchar(255) NULL AFTER `method`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'proof') = 0, 'ALTER TABLE `subscription_payments` ADD COLUMN `proof` varchar(255) NULL AFTER `reference`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'period_start') = 0, 'ALTER TABLE `subscription_payments` ADD COLUMN `period_start` date NULL AFTER `proof`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'period_end') = 0, 'ALTER TABLE `subscription_payments` ADD COLUMN `period_end` date NULL AFTER `period_start`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'status') = 0, 'ALTER TABLE `subscription_payments` ADD COLUMN `status` varchar(255) NOT NULL DEFAULT ''pending'' AFTER `period_end`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'paid_at') = 0, 'ALTER TABLE `subscription_payments` ADD COLUMN `paid_at` timestamp NULL AFTER `status`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'confirmed_by') = 0, 'ALTER TABLE `subscription_payments` ADD COLUMN `confirmed_by` bigint(20) unsigned NULL AFTER `paid_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'confirmed_at') = 0, 'ALTER TABLE `subscription_payments` ADD COLUMN `confirmed_at` timestamp NULL AFTER `confirmed_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'created_by') = 0, 'ALTER TABLE `subscription_payments` ADD COLUMN `created_by` bigint(20) unsigned NULL AFTER `confirmed_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'notes') = 0, 'ALTER TABLE `subscription_payments` ADD COLUMN `notes` text NULL AFTER `created_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `subscription_payments` ADD COLUMN `created_at` timestamp NULL AFTER `notes`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `subscription_payments` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- clients
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `clients` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'full_name') = 0, 'ALTER TABLE `clients` ADD COLUMN `full_name` varchar(255) NOT NULL AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'id_number') = 0, 'ALTER TABLE `clients` ADD COLUMN `id_number` varchar(255) NULL AFTER `full_name`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'phone') = 0, 'ALTER TABLE `clients` ADD COLUMN `phone` varchar(255) NULL AFTER `id_number`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'email') = 0, 'ALTER TABLE `clients` ADD COLUMN `email` varchar(255) NULL AFTER `phone`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'birthdate') = 0, 'ALTER TABLE `clients` ADD COLUMN `birthdate` date NULL AFTER `email`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'gender') = 0, 'ALTER TABLE `clients` ADD COLUMN `gender` varchar(255) NULL AFTER `birthdate`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'address') = 0, 'ALTER TABLE `clients` ADD COLUMN `address` varchar(255) NULL AFTER `gender`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'source') = 0, 'ALTER TABLE `clients` ADD COLUMN `source` varchar(255) NULL AFTER `address`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'notes') = 0, 'ALTER TABLE `clients` ADD COLUMN `notes` text NULL AFTER `source`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'active') = 0, 'ALTER TABLE `clients` ADD COLUMN `active` tinyint(1) NOT NULL DEFAULT 1 AFTER `notes`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'created_by') = 0, 'ALTER TABLE `clients` ADD COLUMN `created_by` bigint(20) unsigned NULL AFTER `active`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `clients` ADD COLUMN `created_at` timestamp NULL AFTER `created_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `clients` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `clients` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- service_categories
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_categories' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `service_categories` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_categories' AND COLUMN_NAME = 'name') = 0, 'ALTER TABLE `service_categories` ADD COLUMN `name` varchar(255) NOT NULL AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_categories' AND COLUMN_NAME = 'description') = 0, 'ALTER TABLE `service_categories` ADD COLUMN `description` text NULL AFTER `name`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_categories' AND COLUMN_NAME = 'active') = 0, 'ALTER TABLE `service_categories` ADD COLUMN `active` tinyint(1) NOT NULL DEFAULT 1 AFTER `description`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_categories' AND COLUMN_NAME = 'created_by') = 0, 'ALTER TABLE `service_categories` ADD COLUMN `created_by` bigint(20) unsigned NULL AFTER `active`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_categories' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `service_categories` ADD COLUMN `created_at` timestamp NULL AFTER `created_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_categories' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `service_categories` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_categories' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `service_categories` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- services
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `services` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'service_category_id') = 0, 'ALTER TABLE `services` ADD COLUMN `service_category_id` bigint(20) unsigned NULL AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'name') = 0, 'ALTER TABLE `services` ADD COLUMN `name` varchar(255) NOT NULL AFTER `service_category_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'description') = 0, 'ALTER TABLE `services` ADD COLUMN `description` text NULL AFTER `name`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'price') = 0, 'ALTER TABLE `services` ADD COLUMN `price` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `description`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'duration_minutes') = 0, 'ALTER TABLE `services` ADD COLUMN `duration_minutes` int(10) unsigned NULL AFTER `price`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'commission_percent') = 0, 'ALTER TABLE `services` ADD COLUMN `commission_percent` decimal(5,2) NULL AFTER `duration_minutes`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'active') = 0, 'ALTER TABLE `services` ADD COLUMN `active` tinyint(1) NOT NULL DEFAULT 1 AFTER `commission_percent`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'created_by') = 0, 'ALTER TABLE `services` ADD COLUMN `created_by` bigint(20) unsigned NULL AFTER `active`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `services` ADD COLUMN `created_at` timestamp NULL AFTER `created_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `services` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `services` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- appointments
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `appointments` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'branch_id') = 0, 'ALTER TABLE `appointments` ADD COLUMN `branch_id` bigint(20) unsigned NULL AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'client_id') = 0, 'ALTER TABLE `appointments` ADD COLUMN `client_id` bigint(20) unsigned NOT NULL AFTER `branch_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'personal_id') = 0, 'ALTER TABLE `appointments` ADD COLUMN `personal_id` bigint(20) unsigned NULL AFTER `client_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'service_id') = 0, 'ALTER TABLE `appointments` ADD COLUMN `service_id` bigint(20) unsigned NULL AFTER `personal_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'starts_at') = 0, 'ALTER TABLE `appointments` ADD COLUMN `starts_at` datetime NOT NULL AFTER `service_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'ends_at') = 0, 'ALTER TABLE `appointments` ADD COLUMN `ends_at` datetime NULL AFTER `starts_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'status') = 0, 'ALTER TABLE `appointments` ADD COLUMN `status` varchar(255) NOT NULL DEFAULT ''pendiente'' AFTER `ends_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'price') = 0, 'ALTER TABLE `appointments` ADD COLUMN `price` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `status`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'notes') = 0, 'ALTER TABLE `appointments` ADD COLUMN `notes` text NULL AFTER `price`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'created_by') = 0, 'ALTER TABLE `appointments` ADD COLUMN `created_by` bigint(20) unsigned NULL AFTER `notes`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `appointments` ADD COLUMN `created_at` timestamp NULL AFTER `created_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `appointments` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `appointments` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- product_categories
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_categories' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `product_categories` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_categories' AND COLUMN_NAME = 'name') = 0, 'ALTER TABLE `product_categories` ADD COLUMN `name` varchar(255) NOT NULL AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_categories' AND COLUMN_NAME = 'description') = 0, 'ALTER TABLE `product_categories` ADD COLUMN `description` text NULL AFTER `name`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_categories' AND COLUMN_NAME = 'active') = 0, 'ALTER TABLE `product_categories` ADD COLUMN `active` tinyint(1) NOT NULL DEFAULT 1 AFTER `description`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_categories' AND COLUMN_NAME = 'created_by') = 0, 'ALTER TABLE `product_categories` ADD COLUMN `created_by` bigint(20) unsigned NULL AFTER `active`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_categories' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `product_categories` ADD COLUMN `created_at` timestamp NULL AFTER `created_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_categories' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `product_categories` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_categories' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `product_categories` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- brands
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'brands' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `brands` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'brands' AND COLUMN_NAME = 'name') = 0, 'ALTER TABLE `brands` ADD COLUMN `name` varchar(255) NOT NULL AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'brands' AND COLUMN_NAME = 'active') = 0, 'ALTER TABLE `brands` ADD COLUMN `active` tinyint(1) NOT NULL DEFAULT 1 AFTER `name`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'brands' AND COLUMN_NAME = 'created_by') = 0, 'ALTER TABLE `brands` ADD COLUMN `created_by` bigint(20) unsigned NULL AFTER `active`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'brands' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `brands` ADD COLUMN `created_at` timestamp NULL AFTER `created_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'brands' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `brands` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'brands' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `brands` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- suppliers
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `suppliers` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'name') = 0, 'ALTER TABLE `suppliers` ADD COLUMN `name` varchar(255) NOT NULL AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'contact_name') = 0, 'ALTER TABLE `suppliers` ADD COLUMN `contact_name` varchar(255) NULL AFTER `name`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'phone') = 0, 'ALTER TABLE `suppliers` ADD COLUMN `phone` varchar(255) NULL AFTER `contact_name`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'email') = 0, 'ALTER TABLE `suppliers` ADD COLUMN `email` varchar(255) NULL AFTER `phone`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'tax_id') = 0, 'ALTER TABLE `suppliers` ADD COLUMN `tax_id` varchar(255) NULL AFTER `email`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'address') = 0, 'ALTER TABLE `suppliers` ADD COLUMN `address` varchar(255) NULL AFTER `tax_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'notes') = 0, 'ALTER TABLE `suppliers` ADD COLUMN `notes` text NULL AFTER `address`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'active') = 0, 'ALTER TABLE `suppliers` ADD COLUMN `active` tinyint(1) NOT NULL DEFAULT 1 AFTER `notes`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'created_by') = 0, 'ALTER TABLE `suppliers` ADD COLUMN `created_by` bigint(20) unsigned NULL AFTER `active`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `suppliers` ADD COLUMN `created_at` timestamp NULL AFTER `created_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `suppliers` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `suppliers` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- payment_methods
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_methods' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_methods' AND COLUMN_NAME = 'name') = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `name` varchar(80) NOT NULL AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_methods' AND COLUMN_NAME = 'code') = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `code` varchar(30) NOT NULL AFTER `name`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_methods' AND COLUMN_NAME = 'icon') = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `icon` varchar(40) NULL AFTER `code`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_methods' AND COLUMN_NAME = 'affects_cash') = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `affects_cash` tinyint(1) NOT NULL DEFAULT 1 AFTER `icon`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_methods' AND COLUMN_NAME = 'is_default') = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `is_default` tinyint(1) NOT NULL DEFAULT 0 AFTER `affects_cash`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_methods' AND COLUMN_NAME = 'active') = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `active` tinyint(1) NOT NULL DEFAULT 1 AFTER `is_default`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_methods' AND COLUMN_NAME = 'sort_order') = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `active`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_methods' AND COLUMN_NAME = 'created_by') = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `created_by` bigint(20) unsigned NULL AFTER `sort_order`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_methods' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `created_at` timestamp NULL AFTER `created_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_methods' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_methods' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- company_settings
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `company_settings` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'currency') = 0, 'ALTER TABLE `company_settings` ADD COLUMN `currency` varchar(10) NOT NULL DEFAULT ''BOB'' AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'currency_symbol') = 0, 'ALTER TABLE `company_settings` ADD COLUMN `currency_symbol` varchar(10) NOT NULL DEFAULT ''Bs'' AFTER `currency`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'tax_rate') = 0, 'ALTER TABLE `company_settings` ADD COLUMN `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00 AFTER `currency_symbol`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'tax_included') = 0, 'ALTER TABLE `company_settings` ADD COLUMN `tax_included` tinyint(1) NOT NULL DEFAULT 1 AFTER `tax_rate`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'tax_label') = 0, 'ALTER TABLE `company_settings` ADD COLUMN `tax_label` varchar(30) NOT NULL DEFAULT ''IVA'' AFTER `tax_included`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'invoice_business_name') = 0, 'ALTER TABLE `company_settings` ADD COLUMN `invoice_business_name` varchar(255) NULL AFTER `tax_label`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'invoice_nit') = 0, 'ALTER TABLE `company_settings` ADD COLUMN `invoice_nit` varchar(30) NULL AFTER `invoice_business_name`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'invoice_address') = 0, 'ALTER TABLE `company_settings` ADD COLUMN `invoice_address` varchar(255) NULL AFTER `invoice_nit`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'invoice_phone') = 0, 'ALTER TABLE `company_settings` ADD COLUMN `invoice_phone` varchar(40) NULL AFTER `invoice_address`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'invoice_footer') = 0, 'ALTER TABLE `company_settings` ADD COLUMN `invoice_footer` text NULL AFTER `invoice_phone`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'sale_prefix') = 0, 'ALTER TABLE `company_settings` ADD COLUMN `sale_prefix` varchar(10) NOT NULL DEFAULT ''V-'' AFTER `invoice_footer`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'sale_padding') = 0, 'ALTER TABLE `company_settings` ADD COLUMN `sale_padding` tinyint(3) unsigned NOT NULL DEFAULT 6 AFTER `sale_prefix`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'appointment_slot_minutes') = 0, 'ALTER TABLE `company_settings` ADD COLUMN `appointment_slot_minutes` smallint(5) unsigned NOT NULL DEFAULT 30 AFTER `sale_padding`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'require_cash_session') = 0, 'ALTER TABLE `company_settings` ADD COLUMN `require_cash_session` tinyint(1) NOT NULL DEFAULT 0 AFTER `appointment_slot_minutes`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `company_settings` ADD COLUMN `created_at` timestamp NULL AFTER `require_cash_session`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `company_settings` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- commission_rules
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `commission_rules` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'personal_id') = 0, 'ALTER TABLE `commission_rules` ADD COLUMN `personal_id` bigint(20) unsigned NULL COMMENT ''NULL = todo el equipo'' AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'applies_to') = 0, 'ALTER TABLE `commission_rules` ADD COLUMN `applies_to` varchar(20) NOT NULL COMMENT ''services | products | category | service'' AFTER `personal_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'service_id') = 0, 'ALTER TABLE `commission_rules` ADD COLUMN `service_id` bigint(20) unsigned NULL COMMENT ''solo si applies_to = service'' AFTER `applies_to`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'service_category_id') = 0, 'ALTER TABLE `commission_rules` ADD COLUMN `service_category_id` bigint(20) unsigned NULL COMMENT ''solo si applies_to = category'' AFTER `service_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'type') = 0, 'ALTER TABLE `commission_rules` ADD COLUMN `type` varchar(20) NOT NULL DEFAULT ''percent'' COMMENT ''percent | fixed'' AFTER `service_category_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'value') = 0, 'ALTER TABLE `commission_rules` ADD COLUMN `value` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `type`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'valid_from') = 0, 'ALTER TABLE `commission_rules` ADD COLUMN `valid_from` date NULL AFTER `value`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'valid_until') = 0, 'ALTER TABLE `commission_rules` ADD COLUMN `valid_until` date NULL AFTER `valid_from`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'active') = 0, 'ALTER TABLE `commission_rules` ADD COLUMN `active` tinyint(1) NOT NULL DEFAULT 1 AFTER `valid_until`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'notes') = 0, 'ALTER TABLE `commission_rules` ADD COLUMN `notes` text NULL AFTER `active`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'created_by') = 0, 'ALTER TABLE `commission_rules` ADD COLUMN `created_by` bigint(20) unsigned NULL AFTER `notes`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `commission_rules` ADD COLUMN `created_at` timestamp NULL AFTER `created_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `commission_rules` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- commission_settlements
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `commission_settlements` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'personal_id') = 0, 'ALTER TABLE `commission_settlements` ADD COLUMN `personal_id` bigint(20) unsigned NOT NULL AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'number') = 0, 'ALTER TABLE `commission_settlements` ADD COLUMN `number` varchar(20) NOT NULL COMMENT ''LIQ-0001, único por empresa'' AFTER `personal_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'period_start') = 0, 'ALTER TABLE `commission_settlements` ADD COLUMN `period_start` date NOT NULL AFTER `number`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'period_end') = 0, 'ALTER TABLE `commission_settlements` ADD COLUMN `period_end` date NOT NULL AFTER `period_start`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'commissions_count') = 0, 'ALTER TABLE `commission_settlements` ADD COLUMN `commissions_count` int(10) unsigned NOT NULL DEFAULT 0 AFTER `period_end`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'commissions_total') = 0, 'ALTER TABLE `commission_settlements` ADD COLUMN `commissions_total` decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `commissions_count`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'tips_total') = 0, 'ALTER TABLE `commission_settlements` ADD COLUMN `tips_total` decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `commissions_total`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'total') = 0, 'ALTER TABLE `commission_settlements` ADD COLUMN `total` decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `tips_total`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'include_tips') = 0, 'ALTER TABLE `commission_settlements` ADD COLUMN `include_tips` tinyint(1) NOT NULL DEFAULT 0 AFTER `total`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'paid_at') = 0, 'ALTER TABLE `commission_settlements` ADD COLUMN `paid_at` datetime NOT NULL AFTER `include_tips`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'paid_by') = 0, 'ALTER TABLE `commission_settlements` ADD COLUMN `paid_by` bigint(20) unsigned NULL AFTER `paid_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'cash_session_id') = 0, 'ALTER TABLE `commission_settlements` ADD COLUMN `cash_session_id` bigint(20) unsigned NULL AFTER `paid_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'cash_movement_id') = 0, 'ALTER TABLE `commission_settlements` ADD COLUMN `cash_movement_id` bigint(20) unsigned NULL AFTER `cash_session_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'notes') = 0, 'ALTER TABLE `commission_settlements` ADD COLUMN `notes` text NULL AFTER `cash_movement_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `commission_settlements` ADD COLUMN `created_at` timestamp NULL AFTER `notes`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `commission_settlements` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- companies
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'name') = 0, 'ALTER TABLE `companies` ADD COLUMN `name` varchar(255) NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'ruc') = 0, 'ALTER TABLE `companies` ADD COLUMN `ruc` varchar(20) NULL AFTER `name`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'address') = 0, 'ALTER TABLE `companies` ADD COLUMN `address` varchar(255) NULL AFTER `ruc`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'phone') = 0, 'ALTER TABLE `companies` ADD COLUMN `phone` varchar(20) NULL AFTER `address`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'email') = 0, 'ALTER TABLE `companies` ADD COLUMN `email` varchar(255) NULL AFTER `phone`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'logo') = 0, 'ALTER TABLE `companies` ADD COLUMN `logo` varchar(255) NULL AFTER `email`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'primary_color') = 0, 'ALTER TABLE `companies` ADD COLUMN `primary_color` varchar(7) NULL AFTER `logo`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'secondary_color') = 0, 'ALTER TABLE `companies` ADD COLUMN `secondary_color` varchar(7) NULL AFTER `primary_color`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'description') = 0, 'ALTER TABLE `companies` ADD COLUMN `description` text NULL AFTER `secondary_color`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'active') = 0, 'ALTER TABLE `companies` ADD COLUMN `active` tinyint(1) NOT NULL DEFAULT 1 AFTER `description`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `companies` ADD COLUMN `created_at` timestamp NULL AFTER `active`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `companies` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `companies` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- personals
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `personals` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals' AND COLUMN_NAME = 'cargo_id') = 0, 'ALTER TABLE `personals` ADD COLUMN `cargo_id` bigint(20) unsigned NOT NULL AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals' AND COLUMN_NAME = 'user_id') = 0, 'ALTER TABLE `personals` ADD COLUMN `user_id` bigint(20) unsigned NULL AFTER `cargo_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals' AND COLUMN_NAME = 'full_name') = 0, 'ALTER TABLE `personals` ADD COLUMN `full_name` varchar(255) NOT NULL AFTER `user_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals' AND COLUMN_NAME = 'id_number') = 0, 'ALTER TABLE `personals` ADD COLUMN `id_number` varchar(50) NULL AFTER `full_name`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals' AND COLUMN_NAME = 'phone') = 0, 'ALTER TABLE `personals` ADD COLUMN `phone` varchar(30) NULL AFTER `id_number`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals' AND COLUMN_NAME = 'email') = 0, 'ALTER TABLE `personals` ADD COLUMN `email` varchar(255) NULL AFTER `phone`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals' AND COLUMN_NAME = 'address') = 0, 'ALTER TABLE `personals` ADD COLUMN `address` varchar(255) NULL AFTER `email`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals' AND COLUMN_NAME = 'hire_date') = 0, 'ALTER TABLE `personals` ADD COLUMN `hire_date` date NULL AFTER `address`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals' AND COLUMN_NAME = 'notes') = 0, 'ALTER TABLE `personals` ADD COLUMN `notes` text NULL AFTER `hire_date`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals' AND COLUMN_NAME = 'active') = 0, 'ALTER TABLE `personals` ADD COLUMN `active` tinyint(1) NOT NULL DEFAULT 1 AFTER `notes`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `personals` ADD COLUMN `created_at` timestamp NULL AFTER `active`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `personals` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `personals` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- sales
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `sales` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'branch_id') = 0, 'ALTER TABLE `sales` ADD COLUMN `branch_id` bigint(20) unsigned NULL AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'client_id') = 0, 'ALTER TABLE `sales` ADD COLUMN `client_id` bigint(20) unsigned NULL AFTER `branch_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'personal_id') = 0, 'ALTER TABLE `sales` ADD COLUMN `personal_id` bigint(20) unsigned NULL AFTER `client_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'warehouse_id') = 0, 'ALTER TABLE `sales` ADD COLUMN `warehouse_id` bigint(20) unsigned NULL AFTER `personal_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'promoter_id') = 0, 'ALTER TABLE `sales` ADD COLUMN `promoter_id` bigint(20) unsigned NULL AFTER `warehouse_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'cash_session_id') = 0, 'ALTER TABLE `sales` ADD COLUMN `cash_session_id` bigint(20) unsigned NULL AFTER `promoter_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'sale_number') = 0, 'ALTER TABLE `sales` ADD COLUMN `sale_number` varchar(50) NOT NULL AFTER `cash_session_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'client_name') = 0, 'ALTER TABLE `sales` ADD COLUMN `client_name` varchar(255) NULL AFTER `sale_number`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'client_phone') = 0, 'ALTER TABLE `sales` ADD COLUMN `client_phone` varchar(50) NULL AFTER `client_name`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'client_document') = 0, 'ALTER TABLE `sales` ADD COLUMN `client_document` varchar(50) NULL AFTER `client_phone`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'sale_date') = 0, 'ALTER TABLE `sales` ADD COLUMN `sale_date` date NOT NULL AFTER `client_document`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'subtotal') = 0, 'ALTER TABLE `sales` ADD COLUMN `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `sale_date`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'tax') = 0, 'ALTER TABLE `sales` ADD COLUMN `tax` decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `subtotal`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'discount') = 0, 'ALTER TABLE `sales` ADD COLUMN `discount` decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `tax`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'total') = 0, 'ALTER TABLE `sales` ADD COLUMN `total` decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `discount`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'tip') = 0, 'ALTER TABLE `sales` ADD COLUMN `tip` decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `total`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'tip_settlement_id') = 0, 'ALTER TABLE `sales` ADD COLUMN `tip_settlement_id` bigint(20) unsigned NULL AFTER `tip`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'payment_method') = 0, 'ALTER TABLE `sales` ADD COLUMN `payment_method` varchar(30) NOT NULL DEFAULT ''efectivo'' AFTER `tip_settlement_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'status') = 0, 'ALTER TABLE `sales` ADD COLUMN `status` varchar(20) NOT NULL DEFAULT ''completada'' AFTER `payment_method`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'notes') = 0, 'ALTER TABLE `sales` ADD COLUMN `notes` text NULL AFTER `status`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'created_by') = 0, 'ALTER TABLE `sales` ADD COLUMN `created_by` bigint(20) unsigned NOT NULL AFTER `notes`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `sales` ADD COLUMN `created_at` timestamp NULL AFTER `created_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `sales` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `sales` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- sale_details
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'sale_id') = 0, 'ALTER TABLE `sale_details` ADD COLUMN `sale_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'product_id') = 0, 'ALTER TABLE `sale_details` ADD COLUMN `product_id` bigint(20) unsigned NULL AFTER `sale_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'service_id') = 0, 'ALTER TABLE `sale_details` ADD COLUMN `service_id` bigint(20) unsigned NULL AFTER `product_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'personal_id') = 0, 'ALTER TABLE `sale_details` ADD COLUMN `personal_id` bigint(20) unsigned NULL AFTER `service_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'description') = 0, 'ALTER TABLE `sale_details` ADD COLUMN `description` varchar(255) NULL AFTER `personal_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'quantity') = 0, 'ALTER TABLE `sale_details` ADD COLUMN `quantity` decimal(12,2) NOT NULL AFTER `description`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'unit_price') = 0, 'ALTER TABLE `sale_details` ADD COLUMN `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `quantity`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'discount') = 0, 'ALTER TABLE `sale_details` ADD COLUMN `discount` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `unit_price`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'total') = 0, 'ALTER TABLE `sale_details` ADD COLUMN `total` decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `discount`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `sale_details` ADD COLUMN `created_at` timestamp NULL AFTER `total`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `sale_details` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `sale_details` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- products
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `products` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'product_category_id') = 0, 'ALTER TABLE `products` ADD COLUMN `product_category_id` bigint(20) unsigned NULL AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'brand_id') = 0, 'ALTER TABLE `products` ADD COLUMN `brand_id` bigint(20) unsigned NULL AFTER `product_category_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'credit_category_id') = 0, 'ALTER TABLE `products` ADD COLUMN `credit_category_id` bigint(20) unsigned NULL AFTER `brand_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'name') = 0, 'ALTER TABLE `products` ADD COLUMN `name` varchar(255) NOT NULL AFTER `credit_category_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'sku') = 0, 'ALTER TABLE `products` ADD COLUMN `sku` varchar(100) NOT NULL AFTER `name`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'description') = 0, 'ALTER TABLE `products` ADD COLUMN `description` text NULL AFTER `sku`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'unit') = 0, 'ALTER TABLE `products` ADD COLUMN `unit` varchar(50) NOT NULL DEFAULT ''unidad'' AFTER `description`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'cost') = 0, 'ALTER TABLE `products` ADD COLUMN `cost` decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `unit`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'price') = 0, 'ALTER TABLE `products` ADD COLUMN `price` decimal(15,2) NOT NULL DEFAULT 0.00 AFTER `cost`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'min_stock') = 0, 'ALTER TABLE `products` ADD COLUMN `min_stock` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `price`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'current_stock') = 0, 'ALTER TABLE `products` ADD COLUMN `current_stock` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `min_stock`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'category') = 0, 'ALTER TABLE `products` ADD COLUMN `category` enum(''product'',''raw_material'',''supply'') NOT NULL DEFAULT ''product'' AFTER `current_stock`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'active') = 0, 'ALTER TABLE `products` ADD COLUMN `active` tinyint(1) NOT NULL DEFAULT 1 AFTER `category`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `products` ADD COLUMN `created_at` timestamp NULL AFTER `active`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `products` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `products` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- inventory_movements
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `inventory_movements` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements' AND COLUMN_NAME = 'warehouse_id') = 0, 'ALTER TABLE `inventory_movements` ADD COLUMN `warehouse_id` bigint(20) unsigned NULL AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements' AND COLUMN_NAME = 'branch_id') = 0, 'ALTER TABLE `inventory_movements` ADD COLUMN `branch_id` bigint(20) unsigned NULL AFTER `warehouse_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements' AND COLUMN_NAME = 'product_id') = 0, 'ALTER TABLE `inventory_movements` ADD COLUMN `product_id` bigint(20) unsigned NOT NULL AFTER `branch_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements' AND COLUMN_NAME = 'user_id') = 0, 'ALTER TABLE `inventory_movements` ADD COLUMN `user_id` bigint(20) unsigned NOT NULL AFTER `product_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements' AND COLUMN_NAME = 'type') = 0, 'ALTER TABLE `inventory_movements` ADD COLUMN `type` varchar(20) NOT NULL AFTER `user_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements' AND COLUMN_NAME = 'quantity') = 0, 'ALTER TABLE `inventory_movements` ADD COLUMN `quantity` decimal(15,2) NOT NULL AFTER `type`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements' AND COLUMN_NAME = 'unit_cost') = 0, 'ALTER TABLE `inventory_movements` ADD COLUMN `unit_cost` decimal(15,2) NULL AFTER `quantity`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements' AND COLUMN_NAME = 'reference') = 0, 'ALTER TABLE `inventory_movements` ADD COLUMN `reference` varchar(100) NULL AFTER `unit_cost`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements' AND COLUMN_NAME = 'notes') = 0, 'ALTER TABLE `inventory_movements` ADD COLUMN `notes` text NULL AFTER `reference`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements' AND COLUMN_NAME = 'movement_date') = 0, 'ALTER TABLE `inventory_movements` ADD COLUMN `movement_date` datetime NOT NULL AFTER `notes`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `inventory_movements` ADD COLUMN `created_at` timestamp NULL AFTER `movement_date`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `inventory_movements` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `inventory_movements` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- entries
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `entries` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'supplier_id') = 0, 'ALTER TABLE `entries` ADD COLUMN `supplier_id` bigint(20) unsigned NULL AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'warehouse_id') = 0, 'ALTER TABLE `entries` ADD COLUMN `warehouse_id` bigint(20) unsigned NULL AFTER `supplier_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'entry_number') = 0, 'ALTER TABLE `entries` ADD COLUMN `entry_number` varchar(50) NOT NULL AFTER `warehouse_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'supplier') = 0, 'ALTER TABLE `entries` ADD COLUMN `supplier` varchar(255) NULL AFTER `entry_number`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'entry_date') = 0, 'ALTER TABLE `entries` ADD COLUMN `entry_date` date NOT NULL AFTER `supplier`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'notes') = 0, 'ALTER TABLE `entries` ADD COLUMN `notes` text NULL AFTER `entry_date`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'status') = 0, 'ALTER TABLE `entries` ADD COLUMN `status` varchar(20) NOT NULL DEFAULT ''pendiente'' AFTER `notes`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'total') = 0, 'ALTER TABLE `entries` ADD COLUMN `total` decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `status`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'created_by') = 0, 'ALTER TABLE `entries` ADD COLUMN `created_by` bigint(20) unsigned NOT NULL AFTER `total`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `entries` ADD COLUMN `created_at` timestamp NULL AFTER `created_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `entries` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `entries` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- cash_movements
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `cash_movements` ADD COLUMN `company_id` bigint(20) unsigned NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'cash_session_id') = 0, 'ALTER TABLE `cash_movements` ADD COLUMN `cash_session_id` bigint(20) unsigned NULL AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'type') = 0, 'ALTER TABLE `cash_movements` ADD COLUMN `type` varchar(20) NOT NULL AFTER `cash_session_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'amount') = 0, 'ALTER TABLE `cash_movements` ADD COLUMN `amount` decimal(14,2) NOT NULL AFTER `type`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'concept') = 0, 'ALTER TABLE `cash_movements` ADD COLUMN `concept` varchar(255) NOT NULL AFTER `amount`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'category') = 0, 'ALTER TABLE `cash_movements` ADD COLUMN `category` varchar(255) NULL AFTER `concept`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'movement_date') = 0, 'ALTER TABLE `cash_movements` ADD COLUMN `movement_date` date NULL AFTER `category`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'payment_method') = 0, 'ALTER TABLE `cash_movements` ADD COLUMN `payment_method` varchar(30) NOT NULL DEFAULT ''efectivo'' AFTER `movement_date`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'reference') = 0, 'ALTER TABLE `cash_movements` ADD COLUMN `reference` varchar(255) NULL AFTER `payment_method`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'created_by') = 0, 'ALTER TABLE `cash_movements` ADD COLUMN `created_by` bigint(20) unsigned NULL AFTER `reference`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `cash_movements` ADD COLUMN `created_at` timestamp NULL AFTER `created_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `cash_movements` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `cash_movements` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- cash_sessions
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'cash_register_id') = 0, 'ALTER TABLE `cash_sessions` ADD COLUMN `cash_register_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'personal_id') = 0, 'ALTER TABLE `cash_sessions` ADD COLUMN `personal_id` bigint(20) unsigned NULL AFTER `cash_register_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'opening_amount') = 0, 'ALTER TABLE `cash_sessions` ADD COLUMN `opening_amount` decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `personal_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'closing_amount') = 0, 'ALTER TABLE `cash_sessions` ADD COLUMN `closing_amount` decimal(14,2) NULL AFTER `opening_amount`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'expected_amount') = 0, 'ALTER TABLE `cash_sessions` ADD COLUMN `expected_amount` decimal(14,2) NULL AFTER `closing_amount`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'difference') = 0, 'ALTER TABLE `cash_sessions` ADD COLUMN `difference` decimal(14,2) NULL AFTER `expected_amount`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'status') = 0, 'ALTER TABLE `cash_sessions` ADD COLUMN `status` enum(''open'',''closed'') NOT NULL DEFAULT ''open'' AFTER `difference`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'opened_at') = 0, 'ALTER TABLE `cash_sessions` ADD COLUMN `opened_at` timestamp NOT NULL DEFAULT current_timestamp() AFTER `status`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'closed_at') = 0, 'ALTER TABLE `cash_sessions` ADD COLUMN `closed_at` timestamp NULL AFTER `opened_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'notes') = 0, 'ALTER TABLE `cash_sessions` ADD COLUMN `notes` text NULL AFTER `closed_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'opened_by') = 0, 'ALTER TABLE `cash_sessions` ADD COLUMN `opened_by` bigint(20) unsigned NOT NULL AFTER `notes`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'closed_by') = 0, 'ALTER TABLE `cash_sessions` ADD COLUMN `closed_by` bigint(20) unsigned NULL AFTER `opened_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `cash_sessions` ADD COLUMN `created_at` timestamp NULL AFTER `closed_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `cash_sessions` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `cash_sessions` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- commissions
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'company_id') = 0, 'ALTER TABLE `commissions` ADD COLUMN `company_id` bigint(20) unsigned NOT NULL AFTER `id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'personal_id') = 0, 'ALTER TABLE `commissions` ADD COLUMN `personal_id` bigint(20) unsigned NULL AFTER `company_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'promoter_id') = 0, 'ALTER TABLE `commissions` ADD COLUMN `promoter_id` bigint(20) unsigned NULL AFTER `personal_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'sale_id') = 0, 'ALTER TABLE `commissions` ADD COLUMN `sale_id` bigint(20) unsigned NULL AFTER `promoter_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'sale_detail_id') = 0, 'ALTER TABLE `commissions` ADD COLUMN `sale_detail_id` bigint(20) unsigned NULL AFTER `sale_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'rule_id') = 0, 'ALTER TABLE `commissions` ADD COLUMN `rule_id` bigint(20) unsigned NULL AFTER `sale_detail_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'settlement_id') = 0, 'ALTER TABLE `commissions` ADD COLUMN `settlement_id` bigint(20) unsigned NULL AFTER `rule_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'reverses_id') = 0, 'ALTER TABLE `commissions` ADD COLUMN `reverses_id` bigint(20) unsigned NULL AFTER `settlement_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'base') = 0, 'ALTER TABLE `commissions` ADD COLUMN `base` decimal(14,2) NULL AFTER `reverses_id`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'calc_type') = 0, 'ALTER TABLE `commissions` ADD COLUMN `calc_type` varchar(20) NULL AFTER `base`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'calc_value') = 0, 'ALTER TABLE `commissions` ADD COLUMN `calc_value` decimal(10,2) NULL AFTER `calc_type`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'accrued_at') = 0, 'ALTER TABLE `commissions` ADD COLUMN `accrued_at` datetime NULL AFTER `calc_value`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'amount') = 0, 'ALTER TABLE `commissions` ADD COLUMN `amount` decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `accrued_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'rate') = 0, 'ALTER TABLE `commissions` ADD COLUMN `rate` decimal(5,2) NOT NULL DEFAULT 0.00 AFTER `amount`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'status') = 0, 'ALTER TABLE `commissions` ADD COLUMN `status` varchar(20) NOT NULL DEFAULT ''pendiente'' AFTER `rate`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'paid_at') = 0, 'ALTER TABLE `commissions` ADD COLUMN `paid_at` timestamp NULL AFTER `status`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'paid_by') = 0, 'ALTER TABLE `commissions` ADD COLUMN `paid_by` bigint(20) unsigned NULL AFTER `paid_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'period_start') = 0, 'ALTER TABLE `commissions` ADD COLUMN `period_start` date NULL AFTER `paid_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'period_end') = 0, 'ALTER TABLE `commissions` ADD COLUMN `period_end` date NULL AFTER `period_start`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'notes') = 0, 'ALTER TABLE `commissions` ADD COLUMN `notes` text NULL AFTER `period_end`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'created_by') = 0, 'ALTER TABLE `commissions` ADD COLUMN `created_by` bigint(20) unsigned NULL AFTER `notes`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'created_at') = 0, 'ALTER TABLE `commissions` ADD COLUMN `created_at` timestamp NULL AFTER `created_by`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'updated_at') = 0, 'ALTER TABLE `commissions` ADD COLUMN `updated_at` timestamp NULL AFTER `created_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'deleted_at') = 0, 'ALTER TABLE `commissions` ADD COLUMN `deleted_at` timestamp NULL AFTER `updated_at`', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 3. Cambios de tipo de las migraciones (ENUM → VARCHAR, columnas que
--    pasan a aceptar NULL). Solo se aplican si la columna aún es distinta.
-- ---------------------------------------------------------------------
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals' AND COLUMN_NAME = 'user_id' AND (COLUMN_TYPE <> 'bigint(20) unsigned' OR IS_NULLABLE <> 'YES')) > 0, 'ALTER TABLE `personals` MODIFY `user_id` bigint(20) unsigned NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements' AND COLUMN_NAME = 'warehouse_id' AND (COLUMN_TYPE <> 'bigint(20) unsigned' OR IS_NULLABLE <> 'YES')) > 0, 'ALTER TABLE `inventory_movements` MODIFY `warehouse_id` bigint(20) unsigned NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'warehouse_id' AND (COLUMN_TYPE <> 'bigint(20) unsigned' OR IS_NULLABLE <> 'YES')) > 0, 'ALTER TABLE `entries` MODIFY `warehouse_id` bigint(20) unsigned NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'type' AND (COLUMN_TYPE <> 'varchar(20)' OR IS_NULLABLE <> 'NO')) > 0, 'ALTER TABLE `cash_movements` MODIFY `type` varchar(20) NOT NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'payment_method' AND (COLUMN_TYPE <> 'varchar(30)' OR IS_NULLABLE <> 'NO')) > 0, 'ALTER TABLE `cash_movements` MODIFY `payment_method` varchar(30) NOT NULL DEFAULT ''efectivo''', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'cash_session_id' AND (COLUMN_TYPE <> 'bigint(20) unsigned' OR IS_NULLABLE <> 'YES')) > 0, 'ALTER TABLE `cash_movements` MODIFY `cash_session_id` bigint(20) unsigned NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'created_by' AND (COLUMN_TYPE <> 'bigint(20) unsigned' OR IS_NULLABLE <> 'YES')) > 0, 'ALTER TABLE `cash_movements` MODIFY `created_by` bigint(20) unsigned NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'payment_method' AND (COLUMN_TYPE <> 'varchar(30)' OR IS_NULLABLE <> 'NO')) > 0, 'ALTER TABLE `sales` MODIFY `payment_method` varchar(30) NOT NULL DEFAULT ''efectivo''', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'status' AND (COLUMN_TYPE <> 'varchar(20)' OR IS_NULLABLE <> 'NO')) > 0, 'ALTER TABLE `sales` MODIFY `status` varchar(20) NOT NULL DEFAULT ''completada''', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements' AND COLUMN_NAME = 'type' AND (COLUMN_TYPE <> 'varchar(20)' OR IS_NULLABLE <> 'NO')) > 0, 'ALTER TABLE `inventory_movements` MODIFY `type` varchar(20) NOT NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'status' AND (COLUMN_TYPE <> 'varchar(20)' OR IS_NULLABLE <> 'NO')) > 0, 'ALTER TABLE `entries` MODIFY `status` varchar(20) NOT NULL DEFAULT ''pendiente''', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'promoter_id' AND (COLUMN_TYPE <> 'bigint(20) unsigned' OR IS_NULLABLE <> 'YES')) > 0, 'ALTER TABLE `commissions` MODIFY `promoter_id` bigint(20) unsigned NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'created_by' AND (COLUMN_TYPE <> 'bigint(20) unsigned' OR IS_NULLABLE <> 'YES')) > 0, 'ALTER TABLE `commissions` MODIFY `created_by` bigint(20) unsigned NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'status' AND (COLUMN_TYPE <> 'varchar(20)' OR IS_NULLABLE <> 'NO')) > 0, 'ALTER TABLE `commissions` MODIFY `status` varchar(20) NOT NULL DEFAULT ''pendiente''', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'product_id' AND (COLUMN_TYPE <> 'bigint(20) unsigned' OR IS_NULLABLE <> 'YES')) > 0, 'ALTER TABLE `sale_details` MODIFY `product_id` bigint(20) unsigned NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'personal_id' AND (COLUMN_TYPE <> 'bigint(20) unsigned' OR IS_NULLABLE <> 'YES')) > 0, 'ALTER TABLE `cash_sessions` MODIFY `personal_id` bigint(20) unsigned NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_methods' AND COLUMN_NAME = 'code' AND (COLUMN_TYPE <> 'varchar(30)' OR IS_NULLABLE <> 'NO')) > 0, 'ALTER TABLE `payment_methods` MODIFY `code` varchar(30) NOT NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 4. Claves foráneas de las tablas nuevas y de las columnas agregadas.
--    Se omite si la columna ya tiene una FK hacia esa tabla (aunque tenga
--    otro nombre), si el nombre ya está en uso o si la tabla referenciada
--    no existe. Las FKs heredadas del proyecto base no se tocan.
-- ---------------------------------------------------------------------
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME = 'companies') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'subscriptions_company_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies')) = 0, 'ALTER TABLE `subscriptions` ADD CONSTRAINT `subscriptions_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'created_by' AND REFERENCED_TABLE_NAME = 'users') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'subscriptions_created_by_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users')) = 0, 'ALTER TABLE `subscriptions` ADD CONSTRAINT `subscriptions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'plan_id' AND REFERENCED_TABLE_NAME = 'plans') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'subscriptions_plan_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans')) = 0, 'ALTER TABLE `subscriptions` ADD CONSTRAINT `subscriptions_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME = 'companies') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'subscription_payments_company_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies')) = 0, 'ALTER TABLE `subscription_payments` ADD CONSTRAINT `subscription_payments_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'confirmed_by' AND REFERENCED_TABLE_NAME = 'users') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'subscription_payments_confirmed_by_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users')) = 0, 'ALTER TABLE `subscription_payments` ADD CONSTRAINT `subscription_payments_confirmed_by_foreign` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'created_by' AND REFERENCED_TABLE_NAME = 'users') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'subscription_payments_created_by_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users')) = 0, 'ALTER TABLE `subscription_payments` ADD CONSTRAINT `subscription_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'subscription_id' AND REFERENCED_TABLE_NAME = 'subscriptions') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'subscription_payments_subscription_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions')) = 0, 'ALTER TABLE `subscription_payments` ADD CONSTRAINT `subscription_payments_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME = 'companies') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'clients_company_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies')) = 0, 'ALTER TABLE `clients` ADD CONSTRAINT `clients_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'created_by' AND REFERENCED_TABLE_NAME = 'users') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'clients_created_by_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users')) = 0, 'ALTER TABLE `clients` ADD CONSTRAINT `clients_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_categories' AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME = 'companies') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'service_categories_company_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies')) = 0, 'ALTER TABLE `service_categories` ADD CONSTRAINT `service_categories_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_categories' AND COLUMN_NAME = 'created_by' AND REFERENCED_TABLE_NAME = 'users') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'service_categories_created_by_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users')) = 0, 'ALTER TABLE `service_categories` ADD CONSTRAINT `service_categories_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME = 'companies') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'services_company_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies')) = 0, 'ALTER TABLE `services` ADD CONSTRAINT `services_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'created_by' AND REFERENCED_TABLE_NAME = 'users') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'services_created_by_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users')) = 0, 'ALTER TABLE `services` ADD CONSTRAINT `services_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'service_category_id' AND REFERENCED_TABLE_NAME = 'service_categories') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'services_service_category_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_categories')) = 0, 'ALTER TABLE `services` ADD CONSTRAINT `services_service_category_id_foreign` FOREIGN KEY (`service_category_id`) REFERENCES `service_categories` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'branch_id' AND REFERENCED_TABLE_NAME = 'branches') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'appointments_branch_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branches')) = 0, 'ALTER TABLE `appointments` ADD CONSTRAINT `appointments_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'client_id' AND REFERENCED_TABLE_NAME = 'clients') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'appointments_client_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients')) = 0, 'ALTER TABLE `appointments` ADD CONSTRAINT `appointments_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME = 'companies') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'appointments_company_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies')) = 0, 'ALTER TABLE `appointments` ADD CONSTRAINT `appointments_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'created_by' AND REFERENCED_TABLE_NAME = 'users') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'appointments_created_by_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users')) = 0, 'ALTER TABLE `appointments` ADD CONSTRAINT `appointments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'personal_id' AND REFERENCED_TABLE_NAME = 'personals') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'appointments_personal_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals')) = 0, 'ALTER TABLE `appointments` ADD CONSTRAINT `appointments_personal_id_foreign` FOREIGN KEY (`personal_id`) REFERENCES `personals` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'service_id' AND REFERENCED_TABLE_NAME = 'services') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'appointments_service_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services')) = 0, 'ALTER TABLE `appointments` ADD CONSTRAINT `appointments_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_categories' AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME = 'companies') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'product_categories_company_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies')) = 0, 'ALTER TABLE `product_categories` ADD CONSTRAINT `product_categories_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_categories' AND COLUMN_NAME = 'created_by' AND REFERENCED_TABLE_NAME = 'users') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'product_categories_created_by_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users')) = 0, 'ALTER TABLE `product_categories` ADD CONSTRAINT `product_categories_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'brands' AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME = 'companies') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'brands_company_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies')) = 0, 'ALTER TABLE `brands` ADD CONSTRAINT `brands_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'brands' AND COLUMN_NAME = 'created_by' AND REFERENCED_TABLE_NAME = 'users') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'brands_created_by_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users')) = 0, 'ALTER TABLE `brands` ADD CONSTRAINT `brands_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME = 'companies') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'suppliers_company_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies')) = 0, 'ALTER TABLE `suppliers` ADD CONSTRAINT `suppliers_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'created_by' AND REFERENCED_TABLE_NAME = 'users') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'suppliers_created_by_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users')) = 0, 'ALTER TABLE `suppliers` ADD CONSTRAINT `suppliers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_methods' AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME = 'companies') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'payment_methods_company_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies')) = 0, 'ALTER TABLE `payment_methods` ADD CONSTRAINT `payment_methods_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME = 'companies') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'company_settings_company_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies')) = 0, 'ALTER TABLE `company_settings` ADD CONSTRAINT `company_settings_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME = 'companies') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'commission_rules_company_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies')) = 0, 'ALTER TABLE `commission_rules` ADD CONSTRAINT `commission_rules_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'personal_id' AND REFERENCED_TABLE_NAME = 'personals') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'commission_rules_personal_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals')) = 0, 'ALTER TABLE `commission_rules` ADD CONSTRAINT `commission_rules_personal_id_foreign` FOREIGN KEY (`personal_id`) REFERENCES `personals` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'service_category_id' AND REFERENCED_TABLE_NAME = 'service_categories') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'commission_rules_service_category_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_categories')) = 0, 'ALTER TABLE `commission_rules` ADD CONSTRAINT `commission_rules_service_category_id_foreign` FOREIGN KEY (`service_category_id`) REFERENCES `service_categories` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules' AND COLUMN_NAME = 'service_id' AND REFERENCED_TABLE_NAME = 'services') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'commission_rules_service_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services')) = 0, 'ALTER TABLE `commission_rules` ADD CONSTRAINT `commission_rules_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'cash_session_id' AND REFERENCED_TABLE_NAME = 'cash_sessions') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'commission_settlements_cash_session_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions')) = 0, 'ALTER TABLE `commission_settlements` ADD CONSTRAINT `commission_settlements_cash_session_id_foreign` FOREIGN KEY (`cash_session_id`) REFERENCES `cash_sessions` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME = 'companies') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'commission_settlements_company_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies')) = 0, 'ALTER TABLE `commission_settlements` ADD CONSTRAINT `commission_settlements_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'paid_by' AND REFERENCED_TABLE_NAME = 'users') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'commission_settlements_paid_by_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users')) = 0, 'ALTER TABLE `commission_settlements` ADD CONSTRAINT `commission_settlements_paid_by_foreign` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'personal_id' AND REFERENCED_TABLE_NAME = 'personals') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'commission_settlements_personal_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals')) = 0, 'ALTER TABLE `commission_settlements` ADD CONSTRAINT `commission_settlements_personal_id_foreign` FOREIGN KEY (`personal_id`) REFERENCES `personals` (`id`)', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'client_id' AND REFERENCED_TABLE_NAME = 'clients') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'sales_client_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients')) = 0, 'ALTER TABLE `sales` ADD CONSTRAINT `sales_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'personal_id' AND REFERENCED_TABLE_NAME = 'personals') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'sales_personal_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals')) = 0, 'ALTER TABLE `sales` ADD CONSTRAINT `sales_personal_id_foreign` FOREIGN KEY (`personal_id`) REFERENCES `personals` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'tip_settlement_id' AND REFERENCED_TABLE_NAME = 'commission_settlements') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'sales_tip_settlement_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements')) = 0, 'ALTER TABLE `sales` ADD CONSTRAINT `sales_tip_settlement_id_foreign` FOREIGN KEY (`tip_settlement_id`) REFERENCES `commission_settlements` (`id`)', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'personal_id' AND REFERENCED_TABLE_NAME = 'personals') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'sale_details_personal_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals')) = 0, 'ALTER TABLE `sale_details` ADD CONSTRAINT `sale_details_personal_id_foreign` FOREIGN KEY (`personal_id`) REFERENCES `personals` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'service_id' AND REFERENCED_TABLE_NAME = 'services') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'sale_details_service_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services')) = 0, 'ALTER TABLE `sale_details` ADD CONSTRAINT `sale_details_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'brand_id' AND REFERENCED_TABLE_NAME = 'brands') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'products_brand_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'brands')) = 0, 'ALTER TABLE `products` ADD CONSTRAINT `products_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'product_category_id' AND REFERENCED_TABLE_NAME = 'product_categories') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'products_product_category_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_categories')) = 0, 'ALTER TABLE `products` ADD CONSTRAINT `products_product_category_id_foreign` FOREIGN KEY (`product_category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'supplier_id' AND REFERENCED_TABLE_NAME = 'suppliers') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'entries_supplier_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers')) = 0, 'ALTER TABLE `entries` ADD CONSTRAINT `entries_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME = 'companies') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'cash_movements_company_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies')) = 0, 'ALTER TABLE `cash_movements` ADD CONSTRAINT `cash_movements_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'personal_id' AND REFERENCED_TABLE_NAME = 'personals') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'commissions_personal_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals')) = 0, 'ALTER TABLE `commissions` ADD CONSTRAINT `commissions_personal_id_foreign` FOREIGN KEY (`personal_id`) REFERENCES `personals` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'reverses_id' AND REFERENCED_TABLE_NAME = 'commissions') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'commissions_reverses_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions')) = 0, 'ALTER TABLE `commissions` ADD CONSTRAINT `commissions_reverses_id_foreign` FOREIGN KEY (`reverses_id`) REFERENCES `commissions` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'rule_id' AND REFERENCED_TABLE_NAME = 'commission_rules') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'commissions_rule_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_rules')) = 0, 'ALTER TABLE `commissions` ADD CONSTRAINT `commissions_rule_id_foreign` FOREIGN KEY (`rule_id`) REFERENCES `commission_rules` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'sale_detail_id' AND REFERENCED_TABLE_NAME = 'sale_details') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'commissions_sale_detail_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details')) = 0, 'ALTER TABLE `commissions` ADD CONSTRAINT `commissions_sale_detail_id_foreign` FOREIGN KEY (`sale_detail_id`) REFERENCES `sale_details` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'settlement_id' AND REFERENCED_TABLE_NAME = 'commission_settlements') + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'commissions_settlement_id_foreign') + (1 - (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements')) = 0, 'ALTER TABLE `commissions` ADD CONSTRAINT `commissions_settlement_id_foreign` FOREIGN KEY (`settlement_id`) REFERENCES `commission_settlements` (`id`)', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 5. Permisos de los módulos (scripts insert_permisos_*.sql, ya idempotentes)
-- ---------------------------------------------------------------------

-- >>> insert_permisos_faltantes.sql
-- ============================================================
-- Inserta los permisos (menús) que faltan en la tabla `permissions`
-- Base de datos: salon_estetica
-- Módulos agregados: cargos, personal, branches (sucursales), cajas
--
-- Es idempotente: solo inserta el permiso si su `slug` aún no existe,
-- por lo que puede ejecutarse varias veces sin duplicar registros.
-- ============================================================

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
    -- Cargos
    SELECT 'Ver Cargos'        AS name, 'cargos.view'     AS slug, 'cargos'   AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Crear Cargos',      'cargos.create',   'cargos',   NULL, NOW(), NOW()
    UNION ALL SELECT 'Editar Cargos',     'cargos.edit',     'cargos',   NULL, NOW(), NOW()
    UNION ALL SELECT 'Eliminar Cargos',   'cargos.delete',   'cargos',   NULL, NOW(), NOW()

    -- Personal
    UNION ALL SELECT 'Ver Personal',      'personal.view',   'personal', NULL, NOW(), NOW()
    UNION ALL SELECT 'Crear Personal',    'personal.create', 'personal', NULL, NOW(), NOW()
    UNION ALL SELECT 'Editar Personal',   'personal.edit',   'personal', NULL, NOW(), NOW()
    UNION ALL SELECT 'Eliminar Personal', 'personal.delete', 'personal', NULL, NOW(), NOW()

    -- Sucursales
    UNION ALL SELECT 'Ver Sucursales',      'branches.view',   'branches', NULL, NOW(), NOW()
    UNION ALL SELECT 'Crear Sucursales',    'branches.create', 'branches', NULL, NOW(), NOW()
    UNION ALL SELECT 'Editar Sucursales',   'branches.edit',   'branches', NULL, NOW(), NOW()
    UNION ALL SELECT 'Eliminar Sucursales', 'branches.delete', 'branches', NULL, NOW(), NOW()

    -- Cajas
    UNION ALL SELECT 'Ver Cajas',      'cajas.view',   'cajas', NULL, NOW(), NOW()
    UNION ALL SELECT 'Crear Cajas',    'cajas.create', 'cajas', NULL, NOW(), NOW()
    UNION ALL SELECT 'Editar Cajas',   'cajas.edit',   'cajas', NULL, NOW(), NOW()
    UNION ALL SELECT 'Eliminar Cajas', 'cajas.delete', 'cajas', NULL, NOW(), NOW()
) AS nuevos
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug
);

-- >>> insert_permisos_saas.sql
-- ============================================================
-- Permisos de los módulos SaaS: planes, suscripciones y pagos.
-- Idempotente: solo inserta si el `slug` no existe.
-- (Estos módulos son solo para super admin, pero se registran
--  por consistencia con el resto de permisos.)
-- ============================================================

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
    -- Planes
    SELECT 'Ver Planes'        AS name, 'plans.view'     AS slug, 'plans' AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Crear Planes',    'plans.create', 'plans', NULL, NOW(), NOW()
    UNION ALL SELECT 'Editar Planes',   'plans.edit',   'plans', NULL, NOW(), NOW()
    UNION ALL SELECT 'Eliminar Planes', 'plans.delete', 'plans', NULL, NOW(), NOW()

    -- Suscripciones
    UNION ALL SELECT 'Ver Suscripciones',    'subscriptions.view',   'subscriptions', NULL, NOW(), NOW()
    UNION ALL SELECT 'Gestionar Suscripciones', 'subscriptions.manage', 'subscriptions', NULL, NOW(), NOW()

    -- Pagos de suscripción
    UNION ALL SELECT 'Ver Pagos',       'payments.view',    'payments', NULL, NOW(), NOW()
    UNION ALL SELECT 'Registrar Pagos', 'payments.create',  'payments', NULL, NOW(), NOW()
    UNION ALL SELECT 'Confirmar Pagos', 'payments.confirm', 'payments', NULL, NOW(), NOW()
) AS nuevos
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug
);

-- >>> insert_permisos_operativos.sql
-- ============================================================
-- Permisos de los módulos operativos del salón + asignación a
-- roles admin / manager / gerente_c. Idempotente (reejecutable).
-- ============================================================

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
    -- Clientes
    SELECT 'Ver Clientes'        AS name, 'clients.view'   AS slug, 'clients' AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Crear Clientes','clients.create','clients',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Clientes','clients.edit','clients',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Clientes','clients.delete','clients',NULL,NOW(),NOW()
    -- Agenda / citas
    UNION ALL SELECT 'Ver Citas','appointments.view','appointments',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Citas','appointments.create','appointments',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Citas','appointments.edit','appointments',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Citas','appointments.delete','appointments',NULL,NOW(),NOW()
    -- Servicios
    UNION ALL SELECT 'Ver Servicios','services.view','services',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Servicios','services.create','services',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Servicios','services.edit','services',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Servicios','services.delete','services',NULL,NOW(),NOW()
    -- Categorías de servicio
    UNION ALL SELECT 'Ver Categorías de servicio','service-categories.view','service-categories',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Categorías de servicio','service-categories.create','service-categories',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Categorías de servicio','service-categories.edit','service-categories',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Categorías de servicio','service-categories.delete','service-categories',NULL,NOW(),NOW()
    -- Productos
    UNION ALL SELECT 'Ver Productos','products.view','products',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Productos','products.create','products',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Productos','products.edit','products',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Productos','products.delete','products',NULL,NOW(),NOW()
    -- Categorías de producto
    UNION ALL SELECT 'Ver Categorías de producto','product-categories.view','product-categories',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Categorías de producto','product-categories.create','product-categories',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Categorías de producto','product-categories.edit','product-categories',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Categorías de producto','product-categories.delete','product-categories',NULL,NOW(),NOW()
    -- Marcas
    UNION ALL SELECT 'Ver Marcas','brands.view','brands',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Marcas','brands.create','brands',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Marcas','brands.edit','brands',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Marcas','brands.delete','brands',NULL,NOW(),NOW()
    -- Existencias
    UNION ALL SELECT 'Ver Existencias','stock.view','stock',NULL,NOW(),NOW()
    UNION ALL SELECT 'Gestionar Existencias','stock.manage','stock',NULL,NOW(),NOW()
    -- Proveedores
    UNION ALL SELECT 'Ver Proveedores','suppliers.view','suppliers',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Proveedores','suppliers.create','suppliers',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Proveedores','suppliers.edit','suppliers',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Proveedores','suppliers.delete','suppliers',NULL,NOW(),NOW()
    -- Compras
    UNION ALL SELECT 'Ver Compras','purchases.view','purchases',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Compras','purchases.create','purchases',NULL,NOW(),NOW()
    UNION ALL SELECT 'Recibir Compras','purchases.receive','purchases',NULL,NOW(),NOW()
    UNION ALL SELECT 'Anular Compras','purchases.void','purchases',NULL,NOW(),NOW()
    -- Ventas
    UNION ALL SELECT 'Ver Ventas','sales.view','sales',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Ventas','sales.create','sales',NULL,NOW(),NOW()
    UNION ALL SELECT 'Anular Ventas','sales.void','sales',NULL,NOW(),NOW()
    -- Caja
    UNION ALL SELECT 'Ver Caja','cash.view','cash',NULL,NOW(),NOW()
    UNION ALL SELECT 'Abrir Caja','cash.open','cash',NULL,NOW(),NOW()
    UNION ALL SELECT 'Movimientos de Caja','cash.move','cash',NULL,NOW(),NOW()
    UNION ALL SELECT 'Cerrar Caja','cash.close','cash',NULL,NOW(),NOW()
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug);

-- Asignar TODOS los permisos operativos/administrativos a los roles de administración
-- (evita bloquear a los usuarios actuales). Se excluyen módulos exclusivos de super admin.
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug IN ('admin','manager','gerente_c')
  AND p.module NOT IN ('companies','plans','subscriptions','payments','roles');

-- >>> insert_permisos_ingresos_gastos.sql
-- Permisos del módulo Ingresos y Gastos. Idempotente (reejecutable).
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
    SELECT 'Ver Ingresos'      AS name, 'incomes.view'    AS slug, 'incomes'  AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Registrar Ingresos','incomes.create','incomes',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Ingresos','incomes.delete','incomes',NULL,NOW(),NOW()
    UNION ALL SELECT 'Ver Gastos','expenses.view','expenses',NULL,NOW(),NOW()
    UNION ALL SELECT 'Registrar Gastos','expenses.create','expenses',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Gastos','expenses.delete','expenses',NULL,NOW(),NOW()
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug);

-- Asignar a los roles de administración.
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug IN ('admin','manager','gerente_c')
  AND p.module IN ('incomes','expenses');

-- >>> insert_permisos_configuracion.sql
-- Permisos de Métodos de pago y Preferencias. Idempotente.
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
    SELECT 'Ver Metodos de pago' AS name, 'payment-methods.view' AS slug, 'payment-methods' AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Crear Metodos de pago','payment-methods.create','payment-methods',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Metodos de pago','payment-methods.edit','payment-methods',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Metodos de pago','payment-methods.delete','payment-methods',NULL,NOW(),NOW()
    UNION ALL SELECT 'Ver Preferencias','settings.view','settings',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Preferencias','settings.edit','settings',NULL,NOW(),NOW()
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug);

INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p
WHERE r.slug IN ('admin','manager','gerente_c') AND p.module IN ('payment-methods','settings');

-- >>> insert_permisos_mi_empresa.sql
-- Permisos de "Mi empresa" (datos de la propia empresa). Idempotente.
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
    SELECT 'Ver Mi empresa' AS name, 'my-company.view' AS slug, 'my-company' AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Editar Mi empresa','my-company.edit','my-company',NULL,NOW(),NOW()
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug);

INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p
WHERE r.slug IN ('admin','manager','gerente_c') AND p.module = 'my-company';


-- ---------------------------------------------------------------------
-- 6. Módulo de comisiones (contenido de actualizar_modulo_comisiones.sql)
-- ---------------------------------------------------------------------
-- ---------------------------------------------------------------------
-- 1. Reglas de comisión
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commission_rules` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`          BIGINT UNSIGNED NOT NULL,
    `personal_id`         BIGINT UNSIGNED NULL COMMENT 'NULL = todo el equipo',
    `applies_to`          VARCHAR(20)     NOT NULL COMMENT 'services | products | category | service',
    `service_id`          BIGINT UNSIGNED NULL COMMENT 'solo si applies_to = service',
    `service_category_id` BIGINT UNSIGNED NULL COMMENT 'solo si applies_to = category',
    `type`                VARCHAR(20)     NOT NULL DEFAULT 'percent' COMMENT 'percent | fixed',
    `value`               DECIMAL(10,2)   NOT NULL DEFAULT 0,
    `valid_from`          DATE            NULL,
    `valid_until`         DATE            NULL,
    `active`              TINYINT(1)      NOT NULL DEFAULT 1,
    `notes`               TEXT            NULL,
    `created_by`          BIGINT UNSIGNED NULL,
    `created_at`          TIMESTAMP       NULL,
    `updated_at`          TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `commission_rules_company_active_index` (`company_id`, `active`),
    CONSTRAINT `commission_rules_company_id_foreign`          FOREIGN KEY (`company_id`)          REFERENCES `companies` (`id`)          ON DELETE CASCADE,
    CONSTRAINT `commission_rules_personal_id_foreign`         FOREIGN KEY (`personal_id`)         REFERENCES `personals` (`id`)          ON DELETE CASCADE,
    CONSTRAINT `commission_rules_service_id_foreign`          FOREIGN KEY (`service_id`)          REFERENCES `services` (`id`)           ON DELETE CASCADE,
    CONSTRAINT `commission_rules_service_category_id_foreign` FOREIGN KEY (`service_category_id`) REFERENCES `service_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. Liquidaciones (pagos al profesional)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commission_settlements` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`        BIGINT UNSIGNED NOT NULL,
    `personal_id`       BIGINT UNSIGNED NOT NULL,
    `number`            VARCHAR(20)     NOT NULL COMMENT 'LIQ-0001, único por empresa',
    `period_start`      DATE            NOT NULL,
    `period_end`        DATE            NOT NULL,
    `commissions_count` INT UNSIGNED    NOT NULL DEFAULT 0,
    `commissions_total` DECIMAL(14,2)   NOT NULL DEFAULT 0,
    `tips_total`        DECIMAL(14,2)   NOT NULL DEFAULT 0,
    `total`             DECIMAL(14,2)   NOT NULL DEFAULT 0,
    `include_tips`      TINYINT(1)      NOT NULL DEFAULT 0,
    `paid_at`           DATETIME        NOT NULL,
    `paid_by`           BIGINT UNSIGNED NULL,
    `cash_session_id`   BIGINT UNSIGNED NULL,
    `cash_movement_id`  BIGINT UNSIGNED NULL,
    `notes`             TEXT            NULL,
    `created_at`        TIMESTAMP       NULL,
    `updated_at`        TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `commission_settlements_company_number_unique` (`company_id`, `number`),
    KEY `commission_settlements_company_personal_index` (`company_id`, `personal_id`, `paid_at`),
    CONSTRAINT `commission_settlements_company_id_foreign`      FOREIGN KEY (`company_id`)      REFERENCES `companies` (`id`)     ON DELETE CASCADE,
    CONSTRAINT `commission_settlements_personal_id_foreign`     FOREIGN KEY (`personal_id`)     REFERENCES `personals` (`id`),
    CONSTRAINT `commission_settlements_paid_by_foreign`         FOREIGN KEY (`paid_by`)         REFERENCES `users` (`id`)         ON DELETE SET NULL,
    CONSTRAINT `commission_settlements_cash_session_id_foreign` FOREIGN KEY (`cash_session_id`) REFERENCES `cash_sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3. Comisión devengada: trazabilidad y copia de la regla aplicada
--    Patrón: se arma el ALTER solo si la columna/índice/FK no existe.
-- ---------------------------------------------------------------------
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `commissions` ADD COLUMN `rule_id` BIGINT UNSIGNED NULL AFTER `sale_detail_id`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'rule_id');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `commissions` ADD COLUMN `settlement_id` BIGINT UNSIGNED NULL AFTER `rule_id`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'settlement_id');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `commissions` ADD COLUMN `reverses_id` BIGINT UNSIGNED NULL AFTER `settlement_id`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'reverses_id');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `commissions` ADD COLUMN `base` DECIMAL(14,2) NULL AFTER `reverses_id`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'base');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `commissions` ADD COLUMN `calc_type` VARCHAR(20) NULL AFTER `base`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'calc_type');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `commissions` ADD COLUMN `calc_value` DECIMAL(10,2) NULL AFTER `calc_type`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'calc_value');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `commissions` ADD COLUMN `accrued_at` DATETIME NULL AFTER `calc_value`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND COLUMN_NAME = 'accrued_at');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

-- Índices para el resumen por profesional/periodo y para las liquidaciones
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `commissions` ADD INDEX `commissions_company_personal_accrued_index` (`company_id`, `personal_id`, `accrued_at`)', 'DO 0')
           FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND INDEX_NAME = 'commissions_company_personal_accrued_index');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `commissions` ADD INDEX `commissions_settlement_id_index` (`settlement_id`)', 'DO 0')
           FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND INDEX_NAME = 'commissions_settlement_id_index');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

-- Claves foráneas
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `commissions` ADD CONSTRAINT `commissions_rule_id_foreign` FOREIGN KEY (`rule_id`) REFERENCES `commission_rules` (`id`) ON DELETE SET NULL', 'DO 0')
           FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND CONSTRAINT_NAME = 'commissions_rule_id_foreign');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `commissions` ADD CONSTRAINT `commissions_settlement_id_foreign` FOREIGN KEY (`settlement_id`) REFERENCES `commission_settlements` (`id`)', 'DO 0')
           FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND CONSTRAINT_NAME = 'commissions_settlement_id_foreign');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `commissions` ADD CONSTRAINT `commissions_reverses_id_foreign` FOREIGN KEY (`reverses_id`) REFERENCES `commissions` (`id`) ON DELETE SET NULL', 'DO 0')
           FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commissions' AND CONSTRAINT_NAME = 'commissions_reverses_id_foreign');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

-- Completa las comisiones antiguas con los datos que antes no se guardaban
UPDATE `commissions` c
LEFT JOIN `sale_details` d ON d.`id` = c.`sale_detail_id`
SET c.`base` = COALESCE(d.`total`, c.`amount`)
WHERE c.`base` IS NULL;

UPDATE `commissions` SET `calc_type` = 'percent', `calc_value` = `rate` WHERE `calc_type` IS NULL;
UPDATE `commissions` SET `accrued_at` = `created_at` WHERE `accrued_at` IS NULL;


-- ---------------------------------------------------------------------
-- 4. Venta: profesional principal y propina
-- ---------------------------------------------------------------------
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD COLUMN `personal_id` BIGINT UNSIGNED NULL AFTER `client_id`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'personal_id');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD COLUMN `tip` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `total`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'tip');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD COLUMN `tip_settlement_id` BIGINT UNSIGNED NULL AFTER `tip`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'tip_settlement_id');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD INDEX `sales_company_personal_date_index` (`company_id`, `personal_id`, `sale_date`)', 'DO 0')
           FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND INDEX_NAME = 'sales_company_personal_date_index');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD CONSTRAINT `sales_personal_id_foreign` FOREIGN KEY (`personal_id`) REFERENCES `personals` (`id`) ON DELETE SET NULL', 'DO 0')
           FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND CONSTRAINT_NAME = 'sales_personal_id_foreign');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD CONSTRAINT `sales_tip_settlement_id_foreign` FOREIGN KEY (`tip_settlement_id`) REFERENCES `commission_settlements` (`id`)', 'DO 0')
           FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND CONSTRAINT_NAME = 'sales_tip_settlement_id_foreign');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 5. El % que tenía cada servicio pasa a ser una regla de "todo el equipo"
--    (antes la comisión salía de services.commission_percent).
--    Solo para empresas que todavía no tienen ninguna regla, así una
--    reejecución no vuelve a crear reglas que el salón ya borró.
-- ---------------------------------------------------------------------
INSERT INTO `commission_rules`
    (`company_id`, `personal_id`, `applies_to`, `service_id`, `type`, `value`, `active`, `notes`, `created_at`, `updated_at`)
SELECT s.`company_id`, NULL, 'service', s.`id`, 'percent', s.`commission_percent`, 1,
       'Migrada del % de comisión que tenía el servicio', NOW(), NOW()
FROM `services` s
WHERE s.`commission_percent` > 0
  AND s.`deleted_at` IS NULL
  AND NOT EXISTS (SELECT 1 FROM `commission_rules` r WHERE r.`company_id` = s.`company_id`);


-- ---------------------------------------------------------------------
-- 6. "Comisiones" pasa a ser un módulo de plan. Se agrega a todos los
--    planes existentes para que ninguna empresa pierda lo que ya tenía.
-- ---------------------------------------------------------------------
UPDATE `plans`
SET `features` = JSON_ARRAY_APPEND(`features`, '$', 'commissions')
WHERE `features` IS NOT NULL
  AND JSON_VALID(`features`)
  AND NOT JSON_CONTAINS(`features`, '"commissions"');


-- ---------------------------------------------------------------------
-- 7. Permisos
--    commissions.view → ver resumen, detalle, liquidaciones e historial
--    commissions.pay  → liquidar (pagar)
--    commission-rules.* → gestionar reglas
-- ---------------------------------------------------------------------
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
              SELECT 'Ver Comisiones' AS name, 'commissions.view' AS slug, 'commissions' AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Liquidar Comisiones',     'commissions.pay',         'commissions',      NULL, NOW(), NOW()
    UNION ALL SELECT 'Ver Reglas de comision',     'commission-rules.view',   'commission-rules', NULL, NOW(), NOW()
    UNION ALL SELECT 'Crear Reglas de comision',   'commission-rules.create', 'commission-rules', NULL, NOW(), NOW()
    UNION ALL SELECT 'Editar Reglas de comision',  'commission-rules.edit',   'commission-rules', NULL, NOW(), NOW()
    UNION ALL SELECT 'Eliminar Reglas de comision','commission-rules.delete', 'commission-rules', NULL, NOW(), NOW()
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug);

-- "Pagar" ahora significa liquidar
UPDATE `permissions` SET `name` = 'Liquidar Comisiones' WHERE `slug` = 'commissions.pay';

-- Dueño / administración: todo
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r CROSS JOIN `permissions` p
WHERE r.`slug` = 'admin' AND p.`module` IN ('commissions', 'commission-rules');

-- Encargados: ver y liquidar; reglas solo en lectura
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r CROSS JOIN `permissions` p
WHERE r.`slug` IN ('manager', 'gerente_c')
  AND p.`slug` IN ('commissions.view', 'commissions.pay', 'commission-rules.view');

-- Fin del script.


-- ---------------------------------------------------------------------
-- 7. Registro en la tabla de migraciones, para que un futuro
--    `php artisan migrate` no intente volver a aplicarlas.
-- ---------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_07_24_000001_add_theme_colors_to_companies_table' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_24_000001_add_theme_colors_to_companies_table');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_07_25_000001_create_plans_table' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_25_000001_create_plans_table');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_07_25_000002_create_subscriptions_table' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_25_000002_create_subscriptions_table');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_07_25_000003_create_subscription_payments_table' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_25_000003_create_subscription_payments_table');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_07_26_000001_make_user_id_nullable_on_personals' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_26_000001_make_user_id_nullable_on_personals');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_07_26_000002_create_clients_table' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_26_000002_create_clients_table');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_07_27_000001_create_service_categories_table' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_27_000001_create_service_categories_table');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_07_27_000002_create_services_table' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_27_000002_create_services_table');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_07_28_000001_create_appointments_table' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_28_000001_create_appointments_table');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_07_29_000001_adapt_sales_for_salon' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_29_000001_adapt_sales_for_salon');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_07_30_000001_setup_inventory' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_30_000001_setup_inventory');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_07_31_000001_setup_purchasing' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_07_31_000001_setup_purchasing');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_09_23_000001_add_overrides_to_subscriptions' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_09_23_000001_add_overrides_to_subscriptions');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_09_27_000001_fix_legacy_enums_and_extend_cash_movements' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_09_27_000001_fix_legacy_enums_and_extend_cash_movements');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_09_27_000002_setup_commissions' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_09_27_000002_setup_commissions');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_09_27_000003_relax_required_legacy_columns' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_09_27_000003_relax_required_legacy_columns');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_10_11_000001_create_payment_methods_and_company_settings' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_11_000001_create_payment_methods_and_company_settings');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_10_12_000001_setup_commission_rules_and_settlements' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_12_000001_setup_commission_rules_and_settlements');

-- Fin del script.
