-- =====================================================================
--  Glamora · Roles propios por empresa
--
--  - roles.company_id: NULL = rol general de la plataforma (admin, manager…);
--    con valor = rol propio de esa empresa, que las demás no ven ni pueden
--    modificar.
--  - Carga inicial, solo la primera vez: los roles personalizados que usa una
--    sola empresa pasan a ser de esa empresa. Los del sistema y los que usan
--    varias empresas quedan como generales: no se pierde ningún permiso.
--
--  SEGURO DE REEJECUTAR: la columna, el índice y la clave comprueban antes si
--  existen, y la carga inicial solo corre cuando la columna se acaba de crear.
--  Probado dos veces sobre una base de prueba.
--  Compatible con MySQL 5.7+ y MariaDB 10.2+ (sin DELIMITER).
--
--  Ejecutar completo: phpMyAdmin → pestaña SQL → pegar y ejecutar.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Columna, índice y clave foránea
-- ---------------------------------------------------------------------
SET @ya_existia := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'company_id');

SET @s := (SELECT IF(@ya_existia = 0,
    'ALTER TABLE `roles` ADD COLUMN `company_id` BIGINT UNSIGNED NULL AFTER `id`',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'company_id') = 0,
    'ALTER TABLE `roles` ADD INDEX `roles_company_id_index` (`company_id`)',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME = 'companies') = 0,
    'ALTER TABLE `roles` ADD CONSTRAINT `roles_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 2. Carga inicial (solo si la columna se acaba de crear)
-- ---------------------------------------------------------------------
UPDATE `roles` r
JOIN (
    SELECT u.`role_id`, MIN(u.`company_id`) AS `empresa`, COUNT(DISTINCT u.`company_id`) AS `empresas`
    FROM (
        SELECT `role_id`, `company_id` FROM `cargos`
        UNION ALL
        SELECT `role_id`, `company_id` FROM `company_user`
    ) u
    GROUP BY u.`role_id`
) t ON t.`role_id` = r.`id`
SET r.`company_id` = t.`empresa`
WHERE @ya_existia = 0
  AND t.`empresas` = 1
  AND r.`slug` NOT IN ('super_admin', 'admin', 'manager', 'cashier', 'employee');


-- ---------------------------------------------------------------------
-- 3. Registro de la migración, para que `php artisan migrate`
--    no la repita
-- ---------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_11_03_000001_setup_company_roles' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_03_000001_setup_company_roles');
