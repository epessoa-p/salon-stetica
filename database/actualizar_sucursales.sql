-- =====================================================================
--  Glamora · Sucursales: todo movimiento sabe de qué sucursal es
--
--  - El personal pertenece a una sucursal (personals.branch_id).
--  - Toda empresa tiene al menos una sucursal con su almacén: a las que
--    no tienen se les crea SUCURSAL PRINCIPAL + ALMACÉN PRINCIPAL.
--  - En empresas con una sola sucursal, lo que quedó sin sucursal
--    (personal, cajas, ventas, citas, cotizaciones, movimientos de
--    inventario) se asigna a esa sucursal. Con varias sucursales no se
--    adivina: se asigna desde la web.
--  - Corrige cash_sessions.opened_at: tenía ON UPDATE CURRENT_TIMESTAMP
--    y al cerrar una caja se perdía la hora de apertura.
--
--  SEGURO DE REEJECUTAR: cada columna, clave e inserción comprueba antes
--  si ya existe, y las asignaciones solo tocan filas sin sucursal.
--  Probado dos veces sobre una base de prueba.
--  Compatible con MySQL 5.7+ y MariaDB 10.2+ (sin DELIMITER).
--
--  ORDEN EN PRODUCCIÓN: después de actualizar_marketing.sql.
--  Ejecutar completo: phpMyAdmin → pestaña SQL → pegar y ejecutar.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Personal → sucursal
-- ---------------------------------------------------------------------
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personals' AND COLUMN_NAME = 'branch_id') = 0,
    'ALTER TABLE `personals` ADD COLUMN `branch_id` BIGINT UNSIGNED NULL AFTER `cargo_id`',
    'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'personals_branch_id_foreign') = 0,
    'ALTER TABLE `personals` ADD CONSTRAINT `personals_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL',
    'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 2. Hora de apertura de caja: sin ON UPDATE (se pisaba al cerrar)
-- ---------------------------------------------------------------------
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'opened_at' AND LOWER(EXTRA) LIKE '%on update%') > 0,
    'ALTER TABLE `cash_sessions` MODIFY `opened_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
    'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 3. Empresas sin sucursal: almacén y sucursal principal
--    (el código WH-<empresa>-P identifica el almacén creado aquí)
-- ---------------------------------------------------------------------
INSERT INTO `warehouses` (`company_id`, `name`, `code`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ALMACÉN PRINCIPAL', CONCAT('WH-', c.`id`, '-P'), 'Almacén de la sucursal principal', 1, NOW(), NOW()
FROM `companies` c
WHERE c.`deleted_at` IS NULL
  AND NOT EXISTS (SELECT 1 FROM `branches` b WHERE b.`company_id` = c.`id` AND b.`deleted_at` IS NULL)
  AND NOT EXISTS (SELECT 1 FROM `warehouses` w WHERE w.`code` = CONCAT('WH-', c.`id`, '-P'));

INSERT INTO `branches` (`company_id`, `warehouse_id`, `name`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, w.`id`, 'SUCURSAL PRINCIPAL', 1, NOW(), NOW()
FROM `companies` c
JOIN `warehouses` w ON w.`company_id` = c.`id` AND w.`code` = CONCAT('WH-', c.`id`, '-P')
WHERE c.`deleted_at` IS NULL
  AND NOT EXISTS (SELECT 1 FROM `branches` b WHERE b.`company_id` = c.`id` AND b.`deleted_at` IS NULL);


-- ---------------------------------------------------------------------
-- 4. Empresas con UNA sola sucursal: lo que no tiene sucursal va a ella
-- ---------------------------------------------------------------------
UPDATE `personals` t
JOIN (SELECT `company_id`, MIN(`id`) AS `bid` FROM `branches` WHERE `deleted_at` IS NULL GROUP BY `company_id` HAVING COUNT(*) = 1) b ON b.`company_id` = t.`company_id`
SET t.`branch_id` = b.`bid`
WHERE t.`branch_id` IS NULL;

UPDATE `cash_registers` t
JOIN (SELECT `company_id`, MIN(`id`) AS `bid` FROM `branches` WHERE `deleted_at` IS NULL GROUP BY `company_id` HAVING COUNT(*) = 1) b ON b.`company_id` = t.`company_id`
SET t.`branch_id` = b.`bid`
WHERE t.`branch_id` IS NULL;

UPDATE `sales` t
JOIN (SELECT `company_id`, MIN(`id`) AS `bid` FROM `branches` WHERE `deleted_at` IS NULL GROUP BY `company_id` HAVING COUNT(*) = 1) b ON b.`company_id` = t.`company_id`
SET t.`branch_id` = b.`bid`
WHERE t.`branch_id` IS NULL;

UPDATE `appointments` t
JOIN (SELECT `company_id`, MIN(`id`) AS `bid` FROM `branches` WHERE `deleted_at` IS NULL GROUP BY `company_id` HAVING COUNT(*) = 1) b ON b.`company_id` = t.`company_id`
SET t.`branch_id` = b.`bid`
WHERE t.`branch_id` IS NULL;

UPDATE `quotes` t
JOIN (SELECT `company_id`, MIN(`id`) AS `bid` FROM `branches` WHERE `deleted_at` IS NULL GROUP BY `company_id` HAVING COUNT(*) = 1) b ON b.`company_id` = t.`company_id`
SET t.`branch_id` = b.`bid`
WHERE t.`branch_id` IS NULL;

UPDATE `inventory_movements` t
JOIN (SELECT `company_id`, MIN(`id`) AS `bid`, MIN(`warehouse_id`) AS `wid` FROM `branches` WHERE `deleted_at` IS NULL GROUP BY `company_id` HAVING COUNT(*) = 1) b ON b.`company_id` = t.`company_id`
SET t.`branch_id` = b.`bid`, t.`warehouse_id` = COALESCE(t.`warehouse_id`, b.`wid`)
WHERE t.`branch_id` IS NULL;


-- ---------------------------------------------------------------------
-- 5. Registro de la migración, para que `php artisan migrate`
--    no la repita
-- ---------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_11_01_000001_setup_branch_structure' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_01_000001_setup_branch_structure');
