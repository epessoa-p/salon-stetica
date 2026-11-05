-- =====================================================================
--  Glamora · Cuentas con movimientos (caja chica, bancos) y origen de los pagos
--
--  - financial_accounts: cuentas de cada empresa.
--  - account_movements: libro de cada cuenta (aportes, gastos, ingresos,
--    traspasos y ajustes). El saldo es la suma de sus movimientos no anulados.
--  - cash_movements: de qué cuenta salió el dinero y a quién se pagó.
--  - payable_payments / commission_settlements: cuenta de la que salió el pago.
--  - entries (órdenes de compra): movimiento del pago de una compra al contado.
--  - Permisos accounts.*: para todo rol que ya administra cajas (cajas.create),
--    incluidos los roles propios de cada empresa.
--
--  SEGURO DE REEJECUTAR: cada tabla, columna y clave comprueba antes si existe,
--  y los permisos no se duplican. No modifica datos existentes.
--  Probado dos veces sobre una base de prueba.
--  Compatible con MySQL 5.7+ y MariaDB 10.2+ (sin DELIMITER).
--
--  ORDEN EN PRODUCCIÓN: después de actualizar_roles_por_empresa.sql.
--  Ejecutar completo: phpMyAdmin → pestaña SQL → pegar y ejecutar.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Cuentas
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `financial_accounts` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`     BIGINT UNSIGNED NOT NULL,
    `branch_id`      BIGINT UNSIGNED NULL,
    `name`           VARCHAR(120)    NOT NULL,
    `type`           VARCHAR(20)     NOT NULL DEFAULT 'caja_chica' COMMENT 'caja_chica | banco | billetera | otra',
    `bank_name`      VARCHAR(120)    NULL,
    `account_number` VARCHAR(60)     NULL,
    `notes`          VARCHAR(255)    NULL,
    `active`         TINYINT(1)      NOT NULL DEFAULT 1,
    `created_by`     BIGINT UNSIGNED NULL,
    `created_at`     TIMESTAMP       NULL,
    `updated_at`     TIMESTAMP       NULL,
    `deleted_at`     TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `financial_accounts_company_index` (`company_id`, `active`),
    CONSTRAINT `financial_accounts_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `financial_accounts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. Movimientos de cada cuenta
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `account_movements` (
    `id`                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`              BIGINT UNSIGNED NOT NULL,
    `financial_account_id`    BIGINT UNSIGNED NOT NULL,
    `type`                    VARCHAR(20)     NOT NULL COMMENT 'aporte | gasto | ingreso | traspaso_entrada | traspaso_salida | ajuste',
    `direction`               VARCHAR(3)      NOT NULL COMMENT 'in | out',
    `amount`                  DECIMAL(14,2)   NOT NULL,
    `concept`                 VARCHAR(255)    NOT NULL,
    `category`                VARCHAR(255)    NULL,
    `movement_date`           DATE            NOT NULL,
    `reference`               VARCHAR(255)    NULL,
    `cash_movement_id`        BIGINT UNSIGNED NULL,
    `counterpart_movement_id` BIGINT UNSIGNED NULL,
    `voided_at`               DATETIME        NULL,
    `voided_by`               BIGINT UNSIGNED NULL,
    `created_by`              BIGINT UNSIGNED NULL,
    `created_at`              TIMESTAMP       NULL,
    `updated_at`              TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `account_movements_account_date_index` (`financial_account_id`, `movement_date`),
    KEY `account_movements_company_index` (`company_id`),
    KEY `account_movements_cash_movement_index` (`cash_movement_id`),
    CONSTRAINT `account_movements_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `account_movements_account_id_foreign` FOREIGN KEY (`financial_account_id`) REFERENCES `financial_accounts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `account_movements_cash_movement_id_foreign` FOREIGN KEY (`cash_movement_id`) REFERENCES `cash_movements` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3. Gastos e ingresos: cuenta de origen y a quién se pagó
-- ---------------------------------------------------------------------
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'financial_account_id') = 0,
    'ALTER TABLE `cash_movements` ADD COLUMN `financial_account_id` BIGINT UNSIGNED NULL AFTER `cash_session_id`',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'financial_account_id' AND REFERENCED_TABLE_NAME = 'financial_accounts') = 0,
    'ALTER TABLE `cash_movements` ADD CONSTRAINT `cash_movements_financial_account_id_foreign` FOREIGN KEY (`financial_account_id`) REFERENCES `financial_accounts` (`id`) ON DELETE SET NULL',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'supplier_id') = 0,
    'ALTER TABLE `cash_movements` ADD COLUMN `supplier_id` BIGINT UNSIGNED NULL AFTER `reference`',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'supplier_id' AND REFERENCED_TABLE_NAME = 'suppliers') = 0,
    'ALTER TABLE `cash_movements` ADD CONSTRAINT `cash_movements_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'personal_id') = 0,
    'ALTER TABLE `cash_movements` ADD COLUMN `personal_id` BIGINT UNSIGNED NULL AFTER `supplier_id`',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_movements' AND COLUMN_NAME = 'personal_id' AND REFERENCED_TABLE_NAME = 'personals') = 0,
    'ALTER TABLE `cash_movements` ADD CONSTRAINT `cash_movements_personal_id_foreign` FOREIGN KEY (`personal_id`) REFERENCES `personals` (`id`) ON DELETE SET NULL',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 4. Pagos a proveedores y liquidaciones: cuenta de origen
-- ---------------------------------------------------------------------
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payable_payments' AND COLUMN_NAME = 'financial_account_id') = 0,
    'ALTER TABLE `payable_payments` ADD COLUMN `financial_account_id` BIGINT UNSIGNED NULL AFTER `cash_session_id`',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payable_payments' AND COLUMN_NAME = 'financial_account_id' AND REFERENCED_TABLE_NAME = 'financial_accounts') = 0,
    'ALTER TABLE `payable_payments` ADD CONSTRAINT `payable_payments_financial_account_id_foreign` FOREIGN KEY (`financial_account_id`) REFERENCES `financial_accounts` (`id`) ON DELETE SET NULL',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'financial_account_id') = 0,
    'ALTER TABLE `commission_settlements` ADD COLUMN `financial_account_id` BIGINT UNSIGNED NULL AFTER `cash_session_id`',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commission_settlements' AND COLUMN_NAME = 'financial_account_id' AND REFERENCED_TABLE_NAME = 'financial_accounts') = 0,
    'ALTER TABLE `commission_settlements` ADD CONSTRAINT `commission_settlements_financial_account_id_foreign` FOREIGN KEY (`financial_account_id`) REFERENCES `financial_accounts` (`id`) ON DELETE SET NULL',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 5. Compras al contado: movimiento del pago
-- ---------------------------------------------------------------------
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'cash_movement_id') = 0,
    'ALTER TABLE `entries` ADD COLUMN `cash_movement_id` BIGINT UNSIGNED NULL',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'cash_movement_id' AND REFERENCED_TABLE_NAME = 'cash_movements') = 0,
    'ALTER TABLE `entries` ADD CONSTRAINT `entries_cash_movement_id_foreign` FOREIGN KEY (`cash_movement_id`) REFERENCES `cash_movements` (`id`) ON DELETE SET NULL',
    'SELECT 1'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 6. Permisos
-- ---------------------------------------------------------------------
INSERT INTO `permissions` (`name`, `slug`, `module`, `created_at`, `updated_at`)
SELECT n.`name`, n.`slug`, 'accounts', NOW(), NOW() FROM (
    SELECT 'Ver Cuentas' AS `name`, 'accounts.view' AS `slug`
    UNION ALL SELECT 'Crear Cuentas', 'accounts.create'
    UNION ALL SELECT 'Editar Cuentas', 'accounts.edit'
    UNION ALL SELECT 'Eliminar Cuentas', 'accounts.delete'
    UNION ALL SELECT 'Aportes, traspasos y ajustes en Cuentas', 'accounts.move'
) n
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`slug` = n.`slug`);

INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT rp.`role_id`, p.`id`
FROM `role_permission` rp
JOIN `permissions` base ON base.`id` = rp.`permission_id` AND base.`slug` = 'cajas.create'
JOIN `permissions` p ON p.`slug` IN ('accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete', 'accounts.move')
WHERE NOT EXISTS (SELECT 1 FROM `role_permission` x WHERE x.`role_id` = rp.`role_id` AND x.`permission_id` = p.`id`);


-- ---------------------------------------------------------------------
-- 7. Registro de la migración, para que `php artisan migrate`
--    no la repita
-- ---------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_11_04_000001_setup_financial_accounts' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_04_000001_setup_financial_accounts');
