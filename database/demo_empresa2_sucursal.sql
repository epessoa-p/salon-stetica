-- ═══════════════════════════════════════════════════════════════════════
-- Glamora · Sucursal de demostración para la empresa 2
-- Para quien ya ejecutó demo_empresa2_ultimos_11_dias.sql (que ahora ya la
-- incluye): crea ALMACÉN CENTRAL y SUCURSAL CENTRAL y se las asigna a las
-- ventas, citas, caja e inventario de demostración.
--
-- SEGURO DE REEJECUTAR: crea solo lo que falta y solo asigna donde no hay
-- sucursal. No toca datos reales.
-- ═══════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
-- ── Sucursal (y su almacén, obligatorio) ─────────────────────────────────
-- Se crean solo si no existen (se buscan por código). Luego se asigna la
-- sucursal a la actividad de demostración que aún no tenga una.
SET @c := 2;
SET @okb := (SELECT COUNT(*) FROM companies WHERE id = @c);

INSERT INTO warehouses (company_id, name, code, location, description, active, created_at, updated_at)
SELECT @c, 'ALMACÉN CENTRAL', 'DEMO2-WH01', 'Sucursal central', 'Datos de demostración', 1, NOW(), NOW()
FROM DUAL WHERE @okb > 0 AND NOT EXISTS (SELECT 1 FROM warehouses WHERE code = 'DEMO2-WH01');
UPDATE warehouses SET deleted_at = NULL WHERE company_id = @c AND code = 'DEMO2-WH01' AND deleted_at IS NOT NULL;
SET @wh := (SELECT id FROM warehouses WHERE company_id = @c AND code = 'DEMO2-WH01' LIMIT 1);

INSERT INTO branches (company_id, warehouse_id, name, code, phone, address, manager_name, active, created_at, updated_at)
SELECT @c, @wh, 'SUCURSAL CENTRAL', 'DEMO2-SC01', '33445566', 'Av. Principal #123', 'GERENTE_1', 1, NOW(), NOW()
FROM DUAL WHERE @okb > 0 AND @wh IS NOT NULL AND NOT EXISTS (SELECT 1 FROM branches WHERE code = 'DEMO2-SC01');
UPDATE branches SET deleted_at = NULL WHERE company_id = @c AND code = 'DEMO2-SC01' AND deleted_at IS NOT NULL;
SET @br := (SELECT id FROM branches WHERE company_id = @c AND code = 'DEMO2-SC01' LIMIT 1);

-- Actividad de demostración sin sucursal → sucursal central (no toca datos reales).
UPDATE sales SET branch_id = @br WHERE company_id = @c AND notes = 'Datos de demostración' AND branch_id IS NULL AND @br IS NOT NULL;
UPDATE appointments SET branch_id = @br WHERE company_id = @c AND notes = 'Datos de demostración' AND branch_id IS NULL AND @br IS NOT NULL;
UPDATE inventory_movements SET branch_id = @br, warehouse_id = COALESCE(warehouse_id, @wh)
WHERE company_id = @c AND (notes = 'Datos de demostración' OR notes LIKE '% · Datos de demostración') AND branch_id IS NULL AND @br IS NOT NULL;
UPDATE cash_registers SET branch_id = @br WHERE company_id = @c AND name = 'CAJA PRINCIPAL' AND code = 'CJ-01' AND branch_id IS NULL AND @br IS NOT NULL;

SELECT IF(@br IS NULL, 'No se pudo crear la sucursal (¿existe la empresa 2?).', 'Sucursal central lista y asignada a los datos de demostración.') AS sucursal,
       (SELECT COUNT(*) FROM sales WHERE company_id = @c AND branch_id = @br) AS ventas_en_sucursal,
       (SELECT COUNT(*) FROM appointments WHERE company_id = @c AND branch_id = @br) AS citas_en_sucursal;
