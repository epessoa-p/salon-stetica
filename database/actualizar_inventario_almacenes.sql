-- =====================================================================
--  Glamora · Inventario por almacén y traspasos entre sucursales
--
--  - product_stocks: existencia de cada producto en cada almacén.
--    products.current_stock queda como el TOTAL de la empresa (la suma),
--    así alertas de stock bajo, reportes y panel siguen igual.
--  - stock_transfers / stock_transfer_items: traspasos de un almacén a
--    otro (salen de uno y entran al otro en el momento).
--  - entries.warehouse_id (órdenes de compra): almacén donde se recibió la compra
--    (de ahí sale una devolución al proveedor).
--  - Carga inicial: el stock actual de cada producto queda en el almacén
--    de la sucursal principal (la más antigua) de su empresa.
--
--  SEGURO DE REEJECUTAR: cada tabla, columna y clave comprueba antes si
--  existe, y la carga inicial solo toma productos que aún no tienen
--  existencias por almacén. Probado dos veces sobre una base de prueba.
--  Compatible con MySQL 5.7+ y MariaDB 10.2+ (sin DELIMITER).
--
--  ORDEN EN PRODUCCIÓN: después de actualizar_sucursales.sql.
--  Ejecutar completo: phpMyAdmin → pestaña SQL → pegar y ejecutar.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Existencias por almacén
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_stocks` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`   BIGINT UNSIGNED NOT NULL,
    `product_id`   BIGINT UNSIGNED NOT NULL,
    `warehouse_id` BIGINT UNSIGNED NOT NULL,
    `quantity`     DECIMAL(15,2)   NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP       NULL,
    `updated_at`   TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `product_stocks_product_warehouse_unique` (`product_id`, `warehouse_id`),
    KEY `product_stocks_company_warehouse_index` (`company_id`, `warehouse_id`),
    CONSTRAINT `product_stocks_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `product_stocks_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. Traspasos entre almacenes
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_transfers` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`        BIGINT UNSIGNED NOT NULL,
    `transfer_number`   VARCHAR(30)     NOT NULL,
    `from_warehouse_id` BIGINT UNSIGNED NOT NULL,
    `to_warehouse_id`   BIGINT UNSIGNED NOT NULL,
    `status`            VARCHAR(20)     NOT NULL DEFAULT 'completada' COMMENT 'completada | anulada',
    `notes`             TEXT            NULL,
    `transferred_at`    DATETIME        NOT NULL,
    `created_by`        BIGINT UNSIGNED NULL,
    `voided_at`         DATETIME        NULL,
    `voided_by`         BIGINT UNSIGNED NULL,
    `created_at`        TIMESTAMP       NULL,
    `updated_at`        TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `stock_transfers_company_number_unique` (`company_id`, `transfer_number`),
    KEY `stock_transfers_company_date_index` (`company_id`, `transferred_at`),
    CONSTRAINT `stock_transfers_from_warehouse_id_foreign` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`),
    CONSTRAINT `stock_transfers_to_warehouse_id_foreign` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_transfer_items` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `stock_transfer_id` BIGINT UNSIGNED NOT NULL,
    `product_id`        BIGINT UNSIGNED NOT NULL,
    `quantity`          DECIMAL(15,2)   NOT NULL,
    `created_at`        TIMESTAMP       NULL,
    `updated_at`        TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `stock_transfer_items_transfer_index` (`stock_transfer_id`),
    CONSTRAINT `stock_transfer_items_transfer_id_foreign` FOREIGN KEY (`stock_transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `stock_transfer_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3. Compras: almacén donde se recibieron
-- ---------------------------------------------------------------------
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'warehouse_id') = 0,
    'ALTER TABLE `entries` ADD COLUMN `warehouse_id` BIGINT UNSIGNED NULL AFTER `company_id`',
    'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

-- La clave puede existir ya con otro nombre (p. ej. entries_ibfk_2): se busca cualquiera sobre la columna.
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'warehouse_id' AND REFERENCED_TABLE_NAME = 'warehouses') = 0,
    'ALTER TABLE `entries` ADD CONSTRAINT `entries_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL',
    'DO 0'));
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 4. Carga inicial: stock actual → almacén de la sucursal principal
--    (si la empresa no tiene sucursal con almacén, su primer almacén)
-- ---------------------------------------------------------------------
INSERT INTO `product_stocks` (`company_id`, `product_id`, `warehouse_id`, `quantity`, `created_at`, `updated_at`)
SELECT t.`company_id`, t.`product_id`, t.`warehouse_id`, t.`quantity`, NOW(), NOW()
FROM (
    SELECT p.`company_id`, p.`id` AS `product_id`, p.`current_stock` AS `quantity`,
           COALESCE(
               (SELECT b.`warehouse_id` FROM `branches` b WHERE b.`company_id` = p.`company_id` AND b.`deleted_at` IS NULL AND b.`warehouse_id` IS NOT NULL ORDER BY b.`id` LIMIT 1),
               (SELECT w.`id` FROM `warehouses` w WHERE w.`company_id` = p.`company_id` AND w.`deleted_at` IS NULL ORDER BY w.`id` LIMIT 1)
           ) AS `warehouse_id`
    FROM `products` p
    WHERE NOT EXISTS (SELECT 1 FROM `product_stocks` s WHERE s.`product_id` = p.`id`)
) t
WHERE t.`warehouse_id` IS NOT NULL;

-- Compras ya recibidas: se asume el almacén principal de su empresa.
UPDATE `entries` o
SET o.`warehouse_id` = (SELECT b.`warehouse_id` FROM `branches` b WHERE b.`company_id` = o.`company_id` AND b.`deleted_at` IS NULL AND b.`warehouse_id` IS NOT NULL ORDER BY b.`id` LIMIT 1)
WHERE o.`warehouse_id` IS NULL AND o.`status` = 'recibida';


-- ---------------------------------------------------------------------
-- 5. Registro de la migración, para que `php artisan migrate`
--    no la repita
-- ---------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_11_02_000001_setup_warehouse_stock' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_02_000001_setup_warehouse_stock');
