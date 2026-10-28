-- =====================================================================
--  Glamora · Cuentas por cobrar (ventas a crédito) y por pagar (compras
--  a crédito a proveedores)
--
--  SEGURO DE REEJECUTAR: cada tabla, columna, clave foránea y permiso
--  comprueba antes si ya existe, así que ejecutarlo de nuevo no duplica
--  ni rompe nada. Probado dos veces sobre una base de prueba.
--  Compatible con MySQL 5.7+ y MariaDB 10.2+ (sin DELIMITER).
--
--  ORDEN EN PRODUCCIÓN: después de actualizar_devoluciones_cotizaciones_combos.sql.
--  Ejecutar completo: phpMyAdmin → pestaña SQL → pegar y ejecutar.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Cuentas por cobrar: el saldo pendiente de una venta a crédito.
--    Saldo = amount - paid_amount - adjusted_amount (devoluciones).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `receivables` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`      BIGINT UNSIGNED NOT NULL,
    `client_id`       BIGINT UNSIGNED NOT NULL,
    `sale_id`         BIGINT UNSIGNED NULL,
    `amount`          DECIMAL(14,2)   NOT NULL DEFAULT 0 COMMENT 'lo que quedó pendiente al vender',
    `paid_amount`     DECIMAL(14,2)   NOT NULL DEFAULT 0 COMMENT 'suma de abonos vigentes',
    `adjusted_amount` DECIMAL(14,2)   NOT NULL DEFAULT 0 COMMENT 'descontado por devoluciones',
    `due_date`        DATE            NOT NULL,
    `status`          VARCHAR(20)     NOT NULL DEFAULT 'pending' COMMENT 'pending | paid | canceled (vencida se deriva de due_date)',
    `notes`           TEXT            NULL,
    `created_by`      BIGINT UNSIGNED NULL,
    `created_at`      TIMESTAMP       NULL,
    `updated_at`      TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `receivables_company_status_index` (`company_id`, `status`, `due_date`),
    KEY `receivables_client_index` (`client_id`, `status`),
    CONSTRAINT `receivables_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `receivables_client_id_foreign`  FOREIGN KEY (`client_id`)  REFERENCES `clients` (`id`)   ON DELETE CASCADE,
    CONSTRAINT `receivables_sale_id_foreign`    FOREIGN KEY (`sale_id`)    REFERENCES `sales` (`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Abonos. Un mismo recibo puede repartirse entre varias cuentas del cliente.
CREATE TABLE IF NOT EXISTS `receivable_payments` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`       BIGINT UNSIGNED NOT NULL,
    `receivable_id`    BIGINT UNSIGNED NOT NULL,
    `client_id`        BIGINT UNSIGNED NOT NULL,
    `receipt_number`   VARCHAR(30)     NOT NULL,
    `amount`           DECIMAL(14,2)   NOT NULL,
    `payment_method`   VARCHAR(50)     NOT NULL,
    `cash_session_id`  BIGINT UNSIGNED NULL,
    `cash_movement_id` BIGINT UNSIGNED NULL,
    `paid_at`          DATETIME        NOT NULL,
    `notes`            VARCHAR(255)    NULL,
    `created_by`       BIGINT UNSIGNED NULL,
    `created_at`       TIMESTAMP       NULL,
    `updated_at`       TIMESTAMP       NULL,
    `deleted_at`       TIMESTAMP       NULL COMMENT 'abono anulado',
    PRIMARY KEY (`id`),
    KEY `receivable_payments_receivable_index` (`receivable_id`),
    KEY `receivable_payments_company_paid_index` (`company_id`, `paid_at`),
    KEY `receivable_payments_receipt_index` (`company_id`, `receipt_number`),
    CONSTRAINT `receivable_payments_company_id_foreign`       FOREIGN KEY (`company_id`)       REFERENCES `companies` (`id`)      ON DELETE CASCADE,
    CONSTRAINT `receivable_payments_receivable_id_foreign`    FOREIGN KEY (`receivable_id`)    REFERENCES `receivables` (`id`)    ON DELETE CASCADE,
    CONSTRAINT `receivable_payments_client_id_foreign`        FOREIGN KEY (`client_id`)        REFERENCES `clients` (`id`)        ON DELETE CASCADE,
    CONSTRAINT `receivable_payments_cash_session_id_foreign`  FOREIGN KEY (`cash_session_id`)  REFERENCES `cash_sessions` (`id`)  ON DELETE SET NULL,
    CONSTRAINT `receivable_payments_cash_movement_id_foreign` FOREIGN KEY (`cash_movement_id`) REFERENCES `cash_movements` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. Cuentas por pagar: deuda con el proveedor por una orden a crédito.
--    Saldo = amount - paid_amount - adjusted_amount (notas de crédito).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payables` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`        BIGINT UNSIGNED NOT NULL,
    `supplier_id`       BIGINT UNSIGNED NULL,
    `purchase_order_id` BIGINT UNSIGNED NULL,
    `amount`            DECIMAL(14,2)   NOT NULL DEFAULT 0,
    `paid_amount`       DECIMAL(14,2)   NOT NULL DEFAULT 0,
    `adjusted_amount`   DECIMAL(14,2)   NOT NULL DEFAULT 0 COMMENT 'descontado por devoluciones al proveedor',
    `due_date`          DATE            NOT NULL,
    `status`            VARCHAR(20)     NOT NULL DEFAULT 'pending' COMMENT 'pending | paid | canceled (vencida se deriva de due_date)',
    `notes`             TEXT            NULL,
    `created_by`        BIGINT UNSIGNED NULL,
    `created_at`        TIMESTAMP       NULL,
    `updated_at`        TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `payables_company_status_index` (`company_id`, `status`, `due_date`),
    CONSTRAINT `payables_company_id_foreign`        FOREIGN KEY (`company_id`)        REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `payables_supplier_id_foreign`       FOREIGN KEY (`supplier_id`)       REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
    CONSTRAINT `payables_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `entries` (`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payable_payments` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`       BIGINT UNSIGNED NOT NULL,
    `payable_id`       BIGINT UNSIGNED NOT NULL,
    `receipt_number`   VARCHAR(30)     NOT NULL,
    `amount`           DECIMAL(14,2)   NOT NULL,
    `payment_method`   VARCHAR(50)     NOT NULL,
    `cash_session_id`  BIGINT UNSIGNED NULL,
    `cash_movement_id` BIGINT UNSIGNED NULL,
    `paid_at`          DATETIME        NOT NULL,
    `notes`            VARCHAR(255)    NULL,
    `created_by`       BIGINT UNSIGNED NULL,
    `created_at`       TIMESTAMP       NULL,
    `updated_at`       TIMESTAMP       NULL,
    `deleted_at`       TIMESTAMP       NULL COMMENT 'pago anulado',
    PRIMARY KEY (`id`),
    KEY `payable_payments_payable_index` (`payable_id`),
    KEY `payable_payments_company_paid_index` (`company_id`, `paid_at`),
    CONSTRAINT `payable_payments_company_id_foreign`       FOREIGN KEY (`company_id`)       REFERENCES `companies` (`id`)      ON DELETE CASCADE,
    CONSTRAINT `payable_payments_payable_id_foreign`       FOREIGN KEY (`payable_id`)       REFERENCES `payables` (`id`)       ON DELETE CASCADE,
    CONSTRAINT `payable_payments_cash_session_id_foreign`  FOREIGN KEY (`cash_session_id`)  REFERENCES `cash_sessions` (`id`)  ON DELETE SET NULL,
    CONSTRAINT `payable_payments_cash_movement_id_foreign` FOREIGN KEY (`cash_movement_id`) REFERENCES `cash_movements` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3. Columnas nuevas
-- ---------------------------------------------------------------------
-- Cliente: límite de crédito (NULL = no se le vende a crédito)
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `clients` ADD COLUMN `credit_limit` DECIMAL(12,2) NULL COMMENT ''NULL = sin crédito'' AFTER `notes`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'credit_limit');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

-- Venta: marca de venta a crédito
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD COLUMN `on_credit` TINYINT(1) NOT NULL DEFAULT 0 AFTER `credit_applied`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'on_credit');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

-- Devolución de cliente: parte que se descontó de la deuda
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sale_returns` ADD COLUMN `applied_to_receivable` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `total`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_returns' AND COLUMN_NAME = 'applied_to_receivable');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

-- Devolución a proveedor: parte que se descontó de la deuda con él
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `purchase_returns` ADD COLUMN `applied_to_payable` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `total`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_returns' AND COLUMN_NAME = 'applied_to_payable');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

-- Orden de compra: contado o crédito con plazo
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `entries` ADD COLUMN `payment_type` VARCHAR(20) NOT NULL DEFAULT ''contado'' COMMENT ''contado | credito'' AFTER `status`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'payment_type');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `entries` ADD COLUMN `credit_days` SMALLINT UNSIGNED NULL AFTER `payment_type`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'credit_days');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

-- Preferencia: plazo por defecto de una venta a crédito
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `company_settings` ADD COLUMN `receivable_default_days` SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER `quote_valid_days`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'receivable_default_days');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 4. Permisos
-- ---------------------------------------------------------------------
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
              SELECT 'Ver Cuentas por cobrar' AS name, 'receivables.view' AS slug, 'receivables' AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Registrar cobros',                 'receivables.collect', 'receivables', NULL, NOW(), NOW()
    UNION ALL SELECT 'Anular cobros',                    'receivables.edit',    'receivables', NULL, NOW(), NOW()
    UNION ALL SELECT 'Ver Cuentas por pagar',            'payables.view',       'payables',    NULL, NOW(), NOW()
    UNION ALL SELECT 'Registrar pagos a proveedores',    'payables.pay',        'payables',    NULL, NOW(), NOW()
    UNION ALL SELECT 'Anular pagos a proveedores',       'payables.edit',       'payables',    NULL, NOW(), NOW()
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug);

-- Dueño y encargados: todo
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r CROSS JOIN `permissions` p
WHERE r.`slug` IN ('admin', 'manager', 'gerente_c')
  AND p.`module` IN ('receivables', 'payables');


-- ---------------------------------------------------------------------
-- 5. Registro de la migración, para que un futuro `php artisan migrate`
--    no la repita
-- ---------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_10_23_000001_setup_receivables_and_payables' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_23_000001_setup_receivables_and_payables');

-- Fin del script.
