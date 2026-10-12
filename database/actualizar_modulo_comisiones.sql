-- =====================================================================
--  Glamora · Módulo de Comisiones (reglas, devengo y liquidaciones)
--
--  SEGURO DE REEJECUTAR: cada cambio comprueba antes si ya existe
--  (tablas, columnas, índices, claves foráneas, permisos y planes), así
--  que ejecutarlo de nuevo no duplica ni rompe nada. Probado 3 veces.
--  Compatible con MySQL 5.7+ y MariaDB 10.2+ (sin DELIMITER).
--
--  Ejecutar completo en la base de datos de producción
--  (phpMyAdmin → pestaña SQL → pegar y ejecutar).
-- =====================================================================


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
