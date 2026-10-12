-- Permisos de Métodos de pago y Preferencias. Idempotente.
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
    SELECT 'Ver Metodos de pago' AS name, 'payment-methods.view' AS slug, 'payment-methods' AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Crear Metodos de pago','payment-methods.create','payment-methods',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Metodos de pago','payment-methods.edit','payment-methods',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Metodos de pago','payment-methods.delete','payment-methods',NULL,NOW(),NOW()
    UNION ALL SELECT 'Ver Preferencias','settings.view','settings',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Preferencias','settings.edit','settings',NULL,NOW(),NOW()
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug);

INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p
WHERE r.slug IN ('admin','manager','gerente_c') AND p.module IN ('payment-methods','settings');
