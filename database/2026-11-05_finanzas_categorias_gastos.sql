-- #####################################################################
-- ##                                                                 ##
-- ##   SEGURO DE REEJECUTAR: SI                                      ##
-- ##   EJECUTAR DESPUES DE: actualizar_cuentas_financieras.sql       ##
-- ##                                                                 ##
-- #####################################################################
--
--  Glamora · Finanzas: categorías de gasto
--
--  - expense_categories: en qué se gasta, por empresa, con su tipo (gasto
--    general, servicio básico, pago a proveedor o a personal).
--  - cash_movements: categoría, número (G-000001), notas, sucursal y anulación.
--  - Datos: a las empresas que ya tienen gastos se les crean las categorías
--    habituales y las de sus gastos, y cada gasto queda enlazado a la suya.
--  - Permisos expense_categories.*: para todo rol que ya administra cajas.
--
--  Por qué es seguro repetirlo: cada tabla, columna y clave comprueba antes si
--  existe, no duplica categorías ni permisos, y solo enlaza gastos sin categoría.
--  Probado dos veces sobre una base de prueba.
--  Compatible con MySQL 5.7+ y MariaDB 10.2+ (sin DELIMITER).
--
--  Ejecutar completo: phpMyAdmin → pestaña SQL → pegar y ejecutar.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Categorías de gasto
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `name`       VARCHAR(120)    NOT NULL,
    `payee`      VARCHAR(20)     NOT NULL DEFAULT 'ninguno' COMMENT 'ninguno | proveedor | personal',
    `recurring`  TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Servicio básico',
    `frequency`  VARCHAR(10)     NULL COMMENT 'mensual | anual',
    `active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `sort_order` INT             NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP       NULL,
    `updated_at` TIMESTAMP       NULL,
    `deleted_at` TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `expense_categories_company_index` (`company_id`, `active`),
    CONSTRAINT `expense_categories_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. Gastos: categoría, número, notas, sucursal y anulación
-- ---------------------------------------------------------------------
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'expense_category_id') = 0,
    'ALTER TABLE `cash_movements` ADD COLUMN `expense_category_id` BIGINT UNSIGNED NULL AFTER `category`',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'expense_category_id' AND REFERENCED_TABLE_NAME = 'expense_categories') = 0,
    'ALTER TABLE `cash_movements` ADD CONSTRAINT `cash_movements_expense_category_id_foreign` FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`id`) ON DELETE SET NULL',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'number') = 0,
    'ALTER TABLE `cash_movements` ADD COLUMN `number` VARCHAR(20) NULL AFTER `company_id`, ADD INDEX `cash_movements_company_number_index` (`company_id`, `number`)',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'notes') = 0,
    'ALTER TABLE `cash_movements` ADD COLUMN `notes` TEXT NULL AFTER `reference`',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'branch_id') = 0,
    'ALTER TABLE `cash_movements` ADD COLUMN `branch_id` BIGINT UNSIGNED NULL AFTER `company_id`',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'branch_id' AND REFERENCED_TABLE_NAME = 'branches') = 0,
    'ALTER TABLE `cash_movements` ADD CONSTRAINT `cash_movements_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'cancelled_at') = 0,
    'ALTER TABLE `cash_movements` ADD COLUMN `cancelled_at` DATETIME NULL, ADD COLUMN `cancelled_by` BIGINT UNSIGNED NULL, ADD COLUMN `cancel_reason` VARCHAR(255) NULL',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 3. Categorías habituales para las empresas que ya tienen gastos
--    (las demás las reciben al entrar por primera vez a Finanzas)
-- ---------------------------------------------------------------------
INSERT INTO `expense_categories` (`company_id`, `name`, `payee`, `recurring`, `frequency`, `active`, `sort_order`, `created_at`, `updated_at`)
SELECT c.`company_id`, d.`name`, d.`payee`, d.`recurring`, d.`frequency`, 1, d.`sort_order`, NOW(), NOW()
FROM (SELECT DISTINCT `company_id` FROM `cash_movements` WHERE `type` = 'egreso' AND `company_id` IS NOT NULL) c
CROSS JOIN (
    SELECT 'PAGO A PROVEEDORES' AS `name`, 'proveedor' AS `payee`, 0 AS `recurring`, NULL AS `frequency`, 0 AS `sort_order`
    UNION ALL SELECT 'PAGO A PERSONAL', 'personal', 0, NULL, 1
    UNION ALL SELECT 'ALQUILER', 'ninguno', 1, 'mensual', 2
    UNION ALL SELECT 'LUZ', 'ninguno', 1, 'mensual', 3
    UNION ALL SELECT 'AGUA', 'ninguno', 1, 'mensual', 4
    UNION ALL SELECT 'INTERNET Y TELÉFONO', 'ninguno', 1, 'mensual', 5
    UNION ALL SELECT 'COMIDA', 'ninguno', 0, NULL, 6
    UNION ALL SELECT 'PASAJES Y TRANSPORTE', 'ninguno', 0, NULL, 7
    UNION ALL SELECT 'PEDIDOS Y ENVÍOS', 'ninguno', 0, NULL, 8
    UNION ALL SELECT 'LIMPIEZA Y MANTENIMIENTO', 'ninguno', 0, NULL, 9
    UNION ALL SELECT 'OTROS', 'ninguno', 0, NULL, 10
) d
WHERE NOT EXISTS (
    SELECT 1 FROM `expense_categories` e
    WHERE e.`company_id` = c.`company_id`
      AND e.`name` = CONVERT(d.`name` USING utf8mb4) COLLATE utf8mb4_unicode_ci
);


-- ---------------------------------------------------------------------
-- 4. Una categoría por cada texto de categoría ya usado en gastos
-- ---------------------------------------------------------------------
INSERT INTO `expense_categories` (`company_id`, `name`, `payee`, `recurring`, `frequency`, `active`, `sort_order`, `created_at`, `updated_at`)
SELECT u.`company_id`, u.`name`,
    CASE WHEN u.`name` = 'SUELDOS' OR u.`name` = 'COMISIONES' THEN 'personal' ELSE 'ninguno' END,
    CASE WHEN u.`name` = 'SERVICIOS BÁSICOS' THEN 1 ELSE 0 END,
    CASE WHEN u.`name` = 'SERVICIOS BÁSICOS' THEN 'mensual' ELSE NULL END,
    1, 50, NOW(), NOW()
FROM (
    SELECT DISTINCT cm.`company_id`, UPPER(CONVERT(cm.`category` USING utf8mb4) COLLATE utf8mb4_unicode_ci) AS `name`
    FROM `cash_movements` cm
    WHERE cm.`type` = 'egreso' AND cm.`company_id` IS NOT NULL AND cm.`category` IS NOT NULL AND cm.`category` <> ''
      AND cm.`category` <> 'Traspaso entre cuentas'
) u
WHERE NOT EXISTS (
    SELECT 1 FROM `expense_categories` e WHERE e.`company_id` = u.`company_id` AND e.`name` = u.`name`
)
AND u.`name` <> 'PAGO A PROVEEDORES';


-- ---------------------------------------------------------------------
-- 5. Enlazar los gastos existentes a su categoría
-- ---------------------------------------------------------------------
UPDATE `cash_movements` cm
JOIN `expense_categories` e
  ON e.`company_id` = cm.`company_id`
 AND e.`name` = UPPER(CONVERT(cm.`category` USING utf8mb4) COLLATE utf8mb4_unicode_ci)
SET cm.`expense_category_id` = e.`id`
WHERE cm.`type` = 'egreso' AND cm.`expense_category_id` IS NULL AND cm.`category` IS NOT NULL AND cm.`category` <> '';


-- ---------------------------------------------------------------------
-- 6. Permisos
-- ---------------------------------------------------------------------
INSERT INTO `permissions` (`name`, `slug`, `module`, `created_at`, `updated_at`)
SELECT n.`name`, n.`slug`, 'expense_categories', NOW(), NOW() FROM (
    SELECT 'Ver Categorías de gasto' AS `name`, 'expense_categories.view' AS `slug`
    UNION ALL SELECT 'Crear Categorías de gasto', 'expense_categories.create'
    UNION ALL SELECT 'Editar Categorías de gasto', 'expense_categories.edit'
    UNION ALL SELECT 'Eliminar Categorías de gasto', 'expense_categories.delete'
) n
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`slug` = n.`slug`);

INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT rp.`role_id`, p.`id`
FROM `role_permission` rp
JOIN `permissions` base ON base.`id` = rp.`permission_id` AND base.`slug` = 'cajas.create'
JOIN `permissions` p ON p.`slug` IN ('expense_categories.view', 'expense_categories.create', 'expense_categories.edit', 'expense_categories.delete')
WHERE NOT EXISTS (SELECT 1 FROM `role_permission` x WHERE x.`role_id` = rp.`role_id` AND x.`permission_id` = p.`id`);


-- ---------------------------------------------------------------------
-- 7. Registro de la migración, para que `php artisan migrate`
--    no la repita
-- ---------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_11_05_000001_setup_expense_categories' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_05_000001_setup_expense_categories');
