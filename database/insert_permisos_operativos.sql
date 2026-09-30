-- ============================================================
-- Permisos de los módulos operativos del salón + asignación a
-- roles admin / manager / gerente_c. Idempotente (reejecutable).
-- ============================================================

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
    -- Clientes
    SELECT 'Ver Clientes'        AS name, 'clients.view'   AS slug, 'clients' AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Crear Clientes','clients.create','clients',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Clientes','clients.edit','clients',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Clientes','clients.delete','clients',NULL,NOW(),NOW()
    -- Agenda / citas
    UNION ALL SELECT 'Ver Citas','appointments.view','appointments',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Citas','appointments.create','appointments',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Citas','appointments.edit','appointments',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Citas','appointments.delete','appointments',NULL,NOW(),NOW()
    -- Servicios
    UNION ALL SELECT 'Ver Servicios','services.view','services',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Servicios','services.create','services',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Servicios','services.edit','services',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Servicios','services.delete','services',NULL,NOW(),NOW()
    -- Categorías de servicio
    UNION ALL SELECT 'Ver Categorías de servicio','service-categories.view','service-categories',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Categorías de servicio','service-categories.create','service-categories',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Categorías de servicio','service-categories.edit','service-categories',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Categorías de servicio','service-categories.delete','service-categories',NULL,NOW(),NOW()
    -- Productos
    UNION ALL SELECT 'Ver Productos','products.view','products',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Productos','products.create','products',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Productos','products.edit','products',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Productos','products.delete','products',NULL,NOW(),NOW()
    -- Categorías de producto
    UNION ALL SELECT 'Ver Categorías de producto','product-categories.view','product-categories',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Categorías de producto','product-categories.create','product-categories',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Categorías de producto','product-categories.edit','product-categories',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Categorías de producto','product-categories.delete','product-categories',NULL,NOW(),NOW()
    -- Marcas
    UNION ALL SELECT 'Ver Marcas','brands.view','brands',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Marcas','brands.create','brands',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Marcas','brands.edit','brands',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Marcas','brands.delete','brands',NULL,NOW(),NOW()
    -- Existencias
    UNION ALL SELECT 'Ver Existencias','stock.view','stock',NULL,NOW(),NOW()
    UNION ALL SELECT 'Gestionar Existencias','stock.manage','stock',NULL,NOW(),NOW()
    -- Proveedores
    UNION ALL SELECT 'Ver Proveedores','suppliers.view','suppliers',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Proveedores','suppliers.create','suppliers',NULL,NOW(),NOW()
    UNION ALL SELECT 'Editar Proveedores','suppliers.edit','suppliers',NULL,NOW(),NOW()
    UNION ALL SELECT 'Eliminar Proveedores','suppliers.delete','suppliers',NULL,NOW(),NOW()
    -- Compras
    UNION ALL SELECT 'Ver Compras','purchases.view','purchases',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Compras','purchases.create','purchases',NULL,NOW(),NOW()
    UNION ALL SELECT 'Recibir Compras','purchases.receive','purchases',NULL,NOW(),NOW()
    UNION ALL SELECT 'Anular Compras','purchases.void','purchases',NULL,NOW(),NOW()
    -- Ventas
    UNION ALL SELECT 'Ver Ventas','sales.view','sales',NULL,NOW(),NOW()
    UNION ALL SELECT 'Crear Ventas','sales.create','sales',NULL,NOW(),NOW()
    UNION ALL SELECT 'Anular Ventas','sales.void','sales',NULL,NOW(),NOW()
    -- Caja
    UNION ALL SELECT 'Ver Caja','cash.view','cash',NULL,NOW(),NOW()
    UNION ALL SELECT 'Abrir Caja','cash.open','cash',NULL,NOW(),NOW()
    UNION ALL SELECT 'Movimientos de Caja','cash.move','cash',NULL,NOW(),NOW()
    UNION ALL SELECT 'Cerrar Caja','cash.close','cash',NULL,NOW(),NOW()
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug);

-- Asignar TODOS los permisos operativos/administrativos a los roles de administración
-- (evita bloquear a los usuarios actuales). Se excluyen módulos exclusivos de super admin.
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug IN ('admin','manager','gerente_c')
  AND p.module NOT IN ('companies','plans','subscriptions','payments','roles');
