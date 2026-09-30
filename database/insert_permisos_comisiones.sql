-- Permisos del módulo Comisiones. Idempotente.
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
    SELECT 'Ver Comisiones' AS name, 'commissions.view' AS slug, 'commissions' AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Pagar Comisiones','commissions.pay','commissions',NULL,NOW(),NOW()
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug);

INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p
WHERE r.slug IN ('admin','manager','gerente_c') AND p.module = 'commissions';
