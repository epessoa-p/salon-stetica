-- ============================================================
-- Inserta los permisos (menús) que faltan en la tabla `permissions`
-- Base de datos: salon_estetica
-- Módulos agregados: cargos, personal, branches (sucursales), cajas
--
-- Es idempotente: solo inserta el permiso si su `slug` aún no existe,
-- por lo que puede ejecutarse varias veces sin duplicar registros.
-- ============================================================

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
    -- Cargos
    SELECT 'Ver Cargos'        AS name, 'cargos.view'     AS slug, 'cargos'   AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Crear Cargos',      'cargos.create',   'cargos',   NULL, NOW(), NOW()
    UNION ALL SELECT 'Editar Cargos',     'cargos.edit',     'cargos',   NULL, NOW(), NOW()
    UNION ALL SELECT 'Eliminar Cargos',   'cargos.delete',   'cargos',   NULL, NOW(), NOW()

    -- Personal
    UNION ALL SELECT 'Ver Personal',      'personal.view',   'personal', NULL, NOW(), NOW()
    UNION ALL SELECT 'Crear Personal',    'personal.create', 'personal', NULL, NOW(), NOW()
    UNION ALL SELECT 'Editar Personal',   'personal.edit',   'personal', NULL, NOW(), NOW()
    UNION ALL SELECT 'Eliminar Personal', 'personal.delete', 'personal', NULL, NOW(), NOW()

    -- Sucursales
    UNION ALL SELECT 'Ver Sucursales',      'branches.view',   'branches', NULL, NOW(), NOW()
    UNION ALL SELECT 'Crear Sucursales',    'branches.create', 'branches', NULL, NOW(), NOW()
    UNION ALL SELECT 'Editar Sucursales',   'branches.edit',   'branches', NULL, NOW(), NOW()
    UNION ALL SELECT 'Eliminar Sucursales', 'branches.delete', 'branches', NULL, NOW(), NOW()

    -- Cajas
    UNION ALL SELECT 'Ver Cajas',      'cajas.view',   'cajas', NULL, NOW(), NOW()
    UNION ALL SELECT 'Crear Cajas',    'cajas.create', 'cajas', NULL, NOW(), NOW()
    UNION ALL SELECT 'Editar Cajas',   'cajas.edit',   'cajas', NULL, NOW(), NOW()
    UNION ALL SELECT 'Eliminar Cajas', 'cajas.delete', 'cajas', NULL, NOW(), NOW()
) AS nuevos
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug
);
