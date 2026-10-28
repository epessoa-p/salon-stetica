-- =====================================================================
--  Glamora · Marketing: plantillas, recordatorios y cumpleaños por
--  WhatsApp, cupones, promociones, campañas y fotos antes/después
--
--  SEGURO DE REEJECUTAR: cada tabla, columna, clave foránea y permiso
--  comprueba antes si ya existe, así que ejecutarlo de nuevo no duplica
--  ni rompe nada. Probado dos veces sobre una base de prueba.
--  Compatible con MySQL 5.7+ y MariaDB 10.2+ (sin DELIMITER).
--
--  ORDEN EN PRODUCCIÓN: después de actualizar_cuentas_cobrar_pagar.sql.
--  Ejecutar completo: phpMyAdmin → pestaña SQL → pegar y ejecutar.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Plantillas de mensajes (WhatsApp) con variables como {nombre}
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `message_templates` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `type`       VARCHAR(20)     NOT NULL COMMENT 'reminder | birthday | campaign | general',
    `name`       VARCHAR(120)    NOT NULL,
    `body`       TEXT            NOT NULL,
    `is_default` TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'la que se propone para su tipo',
    `active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP       NULL,
    `updated_at` TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `message_templates_company_type_index` (`company_id`, `type`),
    CONSTRAINT `message_templates_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. Cupones (con código) y promociones (automáticas)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `coupons` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`          BIGINT UNSIGNED NOT NULL,
    `client_id`           BIGINT UNSIGNED NULL COMMENT 'cupón personal: solo lo usa este cliente',
    `code`                VARCHAR(40)     NOT NULL,
    `name`                VARCHAR(120)    NOT NULL,
    `discount_type`       VARCHAR(10)     NOT NULL DEFAULT 'percent' COMMENT 'percent | amount',
    `value`               DECIMAL(12,2)   NOT NULL DEFAULT 0,
    `applies_to`          VARCHAR(20)     NOT NULL DEFAULT 'all' COMMENT 'all | services | products',
    `min_purchase`        DECIMAL(12,2)   NULL,
    `max_discount`        DECIMAL(12,2)   NULL COMMENT 'tope del descuento en %',
    `starts_on`           DATE            NULL,
    `ends_on`             DATE            NULL,
    `max_uses`            INT UNSIGNED    NULL,
    `max_uses_per_client` INT UNSIGNED    NULL,
    `active`              TINYINT(1)      NOT NULL DEFAULT 1,
    `notes`               VARCHAR(255)    NULL,
    `created_by`          BIGINT UNSIGNED NULL,
    `created_at`          TIMESTAMP       NULL,
    `updated_at`          TIMESTAMP       NULL,
    `deleted_at`          TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `coupons_company_code_unique` (`company_id`, `code`),
    CONSTRAINT `coupons_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `coupons_client_id_foreign`  FOREIGN KEY (`client_id`)  REFERENCES `clients` (`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `promotions` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`          BIGINT UNSIGNED NOT NULL,
    `name`                VARCHAR(120)    NOT NULL,
    `discount_type`       VARCHAR(10)     NOT NULL DEFAULT 'percent' COMMENT 'percent | amount (por unidad)',
    `value`               DECIMAL(12,2)   NOT NULL DEFAULT 0,
    `applies_to`          VARCHAR(20)     NOT NULL DEFAULT 'services' COMMENT 'services | products | service | category | all',
    `service_id`          BIGINT UNSIGNED NULL,
    `service_category_id` BIGINT UNSIGNED NULL,
    `weekdays`            VARCHAR(20)     NULL COMMENT 'días ISO separados por coma (1 = lunes), NULL = todos',
    `starts_on`           DATE            NULL,
    `ends_on`             DATE            NULL,
    `active`              TINYINT(1)      NOT NULL DEFAULT 1,
    `created_by`          BIGINT UNSIGNED NULL,
    `created_at`          TIMESTAMP       NULL,
    `updated_at`          TIMESTAMP       NULL,
    `deleted_at`          TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `promotions_company_active_index` (`company_id`, `active`),
    CONSTRAINT `promotions_company_id_foreign`          FOREIGN KEY (`company_id`)          REFERENCES `companies` (`id`)          ON DELETE CASCADE,
    CONSTRAINT `promotions_service_id_foreign`          FOREIGN KEY (`service_id`)          REFERENCES `services` (`id`)           ON DELETE CASCADE,
    CONSTRAINT `promotions_service_category_id_foreign` FOREIGN KEY (`service_category_id`) REFERENCES `service_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3. Campañas: segmento de clientes + mensaje, con avance de envío
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `campaigns` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `name`       VARCHAR(120)    NOT NULL,
    `body`       TEXT            NOT NULL,
    `filters`    TEXT            NULL COMMENT 'segmento en JSON',
    `coupon_id`  BIGINT UNSIGNED NULL,
    `status`     VARCHAR(20)     NOT NULL DEFAULT 'active' COMMENT 'active | done',
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP       NULL,
    `updated_at` TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `campaigns_company_index` (`company_id`, `status`),
    CONSTRAINT `campaigns_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `campaigns_coupon_id_foreign`  FOREIGN KEY (`coupon_id`)  REFERENCES `coupons` (`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `campaign_recipients` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id` BIGINT UNSIGNED NOT NULL,
    `client_id`   BIGINT UNSIGNED NOT NULL,
    `sent_at`     DATETIME        NULL,
    `sent_by`     BIGINT UNSIGNED NULL,
    `created_at`  TIMESTAMP       NULL,
    `updated_at`  TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `campaign_recipients_campaign_client_unique` (`campaign_id`, `client_id`),
    CONSTRAINT `campaign_recipients_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `campaign_recipients_client_id_foreign`   FOREIGN KEY (`client_id`)   REFERENCES `clients` (`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 4. Registro de mensajes enviados (quién contactó a quién y cuándo)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `message_logs` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`     BIGINT UNSIGNED NOT NULL,
    `client_id`      BIGINT UNSIGNED NOT NULL,
    `type`           VARCHAR(20)     NOT NULL COMMENT 'reminder | birthday | campaign | general',
    `channel`        VARCHAR(20)     NOT NULL DEFAULT 'whatsapp',
    `appointment_id` BIGINT UNSIGNED NULL,
    `campaign_id`    BIGINT UNSIGNED NULL,
    `phone`          VARCHAR(30)     NULL,
    `body`           TEXT            NULL,
    `sent_by`        BIGINT UNSIGNED NULL,
    `sent_at`        DATETIME        NOT NULL,
    `created_at`     TIMESTAMP       NULL,
    `updated_at`     TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `message_logs_company_type_index` (`company_id`, `type`, `sent_at`),
    KEY `message_logs_appointment_index` (`appointment_id`),
    KEY `message_logs_client_index` (`client_id`, `sent_at`),
    CONSTRAINT `message_logs_company_id_foreign`     FOREIGN KEY (`company_id`)     REFERENCES `companies` (`id`)    ON DELETE CASCADE,
    CONSTRAINT `message_logs_client_id_foreign`      FOREIGN KEY (`client_id`)      REFERENCES `clients` (`id`)      ON DELETE CASCADE,
    CONSTRAINT `message_logs_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `message_logs_campaign_id_foreign`    FOREIGN KEY (`campaign_id`)    REFERENCES `campaigns` (`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 5. Fotos antes/después del cliente (archivos privados en storage)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `client_photos` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`     BIGINT UNSIGNED NOT NULL,
    `client_id`      BIGINT UNSIGNED NOT NULL,
    `appointment_id` BIGINT UNSIGNED NULL,
    `service_id`     BIGINT UNSIGNED NULL,
    `kind`           VARCHAR(10)     NOT NULL DEFAULT 'other' COMMENT 'before | after | other',
    `path`           VARCHAR(255)    NOT NULL,
    `thumb_path`     VARCHAR(255)    NULL,
    `taken_on`       DATE            NOT NULL,
    `notes`          VARCHAR(255)    NULL,
    `uploaded_by`    BIGINT UNSIGNED NULL,
    `created_at`     TIMESTAMP       NULL,
    `updated_at`     TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `client_photos_client_index` (`client_id`, `taken_on`),
    CONSTRAINT `client_photos_company_id_foreign`     FOREIGN KEY (`company_id`)     REFERENCES `companies` (`id`)    ON DELETE CASCADE,
    CONSTRAINT `client_photos_client_id_foreign`      FOREIGN KEY (`client_id`)      REFERENCES `clients` (`id`)      ON DELETE CASCADE,
    CONSTRAINT `client_photos_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `client_photos_service_id_foreign`     FOREIGN KEY (`service_id`)     REFERENCES `services` (`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 6. Columnas en ventas: cupón y promociones aplicadas
-- ---------------------------------------------------------------------
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD COLUMN `coupon_id` BIGINT UNSIGNED NULL AFTER `quote_id`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'coupon_id');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD COLUMN `coupon_discount` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `coupon_id`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'coupon_discount');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD COLUMN `promotion_discount` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `coupon_discount`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'promotion_discount');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'sales_coupon_id_foreign') = 0,
           'ALTER TABLE `sales` ADD CONSTRAINT `sales_coupon_id_foreign` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sale_details` ADD COLUMN `promotion_id` BIGINT UNSIGNED NULL AFTER `client_package_id`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'promotion_id');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sale_details` ADD COLUMN `promotion_discount` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `package_discount`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'promotion_discount');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'sale_details_promotion_id_foreign') = 0,
           'ALTER TABLE `sale_details` ADD CONSTRAINT `sale_details_promotion_id_foreign` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 7. Permisos
-- ---------------------------------------------------------------------
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
              SELECT 'Ver Recordatorios de citas' AS name, 'reminders.view' AS slug, 'reminders' AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Ver Marketing',                  'marketing.view',   'marketing',  NULL, NOW(), NOW()
    UNION ALL SELECT 'Enviar mensajes de Marketing',   'marketing.send',   'marketing',  NULL, NOW(), NOW()
    UNION ALL SELECT 'Editar plantillas y campañas',   'marketing.edit',   'marketing',  NULL, NOW(), NOW()
    UNION ALL SELECT 'Ver Cupones',                    'coupons.view',     'coupons',    NULL, NOW(), NOW()
    UNION ALL SELECT 'Crear Cupones',                  'coupons.create',   'coupons',    NULL, NOW(), NOW()
    UNION ALL SELECT 'Editar Cupones',                 'coupons.edit',     'coupons',    NULL, NOW(), NOW()
    UNION ALL SELECT 'Eliminar Cupones',               'coupons.delete',   'coupons',    NULL, NOW(), NOW()
    UNION ALL SELECT 'Ver Promociones',                'promotions.view',  'promotions', NULL, NOW(), NOW()
    UNION ALL SELECT 'Crear Promociones',              'promotions.create','promotions', NULL, NOW(), NOW()
    UNION ALL SELECT 'Editar Promociones',             'promotions.edit',  'promotions', NULL, NOW(), NOW()
    UNION ALL SELECT 'Eliminar Promociones',           'promotions.delete','promotions', NULL, NOW(), NOW()
    UNION ALL SELECT 'Ver fotos de clientes',          'photos.view',      'photos',     NULL, NOW(), NOW()
    UNION ALL SELECT 'Subir fotos de clientes',        'photos.create',    'photos',     NULL, NOW(), NOW()
    UNION ALL SELECT 'Eliminar fotos de clientes',     'photos.delete',    'photos',     NULL, NOW(), NOW()
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug);

-- Dueño y encargados: todo
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r CROSS JOIN `permissions` p
WHERE r.`slug` IN ('admin', 'manager', 'gerente_c')
  AND p.`module` IN ('reminders', 'marketing', 'coupons', 'promotions', 'photos');


-- ---------------------------------------------------------------------
-- 8. Registro de la migración, para que un futuro `php artisan migrate`
--    no la repita
-- ---------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_10_24_000001_setup_marketing' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_24_000001_setup_marketing');

-- Fin del script.
