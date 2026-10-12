-- Permisos de "Mi empresa" (datos de la propia empresa). Idempotente.
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
    SELECT 'Ver Mi empresa' AS name, 'my-company.view' AS slug, 'my-company' AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Editar Mi empresa','my-company.edit','my-company',NULL,NOW(),NOW()
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug);

INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p
WHERE r.slug IN ('admin','manager','gerente_c') AND p.module = 'my-company';
