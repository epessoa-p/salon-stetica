-- Permisos del módulo Ingresos y Gastos. Idempotente (reejecutable).
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
    SELECT 'Ver Ingresos'      AS name, 'incomes.view'    AS slug, 'incomes'  AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Registrar Ingresos','incomes.create','incomes',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Ingresos','incomes.delete','incomes',NULL,NOW(),NOW()
    UNION ALL SELECT 'Ver Gastos','expenses.view','expenses',NULL,NOW(),NOW()
    UNION ALL SELECT 'Registrar Gastos','expenses.create','expenses',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Gastos','expenses.delete','expenses',NULL,NOW(),NOW()
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug);

-- Asignar a los roles de administración.
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug IN ('admin','manager','gerente_c')
  AND p.module IN ('incomes','expenses');
