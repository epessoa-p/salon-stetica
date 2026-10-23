-- =====================================================================
--  Glamora · Combos y Paquetes · Cotizaciones · Devoluciones · Saldo a favor
--
--  SEGURO DE REEJECUTAR: cada tabla, columna, clave foránea y permiso
--  comprueba antes si ya existe, así que ejecutarlo de nuevo no duplica
--  ni rompe nada. Probado dos veces sobre una base de prueba.
--  Compatible con MySQL 5.7+ y MariaDB 10.2+ (sin DELIMITER).
--
--  ORDEN EN PRODUCCIÓN: después de actualizar_produccion.sql,
--  actualizar_membresias_fidelizacion.sql, actualizar_horarios_asistencia.sql
--  y actualizar_api_movil.sql.
--  Ejecutar completo: phpMyAdmin → pestaña SQL → pegar y ejecutar.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Combos: varios servicios y productos a un precio especial que se
--    cobran juntos en una sola venta (ej. "Novia").
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `combos` (
    `id`          BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `company_id`  BIGINT UNSIGNED   NOT NULL,
    `name`        VARCHAR(120)      NOT NULL,
    `description` TEXT              NULL,
    `price`       DECIMAL(12,2)     NOT NULL DEFAULT 0,
    `active`      TINYINT(1)        NOT NULL DEFAULT 1,
    `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_by`  BIGINT UNSIGNED   NULL,
    `created_at`  TIMESTAMP         NULL,
    `updated_at`  TIMESTAMP         NULL,
    `deleted_at`  TIMESTAMP         NULL,
    PRIMARY KEY (`id`),
    KEY `combos_company_active_index` (`company_id`, `active`),
    CONSTRAINT `combos_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `combo_items` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `combo_id`   BIGINT UNSIGNED NOT NULL,
    `service_id` BIGINT UNSIGNED NULL,
    `product_id` BIGINT UNSIGNED NULL,
    `quantity`   DECIMAL(10,2)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP       NULL,
    `updated_at` TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `combo_items_combo_id_index` (`combo_id`),
    CONSTRAINT `combo_items_combo_id_foreign`   FOREIGN KEY (`combo_id`)   REFERENCES `combos` (`id`)   ON DELETE CASCADE,
    CONSTRAINT `combo_items_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
    CONSTRAINT `combo_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. Paquetes: sesiones prepagadas que el cliente usa en visitas futuras
--    (ej. 10 depilaciones). Catálogo y paquetes vendidos.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_packages` (
    `id`            BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `company_id`    BIGINT UNSIGNED   NOT NULL,
    `name`          VARCHAR(120)      NOT NULL,
    `description`   TEXT              NULL,
    `price`         DECIMAL(12,2)     NOT NULL DEFAULT 0,
    `validity_days` SMALLINT UNSIGNED NULL COMMENT 'días para usar las sesiones, NULL = no vence',
    `active`        TINYINT(1)        NOT NULL DEFAULT 1,
    `sort_order`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_by`    BIGINT UNSIGNED   NULL,
    `created_at`    TIMESTAMP         NULL,
    `updated_at`    TIMESTAMP         NULL,
    `deleted_at`    TIMESTAMP         NULL,
    PRIMARY KEY (`id`),
    KEY `service_packages_company_active_index` (`company_id`, `active`),
    CONSTRAINT `service_packages_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_package_items` (
    `id`                 BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `service_package_id` BIGINT UNSIGNED   NOT NULL,
    `service_id`         BIGINT UNSIGNED   NOT NULL,
    `quantity`           SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at`         TIMESTAMP         NULL,
    `updated_at`         TIMESTAMP         NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `service_package_items_package_service_unique` (`service_package_id`, `service_id`),
    CONSTRAINT `service_package_items_service_package_id_foreign` FOREIGN KEY (`service_package_id`) REFERENCES `service_packages` (`id`) ON DELETE CASCADE,
    CONSTRAINT `service_package_items_service_id_foreign`         FOREIGN KEY (`service_id`)         REFERENCES `services` (`id`)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paquete vendido: guarda una COPIA del paquete (lo vendido no cambia).
CREATE TABLE IF NOT EXISTS `client_packages` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`         BIGINT UNSIGNED NOT NULL,
    `client_id`          BIGINT UNSIGNED NOT NULL,
    `service_package_id` BIGINT UNSIGNED NULL,
    `sale_id`            BIGINT UNSIGNED NULL,
    `sale_detail_id`     BIGINT UNSIGNED NULL,
    `package_name`       VARCHAR(120)    NOT NULL,
    `price_paid`         DECIMAL(12,2)   NOT NULL DEFAULT 0,
    `starts_on`          DATE            NOT NULL,
    `expires_on`         DATE            NULL COMMENT 'NULL = no vence',
    `status`             VARCHAR(20)     NOT NULL DEFAULT 'active' COMMENT 'active | canceled (vencido y agotado se derivan)',
    `canceled_at`        DATETIME        NULL,
    `notes`              TEXT            NULL,
    `created_by`         BIGINT UNSIGNED NULL,
    `created_at`         TIMESTAMP       NULL,
    `updated_at`         TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `client_packages_company_client_index` (`company_id`, `client_id`, `status`),
    CONSTRAINT `client_packages_company_id_foreign`         FOREIGN KEY (`company_id`)         REFERENCES `companies` (`id`)        ON DELETE CASCADE,
    CONSTRAINT `client_packages_client_id_foreign`          FOREIGN KEY (`client_id`)          REFERENCES `clients` (`id`)          ON DELETE CASCADE,
    CONSTRAINT `client_packages_service_package_id_foreign` FOREIGN KEY (`service_package_id`) REFERENCES `service_packages` (`id`) ON DELETE SET NULL,
    CONSTRAINT `client_packages_sale_id_foreign`            FOREIGN KEY (`sale_id`)            REFERENCES `sales` (`id`)            ON DELETE SET NULL,
    CONSTRAINT `client_packages_sale_detail_id_foreign`     FOREIGN KEY (`sale_detail_id`)     REFERENCES `sale_details` (`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sesiones de cada paquete vendido. Las usadas se cuentan desde las ventas.
CREATE TABLE IF NOT EXISTS `client_package_items` (
    `id`                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `client_package_id` BIGINT UNSIGNED   NOT NULL,
    `service_id`        BIGINT UNSIGNED   NOT NULL,
    `included`          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `unit_value`        DECIMAL(12,2)     NOT NULL DEFAULT 0 COMMENT 'lo cobrado por cada sesión: base de la comisión al usarla',
    `created_at`        TIMESTAMP         NULL,
    `updated_at`        TIMESTAMP         NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `client_package_items_package_service_unique` (`client_package_id`, `service_id`),
    CONSTRAINT `client_package_items_client_package_id_foreign` FOREIGN KEY (`client_package_id`) REFERENCES `client_packages` (`id`) ON DELETE CASCADE,
    CONSTRAINT `client_package_items_service_id_foreign`        FOREIGN KEY (`service_id`)        REFERENCES `services` (`id`)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3. Cotizaciones (presupuestos). Se arman en el punto de venta y luego
--    se convierten en venta.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quotes` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`      BIGINT UNSIGNED NOT NULL,
    `branch_id`       BIGINT UNSIGNED NULL,
    `client_id`       BIGINT UNSIGNED NULL,
    `client_name`     VARCHAR(255)    NULL,
    `personal_id`     BIGINT UNSIGNED NULL,
    `quote_number`    VARCHAR(30)     NOT NULL,
    `valid_until`     DATE            NOT NULL,
    `status`          VARCHAR(20)     NOT NULL DEFAULT 'pendiente' COMMENT 'pendiente | convertida | anulada (vencida se deriva de valid_until)',
    `subtotal`        DECIMAL(14,2)   NOT NULL DEFAULT 0,
    `discount`        DECIMAL(14,2)   NOT NULL DEFAULT 0,
    `global_discount` DECIMAL(14,2)   NOT NULL DEFAULT 0,
    `tax`             DECIMAL(14,2)   NOT NULL DEFAULT 0,
    `total`           DECIMAL(14,2)   NOT NULL DEFAULT 0,
    `notes`           TEXT            NULL,
    `sale_id`         BIGINT UNSIGNED NULL,
    `converted_at`    DATETIME        NULL,
    `created_by`      BIGINT UNSIGNED NULL,
    `created_at`      TIMESTAMP       NULL,
    `updated_at`      TIMESTAMP       NULL,
    `deleted_at`      TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `quotes_company_status_index` (`company_id`, `status`, `valid_until`),
    CONSTRAINT `quotes_company_id_foreign`  FOREIGN KEY (`company_id`)  REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `quotes_client_id_foreign`   FOREIGN KEY (`client_id`)   REFERENCES `clients` (`id`)   ON DELETE SET NULL,
    CONSTRAINT `quotes_personal_id_foreign` FOREIGN KEY (`personal_id`) REFERENCES `personals` (`id`) ON DELETE SET NULL,
    CONSTRAINT `quotes_sale_id_foreign`     FOREIGN KEY (`sale_id`)     REFERENCES `sales` (`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quote_items` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `quote_id`           BIGINT UNSIGNED NOT NULL,
    `kind`               VARCHAR(20)     NOT NULL COMMENT 'service | product | combo | package | membership | custom',
    `service_id`         BIGINT UNSIGNED NULL,
    `product_id`         BIGINT UNSIGNED NULL,
    `combo_id`           BIGINT UNSIGNED NULL,
    `service_package_id` BIGINT UNSIGNED NULL,
    `membership_plan_id` BIGINT UNSIGNED NULL,
    `personal_id`        BIGINT UNSIGNED NULL,
    `description`        VARCHAR(255)    NULL,
    `quantity`           DECIMAL(10,2)   NOT NULL DEFAULT 1,
    `unit_price`         DECIMAL(12,2)   NOT NULL DEFAULT 0,
    `discount`           DECIMAL(12,2)   NOT NULL DEFAULT 0,
    `total`              DECIMAL(12,2)   NOT NULL DEFAULT 0,
    `created_at`         TIMESTAMP       NULL,
    `updated_at`         TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `quote_items_quote_id_index` (`quote_id`),
    CONSTRAINT `quote_items_quote_id_foreign` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 4. Devoluciones de clientes (parciales por línea y cantidad).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sale_returns` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`      BIGINT UNSIGNED NOT NULL,
    `sale_id`         BIGINT UNSIGNED NOT NULL,
    `client_id`       BIGINT UNSIGNED NULL,
    `return_number`   VARCHAR(30)     NOT NULL,
    `return_date`     DATETIME        NOT NULL,
    `reason`          VARCHAR(255)    NOT NULL,
    `refund_method`   VARCHAR(20)     NOT NULL COMMENT 'cash = dinero | credit = saldo a favor',
    `payment_method`  VARCHAR(50)     NULL COMMENT 'con qué se devolvió el dinero',
    `cash_session_id` BIGINT UNSIGNED NULL,
    `total`           DECIMAL(14,2)   NOT NULL DEFAULT 0,
    `points_removed`  INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'puntos ganados que se quitan',
    `points_returned` INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'puntos canjeados que se devuelven',
    `notes`           TEXT            NULL,
    `created_by`      BIGINT UNSIGNED NULL,
    `created_at`      TIMESTAMP       NULL,
    `updated_at`      TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `sale_returns_company_date_index` (`company_id`, `return_date`),
    KEY `sale_returns_sale_id_index` (`sale_id`),
    CONSTRAINT `sale_returns_company_id_foreign`      FOREIGN KEY (`company_id`)      REFERENCES `companies` (`id`)     ON DELETE CASCADE,
    CONSTRAINT `sale_returns_sale_id_foreign`         FOREIGN KEY (`sale_id`)         REFERENCES `sales` (`id`)         ON DELETE CASCADE,
    CONSTRAINT `sale_returns_client_id_foreign`       FOREIGN KEY (`client_id`)       REFERENCES `clients` (`id`)       ON DELETE SET NULL,
    CONSTRAINT `sale_returns_cash_session_id_foreign` FOREIGN KEY (`cash_session_id`) REFERENCES `cash_sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sale_return_items` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_return_id` BIGINT UNSIGNED NOT NULL,
    `sale_detail_id` BIGINT UNSIGNED NOT NULL,
    `quantity`       DECIMAL(10,2)   NOT NULL,
    `amount`         DECIMAL(12,2)   NOT NULL DEFAULT 0 COMMENT 'lo que se reintegra por esta línea',
    `restock`        TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'el producto vuelve al stock',
    `created_at`     TIMESTAMP       NULL,
    `updated_at`     TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `sale_return_items_detail_index` (`sale_detail_id`),
    CONSTRAINT `sale_return_items_sale_return_id_foreign` FOREIGN KEY (`sale_return_id`) REFERENCES `sale_returns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `sale_return_items_sale_detail_id_foreign` FOREIGN KEY (`sale_detail_id`) REFERENCES `sale_details` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 5. Saldo a favor del cliente. El saldo es la suma de amount.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `client_credit_movements` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`     BIGINT UNSIGNED NOT NULL,
    `client_id`      BIGINT UNSIGNED NOT NULL,
    `sale_id`        BIGINT UNSIGNED NULL,
    `sale_return_id` BIGINT UNSIGNED NULL,
    `type`           VARCHAR(20)     NOT NULL COMMENT 'return | use | reverse | adjust',
    `amount`         DECIMAL(12,2)   NOT NULL COMMENT 'con signo: positivo suma saldo, negativo lo usa',
    `notes`          VARCHAR(255)    NULL,
    `created_by`     BIGINT UNSIGNED NULL,
    `created_at`     TIMESTAMP       NULL,
    `updated_at`     TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `client_credit_movements_company_client_index` (`company_id`, `client_id`, `created_at`),
    KEY `client_credit_movements_sale_id_index` (`sale_id`),
    CONSTRAINT `client_credit_movements_company_id_foreign`     FOREIGN KEY (`company_id`)     REFERENCES `companies` (`id`)    ON DELETE CASCADE,
    CONSTRAINT `client_credit_movements_client_id_foreign`      FOREIGN KEY (`client_id`)      REFERENCES `clients` (`id`)      ON DELETE CASCADE,
    CONSTRAINT `client_credit_movements_sale_id_foreign`        FOREIGN KEY (`sale_id`)        REFERENCES `sales` (`id`)        ON DELETE SET NULL,
    CONSTRAINT `client_credit_movements_sale_return_id_foreign` FOREIGN KEY (`sale_return_id`) REFERENCES `sale_returns` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 6. Devoluciones a proveedores (desde una orden de compra recibida).
--    `entries` / `entry_details` son las órdenes de compra y sus líneas.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_returns` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`        BIGINT UNSIGNED NOT NULL,
    `supplier_id`       BIGINT UNSIGNED NULL,
    `purchase_order_id` BIGINT UNSIGNED NULL,
    `return_number`     VARCHAR(30)     NOT NULL,
    `return_date`       DATETIME        NOT NULL,
    `reason`            VARCHAR(255)    NOT NULL,
    `refund_method`     VARCHAR(20)     NOT NULL COMMENT 'credit = nota de crédito del proveedor | cash = devolvió dinero',
    `cash_movement_id`  BIGINT UNSIGNED NULL,
    `total`             DECIMAL(14,2)   NOT NULL DEFAULT 0,
    `notes`             TEXT            NULL,
    `created_by`        BIGINT UNSIGNED NULL,
    `created_at`        TIMESTAMP       NULL,
    `updated_at`        TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `purchase_returns_company_date_index` (`company_id`, `return_date`),
    CONSTRAINT `purchase_returns_company_id_foreign`        FOREIGN KEY (`company_id`)        REFERENCES `companies` (`id`)      ON DELETE CASCADE,
    CONSTRAINT `purchase_returns_supplier_id_foreign`       FOREIGN KEY (`supplier_id`)       REFERENCES `suppliers` (`id`)      ON DELETE SET NULL,
    CONSTRAINT `purchase_returns_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `entries` (`id`)        ON DELETE SET NULL,
    CONSTRAINT `purchase_returns_cash_movement_id_foreign`  FOREIGN KEY (`cash_movement_id`)  REFERENCES `cash_movements` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_return_items` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_return_id` BIGINT UNSIGNED NOT NULL,
    `entry_detail_id`    BIGINT UNSIGNED NULL,
    `product_id`         BIGINT UNSIGNED NOT NULL,
    `quantity`           DECIMAL(10,2)   NOT NULL,
    `unit_cost`          DECIMAL(12,2)   NOT NULL DEFAULT 0,
    `total`              DECIMAL(12,2)   NOT NULL DEFAULT 0,
    `created_at`         TIMESTAMP       NULL,
    `updated_at`         TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `purchase_return_items_return_index` (`purchase_return_id`),
    KEY `purchase_return_items_entry_detail_index` (`entry_detail_id`),
    CONSTRAINT `purchase_return_items_purchase_return_id_foreign` FOREIGN KEY (`purchase_return_id`) REFERENCES `purchase_returns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `purchase_return_items_entry_detail_id_foreign`    FOREIGN KEY (`entry_detail_id`)    REFERENCES `entry_details` (`id`)    ON DELETE SET NULL,
    CONSTRAINT `purchase_return_items_product_id_foreign`         FOREIGN KEY (`product_id`)         REFERENCES `products` (`id`)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 7. Líneas de venta: combo de origen, paquete vendido / usado y
--    cantidad devuelta.
-- ---------------------------------------------------------------------
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sale_details` ADD COLUMN `combo_id` BIGINT UNSIGNED NULL AFTER `client_membership_id`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'combo_id');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sale_details` ADD COLUMN `service_package_id` BIGINT UNSIGNED NULL AFTER `combo_id`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'service_package_id');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sale_details` ADD COLUMN `client_package_id` BIGINT UNSIGNED NULL AFTER `service_package_id`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'client_package_id');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sale_details` ADD COLUMN `package_discount` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `membership_discount`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'package_discount');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sale_details` ADD COLUMN `returned_quantity` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `quantity`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_details' AND COLUMN_NAME = 'returned_quantity');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'sale_details_combo_id_foreign') = 0,
           'ALTER TABLE `sale_details` ADD CONSTRAINT `sale_details_combo_id_foreign` FOREIGN KEY (`combo_id`) REFERENCES `combos` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'sale_details_service_package_id_foreign') = 0,
           'ALTER TABLE `sale_details` ADD CONSTRAINT `sale_details_service_package_id_foreign` FOREIGN KEY (`service_package_id`) REFERENCES `service_packages` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'sale_details_client_package_id_foreign') = 0,
           'ALTER TABLE `sale_details` ADD CONSTRAINT `sale_details_client_package_id_foreign` FOREIGN KEY (`client_package_id`) REFERENCES `client_packages` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 8. Venta: paquetes usados, saldo a favor aplicado, cotización de
--    origen y total devuelto.
-- ---------------------------------------------------------------------
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD COLUMN `package_discount` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `membership_discount`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'package_discount');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD COLUMN `credit_applied` DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT ''parte del total pagada con saldo a favor'' AFTER `tip`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'credit_applied');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD COLUMN `returned_total` DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER `credit_applied`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'returned_total');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `sales` ADD COLUMN `quote_id` BIGINT UNSIGNED NULL AFTER `returned_total`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'quote_id');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'sales_quote_id_foreign') = 0,
           'ALTER TABLE `sales` ADD CONSTRAINT `sales_quote_id_foreign` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE SET NULL', 'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 9. Preferencia: días de validez de una cotización
-- ---------------------------------------------------------------------
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `company_settings` ADD COLUMN `quote_valid_days` SMALLINT UNSIGNED NOT NULL DEFAULT 15 AFTER `sale_padding`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'quote_valid_days');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 10. Permisos
-- ---------------------------------------------------------------------
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
              SELECT 'Ver Combos' AS name, 'combos.view' AS slug, 'combos' AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Crear Combos',                      'combos.create',             'combos',           NULL, NOW(), NOW()
    UNION ALL SELECT 'Editar Combos',                     'combos.edit',               'combos',           NULL, NOW(), NOW()
    UNION ALL SELECT 'Eliminar Combos',                   'combos.delete',             'combos',           NULL, NOW(), NOW()
    UNION ALL SELECT 'Ver Paquetes',                      'packages.view',             'packages',         NULL, NOW(), NOW()
    UNION ALL SELECT 'Crear Paquetes',                    'packages.create',           'packages',         NULL, NOW(), NOW()
    UNION ALL SELECT 'Editar Paquetes',                   'packages.edit',             'packages',         NULL, NOW(), NOW()
    UNION ALL SELECT 'Eliminar Paquetes',                 'packages.delete',           'packages',         NULL, NOW(), NOW()
    UNION ALL SELECT 'Ver Cotizaciones',                  'quotes.view',               'quotes',           NULL, NOW(), NOW()
    UNION ALL SELECT 'Crear Cotizaciones',                'quotes.create',             'quotes',           NULL, NOW(), NOW()
    UNION ALL SELECT 'Anular Cotizaciones',               'quotes.delete',             'quotes',           NULL, NOW(), NOW()
    UNION ALL SELECT 'Ver Devoluciones de ventas',        'returns.view',              'returns',          NULL, NOW(), NOW()
    UNION ALL SELECT 'Registrar Devoluciones de ventas',  'returns.create',            'returns',          NULL, NOW(), NOW()
    UNION ALL SELECT 'Ver Devoluciones a proveedor',      'purchase-returns.view',     'purchase-returns', NULL, NOW(), NOW()
    UNION ALL SELECT 'Registrar Devoluciones a proveedor','purchase-returns.create',   'purchase-returns', NULL, NOW(), NOW()
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug);

-- Dueño y encargados: todo
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r CROSS JOIN `permissions` p
WHERE r.`slug` IN ('admin', 'manager', 'gerente_c')
  AND p.`module` IN ('combos', 'packages', 'quotes', 'returns', 'purchase-returns');


-- ---------------------------------------------------------------------
-- 11. Registro de la migración, para que un futuro `php artisan migrate`
--     no la repita
-- ---------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_10_22_200001_setup_returns_quotes_combos_packages' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_22_200001_setup_returns_quotes_combos_packages');

-- Fin del script.
