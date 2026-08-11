-- ============================================================
-- Permisos de los módulos SaaS: planes, suscripciones y pagos.
-- Idempotente: solo inserta si el `slug` no existe.
-- (Estos módulos son solo para super admin, pero se registran
--  por consistencia con el resto de permisos.)
-- ============================================================

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
    -- Planes
    SELECT 'Ver Planes'        AS name, 'plans.view'     AS slug, 'plans' AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Crear Planes',    'plans.create', 'plans', NULL, NOW(), NOW()
    UNION ALL SELECT 'Editar Planes',   'plans.edit',   'plans', NULL, NOW(), NOW()
    UNION ALL SELECT 'Eliminar Planes', 'plans.delete', 'plans', NULL, NOW(), NOW()

    -- Suscripciones
    UNION ALL SELECT 'Ver Suscripciones',    'subscriptions.view',   'subscriptions', NULL, NOW(), NOW()
    UNION ALL SELECT 'Gestionar Suscripciones', 'subscriptions.manage', 'subscriptions', NULL, NOW(), NOW()

    -- Pagos de suscripción
    UNION ALL SELECT 'Ver Pagos',       'payments.view',    'payments', NULL, NOW(), NOW()
    UNION ALL SELECT 'Registrar Pagos', 'payments.create',  'payments', NULL, NOW(), NOW()
    UNION ALL SELECT 'Confirmar Pagos', 'payments.confirm', 'payments', NULL, NOW(), NOW()
) AS nuevos
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug
);
