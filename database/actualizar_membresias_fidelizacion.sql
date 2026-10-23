-- =====================================================================
--  Glamora · Membresías y Fidelización (puntos)
--
--  SEGURO DE REEJECUTAR: cada tabla, columna, clave foránea, permiso y
--  plan comprueba antes si ya existe, así que ejecutarlo de nuevo no
--  duplica ni rompe nada. Probado dos veces sobre una base de prueba.
--  Compatible con MySQL 5.7+ y MariaDB 10.2+ (sin DELIMITER).
--
--  ORDEN EN PRODUCCIÓN: primero actualizar_produccion.sql (si aún no se
--  ejecutó), después este archivo.
--  Ejecutar completo: phpMyAdmin → pestaña SQL → pegar y ejecutar.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Planes de membresía (catálogo que vende el salón)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `membership_plans` (
    `id`                  BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `company_id`          BIGINT UNSIGNED   NOT NULL,
    `name`                VARCHAR(120)      NOT NULL,
    `description`         TEXT              NULL,
    `price`               DECIMAL(12,2)     NOT NULL DEFAULT 0,
    `duration_days`       SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    `discount_percent`    DECIMAL(5,2)      NOT NULL DEFAULT 0,
    `discount_scope`      VARCHAR(20)       NOT NULL DEFAULT 'services' COMMENT 'services | products | category | all',
    `service_category_id` BIGINT UNSIGNED   NULL COMMENT 'solo si discount_scope = category',
    `active`              TINYINT(1)        NOT NULL DEFAULT 1,
    `sort_order`          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_by`          BIGINT UNSIGNED   NULL,
    `created_at`          TIMESTAMP         NULL,
    `updated_at`          TIMESTAMP         NULL,
    `deleted_at`          TIMESTAMP         NULL,
    PRIMARY KEY (`id`),
    KEY `membership_plans_company_active_index` (`company_id`, `active`),
    CONSTRAINT `membership_plans_company_id_foreign`          FOREIGN KEY (`company_id`)          REFERENCES `companies` (`id`)          ON DELETE CASCADE,
    CONSTRAINT `membership_plans_service_category_id_foreign` FOREIGN KEY (`service_category_id`) REFERENCES `service_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sesiones incluidas durante la vigencia (ej. 4 manicuras)
CREATE TABLE IF NOT EXISTS `membership_plan_sessions` (
    `id`                 BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `membership_plan_id` BIGINT UNSIGNED   NOT NULL,
    `service_id`         BIGINT UNSIGNED   NOT NULL,
    `quantity`           SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at`         TIMESTAMP         NULL,
    `updated_at`         TIMESTAMP         NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `membership_plan_sessions_plan_service_unique` (`membership_plan_id`, `service_id`),
    CONSTRAINT `membership_plan_sessions_membership_plan_id_foreign` FOREIGN KEY (`membership_plan_id`) REFERENCES `membership_plans` (`id`) ON DELETE CASCADE,
    CONSTRAINT `membership_plan_sessions_service_id_foreign`         FOREIGN KEY (`service_id`)         REFERENCES `services` (`id`)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. Membresías vendidas. Guardan una COPIA del plan: si el plan cambia
--    después, lo que ya se vendió no cambia.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `client_memberships` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`          BIGINT UNSIGNED NOT NULL,
    `client_id`           BIGINT UNSIGNED NOT NULL,
    `membership_plan_id`  BIGINT UNSIGNED NULL,
    `sale_id`             BIGINT UNSIGNED NULL,
    `sale_detail_id`      BIGINT UNSIGNED NULL,
    `plan_name`           VARCHAR(120)    NOT NULL,
    `price_paid`          DECIMAL(12,2)   NOT NULL DEFAULT 0,
    `discount_percent`    DECIMAL(5,2)    NOT NULL DEFAULT 0,
    `discount_scope`      VARCHAR(20)     NOT NULL DEFAULT 'services',
    `service_category_id` BIGINT UNSIGNED NULL,
    `session_unit_value`  DECIMAL(12,2)   NOT NULL DEFAULT 0 COMMENT 'precio pagado / total de sesiones incluidas',
    `starts_on`           DATE            NOT NULL,
    `ends_on`             DATE            NOT NULL,
    `status`              VARCHAR(20)     NOT NULL DEFAULT 'active' COMMENT 'active | canceled (vencida se deriva de ends_on)',
    `canceled_at`         DATETIME        NULL,
    `notes`               TEXT            NULL,
    `created_by`          BIGINT UNSIGNED NULL,
    `created_at`          TIMESTAMP       NULL,
    `updated_at`          TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `client_memberships_company_client_ends_index` (`company_id`, `client_id`, `ends_on`),
    CONSTRAINT `client_memberships_company_id_foreign`         FOREIGN KEY (`company_id`)         REFERENCES `companies` (`id`)        ON DELETE CASCADE,
    CONSTRAINT `client_memberships_client_id_foreign`          FOREIGN KEY (`client_id`)          REFERENCES `clients` (`id`)          ON DELETE CASCADE,
    CONSTRAINT `client_memberships_membership_plan_id_foreign` FOREIGN KEY (`membership_plan_id`) REFERENCES `membership_plans` (`id`) ON DELETE SET NULL,
    CONSTRAINT `client_memberships_sale_id_foreign`            FOREIGN KEY (`sale_id`)            REFERENCES `sales` (`id`)            ON DELETE SET NULL,
    CONSTRAINT `client_memberships_sale_detail_id_foreign`     FOREIGN KEY (`sale_detail_id`)     REFERENCES `sale_details` (`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sesiones incluidas en cada membresía vendida (copia del plan).
-- Las usadas no se guardan: se cuentan desde las líneas de venta no anuladas.
CREATE TABLE IF NOT EXISTS `client_membership_sessions` (
    `id`                   BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `client_membership_id` BIGINT UNSIGNED   NOT NULL,
    `service_id`           BIGINT UNSIGNED   NOT NULL,
    `included`             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`           TIMESTAMP         NULL,
    `updated_at`           TIMESTAMP         NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `client_membership_sessions_membership_service_unique` (`client_membership_id`, `service_id`),
    CONSTRAINT `client_membership_sessions_client_membership_id_foreign` FOREIGN KEY (`client_membership_id`) REFERENCES `client_memberships` (`id`) ON DELETE CASCADE,
    CONSTRAINT `client_membership_sessions_service_id_foreign`           FOREIGN KEY (`service_id`)           REFERENCES `services` (`id`)           ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3. Movimientos de puntos. El saldo de un cliente es la suma de points.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `loyalty_movements` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `client_id`  BIGINT UNSIGNED NOT NULL,
    `sale_id`    BIGINT UNSIGNED NULL,
    `type`       VARCHAR(20)     NOT NULL COMMENT 'earn | redeem | reverse | expire | adjust',
    `points`     INT             NOT NULL COMMENT 'con signo: positivo suma, negativo resta',
    `amount`     DECIMAL(12,2)   NOT NULL DEFAULT 0 COMMENT 'equivalente en dinero',
    `notes`      VARCHAR(255)    NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP       NULL,
    `updated_at` TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `loyalty_movements_company_client_index` (`company_id`, `client_id`, `created_at`),
    KEY `loyalty_movements_sale_id_index` (`sale_id`),
    CONSTRAINT `loyalty_movements_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `loyalty_movements_client_id_foreign`  FOREIGN KEY (`client_id`)  REFERENCES `clients` (`id`)   ON DELETE CASCADE,
    CONSTRAINT `loyalty_movements_sale_id_foreign`    FOREIGN KEY (`sale_id`)    REFERENCES `sales` (`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 4. Líneas de venta: venta de membresía, sesión usada y su descuento
-- ---------------------------------------------------------------------
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sale_details` ADD COLUMN `membership_plan_id` BIGINT UNSIGNED NULL AFTER `personal_id`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'membership_plan_id');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sale_details` ADD COLUMN `client_membership_id` BIGINT UNSIGNED NULL AFTER `membership_plan_id`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'client_membership_id');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sale_details` ADD COLUMN `uses_session` TINYINT(1) NOT NULL DEFAULT 0 AFTER `client_membership_id`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'uses_session');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sale_details` ADD COLUMN `membership_discount` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `discount`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'membership_discount');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'membership_plan_id' AND REFERENCED_TABLE_NAME = 'membership_plans')
                   + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'sale_details_membership_plan_id_foreign') = 0,
           'ALTER TABLE `sale_details` ADD CONSTRAINT `sale_details_membership_plan_id_foreign` FOREIGN KEY (`membership_plan_id`) REFERENCES `membership_plans` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'client_membership_id' AND REFERENCED_TABLE_NAME = 'client_memberships')
                   + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'sale_details_client_membership_id_foreign') = 0,
           'ALTER TABLE `sale_details` ADD CONSTRAINT `sale_details_client_membership_id_foreign` FOREIGN KEY (`client_membership_id`) REFERENCES `client_memberships` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 5. Venta: descuento de membresía y puntos canjeados / ganados
-- ---------------------------------------------------------------------
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD COLUMN `membership_discount` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `discount`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'membership_discount');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD COLUMN `loyalty_points_redeemed` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `membership_discount`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'loyalty_points_redeemed');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD COLUMN `loyalty_discount` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `loyalty_points_redeemed`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'loyalty_discount');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD COLUMN `loyalty_points_earned` INT NOT NULL DEFAULT 0 AFTER `loyalty_discount`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'loyalty_points_earned');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 6. Preferencias de fidelización por empresa
-- ---------------------------------------------------------------------
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `company_settings` ADD COLUMN `loyalty_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `require_cash_session`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'loyalty_enabled');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `company_settings` ADD COLUMN `loyalty_amount_per_point` DECIMAL(10,2) NOT NULL DEFAULT 10.00 COMMENT ''Bs que hay que gastar para ganar 1 punto'' AFTER `loyalty_enabled`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'loyalty_amount_per_point');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `company_settings` ADD COLUMN `loyalty_point_value` DECIMAL(10,4) NOT NULL DEFAULT 0.1000 COMMENT ''Bs que vale 1 punto al canjearlo'' AFTER `loyalty_amount_per_point`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'loyalty_point_value');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `company_settings` ADD COLUMN `loyalty_min_redeem` INT UNSIGNED NOT NULL DEFAULT 100 AFTER `loyalty_point_value`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'loyalty_min_redeem');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `company_settings` ADD COLUMN `loyalty_expiry_months` SMALLINT UNSIGNED NULL DEFAULT 12 COMMENT ''meses sin actividad para que venza el saldo, NULL = no vence'' AFTER `loyalty_min_redeem`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'loyalty_expiry_months');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 7. "Membresías y fidelización" es un módulo de plan. Se agrega a los
--    planes existentes; luego puede quitarse del plan que no lo incluya.
-- ---------------------------------------------------------------------
UPDATE `plans`
SET `features` = JSON_ARRAY_APPEND(`features`, '$', 'loyalty')
WHERE `features` IS NOT NULL
  AND JSON_VALID(`features`)
  AND NOT JSON_CONTAINS(`features`, '"loyalty"');


-- ---------------------------------------------------------------------
-- 8. Permisos
-- ---------------------------------------------------------------------
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
              SELECT 'Ver Membresias' AS name, 'memberships.view' AS slug, 'memberships' AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Crear Membresias',          'memberships.create', 'memberships', NULL, NOW(), NOW()
    UNION ALL SELECT 'Editar Membresias',         'memberships.edit',   'memberships', NULL, NOW(), NOW()
    UNION ALL SELECT 'Eliminar Membresias',       'memberships.delete', 'memberships', NULL, NOW(), NOW()
    UNION ALL SELECT 'Ver Fidelizacion',          'loyalty.view',       'loyalty',     NULL, NOW(), NOW()
    UNION ALL SELECT 'Configurar Fidelizacion',   'loyalty.edit',       'loyalty',     NULL, NOW(), NOW()
    UNION ALL SELECT 'Ajustar puntos',            'loyalty.adjust',     'loyalty',     NULL, NOW(), NOW()
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug);

-- Dueño / administración: todo
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r CROSS JOIN `permissions` p
WHERE r.`slug` = 'admin' AND p.`module` IN ('memberships', 'loyalty');

-- Encargados: consultar y ajustar puntos; no configuran planes ni reglas de puntos
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r CROSS JOIN `permissions` p
WHERE r.`slug` IN ('manager', 'gerente_c')
  AND p.`slug` IN ('memberships.view', 'loyalty.view', 'loyalty.adjust');

-- Fin del script.
